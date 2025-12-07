<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK\Commands;

use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

/**
 * Interface for bot commands
 *
 * Implement this to create a new command for your bot.
 *
 * Example:
 *   class PingCommand implements CommandInterface
 *   {
 *       public function getName(): string { return 'ping'; }
 *       public function getDescription(): string { return 'Check if bot is alive'; }
 *       public function matches(Message $m): bool { return $m->getCommand() === 'ping'; }
 *       public function handle(Message $m, Response $r): void { $r->text('pong!'); }
 *   }
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
     * Handle the command - add responses to the Response object
     */
    public function handle(Message $message, Response $response): void;
}
