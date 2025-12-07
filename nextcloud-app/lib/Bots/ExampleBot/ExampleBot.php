<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\ExampleBot;

use OCA\DocuSealIntegration\BotSDK\AbstractBot;
use OCA\DocuSealIntegration\BotSDK\Commands\HelpCommand;
use OCA\DocuSealIntegration\Bots\ExampleBot\Commands\PingCommand;
use OCA\DocuSealIntegration\Bots\ExampleBot\Commands\EchoCommand;
use OCA\DocuSealIntegration\Bots\ExampleBot\Commands\TimeCommand;
use Psr\Log\LoggerInterface;

/**
 * Example bot - use as a template for creating new bots
 *
 * This bot demonstrates:
 * - Basic command structure
 * - Help command integration
 * - Simple stateless commands
 *
 * To create your own bot:
 * 1. Copy this directory to lib/Bots/YourBotName/
 * 2. Rename classes and update namespaces
 * 3. Implement your commands in Commands/
 * 4. Register in the controller's buildBotRegistry() method
 */
class ExampleBot extends AbstractBot
{
    public function __construct(
        LoggerInterface $logger,
        private string $botUserId = '@example-bot:matrix.example.com',
    ) {
        parent::__construct($logger);
    }

    public function getId(): string
    {
        return 'example';
    }

    public function getName(): string
    {
        return 'Example Bot';
    }

    public function getBotUserId(): string
    {
        return $this->botUserId;
    }

    public function getDescription(): string
    {
        return 'A simple example bot demonstrating the SDK';
    }

    protected function registerCommands(): void
    {
        // Built-in help command
        $this->addCommand(new HelpCommand($this));

        // Custom commands
        $this->addCommand(new PingCommand());
        $this->addCommand(new EchoCommand());
        $this->addCommand(new TimeCommand());
    }
}
