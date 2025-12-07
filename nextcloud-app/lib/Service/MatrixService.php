<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Service;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Matrix homeserver integration for signing room coordination
 */
class MatrixService
{
    /** Default signing request expiry in seconds (7 days) */
    private const SIGNING_REQUEST_EXPIRY_SECONDS = 7 * 24 * 3600;

    private string $homeserver;
    private string $accessToken;
    private string $botUserId;

    public function __construct(
        private IConfig $config,
        private IClientService $clientService,
        private LoggerInterface $logger,
    ) {
        $this->homeserver = rtrim($this->config->getAppValue(
            Application::APP_ID,
            'matrix_homeserver',
            ''
        ), '/');
        $this->accessToken = $this->config->getAppValue(
            Application::APP_ID,
            'matrix_access_token',
            ''
        );
        $this->botUserId = $this->config->getAppValue(
            Application::APP_ID,
            'matrix_bot_user',
            ''
        );
    }

    /**
     * Test connection to Matrix homeserver
     */
    public function testConnection(): array
    {
        if (empty($this->homeserver) || empty($this->accessToken)) {
            return ['connected' => false, 'error' => 'Not configured'];
        }

        try {
            $client = $this->clientService->newClient();
            $response = $client->get(
                "{$this->homeserver}/_matrix/client/v3/account/whoami",
                ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]
            );

            $data = json_decode($response->getBody(), true);
            return [
                'connected' => true,
                'user_id' => $data['user_id'] ?? null,
                'device_id' => $data['device_id'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Matrix connection failed', ['error' => $e->getMessage()]);
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a signing room for a document
     */
    public function createSigningRoom(
        string $documentName,
        string $documentHash,
        array $inviteMatrixIds
    ): array {
        $client = $this->clientService->newClient();

        $response = $client->post(
            "{$this->homeserver}/_matrix/client/v3/createRoom",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => "📝 Sign: {$documentName}",
                    'topic' => "Document signing room | SHA-256: {$documentHash}",
                    'invite' => $inviteMatrixIds,
                    'preset' => 'private_chat',
                    'initial_state' => [
                        // Enable encryption
                        [
                            'type' => 'm.room.encryption',
                            'state_key' => '',
                            'content' => ['algorithm' => 'm.megolm.v1.aes-sha2'],
                        ],
                    ],
                ],
            ]
        );

        $data = json_decode($response->getBody(), true);
        $roomId = $data['room_id'];

        $this->logger->info('Created signing room', [
            'room_id' => $roomId,
            'document' => $documentName,
        ]);

        return [
            'room_id' => $roomId,
            'document_name' => $documentName,
            'document_hash' => $documentHash,
        ];
    }

    /**
     * Upload a file to Matrix media repository
     */
    public function uploadFile(string $content, string $filename, string $mimeType): string
    {
        $client = $this->clientService->newClient();

        $response = $client->post(
            "{$this->homeserver}/_matrix/media/v3/upload?filename=" . urlencode($filename),
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => $mimeType,
                ],
                'body' => $content,
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['content_uri']; // mxc://server/mediaId
    }

    /**
     * Send a message to a room
     */
    public function sendMessage(string $roomId, string $text, ?string $html = null): string
    {
        $client = $this->clientService->newClient();
        $txnId = uniqid('msg_', true);

        $content = [
            'msgtype' => 'm.text',
            'body' => $text,
        ];

        if ($html) {
            $content['format'] = 'org.matrix.custom.html';
            $content['formatted_body'] = $html;
        }

        $response = $client->put(
            "{$this->homeserver}/_matrix/client/v3/rooms/" . urlencode($roomId) .
            "/send/m.room.message/{$txnId}",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $content,
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['event_id'];
    }

    /**
     * Send a file message to a room
     */
    public function sendFile(
        string $roomId,
        string $mxcUri,
        string $filename,
        string $mimeType,
        int $size
    ): string {
        $client = $this->clientService->newClient();
        $txnId = uniqid('file_', true);

        $response = $client->put(
            "{$this->homeserver}/_matrix/client/v3/rooms/" . urlencode($roomId) .
            "/send/m.room.message/{$txnId}",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'msgtype' => 'm.file',
                    'body' => $filename,
                    'filename' => $filename,
                    'url' => $mxcUri,
                    'info' => [
                        'mimetype' => $mimeType,
                        'size' => $size,
                    ],
                ],
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['event_id'];
    }

    /**
     * Send a custom signing event to the room
     */
    public function sendSigningEvent(string $roomId, string $eventType, array $content): string
    {
        $client = $this->clientService->newClient();
        $txnId = uniqid('sign_', true);

        $response = $client->put(
            "{$this->homeserver}/_matrix/client/v3/rooms/" . urlencode($roomId) .
            "/send/{$eventType}/{$txnId}",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $content,
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['event_id'];
    }

    /**
     * Post document for signing in a room
     */
    public function postDocumentForSigning(
        string $roomId,
        string $fileContent,
        string $filename,
        array $requiredSigners
    ): array {
        // Calculate hash
        $documentHash = hash('sha256', $fileContent);

        // Upload file
        $mxcUri = $this->uploadFile($fileContent, $filename, 'application/pdf');

        // Send file message
        $fileEventId = $this->sendFile(
            $roomId,
            $mxcUri,
            $filename,
            'application/pdf',
            strlen($fileContent)
        );

        // Send signing request event
        $signingEventId = $this->sendSigningEvent($roomId, 'com.signing.request', [
            'document' => [
                'name' => $filename,
                'mxc_uri' => $mxcUri,
                'sha256' => $documentHash,
                'size' => strlen($fileContent),
                'file_event_id' => $fileEventId,
            ],
            'required_signers' => $requiredSigners,
            'created_at' => time() * 1000,
            'expires_at' => (time() + self::SIGNING_REQUEST_EXPIRY_SECONDS) * 1000,
            'instructions' => 'Each signer must: 1) Verify other parties, 2) Download and review document, 3) Sign hash with SSH/GPG key, 4) Post signature to this room',
        ]);

        // Send human-readable message
        $signerList = implode(', ', $requiredSigners);
        $this->sendMessage(
            $roomId,
            "📄 Document ready for signing: {$filename}\n\n" .
            "SHA-256: {$documentHash}\n\n" .
            "Required signers: {$signerList}\n\n" .
            "Instructions:\n" .
            "1. Verify all other parties (emoji verification)\n" .
            "2. Download and review the document\n" .
            "3. Sign the SHA-256 hash with your SSH or GPG key\n" .
            "4. Post your signature to this room",
            "<h3>📄 Document ready for signing: {$filename}</h3>" .
            "<p><strong>SHA-256:</strong> <code>{$documentHash}</code></p>" .
            "<p><strong>Required signers:</strong> {$signerList}</p>" .
            "<h4>Instructions:</h4>" .
            "<ol>" .
            "<li>Verify all other parties (emoji verification)</li>" .
            "<li>Download and review the document</li>" .
            "<li>Sign the SHA-256 hash with your SSH or GPG key</li>" .
            "<li>Post your signature to this room</li>" .
            "</ol>"
        );

        return [
            'room_id' => $roomId,
            'document_hash' => $documentHash,
            'mxc_uri' => $mxcUri,
            'file_event_id' => $fileEventId,
            'signing_event_id' => $signingEventId,
        ];
    }

    /**
     * Post a signature to a room
     */
    public function postSignature(
        string $roomId,
        string $documentHash,
        string $signature,
        string $publicKey,
        string $keyType, // 'ssh-ed25519', 'ssh-rsa', 'gpg'
        string $signerMatrixId
    ): string {
        // Send signature event
        $eventId = $this->sendSigningEvent($roomId, 'com.signing.signature', [
            'document_hash' => $documentHash,
            'signature' => $signature,
            'public_key' => $publicKey,
            'key_type' => $keyType,
            'signer' => $signerMatrixId,
            'signed_at' => time() * 1000,
        ]);

        // Send human-readable message
        $shortKey = substr($publicKey, 0, 50) . '...';
        $this->sendMessage(
            $roomId,
            "✍️ Signature from {$signerMatrixId}\n\n" .
            "Document hash: {$documentHash}\n" .
            "Key type: {$keyType}\n" .
            "Public key: {$shortKey}\n\n" .
            "Signature:\n{$signature}"
        );

        return $eventId;
    }

    /**
     * Get room messages (for fetching signatures)
     */
    public function getRoomMessages(string $roomId, int $limit = 100): array
    {
        $client = $this->clientService->newClient();

        $response = $client->get(
            "{$this->homeserver}/_matrix/client/v3/rooms/" . urlencode($roomId) . "/messages",
            [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
                'query' => [
                    'dir' => 'b',
                    'limit' => $limit,
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * Get all signatures for a document from room history
     */
    public function getSignatures(string $roomId, string $documentHash): array
    {
        $messages = $this->getRoomMessages($roomId);
        $signatures = [];

        foreach ($messages['chunk'] ?? [] as $event) {
            if (($event['type'] ?? '') === 'com.signing.signature') {
                $content = $event['content'] ?? [];
                if (($content['document_hash'] ?? '') === $documentHash) {
                    $signatures[] = [
                        'event_id' => $event['event_id'],
                        'signer' => $content['signer'],
                        'signature' => $content['signature'],
                        'public_key' => $content['public_key'],
                        'key_type' => $content['key_type'],
                        'signed_at' => $content['signed_at'],
                        'matrix_sender' => $event['sender'],
                    ];
                }
            }
        }

        return $signatures;
    }

    /**
     * Download file from Matrix media repo
     */
    public function downloadFile(string $mxcUri): string
    {
        // Parse mxc://server/mediaId
        if (!preg_match('/^mxc:\/\/([^\/]+)\/(.+)$/', $mxcUri, $matches)) {
            throw new \InvalidArgumentException('Invalid mxc URI');
        }

        $serverName = $matches[1];
        $mediaId = $matches[2];

        $client = $this->clientService->newClient();

        $response = $client->get(
            "{$this->homeserver}/_matrix/media/v3/download/{$serverName}/{$mediaId}",
            ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]
        );

        return $response->getBody();
    }

    /**
     * Resolve Matrix user ID from email or username
     */
    public function resolveUserId(string $identifier): ?string
    {
        // If already a Matrix ID, return as-is
        if (str_starts_with($identifier, '@') && str_contains($identifier, ':')) {
            return $identifier;
        }

        // Try to look up by 3PID (email)
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            try {
                $client = $this->clientService->newClient();
                $response = $client->get(
                    "{$this->homeserver}/_matrix/client/v3/profile/" . urlencode($identifier),
                    ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]
                );
                $data = json_decode($response->getBody(), true);
                return $data['user_id'] ?? null;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
