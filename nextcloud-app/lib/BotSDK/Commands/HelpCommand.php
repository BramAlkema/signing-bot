<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK\Commands;

use OCA\DocuSealIntegration\BotSDK\BotInterface;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

/**
 * Built-in help command - shows available commands for a bot
 */
class HelpCommand extends AbstractCommand
{
    protected string $name = 'help';
    protected string $description = 'Show available commands';
    protected array $aliases = ['h', '?'];

    public function __construct(
        private BotInterface $bot,
    ) {
    }

    public function handle(Message $message, Response $response): void
    {
        $response->text($this->bot->getHelpText());
    }
}
