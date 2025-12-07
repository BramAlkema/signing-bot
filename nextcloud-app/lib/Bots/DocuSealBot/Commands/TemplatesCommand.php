<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\DocuSealBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;
use OCA\DocuSealIntegration\Service\DocuSealService;

/**
 * Templates command - list available DocuSeal templates
 *
 * Usage: /templates
 */
class TemplatesCommand extends AbstractCommand
{
    protected string $name = 'templates';
    protected string $description = 'List available document templates';
    protected array $aliases = ['tpl', 't'];

    public function __construct(
        private DocuSealService $docuSealService,
    ) {
    }

    public function handle(Message $message, Response $response): void
    {
        try {
            $templates = $this->docuSealService->getTemplates();

            if (empty($templates)) {
                $response->text("No templates found.\n\nYou can still use /sign with an attached PDF.");
                return;
            }

            $lines = ["Available templates:\n"];

            foreach ($templates as $template) {
                $id = $template['id'] ?? '?';
                $name = $template['name'] ?? 'Unnamed';
                $lines[] = "  [{$id}] {$name}";
            }

            $lines[] = "\nTo use a template, upload it via the Nextcloud interface.";

            $response->text(implode("\n", $lines));

        } catch (\Throwable $e) {
            $response->error("Could not fetch templates: {$e->getMessage()}");
        }
    }
}
