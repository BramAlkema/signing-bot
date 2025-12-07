<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot;

use OCA\DocuSealIntegration\Bot\Commands\CommandInterface;
use OCA\DocuSealIntegration\Bot\Drivers\DriverInterface;
use Psr\Log\LoggerInterface;

/**
 * Bot framework dispatcher - routes messages to commands
 */
class BotFramework
{
    /** @var CommandInterface[] */
    private array $commands = [];

    /** @var DriverInterface[] */
    private array $drivers = [];

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Register a command handler
     */
    public function registerCommand(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * Register a platform driver
     */
    public function registerDriver(DriverInterface $driver): self
    {
        $this->drivers[$driver->getPlatform()] = $driver;
        return $this;
    }

    /**
     * Get driver for a platform
     */
    public function getDriver(string $platform): ?DriverInterface
    {
        return $this->drivers[$platform] ?? null;
    }

    /**
     * Process an incoming message
     */
    public function process(Message $message): Response
    {
        $response = new Response();

        $this->logger->debug('Bot processing message', [
            'platform' => $message->getPlatform(),
            'sender' => $message->getSender(),
            'text' => $message->getText(),
        ]);

        // Find matching command
        foreach ($this->commands as $command) {
            if ($command->matches($message)) {
                try {
                    $command->handle($message, $response);
                    $this->logger->info('Command handled', [
                        'command' => $command->getName(),
                        'sender' => $message->getSender(),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Command error', [
                        'command' => $command->getName(),
                        'error' => $e->getMessage(),
                    ]);
                    $response->text("Error: {$e->getMessage()}");
                }
                return $response;
            }
        }

        // No command matched - could show help or ignore
        $this->logger->debug('No command matched', [
            'text' => $message->getText(),
        ]);

        return $response;
    }

    /**
     * Process and send response using appropriate driver
     */
    public function handleMessage(Message $message): bool
    {
        $response = $this->process($message);

        if (!$response->hasContent()) {
            return true; // Nothing to send
        }

        $driver = $this->getDriver($message->getPlatform());
        if (!$driver) {
            $this->logger->error('No driver for platform', [
                'platform' => $message->getPlatform(),
            ]);
            return false;
        }

        return $driver->send($message, $response);
    }

    /**
     * Get all registered commands (for /help)
     *
     * @return CommandInterface[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Generate help text
     */
    public function getHelpText(): string
    {
        $lines = ["Available commands:\n"];

        foreach ($this->commands as $command) {
            $lines[] = "/{$command->getName()} - {$command->getDescription()}";
        }

        return implode("\n", $lines);
    }
}
