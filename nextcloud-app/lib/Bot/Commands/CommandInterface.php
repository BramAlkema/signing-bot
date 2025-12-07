<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot\Commands;

use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;

/**
 * Interface for bot commands
 */
interface CommandInterface
{
    /**
     * Get the command name (without the leading slash)
     */
    public function getName(): string;

    /**
     * Get command description for /help
     */
    public function getDescription(): string;

    /**
     * Check if this command matches the message
     */
    public function matches(Message $message): bool;

    /**
     * Handle the command
     */
    public function handle(Message $message, Response $response): void;
}
