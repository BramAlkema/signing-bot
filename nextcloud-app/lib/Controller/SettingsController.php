<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Controller;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCA\DocuSealIntegration\BotSDK\Drivers\SignalDriver;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCA\DocuSealIntegration\Service\MatrixService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
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
        private MatrixService $matrixService,
        private SignalDriver $signalDriver,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get current settings (non-sensitive)
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

        $matrixHomeserver = $this->config->getAppValue(
            Application::APP_ID,
            'matrix_homeserver',
            ''
        );

        $hasMatrixToken = !empty($this->config->getAppValue(
            Application::APP_ID,
            'matrix_access_token',
            ''
        ));

        $matrixBotUser = $this->config->getAppValue(
            Application::APP_ID,
            'matrix_bot_user',
            ''
        );

        $signedDocumentsFolder = $this->config->getUserValue(
            $this->userId,
            Application::APP_ID,
            'signed_documents_folder',
            'Signed Documents'
        );

        // Signal settings
        $signalEnabled = $this->config->getAppValue(
            Application::APP_ID,
            'signal_enabled',
            '0'
        ) === '1';

        $signalPhoneNumber = $this->config->getAppValue(
            Application::APP_ID,
            'signal_phone_number',
            ''
        );

        $signalSocket = $this->config->getAppValue(
            Application::APP_ID,
            'signal_socket',
            'tcp://172.18.0.1:7583'
        );

        return new JSONResponse([
            'docuseal_url' => $docusealUrl,
            'has_api_key' => $hasApiKey,
            'matrix_homeserver' => $matrixHomeserver,
            'has_matrix_token' => $hasMatrixToken,
            'matrix_bot_user' => $matrixBotUser,
            'signed_documents_folder' => $signedDocumentsFolder,
            'signal_enabled' => $signalEnabled,
            'signal_phone_number' => $signalPhoneNumber,
            'signal_socket' => $signalSocket,
            'docuseal_connected' => $hasApiKey ? $this->docuSealService->testConnection() : false,
            'matrix_connected' => $hasMatrixToken ? $this->matrixService->testConnection() : ['connected' => false],
        ]);
    }

    /**
     * Update admin settings (DocuSeal and Matrix configuration)
     * Admin only - requires admin authorization
     */
    #[AuthorizedAdminSetting(settings: \OCA\DocuSealIntegration\Settings\Admin::class)]
    public function setSettings(): JSONResponse
    {
        $docusealUrl = $this->request->getParam('docuseal_url');
        $apiKey = $this->request->getParam('api_key');
        $webhookSecret = $this->request->getParam('webhook_secret');

        $matrixHomeserver = $this->request->getParam('matrix_homeserver');
        $matrixAccessToken = $this->request->getParam('matrix_access_token');
        $matrixBotUser = $this->request->getParam('matrix_bot_user');

        // Signal settings
        $signalEnabled = $this->request->getParam('signal_enabled');
        $signalPhoneNumber = $this->request->getParam('signal_phone_number');
        $signalSocket = $this->request->getParam('signal_socket');

        // DocuSeal settings
        if ($docusealUrl !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'docuseal_url',
                rtrim($docusealUrl, '/')
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

        // Matrix settings
        if ($matrixHomeserver !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'matrix_homeserver',
                rtrim($matrixHomeserver, '/')
            );
        }

        if ($matrixAccessToken !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'matrix_access_token',
                $matrixAccessToken
            );
        }

        if ($matrixBotUser !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'matrix_bot_user',
                $matrixBotUser
            );
        }

        // Signal settings - always save if present in request (even if false)
        $params = $this->request->getParams();
        if (array_key_exists('signal_enabled', $params)) {
            $this->config->setAppValue(
                Application::APP_ID,
                'signal_enabled',
                $signalEnabled ? '1' : '0'
            );
        }

        if ($signalPhoneNumber !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'signal_phone_number',
                $signalPhoneNumber
            );
        }

        if ($signalSocket !== null) {
            $this->config->setAppValue(
                Application::APP_ID,
                'signal_socket',
                $signalSocket
            );
        }

        // Test connections
        $docusealConnected = false;
        $matrixConnected = ['connected' => false];

        if ($apiKey || $this->config->getAppValue(Application::APP_ID, 'api_key', '')) {
            $docusealConnected = $this->docuSealService->testConnection();
        }

        if ($matrixAccessToken || $this->config->getAppValue(Application::APP_ID, 'matrix_access_token', '')) {
            $matrixConnected = $this->matrixService->testConnection();
        }

        return new JSONResponse([
            'status' => 'ok',
            'docuseal_connected' => $docusealConnected,
            'matrix_connected' => $matrixConnected,
        ]);
    }

    /**
     * Update user-specific settings
     */
    #[NoAdminRequired]
    public function setUserSettings(): JSONResponse
    {
        $signedDocumentsFolder = $this->request->getParam('signed_documents_folder');

        if ($signedDocumentsFolder !== null && $this->userId) {
            $this->config->setUserValue(
                $this->userId,
                Application::APP_ID,
                'signed_documents_folder',
                $signedDocumentsFolder
            );
        }

        return new JSONResponse(['status' => 'ok']);
    }

    /**
     * Test DocuSeal connection
     */
    #[AuthorizedAdminSetting(settings: \OCA\DocuSealIntegration\Settings\Admin::class)]
    public function testDocuSeal(): JSONResponse
    {
        return new JSONResponse([
            'connected' => $this->docuSealService->testConnection(),
        ]);
    }

    /**
     * Test Matrix connection
     */
    #[AuthorizedAdminSetting(settings: \OCA\DocuSealIntegration\Settings\Admin::class)]
    public function testMatrix(): JSONResponse
    {
        return new JSONResponse($this->matrixService->testConnection());
    }

    /**
     * Test Signal connection
     */
    #[AuthorizedAdminSetting(settings: \OCA\DocuSealIntegration\Settings\Admin::class)]
    public function testSignal(): JSONResponse
    {
        try {
            $version = $this->signalDriver->getVersion();
            return new JSONResponse([
                'connected' => true,
                'version' => $version,
            ]);
        } catch (\Throwable $e) {
            return new JSONResponse([
                'connected' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
