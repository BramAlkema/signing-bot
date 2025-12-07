<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\DocuSealBot;

use OCA\DocuSealIntegration\BotSDK\AbstractBot;
use OCA\DocuSealIntegration\BotSDK\Commands\HelpCommand;
use OCA\DocuSealIntegration\Bots\DocuSealBot\Commands\SignCommand;
use OCA\DocuSealIntegration\Bots\DocuSealBot\Commands\StatusCommand;
use OCA\DocuSealIntegration\Bots\DocuSealBot\Commands\TemplatesCommand;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * DocuSeal Bot - Document signing via chat
 *
 * Commands:
 * - /sign (or /docuseal) - Send document for signing
 * - /status - Check signing status
 * - /templates - List available templates
 * - /help - Show available commands
 */
class DocuSealBot extends AbstractBot
{
    private string $botUserId;

    public function __construct(
        LoggerInterface $logger,
        private DocuSealService $docuSealService,
        private IConfig $config,
        private string $appId = 'docuseal_integration',
    ) {
        $this->botUserId = $this->config->getAppValue(
            $this->appId,
            'matrix_bot_user',
            '@docuseal-bot:matrix.example.com'
        );

        parent::__construct($logger);
    }

    public function getId(): string
    {
        return 'docuseal';
    }

    public function getName(): string
    {
        return 'DocuSeal Bot';
    }

    public function getBotUserId(): string
    {
        return $this->botUserId;
    }

    public function getDescription(): string
    {
        return 'Send documents for electronic signing via DocuSeal';
    }

    protected function registerCommands(): void
    {
        // Help command
        $this->addCommand(new HelpCommand($this));

        // Sign command (main functionality)
        $signCommand = new SignCommand($this->docuSealService, $this->logger);
        $this->addCommand($signCommand);

        // Status command
        $this->addCommand(new StatusCommand($this->docuSealService));

        // Templates command
        $this->addCommand(new TemplatesCommand($this->docuSealService));
    }

    /**
     * Get the DocuSeal service for commands that need it
     */
    public function getDocuSealService(): DocuSealService
    {
        return $this->docuSealService;
    }
}
