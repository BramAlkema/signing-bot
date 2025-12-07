<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\DocuSealBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;
use OCA\DocuSealIntegration\Service\DocuSealService;

/**
 * Status command - check status of a signing submission
 *
 * Usage: /status <submission_id>
 */
class StatusCommand extends AbstractCommand
{
    protected string $name = 'status';
    protected string $description = 'Check signing status by ID';
    protected array $aliases = ['st'];

    public function __construct(
        private DocuSealService $docuSealService,
    ) {
    }

    public function handle(Message $message, Response $response): void
    {
        $args = $message->getArgumentList();

        if (empty($args)) {
            $response->text("Usage: /status <submission_id>\n\nExample: /status 12345");
            return;
        }

        $submissionId = (int) $args[0];

        try {
            $submission = $this->docuSealService->getSubmission($submissionId);

            $lines = ["Submission #{$submissionId}\n"];

            // Status
            $status = $submission['status'] ?? 'unknown';
            $lines[] = "Status: {$status}";

            // Template/document name
            if (isset($submission['template']['name'])) {
                $lines[] = "Document: {$submission['template']['name']}";
            }

            // Submitters and their status
            $lines[] = "\nSigners:";
            foreach ($submission['submitters'] ?? [] as $submitter) {
                $email = $submitter['email'] ?? 'unknown';
                $subStatus = $submitter['status'] ?? 'pending';
                $completedAt = $submitter['completed_at'] ?? null;

                $statusLine = "  {$email}: {$subStatus}";
                if ($completedAt) {
                    $statusLine .= " (completed {$completedAt})";
                }
                $lines[] = $statusLine;
            }

            // Documents if completed
            if (!empty($submission['documents'])) {
                $lines[] = "\nSigned documents available.";
            }

            $response->text(implode("\n", $lines));

        } catch (\Throwable $e) {
            $response->error("Could not fetch status: {$e->getMessage()}");
        }
    }
}
