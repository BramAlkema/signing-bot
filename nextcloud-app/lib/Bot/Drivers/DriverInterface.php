<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot\Drivers;

use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;

/**
 * Interface for platform-specific bot drivers
 */
interface DriverInterface
{
    /**
     * Get the platform name (e.g., "signal", "matrix")
     */
    public function getPlatform(): string;

    /**
     * Send a response to the original sender/room
     */
    public function send(Message $originalMessage, Response $response): bool;

    /**
     * Send a direct message to a specific recipient
     */
    public function sendTo(string $recipient, Response $response): bool;
}
