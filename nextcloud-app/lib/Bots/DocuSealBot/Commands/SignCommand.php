<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Bots\DocuSealBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Drivers\MatrixDriver;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;
use OCA\DocuSealIntegration\Service\DocuSealService;
use Psr\Log\LoggerInterface;

/**
 * Sign command - send attached documents for signing
 *
 * Usage: /sign alice@example.com bob@example.com
 *        (attach a PDF)
 */
class SignCommand extends AbstractCommand
{
    protected string $name = 'sign';
    protected string $description = 'Send attached document for signing';
    protected array $aliases = ['docuseal', 's'];

    private ?MatrixDriver $matrixDriver = null;

    public function __construct(
        private DocuSealService $docuSealService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Set Matrix driver for downloading attachments
     */
    public function setMatrixDriver(MatrixDriver $driver): void
    {
        $this->matrixDriver = $driver;
    }

    public function handle(Message $message, Response $response): void
    {
        // Check for attachments
        if (!$message->hasAttachments()) {
            $response->text(
                "Please attach a document to sign.\n\n" .
                "Usage:\n" .
                "/sign user@email.com another@email.com\n\n" .
                "Attach a PDF and mention the signers."
            );
            return;
        }

        // Get signers from mentioned users (emails only, not Matrix IDs)
        $signers = $message->getMentionedUsers();
        $emailSigners = array_filter(
            $signers,
            fn($s) => str_contains($s, '@') && !str_starts_with($s, '@')
        );

        if (empty($emailSigners)) {
            $response->text(
                "Please specify at least one signer email.\n\n" .
                "Usage:\n" .
                "/sign alice@company.com bob@client.com"
            );
            return;
        }

        $attachments = $message->getAttachments();
        $attachment = $attachments[0];

        try {
            // Get attachment content
            $fileContent = $this->getAttachmentContent($message, $attachment);
            $fileName = $attachment['filename'] ?? $attachment['id'] ?? 'document.pdf';

            // Create DocuSeal submission
            $submitters = array_map(fn($email) => [
                'email' => $email,
                'role' => 'Signer',
            ], array_values($emailSigners));

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
                    $lines[] = "  {$email}:\n  {$url}";
                }
            }

            $lines[] = "\nI'll notify you when everyone has signed.";

            $response->text(implode("\n", $lines));

            $this->logger->info('DocuSeal submission created via bot', [
                'submission_id' => $result['id'],
                'signers' => array_values($emailSigners),
                'platform' => $message->getPlatform(),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Sign command failed', [
                'error' => $e->getMessage(),
                'sender' => $message->getSender(),
            ]);
            $response->error("Failed to create signing session: {$e->getMessage()}");
        }
    }

    /**
     * Get attachment content based on platform
     */
    private function getAttachmentContent(Message $message, array $attachment): string
    {
        $platform = $message->getPlatform();

        if ($platform === 'matrix') {
            $mxcUri = $attachment['url'] ?? $attachment['mxc_uri'] ?? null;
            if ($mxcUri && $this->matrixDriver !== null) {
                return $this->matrixDriver->downloadMedia($mxcUri);
            }
            throw new \RuntimeException('Matrix attachment URL not found or driver not available');
        }

        if ($platform === 'signal') {
            $path = $attachment['file'] ?? $attachment['path'] ?? null;
            if ($path && file_exists($path)) {
                return file_get_contents($path);
            }
            throw new \RuntimeException('Signal attachment file not found');
        }

        throw new \RuntimeException("Cannot get attachment content for platform: {$platform}");
    }
}
