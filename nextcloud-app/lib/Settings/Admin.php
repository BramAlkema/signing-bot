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

        return new TemplateResponse(Application::APP_ID, 'admin', [
            'docuseal_url' => $docusealUrl,
            'has_api_key' => $hasApiKey,
            'webhook_url' => $webhookUrl,
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
