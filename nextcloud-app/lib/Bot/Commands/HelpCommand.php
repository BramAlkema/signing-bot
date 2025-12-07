<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot\Commands;

use OCA\DocuSealIntegration\Bot\BotFramework;
use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;

class HelpCommand implements CommandInterface
{
    public function __construct(
        private BotFramework $framework,
    ) {
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Show available commands';
    }

    public function matches(Message $message): bool
    {
        return $message->getCommand() === 'help';
    }

    public function handle(Message $message, Response $response): void
    {
        $response->text($this->framework->getHelpText());
    }
}
