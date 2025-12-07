<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Controller;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCA\DocuSealIntegration\Db\SubmissionMapper;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface;

class WebhookController extends Controller
{
    private ?string $rawBody = null;

    public function __construct(
        IRequest $request,
        private IConfig $config,
        private SubmissionMapper $submissionMapper,
        private DocuSealService $docuSealService,
        private IRootFolder $rootFolder,
        private IClientService $clientService,
        private NotificationManager $notificationManager,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
        // Capture raw body for signature verification before it's parsed
        $this->rawBody = file_get_contents('php://input');
    }

    /**
     * Handle DocuSeal webhook events
     *
     * Events:
     * - form.viewed - Document was viewed
     * - form.started - Signing started
     * - form.completed - Document fully signed
     * - submission.completed - All submitters completed
     * - submission.archived - Submission was archived
     */
    #[PublicPage]
    #[NoCSRFRequired]
    public function handle(): JSONResponse
    {
        // Parse payload from raw body to ensure consistency
        $payload = json_decode($this->rawBody ?? '', true) ?? $this->request->getParams();
        $event = $payload['event_type'] ?? null;

        // Log webhook receipt without sensitive payload data
        $this->logger->info('DocuSeal webhook received', [
            'event' => $event,
            'submission_id' => $payload['data']['submission_id'] ?? $payload['data']['id'] ?? null,
        ]);

        // Verify webhook signature
        $secret = $this->config->getAppValue(Application::APP_ID, 'webhook_secret');
        if ($secret) {
            $signature = $this->request->getHeader('X-DocuSeal-Signature');
            if (!$this->verifySignature($this->rawBody ?? '', $signature, $secret)) {
                $this->logger->warning('Invalid webhook signature');
                return new JSONResponse(
                    ['error' => 'Invalid signature'],
                    Http::STATUS_UNAUTHORIZED
                );
            }
        } else {
            // Security warning: webhook secret should be configured in production
            $this->logger->warning('DocuSeal webhook received without signature verification - configure webhook_secret for security');
        }

        try {
            switch ($event) {
                case 'form.completed':
                    $this->handleFormCompleted($payload);
                    break;

                case 'submission.completed':
                    $this->handleSubmissionCompleted($payload);
                    break;

                case 'form.viewed':
                case 'form.started':
                    $this->updateSubmissionStatus($payload);
                    break;

                default:
                    $this->logger->info('Unhandled webhook event', ['event' => $event]);
            }

            return new JSONResponse(['status' => 'ok']);
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);

            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Handle form.completed event - a single submitter completed
     */
    private function handleFormCompleted(array $payload): void
    {
        $submissionId = $payload['data']['submission_id'] ?? null;
        if (!$submissionId) {
            return;
        }

        $this->updateSubmissionStatus($payload);

        // Send notification to user
        $submission = $this->submissionMapper->findByDocuSealId($submissionId);
        if ($submission) {
            $this->sendNotification(
                $submission->getUserId(),
                'form_completed',
                [
                    'submitter' => $payload['data']['email'] ?? 'Unknown',
                    'document' => $payload['data']['template_name'] ?? 'Document',
                ]
            );
        }
    }

    /**
     * Handle submission.completed event - all submitters done
     */
    private function handleSubmissionCompleted(array $payload): void
    {
        $submissionId = $payload['data']['id'] ?? null;
        if (!$submissionId) {
            return;
        }

        $submission = $this->submissionMapper->findByDocuSealId($submissionId);
        if (!$submission) {
            $this->logger->warning('Submission not found for webhook', [
                'docuseal_id' => $submissionId,
            ]);
            return;
        }

        // Update status
        $submission->setStatus('completed');
        $submission->setCompletedAt(new \DateTime());
        $this->submissionMapper->update($submission);

        // Download signed document and save to Nextcloud
        $this->downloadAndSaveDocument($payload, $submission);

        // Send notification
        $this->sendNotification(
            $submission->getUserId(),
            'submission_completed',
            [
                'document' => $payload['data']['template_name'] ?? 'Document',
            ]
        );
    }

    /**
     * Download signed document and save to user's Nextcloud
     */
    private function downloadAndSaveDocument(array $payload, $submission): void
    {
        $documents = $payload['data']['documents'] ?? [];
        if (empty($documents)) {
            return;
        }

        $userId = $submission->getUserId();
        $userFolder = $this->rootFolder->getUserFolder($userId);

        // Get user's configured folder or use default
        $signedFolderPath = $this->config->getUserValue(
            $userId,
            Application::APP_ID,
            'signed_documents_folder',
            'Signed Documents'
        );

        // Create signed documents folder if it doesn't exist
        if (!$userFolder->nodeExists($signedFolderPath)) {
            $userFolder->newFolder($signedFolderPath);
        }
        $signedFolder = $userFolder->get($signedFolderPath);

        foreach ($documents as $doc) {
            $documentUrl = $doc['url'] ?? null;
            if (!$documentUrl) {
                continue;
            }

            // Download the signed document
            $client = $this->clientService->newClient();
            $response = $client->get($documentUrl);
            $content = $response->getBody();

            // Generate filename
            $originalName = $submission->getOriginalFilename() ?: 'document';
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $timestamp = date('Y-m-d_His');
            $fileName = "{$baseName}_signed_{$timestamp}.pdf";

            // Ensure unique filename
            $counter = 1;
            while ($signedFolder->nodeExists($fileName)) {
                $fileName = "{$baseName}_signed_{$timestamp}_{$counter}.pdf";
                $counter++;
            }

            // Save to Nextcloud
            $signedFolder->newFile($fileName, $content);

            $this->logger->info('Signed document saved', [
                'user' => $userId,
                'filename' => $fileName,
            ]);
        }

        // Update submission with saved path
        $submission->setSignedFilePath("{$signedFolderPath}/{$fileName}");
        $this->submissionMapper->update($submission);
    }

    /**
     * Update submission status from webhook payload
     */
    private function updateSubmissionStatus(array $payload): void
    {
        $submissionId = $payload['data']['submission_id'] ?? $payload['data']['id'] ?? null;
        if (!$submissionId) {
            return;
        }

        $submission = $this->submissionMapper->findByDocuSealId($submissionId);
        if ($submission) {
            $event = $payload['event_type'] ?? '';
            $statusMap = [
                'form.viewed' => 'viewed',
                'form.started' => 'in_progress',
                'form.completed' => 'partially_completed',
            ];

            if (isset($statusMap[$event])) {
                $submission->setStatus($statusMap[$event]);
                $this->submissionMapper->update($submission);
            }
        }
    }

    /**
     * Send Nextcloud notification to user
     */
    private function sendNotification(string $userId, string $subject, array $params): void
    {
        $notification = $this->notificationManager->createNotification();

        $notification->setApp(Application::APP_ID)
            ->setUser($userId)
            ->setDateTime(new \DateTime())
            ->setObject('submission', $params['document'] ?? 'document')
            ->setSubject($subject, $params);

        $this->notificationManager->notify($notification);
    }

    /**
     * Verify webhook signature using raw request body
     *
     * @param string $rawBody The raw JSON body from the request
     * @param string|null $signature The signature header value
     * @param string $secret The webhook secret
     * @return bool Whether the signature is valid
     */
    private function verifySignature(string $rawBody, ?string $signature, string $secret): bool
    {
        if (!$signature || empty($rawBody)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
