<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK;

/**
 * Platform-agnostic message representation
 *
 * This class normalizes messages from different platforms (Matrix, Signal, etc.)
 * into a common format that commands can work with.
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
        private ?array $raw = null,
    ) {
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

    /**
     * Get command name if message starts with / or !
     */
    public function getCommand(): ?string
    {
        $text = trim($this->text);
        if (str_starts_with($text, '/') || str_starts_with($text, '!')) {
            $parts = explode(' ', substr($text, 1), 2);
            return strtolower($parts[0]);
        }
        return null;
    }

    /**
     * Get command arguments (everything after the command name)
     */
    public function getArguments(): string
    {
        $text = trim($this->text);
        if (str_starts_with($text, '/') || str_starts_with($text, '!')) {
            $parts = explode(' ', substr($text, 1), 2);
            return $parts[1] ?? '';
        }
        return $text;
    }

    /**
     * Get command arguments as array
     */
    public function getArgumentList(): array
    {
        $args = $this->getArguments();
        return $args ? preg_split('/\s+/', $args) : [];
    }

    /**
     * Extract mentioned users (email addresses or @user:domain format)
     */
    public function getMentionedUsers(): array
    {
        $users = [];

        // Email pattern
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $this->text, $emails);
        $users = array_merge($users, $emails[0]);

        // Matrix user ID pattern
        preg_match_all('/@[a-zA-Z0-9._=-]+:[a-zA-Z0-9.-]+/', $this->text, $matrixIds);
        $users = array_merge($users, $matrixIds[0]);

        return array_unique($users);
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function getAttachments(): array
    {
        return $this->attachments ?? [];
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    /**
     * Get raw platform-specific data
     */
    public function getRaw(): ?array
    {
        return $this->raw;
    }

    /**
     * Check if message is a command (starts with / or !)
     */
    public function isCommand(): bool
    {
        return $this->getCommand() !== null;
    }
}
