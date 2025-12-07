<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bot\Commands;

use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;
use OCA\DocuSealIntegration\Service\DocuSealService;
use Psr\Log\LoggerInterface;

class DocuSealCommand implements CommandInterface
{
    public function __construct(
        private DocuSealService $docuSealService,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'docuseal';
    }

    public function getDescription(): string
    {
        return 'Send attached document for signing via DocuSeal';
    }

    public function matches(Message $message): bool
    {
        return $message->getCommand() === 'docuseal';
    }

    public function handle(Message $message, Response $response): void
    {
        // Check for attachments
        if (!$message->hasAttachments()) {
            $response->text("Please attach a document to sign.\n\nUsage:\n/docuseal user@email.com another@email.com\n\nAttach a PDF and mention the signers.");
            return;
        }

        // Get signers from mentioned users (emails)
        $signers = $message->getMentionedUsers();
        $emailSigners = array_filter($signers, fn($s) => str_contains($s, '@') && !str_starts_with($s, '@'));

        if (empty($emailSigners)) {
            $response->text("Please specify at least one signer email.\n\nUsage:\n/docuseal alice@company.com bob@client.com");
            return;
        }

        $attachments = $message->getAttachments();
        $attachment = $attachments[0]; // Use first attachment

        try {
            $response->text("Creating DocuSeal signing session...");

            // Get attachment content based on platform
            $fileContent = $this->getAttachmentContent($message, $attachment);
            $fileName = $attachment['filename'] ?? $attachment['id'] ?? 'document.pdf';

            // Create DocuSeal submission
            $submitters = array_map(fn($email) => [
                'email' => $email,
                'role' => 'Signer',
            ], $emailSigners);

            $result = $this->docuSealService->sendFileForSigning(
                $fileContent,
                $fileName,
                $submitters
            );

            // Build response with signing links
            $lines = ["Document sent for signing!\n"];
            $lines[] = "Document: {$fileName}";
            $lines[] = "Tracking ID: {$result['id']}\n";
            $lines[] = "Signing links:";

            foreach ($result['submitters'] ?? [] as $submitter) {
                $email = $submitter['email'] ?? 'unknown';
                $url = $submitter['embed_src'] ?? $submitter['slug'] ?? '';
                if ($url) {
                    $lines[] = "{$email}:\n{$url}";
                }
            }

            $lines[] = "\nI'll notify you when everyone has signed.";

            $response->text(implode("\n", $lines));

            $this->logger->info('DocuSeal submission created via bot', [
                'submission_id' => $result['id'],
                'signers' => $emailSigners,
                'platform' => $message->getPlatform(),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('DocuSeal command failed', [
                'error' => $e->getMessage(),
                'sender' => $message->getSender(),
            ]);
            $response->text("Failed to create signing session: {$e->getMessage()}");
        }
    }

    /**
     * Get attachment content based on platform
     */
    private function getAttachmentContent(Message $message, array $attachment): string
    {
        $platform = $message->getPlatform();

        if ($platform === 'signal') {
            // Signal attachments have a local file path
            $path = $attachment['file'] ?? $attachment['path'] ?? null;
            if ($path && file_exists($path)) {
                return file_get_contents($path);
            }
            throw new \RuntimeException('Signal attachment file not found');
        }

        if ($platform === 'matrix') {
            // Matrix attachments have mxc:// URIs - fetch via Matrix driver
            $mxcUri = $attachment['url'] ?? $attachment['mxc_uri'] ?? null;
            if ($mxcUri && $this->matrixDriver !== null) {
                return $this->matrixDriver->downloadMedia($mxcUri);
            }
            throw new \RuntimeException('Matrix attachment URL not found or driver not available');
        }

        throw new \RuntimeException("Cannot get attachment content for platform: {$platform}");
    }

    /**
     * Set Matrix driver for attachment downloads
     */
    public function setMatrixDriver(\OCA\DocuSealIntegration\Bot\Drivers\MatrixDriver $driver): void
    {
        $this->matrixDriver = $driver;
    }

    private ?\OCA\DocuSealIntegration\Bot\Drivers\MatrixDriver $matrixDriver = null;
}
