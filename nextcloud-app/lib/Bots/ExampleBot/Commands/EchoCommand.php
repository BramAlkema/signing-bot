<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\ExampleBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

/**
 * Echo command - repeats back what you say
 *
 * Usage: /echo Hello world
 * Response: Hello world
 */
class EchoCommand extends AbstractCommand
{
    protected string $name = 'echo';
    protected string $description = 'Repeat back your message';
    protected array $aliases = ['say'];

    public function handle(Message $message, Response $response): void
    {
        $text = $message->getArguments();

        if (empty($text)) {
            $response->text('Usage: /echo <message>');
            return;
        }

        $response->text($text);
    }
}
