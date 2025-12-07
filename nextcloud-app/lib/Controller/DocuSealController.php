<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Controller;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCA\DocuSealIntegration\Db\SubmissionMapper;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class DocuSealController extends Controller
{
    public function __construct(
        IRequest $request,
        private DocuSealService $docuSealService,
        private SubmissionMapper $submissionMapper,
        private IRootFolder $rootFolder,
        private IUserManager $userManager,
        private INotificationManager $notificationManager,
        private LoggerInterface $logger,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get all available DocuSeal templates
     */
    #[NoAdminRequired]
    public function getTemplates(): JSONResponse
    {
        try {
            $templates = $this->docuSealService->getTemplates();
            return new JSONResponse($templates);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Create a new submission from a template
     */
    #[NoAdminRequired]
    public function createSubmission(): JSONResponse
    {
        $templateId = $this->request->getParam('template_id');
        $submitters = $this->request->getParam('submitters', []);
        $sendEmail = $this->request->getParam('send_email', true);
        $message = $this->request->getParam('message');

        if (!$templateId || empty($submitters)) {
            return new JSONResponse(
                ['error' => 'Template ID and submitters are required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $result = $this->docuSealService->createSubmission(
                (int) $templateId,
                $submitters,
                $sendEmail,
                $message
            );

            // Store submission in local database for tracking
            $this->submissionMapper->createFromDocuSeal($result, $this->userId);

            return new JSONResponse($result, Http::STATUS_CREATED);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Send a Nextcloud file to DocuSeal
     */
    #[NoAdminRequired]
    public function sendFile(): JSONResponse
    {
        $filePath = $this->request->getParam('file_path');
        $templateId = $this->request->getParam('template_id');
        $submitters = $this->request->getParam('submitters', []);
        $message = $this->request->getParam('message', '');

        if (!$filePath || empty($submitters)) {
            return new JSONResponse(
                ['error' => 'File path and submitters are required'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            // Get file from Nextcloud
            $userFolder = $this->rootFolder->getUserFolder($this->userId);
            $file = $userFolder->get($filePath);

            if ($file->getMimeType() !== 'application/pdf') {
                return new JSONResponse(
                    ['error' => 'Only PDF files are supported'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $fileContent = $file->getContent();
            $fileName = $file->getName();

            // Resolve user IDs to emails
            $resolvedSubmitters = $this->resolveSubmitters($submitters);

            if (empty($resolvedSubmitters)) {
                return new JSONResponse(
                    ['error' => 'No valid users found with email addresses'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // Upload to DocuSeal and create submission
            // Don't send email from DocuSeal - we'll use Nextcloud notifications
            $result = $this->docuSealService->sendFileForSigning(
                $fileContent,
                $fileName,
                $resolvedSubmitters,
                false, // Don't send DocuSeal emails
                $templateId
            );

            // Store submission for tracking
            $submission = $this->submissionMapper->createFromDocuSeal(
                $result,
                $this->userId,
                $filePath
            );

            // Send Nextcloud notifications to signers
            $this->notifySigners($resolvedSubmitters, $fileName, $submission->getId(), $message);

            return new JSONResponse($result, Http::STATUS_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send file for signing', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Resolve user IDs to email addresses
     */
    private function resolveSubmitters(array $submitters): array
    {
        $resolved = [];

        foreach ($submitters as $submitter) {
            $uid = $submitter['uid'] ?? null;
            $name = $submitter['name'] ?? '';

            if (!$uid) {
                continue;
            }

            $user = $this->userManager->get($uid);
            if (!$user) {
                $this->logger->warning('User not found', ['uid' => $uid]);
                continue;
            }

            $email = $user->getEMailAddress();
            if (!$email) {
                $this->logger->warning('User has no email address', ['uid' => $uid]);
                continue;
            }

            $resolved[] = [
                'uid' => $uid,
                'name' => $name ?: $user->getDisplayName(),
                'email' => $email,
            ];
        }

        return $resolved;
    }

    /**
     * Send Nextcloud notifications to signers
     */
    private function notifySigners(array $submitters, string $fileName, int $submissionId, string $message): void
    {
        foreach ($submitters as $submitter) {
            $uid = $submitter['uid'] ?? null;
            if (!$uid) {
                continue;
            }

            try {
                $notification = $this->notificationManager->createNotification();
                $notification
                    ->setApp(Application::APP_ID)
                    ->setUser($uid)
                    ->setDateTime(new \DateTime())
                    ->setObject('submission', (string) $submissionId)
                    ->setSubject('signature_request', [
                        'file' => $fileName,
                        'sender' => $this->userId,
                        'message' => $message,
                    ]);

                $this->notificationManager->notify($notification);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send notification', [
                    'uid' => $uid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get a specific submission status
     */
    #[NoAdminRequired]
    public function getSubmission(int $id): JSONResponse
    {
        try {
            $submission = $this->submissionMapper->find($id, $this->userId);

            // Also fetch current status from DocuSeal
            $docuSealStatus = $this->docuSealService->getSubmission($submission->getDocusealId());

            return new JSONResponse([
                'local' => $submission,
                'docuseal' => $docuSealStatus,
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * List all submissions for current user
     */
    #[NoAdminRequired]
    public function listSubmissions(): JSONResponse
    {
        try {
            $submissions = $this->submissionMapper->findAllForUser($this->userId);
            return new JSONResponse($submissions);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
