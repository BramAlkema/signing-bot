<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK;

use OCA\DocuSealIntegration\BotSDK\Drivers\DriverInterface;
use Psr\Log\LoggerInterface;

/**
 * Registry that manages multiple bots and routes messages to them
 *
 * Usage:
 *   $registry = new BotRegistry($logger);
 *   $registry->setDriver($matrixDriver);
 *   $registry->register(new DocuSealBot(...));
 *   $registry->register(new WeatherBot(...));
 *   $registry->handleMessage($message);
 */
class BotRegistry
{
    /** @var BotInterface[] */
    private array $bots = [];

    private ?DriverInterface $driver = null;

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Set the shared driver for all bots
     */
    public function setDriver(DriverInterface $driver): self
    {
        $this->driver = $driver;

        // Update driver on all registered bots
        foreach ($this->bots as $bot) {
            if ($bot instanceof AbstractBot) {
                $bot->setDriver($driver);
            }
        }

        return $this;
    }

    /**
     * Register a bot
     */
    public function register(BotInterface $bot): self
    {
        $this->bots[$bot->getId()] = $bot;

        // Set driver if we have one
        if ($this->driver && $bot instanceof AbstractBot) {
            $bot->setDriver($this->driver);
        }

        $this->logger->info('Bot registered', [
            'bot_id' => $bot->getId(),
            'bot_name' => $bot->getName(),
            'commands' => array_keys($bot->getCommands()),
        ]);

        return $this;
    }

    /**
     * Get a bot by ID
     */
    public function get(string $botId): ?BotInterface
    {
        return $this->bots[$botId] ?? null;
    }

    /**
     * Get all registered bots
     *
     * @return BotInterface[]
     */
    public function all(): array
    {
        return $this->bots;
    }

    /**
     * Find bot by Matrix user ID
     */
    public function findByUserId(string $userId): ?BotInterface
    {
        foreach ($this->bots as $bot) {
            if ($bot->getBotUserId() === $userId) {
                return $bot;
            }
        }
        return null;
    }

    /**
     * Handle an incoming message - routes to all bots that should handle it
     *
     * @return bool True if at least one bot handled the message
     */
    public function handleMessage(Message $message): bool
    {
        $handled = false;

        foreach ($this->bots as $bot) {
            if (!$bot->shouldHandle($message)) {
                continue;
            }

            $response = $bot->handle($message);

            if ($response->hasContent()) {
                $this->sendResponse($bot, $message, $response);
                $handled = true;
            }
        }

        return $handled;
    }

    /**
     * Send a response from a bot
     */
    private function sendResponse(BotInterface $bot, Message $message, Response $response): bool
    {
        if ($bot instanceof AbstractBot) {
            return $bot->sendResponse($message, $response);
        }

        // Fallback for non-AbstractBot implementations
        if ($this->driver) {
            return $this->driver->send($message, $response);
        }

        $this->logger->error('No driver available to send response', [
            'bot' => $bot->getId(),
        ]);
        return false;
    }

    /**
     * Get combined help text from all bots
     */
    public function getHelpText(): string
    {
        $sections = ["Available Bots:\n"];

        foreach ($this->bots as $bot) {
            $sections[] = "--- {$bot->getName()} ({$bot->getBotUserId()}) ---";
            $sections[] = $bot->getHelpText();
            $sections[] = "";
        }

        return implode("\n", $sections);
    }

    /**
     * Get list of all commands across all bots
     */
    public function getAllCommands(): array
    {
        $commands = [];
        foreach ($this->bots as $bot) {
            foreach ($bot->getCommands() as $name => $command) {
                $commands[$bot->getId() . ':' . $name] = [
                    'bot' => $bot->getId(),
                    'command' => $name,
                    'description' => $command->getDescription(),
                ];
            }
        }
        return $commands;
    }
}
