<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Controller;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller
{
    public function __construct(
        IRequest $request,
        private IConfig $config,
        private DocuSealService $docuSealService,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get current settings
     */
    #[NoAdminRequired]
    public function getSettings(): JSONResponse
    {
        $docusealUrl = $this->config->getAppValue(
            Application::APP_ID,
            'docuseal_url',
            ''
        );

        $hasApiKey = !empty($this->config->getAppValue(
            Application::APP_ID,
            'api_key',
            ''
        ));

        $signedDocumentsFolder = $this->config->getUserValue(
            $this->userId,
            Application::APP_ID,
            'signed_documents_folder',
            'Signed Documents'
        );

        return new JSONResponse([
            'docuseal_url' => $docusealUrl,
            'has_api_key' => $hasApiKey,
            'signed_documents_folder' => $signedDocumentsFolder,
            'connection_status' => $hasApiKey ? $this->docuSealService->testConnection() : false,
        ]);
    }

    /**
     * Update settings (admin only for API settings)
     */
    public function setSettings(): JSONResponse
    {
        $docusealUrl = $this->request->getParam('docuseal_url');
        $apiKey = $this->request->getParam('api_key');
        $webhookSecret = $this->request->getParam('webhook_secret');
        $signedDocumentsFolder = $this->request->getParam('signed_documents_folder');

        // App-wide settings (admin only)
        if ($docusealUrl !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'docuseal_url',
                $docusealUrl
            );
        }

        if ($apiKey !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'api_key',
                $apiKey
            );
        }

        if ($webhookSecret !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'webhook_secret',
                $webhookSecret
            );
        }

        // User-specific settings
        if ($signedDocumentsFolder !== null && $this->userId) {
            $this->config->setUserValue(
                $this->userId,
                Application::APP_ID,
                'signed_documents_folder',
                $signedDocumentsFolder
            );
        }

        // Test connection if API key was provided
        $connectionStatus = false;
        if ($apiKey) {
            $connectionStatus = $this->docuSealService->testConnection();
        }

        return new JSONResponse([
            'status' => 'ok',
            'connection_status' => $connectionStatus,
        ]);
    }
}
