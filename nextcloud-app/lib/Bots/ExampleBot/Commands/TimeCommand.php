<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\ExampleBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

/**
 * Time command - shows current server time
 *
 * Usage: /time [timezone]
 * Response: Current time in specified timezone
 */
class TimeCommand extends AbstractCommand
{
    protected string $name = 'time';
    protected string $description = 'Show current time';
    protected array $aliases = ['now', 'date'];

    public function handle(Message $message, Response $response): void
    {
        $timezone = $message->getArguments() ?: 'UTC';

        try {
            $tz = new \DateTimeZone($timezone);
            $now = new \DateTime('now', $tz);
            $response->text($now->format('Y-m-d H:i:s T'));
        } catch (\Exception $e) {
            $response->error("Unknown timezone: {$timezone}");
        }
    }
}
