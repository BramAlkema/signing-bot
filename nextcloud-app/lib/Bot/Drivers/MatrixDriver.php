<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot\Drivers;

use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;
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
    ) {
    }

    public function getPlatform(): string
    {
        return self::PLATFORM;
    }

    /**
     * Get Matrix homeserver URL from config
     */
    private function getHomeserverUrl(): string
    {
        return rtrim($this->config->getAppValue(
            'docuseal_integration',
            'matrix_homeserver',
            'https://matrix.ctrlaltdef.eu'
        ), '/');
    }

    /**
     * Get appservice token (as_token) for authenticating to homeserver
     */
    private function getAsToken(): string
    {
        return $this->config->getAppValue(
            'docuseal_integration',
            'matrix_as_token',
            ''
        );
    }

    /**
     * Get the bot's Matrix user ID
     */
    private function getBotUserId(): string
    {
        return $this->config->getAppValue(
            'docuseal_integration',
            'matrix_bot_user',
            '@docuseal-bot:matrix.ctrlaltdef.eu'
        );
    }

    /**
     * Verify homeserver token (hs_token) from incoming requests
     */
    public function verifyHsToken(string $token): bool
    {
        $hsToken = $this->config->getAppValue(
            'docuseal_integration',
            'matrix_hs_token',
            ''
        );
        return hash_equals($hsToken, $token);
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
            if ($method === 'GET') {
                $response = $client->get($url, $options);
            } elseif ($method === 'POST') {
                $response = $client->post($url, $options);
            } elseif ($method === 'PUT') {
                $response = $client->put($url, $options);
            } else {
                throw new \InvalidArgumentException("Unsupported method: $method");
            }

            return json_decode($response->getBody(), true) ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('Matrix API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        // mxc://server/mediaId -> /_matrix/media/v3/download/server/mediaId
        if (!str_starts_with($mxcUri, 'mxc://')) {
            throw new \InvalidArgumentException('Invalid mxc URI');
        }

        $path = substr($mxcUri, 6); // Remove "mxc://"
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
     * Invite a user to a room
     */
    public function inviteUser(string $roomId, string $userId): array
    {
        $encodedRoomId = urlencode($roomId);
        $endpoint = "/_matrix/client/v3/rooms/{$encodedRoomId}/invite";
        return $this->matrixRequest('POST', $endpoint, [
            'user_id' => $userId,
        ]);
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

        // Ignore messages from our own bot
        if ($sender === $this->getBotUserId()) {
            return null;
        }

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

    public function send(Message $originalMessage, Response $response): bool
    {
        $roomId = $originalMessage->getRoomId();
        if (!$roomId) {
            $this->logger->error('Cannot send Matrix message: no room ID');
            return false;
        }

        try {
            foreach ($response->getMessages() as $msg) {
                if ($msg['type'] === 'text') {
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

    public function sendTo(string $recipient, Response $response): bool
    {
        // For Matrix, recipient could be a room ID or user ID
        // If it's a user ID, we'd need to create/find a DM room first
        // For now, assume it's a room ID
        if (str_starts_with($recipient, '!')) {
            // It's a room ID
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
}
