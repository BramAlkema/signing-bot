<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\ExampleBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

/**
 * Simple ping command - responds with pong
 */
class PingCommand extends AbstractCommand
{
    protected string $name = 'ping';
    protected string $description = 'Check if bot is alive';
    protected array $aliases = ['p'];

    public function handle(Message $message, Response $response): void
    {
        $response->text('pong!');
    }
}
