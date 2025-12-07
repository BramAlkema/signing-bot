<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK;

use OCA\DocuSealIntegration\BotSDK\Commands\CommandInterface;
use OCA\DocuSealIntegration\BotSDK\Drivers\DriverInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for bots - implement this to create a new bot
 *
 * Example:
 *   class WeatherBot extends AbstractBot
 *   {
 *       public function getId(): string { return 'weather'; }
 *       public function getName(): string { return 'Weather Bot'; }
 *       protected function registerCommands(): void {
 *           $this->addCommand(new ForecastCommand($this->weatherService));
 *       }
 *   }
 */
abstract class AbstractBot implements BotInterface
{
    /** @var CommandInterface[] */
    protected array $commands = [];

    protected ?DriverInterface $driver = null;

    public function __construct(
        protected LoggerInterface $logger,
    ) {
        $this->registerCommands();
    }

    /**
     * Override this to register your bot's commands
     */
    abstract protected function registerCommands(): void;

    /**
     * Add a command to this bot
     */
    protected function addCommand(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * Set the driver for sending messages
     */
    public function setDriver(DriverInterface $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function getDriver(): ?DriverInterface
    {
        return $this->driver;
    }

    /**
     * Get the bot's Matrix user ID - override or configure via constructor
     */
    public function getBotUserId(): string
    {
        return '@' . $this->getId() . '-bot:matrix.example.com';
    }

    public function getDescription(): string
    {
        return 'A helpful bot';
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Default: handle all messages (override to filter by room, mention, etc.)
     */
    public function shouldHandle(Message $message): bool
    {
        // Don't handle messages from ourselves
        if ($message->getSender() === $this->getBotUserId()) {
            return false;
        }

        return true;
    }

    /**
     * Process message through registered commands
     */
    public function handle(Message $message): Response
    {
        $response = new Response();

        $this->logger->debug('Bot processing message', [
            'bot' => $this->getId(),
            'sender' => $message->getSender(),
            'text' => $message->getText(),
        ]);

        // Find matching command
        foreach ($this->commands as $command) {
            if ($command->matches($message)) {
                try {
                    $command->handle($message, $response);
                    $this->logger->info('Command handled', [
                        'bot' => $this->getId(),
                        'command' => $command->getName(),
                        'sender' => $message->getSender(),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Command error', [
                        'bot' => $this->getId(),
                        'command' => $command->getName(),
                        'error' => $e->getMessage(),
                    ]);
                    $response->text("Error: {$e->getMessage()}");
                }
                return $response;
            }
        }

        return $response;
    }

    /**
     * Send a response using the configured driver
     */
    public function sendResponse(Message $originalMessage, Response $response): bool
    {
        if (!$this->driver) {
            $this->logger->error('No driver configured for bot', ['bot' => $this->getId()]);
            return false;
        }

        if (!$response->hasContent()) {
            return true;
        }

        return $this->driver->send($originalMessage, $response);
    }

    public function getHelpText(): string
    {
        $lines = ["{$this->getName()}\n", $this->getDescription(), "\nCommands:"];

        foreach ($this->commands as $command) {
            $lines[] = "  /{$command->getName()} - {$command->getDescription()}";
        }

        return implode("\n", $lines);
    }
}
