<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK\Drivers;

use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Matrix driver using Application Service API
 *
 * Receives events via PUT /transactions/{txnId} (push from homeserver)
 * Sends messages via Matrix Client-Server API using as_token
 */
class MatrixDriver implements DriverInterface
{
    private const PLATFORM = 'matrix';

    public function __construct(
        private IConfig $config,
        private IClientService $clientService,
        private LoggerInterface $logger,
        private string $appId = 'docuseal_integration',
    ) {
    }

    public function getPlatform(): string
    {
        return self::PLATFORM;
    }

    /**
     * Get Matrix homeserver URL from config
     */
    public function getHomeserverUrl(): string
    {
        return rtrim($this->config->getAppValue(
            $this->appId,
            'matrix_homeserver',
            'https://matrix.example.com'
        ), '/');
    }

    /**
     * Get appservice token (as_token) for authenticating to homeserver
     */
    public function getAsToken(): string
    {
        return $this->config->getAppValue($this->appId, 'matrix_as_token', '');
    }

    /**
     * Verify homeserver token (hs_token) from incoming requests
     */
    public function verifyHsToken(string $token): bool
    {
        $hsToken = $this->config->getAppValue($this->appId, 'matrix_hs_token', '');
        return hash_equals($hsToken, $token);
    }

    /**
     * Parse Matrix event into Message object
     */
    public function parseEvent(array $event): ?Message
    {
        $type = $event['type'] ?? '';

        if ($type !== 'm.room.message') {
            return null;
        }

        $content = $event['content'] ?? [];
        $sender = $event['sender'] ?? '';
        $roomId = $event['room_id'] ?? '';
        $msgtype = $content['msgtype'] ?? '';
        $body = $content['body'] ?? '';
        $attachments = [];

        // Handle file attachments
        if (in_array($msgtype, ['m.file', 'm.image', 'm.video', 'm.audio'])) {
            $attachments[] = [
                'url' => $content['url'] ?? null,
                'mxc_uri' => $content['url'] ?? null,
                'filename' => $content['body'] ?? 'attachment',
                'mimetype' => $content['info']['mimetype'] ?? 'application/octet-stream',
                'size' => $content['info']['size'] ?? 0,
            ];
        }

        return new Message(
            platform: self::PLATFORM,
            sender: $sender,
            text: $body,
            roomId: $roomId,
            attachments: $attachments ?: null,
            timestamp: isset($event['origin_server_ts']) ? (int)($event['origin_server_ts'] / 1000) : null,
            raw: $event,
        );
    }

    /**
     * Send a response to a room
     */
    public function send(Message $originalMessage, Response $response): bool
    {
        $roomId = $originalMessage->getRoomId();
        if (!$roomId) {
            $this->logger->error('Cannot send Matrix message: no room ID');
            return false;
        }

        try {
            foreach ($response->getMessages() as $msg) {
                if (in_array($msg['type'], ['text', 'markdown'])) {
                    $this->sendMessage($roomId, $msg['content']);
                }
            }

            foreach ($response->getAttachments() as $attachment) {
                $content = file_get_contents($attachment['path']);
                $mxcUri = $this->uploadMedia(
                    $content,
                    $attachment['filename'],
                    $attachment['mime_type']
                );
                $this->sendFile(
                    $roomId,
                    $mxcUri,
                    $attachment['filename'],
                    $attachment['mime_type'],
                    strlen($content)
                );
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Matrix message', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send to a specific recipient (room ID)
     */
    public function sendTo(string $recipient, Response $response): bool
    {
        if (str_starts_with($recipient, '!')) {
            $message = new Message(
                platform: self::PLATFORM,
                sender: '',
                text: '',
                roomId: $recipient,
            );
            return $this->send($message, $response);
        }

        $this->logger->warning('sendTo with user ID not yet implemented for Matrix');
        return false;
    }

    /**
     * Send a text message to a room
     */
    public function sendMessage(string $roomId, string $message): array
    {
        $txnId = bin2hex(random_bytes(16));
        $encodedRoomId = urlencode($roomId);
        $endpoint = "/_matrix/client/v3/rooms/{$encodedRoomId}/send/m.room.message/{$txnId}";

        return $this->matrixRequest('PUT', $endpoint, [
            'msgtype' => 'm.text',
            'body' => $message,
        ]);
    }

    /**
     * Send a file/attachment to a room
     */
    public function sendFile(string $roomId, string $mxcUri, string $filename, string $mimeType, int $size): array
    {
        $txnId = bin2hex(random_bytes(16));
        $encodedRoomId = urlencode($roomId);
        $endpoint = "/_matrix/client/v3/rooms/{$encodedRoomId}/send/m.room.message/{$txnId}";

        return $this->matrixRequest('PUT', $endpoint, [
            'msgtype' => 'm.file',
            'body' => $filename,
            'url' => $mxcUri,
            'info' => [
                'mimetype' => $mimeType,
                'size' => $size,
            ],
        ]);
    }

    /**
     * Upload content to Matrix media repository
     */
    public function uploadMedia(string $content, string $filename, string $mimeType): string
    {
        $client = $this->clientService->newClient();
        $url = $this->getHomeserverUrl() . '/_matrix/media/v3/upload?filename=' . urlencode($filename);

        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAsToken(),
                'Content-Type' => $mimeType,
            ],
            'body' => $content,
        ]);

        $result = json_decode($response->getBody(), true);
        return $result['content_uri'] ?? '';
    }

    /**
     * Download content from Matrix media repository
     */
    public function downloadMedia(string $mxcUri): string
    {
        if (!str_starts_with($mxcUri, 'mxc://')) {
            throw new \InvalidArgumentException('Invalid mxc URI');
        }

        $path = substr($mxcUri, 6);
        $client = $this->clientService->newClient();
        $url = $this->getHomeserverUrl() . '/_matrix/media/v3/download/' . $path;

        $response = $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAsToken(),
            ],
        ]);

        return $response->getBody();
    }

    /**
     * Join a room
     */
    public function joinRoom(string $roomIdOrAlias): array
    {
        $endpoint = '/_matrix/client/v3/join/' . urlencode($roomIdOrAlias);
        return $this->matrixRequest('POST', $endpoint);
    }

    /**
     * Make authenticated request to Matrix homeserver
     */
    private function matrixRequest(string $method, string $endpoint, array $body = []): array
    {
        $client = $this->clientService->newClient();
        $url = $this->getHomeserverUrl() . $endpoint;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAsToken(),
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($body)) {
            $options['body'] = json_encode($body);
        }

        try {
            $response = match ($method) {
                'GET' => $client->get($url, $options),
                'POST' => $client->post($url, $options),
                'PUT' => $client->put($url, $options),
                default => throw new \InvalidArgumentException("Unsupported method: $method"),
            };

            return json_decode($response->getBody(), true) ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('Matrix API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
