<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK\Commands;

use OCA\DocuSealIntegration\BotSDK\Message;

/**
 * Abstract base class for commands - provides default matches() implementation
 *
 * Example:
 *   class PingCommand extends AbstractCommand
 *   {
 *       protected string $name = 'ping';
 *       protected string $description = 'Check if bot is alive';
 *
 *       public function handle(Message $message, Response $response): void
 *       {
 *           $response->text('pong!');
 *       }
 *   }
 */
abstract class AbstractCommand implements CommandInterface
{
    protected string $name = '';
    protected string $description = '';

    /** @var string[] Alternative names/aliases for this command */
    protected array $aliases = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Default matching: check if command equals name or any alias
     */
    public function matches(Message $message): bool
    {
        $command = $message->getCommand();
        if ($command === null) {
            return false;
        }

        if ($command === $this->name) {
            return true;
        }

        return in_array($command, $this->aliases, true);
    }
}
