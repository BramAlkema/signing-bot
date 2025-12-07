<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK\Drivers;

use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Signal driver using signal-cli daemon over Unix socket (JSON-RPC)
 */
class SignalDriver implements DriverInterface
{
    private const PLATFORM = 'signal';
    private const DEFAULT_SOCKET = 'unix:///run/signal-cli/socket';

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private IConfig $config,
        private LoggerInterface $logger,
    ) {
    }

    public function getPlatform(): string
    {
        return self::PLATFORM;
    }

    /**
     * Get the socket path from config or use default
     */
    private function getSocketPath(): string
    {
        return $this->config->getAppValue(
            'docuseal_integration',
            'signal_socket',
            self::DEFAULT_SOCKET
        );
    }

    /**
     * Get the bot's phone number from config
     */
    public function getBotPhoneNumber(): string
    {
        return $this->config->getAppValue(
            'docuseal_integration',
            'signal_phone_number',
            ''
        );
    }

    /**
     * Connect to signal-cli Unix socket
     *
     * @return resource
     */
    public function connect()
    {
        if ($this->socket !== null) {
            return $this->socket;
        }

        $socketPath = $this->getSocketPath();
        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            $socketPath,
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->socket) {
            throw new \RuntimeException("Failed to connect to signal-cli socket: $errstr ($errno)");
        }

        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, 30);

        $this->logger->debug('Connected to signal-cli socket', [
            'socket' => $socketPath,
        ]);

        return $this->socket;
    }

    /**
     * Close the socket connection
     */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send JSON-RPC request to signal-cli
     */
    private function jsonRpcCall(string $method, array $params = []): array
    {
        $socket = $this->connect();

        $id = random_int(1, PHP_INT_MAX);
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            // Cast empty array to object so it encodes as {} not []
            'params' => empty($params) ? (object)$params : $params,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON-RPC payload');
        }

        fwrite($socket, $json . "\n");

        // Wait for response with matching ID
        while (!feof($socket)) {
            $line = fgets($socket);
            if ($line === false) {
                continue;
            }

            $response = json_decode($line, true);
            if (!is_array($response)) {
                continue;
            }

            // Handle notifications (incoming messages) - skip them when waiting for RPC response
            if (!isset($response['id']) && isset($response['method'])) {
                continue;
            }

            if (isset($response['id']) && (int)$response['id'] === $id) {
                if (isset($response['error'])) {
                    throw new \RuntimeException('signal-cli error: ' . json_encode($response['error']));
                }
                return $response['result'] ?? [];
            }
        }

        throw new \RuntimeException('No response from signal-cli');
    }

    /**
     * Send a message to a recipient
     */
    public function sendMessage(string $recipient, string $message, ?string $attachment = null): array
    {
        $params = [
            'recipient' => [$recipient],
            'message' => $message,
        ];

        if ($attachment !== null) {
            $params['attachment'] = [$attachment];
        }

        return $this->jsonRpcCall('send', $params);
    }

    /**
     * Receive messages (blocking read from socket)
     *
     * @return Message[]
     */
    public function receiveMessages(int $timeout = 2): array
    {
        $socket = $this->connect();
        stream_set_timeout($socket, $timeout);

        $messages = [];

        while (true) {
            $line = fgets($socket);

            if ($line === false) {
                // Timeout or EOF
                break;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                continue;
            }

            // Only process "receive" notifications
            if (($data['method'] ?? null) !== 'receive') {
                continue;
            }

            $message = $this->parseEvent($data);
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Parse signal-cli notification into Message object
     * Implements DriverInterface::parseEvent
     */
    public function parseEvent(array $event): ?Message
    {
        $params = $event['params'] ?? [];
        $envelope = $params['envelope'] ?? [];
        $sender = $envelope['source'] ?? null;

        if (!$sender) {
            return null;
        }

        // Skip messages from ourselves
        $botPhone = $this->getBotPhoneNumber();
        if ($botPhone && $sender === $botPhone) {
            return null;
        }

        $text = null;
        $attachments = [];

        // Regular data message
        if (isset($envelope['dataMessage'])) {
            $dataMsg = $envelope['dataMessage'];
            $text = $dataMsg['message'] ?? '';
            $attachments = $this->parseAttachments($dataMsg['attachments'] ?? []);
        }

        // Sync message (Note to Self)
        if (isset($envelope['syncMessage']['sentMessage'])) {
            $sent = $envelope['syncMessage']['sentMessage'];
            $text = $sent['message'] ?? '';
            $attachments = $this->parseAttachments($sent['attachments'] ?? []);
        }

        if ($text === null) {
            return null;
        }

        return new Message(
            platform: self::PLATFORM,
            sender: $sender,
            text: $text,
            roomId: $sender, // Signal doesn't have rooms - use sender as "room"
            attachments: $attachments,
            timestamp: isset($envelope['timestamp']) ? (int)($envelope['timestamp'] / 1000) : null,
            raw: $event,
        );
    }

    /**
     * Parse signal-cli attachments into normalized format
     */
    private function parseAttachments(array $attachments): array
    {
        $result = [];
        foreach ($attachments as $att) {
            $result[] = [
                'path' => $att['filename'] ?? $att['id'] ?? null,
                'mime_type' => $att['contentType'] ?? 'application/octet-stream',
                'filename' => basename($att['filename'] ?? 'attachment'),
                'size' => $att['size'] ?? null,
            ];
        }
        return $result;
    }

    public function send(Message $originalMessage, Response $response): bool
    {
        return $this->sendTo($originalMessage->getSender(), $response);
    }

    public function sendTo(string $recipient, Response $response): bool
    {
        try {
            foreach ($response->getMessages() as $msg) {
                $content = $msg['content'] ?? '';
                if (!empty($content)) {
                    $this->sendMessage($recipient, $content);
                }
            }

            foreach ($response->getAttachments() as $attachment) {
                $path = $attachment['path'] ?? null;
                if ($path) {
                    $this->sendMessage($recipient, '', $path);
                }
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Signal message', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get signal-cli version (useful for health checks)
     */
    public function getVersion(): string
    {
        $result = $this->jsonRpcCall('version', []);
        return $result['version'] ?? 'unknown';
    }
}
