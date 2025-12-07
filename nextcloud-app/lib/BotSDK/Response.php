<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK;

/**
 * Fluent response builder for bot replies
 *
 * Usage:
 *   $response->text('Hello!')
 *            ->text('How can I help?')
 *            ->attachment('/path/to/file.pdf', 'application/pdf');
 */
class Response
{
    private array $messages = [];
    private array $attachments = [];

    /**
     * Add a text message
     */
    public function text(string $content): self
    {
        $this->messages[] = [
            'type' => 'text',
            'content' => $content,
        ];
        return $this;
    }

    /**
     * Add formatted text (markdown)
     */
    public function markdown(string $content): self
    {
        $this->messages[] = [
            'type' => 'markdown',
            'content' => $content,
        ];
        return $this;
    }

    /**
     * Add a file attachment
     */
    public function attachment(string $path, string $mimeType, ?string $filename = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'mime_type' => $mimeType,
            'filename' => $filename ?? basename($path),
        ];
        return $this;
    }

    /**
     * Add attachment from content (not file path)
     */
    public function attachmentFromContent(string $content, string $filename, string $mimeType): self
    {
        // Write to temp file
        $tempPath = tempnam(sys_get_temp_dir(), 'bot_attachment_');
        file_put_contents($tempPath, $content);

        $this->attachments[] = [
            'path' => $tempPath,
            'mime_type' => $mimeType,
            'filename' => $filename,
            'temp' => true, // Mark for cleanup
        ];
        return $this;
    }

    /**
     * Add an error message
     */
    public function error(string $message): self
    {
        return $this->text("Error: {$message}");
    }

    /**
     * Check if response has any content
     */
    public function hasContent(): bool
    {
        return !empty($this->messages) || !empty($this->attachments);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Get all text content as a single string
     */
    public function getFullText(): string
    {
        $texts = [];
        foreach ($this->messages as $msg) {
            if (in_array($msg['type'], ['text', 'markdown'])) {
                $texts[] = $msg['content'];
            }
        }
        return implode("\n\n", $texts);
    }

    /**
     * Clean up temp files
     */
    public function cleanup(): void
    {
        foreach ($this->attachments as $attachment) {
            if (!empty($attachment['temp']) && file_exists($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
