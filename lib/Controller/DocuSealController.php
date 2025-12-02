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

class DocuSealController extends Controller
{
    public function __construct(
        IRequest $request,
        private DocuSealService $docuSealService,
        private SubmissionMapper $submissionMapper,
        private IRootFolder $rootFolder,
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
        $sendEmail = $this->request->getParam('send_email', true);

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

            // Upload to DocuSeal and create submission
            $result = $this->docuSealService->sendFileForSigning(
                $fileContent,
                $fileName,
                $submitters,
                $sendEmail,
                $templateId
            );

            // Store submission for tracking
            $this->submissionMapper->createFromDocuSeal(
                $result,
                $this->userId,
                $filePath
            );

            return new JSONResponse($result, Http::STATUS_CREATED);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
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
