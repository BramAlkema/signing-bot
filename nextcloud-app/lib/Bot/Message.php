<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot;

/**
 * Unified message object - works across all platforms (Matrix, Signal, etc.)
 */
class Message
{
    public function __construct(
        private string $platform,
        private string $sender,
        private string $text,
        private ?string $roomId = null,
        private ?array $attachments = null,
        private ?int $timestamp = null,
        private array $raw = [],
    ) {
        $this->timestamp = $timestamp ?? time();
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getRoomId(): ?string
    {
        return $this->roomId;
    }

    public function getAttachments(): array
    {
        return $this->attachments ?? [];
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Get the command from the message (e.g., "/docuseal" or "!docuseal" returns "docuseal")
     */
    public function getCommand(): ?string
    {
        $text = trim($this->text);
        if (str_starts_with($text, '/') || str_starts_with($text, '!')) {
            $parts = explode(' ', $text, 2);
            return ltrim($parts[0], '/!');
        }
        return null;
    }

    /**
     * Get command arguments (everything after the command)
     */
    public function getArguments(): string
    {
        $text = trim($this->text);
        if (str_starts_with($text, '/') || str_starts_with($text, '!')) {
            $parts = explode(' ', $text, 2);
            return $parts[1] ?? '';
        }
        return $text;
    }

    /**
     * Parse mentioned users from arguments (e.g., "@user:matrix.org" or "+31612345678")
     */
    public function getMentionedUsers(): array
    {
        $args = $this->getArguments();
        $users = [];

        // Matrix format: @user:server.com
        preg_match_all('/@[\w.-]+:[\w.-]+/', $args, $matrixMatches);
        $users = array_merge($users, $matrixMatches[0]);

        // Signal format: +31612345678
        preg_match_all('/\+\d{10,15}/', $args, $signalMatches);
        $users = array_merge($users, $signalMatches[0]);

        // Email format: user@domain.com (for DocuSeal)
        preg_match_all('/[\w.-]+@[\w.-]+\.\w+/', $args, $emailMatches);
        $users = array_merge($users, $emailMatches[0]);

        return array_unique($users);
    }
}
