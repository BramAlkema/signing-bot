<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Command;

use OCA\DocuSealIntegration\Bot\BotFramework;
use OCA\DocuSealIntegration\Bot\Commands\DocuSealCommand;
use OCA\DocuSealIntegration\Bot\Commands\HelpCommand;
use OCA\DocuSealIntegration\Bot\Drivers\SignalDriver;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Signal bot worker - runs as a daemon, connects to signal-cli socket
 *
 * Usage:
 *   occ docuseal:signal-worker
 *
 * Run as systemd service:
 *   ExecStart=/usr/bin/docker exec nextcloud-proper-php-1 php occ docuseal:signal-worker
 */
class SignalWorker extends Command
{
    private bool $running = true;

    public function __construct(
        private IConfig $config,
        private LoggerInterface $logger,
        private DocuSealService $docuSealService,
        private SignalDriver $signalDriver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('docuseal:signal-worker')
            ->setDescription('Run the Signal bot worker (daemon mode)')
            ->addOption(
                'once',
                null,
                InputOption::VALUE_NONE,
                'Process messages once and exit (for testing)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $once = $input->getOption('once');

        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT, fn() => $this->running = false);
        }

        $output->writeln('<info>Signal Bot Worker Starting</info>');
        $output->writeln('');

        try {
            // Test connection
            $version = $this->signalDriver->getVersion();
            $output->writeln("signal-cli version: <comment>{$version}</comment>");
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to connect to signal-cli: {$e->getMessage()}</error>");
            $output->writeln('');
            $output->writeln('Make sure signal-cli daemon is running:');
            $output->writeln('  systemctl status signal-cli');
            return Command::FAILURE;
        }

        // Build bot framework
        $framework = $this->buildFramework();

        $output->writeln('');
        $output->writeln('Registered commands:');
        foreach ($framework->getCommands() as $cmd) {
            $output->writeln("  /{$cmd->getName()} - {$cmd->getDescription()}");
        }
        $output->writeln('');
        $output->writeln('<info>Listening for messages...</info>');
        $output->writeln('');

        // Main loop
        while ($this->running) {
            try {
                // Process signals if available
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Receive messages (blocking with timeout)
                $messages = $this->signalDriver->receiveMessages(5);

                foreach ($messages as $message) {
                    $sender = $message->getSender();
                    $text = $message->getText();

                    $output->writeln("[" . date('H:i:s') . "] <comment>{$sender}</comment>: {$text}");

                    // Process through bot framework
                    $handled = $framework->handleMessage($message);

                    if ($handled) {
                        $output->writeln("  <info>-> Responded</info>");
                    }
                }

                if ($once) {
                    break;
                }

            } catch (\Throwable $e) {
                $this->logger->error('Signal worker error', [
                    'error' => $e->getMessage(),
                ]);
                $output->writeln("<error>Error: {$e->getMessage()}</error>");

                // Reconnect after error
                $this->signalDriver->disconnect();
                sleep(5);
            }
        }

        $output->writeln('');
        $output->writeln('<info>Shutting down...</info>');
        $this->signalDriver->disconnect();

        return Command::SUCCESS;
    }

    /**
     * Build and configure the bot framework
     */
    private function buildFramework(): BotFramework
    {
        $framework = new BotFramework($this->logger);

        // Register driver
        $framework->registerDriver($this->signalDriver);

        // Register commands
        $framework->registerCommand(new HelpCommand($framework));
        $framework->registerCommand(new DocuSealCommand(
            $this->docuSealService,
            $this->logger
        ));

        return $framework;
    }
}
