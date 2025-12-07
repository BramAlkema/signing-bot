<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Settings;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings
{
    public function __construct(
        private IConfig $config,
    ) {
    }

    public function getForm(): TemplateResponse
    {
        // DocuSeal settings
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

        $webhookUrl = \OC::$server->getURLGenerator()->linkToRouteAbsolute(
            'docuseal_integration.webhook.handle'
        );

        // Matrix settings
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

        return new TemplateResponse(Application::APP_ID, 'admin', [
            'docuseal_url' => $docusealUrl,
            'has_api_key' => $hasApiKey,
            'webhook_url' => $webhookUrl,
            'matrix_homeserver' => $matrixHomeserver,
            'has_matrix_token' => $hasMatrixToken,
            'matrix_bot_user' => $matrixBotUser,
            'signal_enabled' => $signalEnabled,
            'signal_phone_number' => $signalPhoneNumber,
            'signal_socket' => $signalSocket,
        ]);
    }

    public function getSection(): string
    {
        return 'docuseal_integration';
    }

    public function getPriority(): int
    {
        return 10;
    }
}
