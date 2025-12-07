<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot;

/**
 * Unified response object - driver translates to platform-specific format
 */
class Response
{
    private array $messages = [];
    private array $attachments = [];

    public function text(string $message): self
    {
        $this->messages[] = [
            'type' => 'text',
            'content' => $message,
        ];
        return $this;
    }

    public function attachment(string $path, string $mimeType, ?string $filename = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'mime_type' => $mimeType,
            'filename' => $filename ?? basename($path),
        ];
        return $this;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function hasContent(): bool
    {
        return !empty($this->messages) || !empty($this->attachments);
    }
}
