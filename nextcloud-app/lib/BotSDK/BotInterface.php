<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK;

/**
 * Interface that all bots must implement
 */
interface BotInterface
{
    /**
     * Get the bot's unique identifier (e.g., 'docuseal', 'weather', 'remind')
     */
    public function getId(): string;

    /**
     * Get the bot's display name
     */
    public function getName(): string;

    /**
     * Get the bot's Matrix user ID (e.g., '@docuseal-bot:domain.com')
     */
    public function getBotUserId(): string;

    /**
     * Get the bot's description
     */
    public function getDescription(): string;

    /**
     * Get all commands this bot supports
     *
     * @return CommandInterface[]
     */
    public function getCommands(): array;

    /**
     * Check if this bot should handle a message
     * (e.g., check if message is in a room the bot is in, or mentions the bot)
     */
    public function shouldHandle(Message $message): bool;

    /**
     * Process an incoming message and return a response
     */
    public function handle(Message $message): Response;

    /**
     * Get help text for this bot
     */
    public function getHelpText(): string;
}
