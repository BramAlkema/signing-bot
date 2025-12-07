<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK\Drivers;

use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

/**
 * Interface for platform-specific bot drivers
 *
 * Implement this to add support for a new platform (Telegram, Slack, etc.)
 */
interface DriverInterface
{
    /**
     * Get the platform identifier (e.g., 'matrix', 'signal', 'telegram')
     */
    public function getPlatform(): string;

    /**
     * Send a response to the original message's room/channel
     */
    public function send(Message $originalMessage, Response $response): bool;

    /**
     * Send a message directly to a recipient (room ID or user ID)
     */
    public function sendTo(string $recipient, Response $response): bool;

    /**
     * Parse a platform-specific event into a Message object
     *
     * @param array $event Raw event data from the platform
     * @return Message|null Parsed message, or null if event should be ignored
     */
    public function parseEvent(array $event): ?Message;
}
