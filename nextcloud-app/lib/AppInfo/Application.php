<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\AppInfo;

use OCA\DocuSealIntegration\Listener\FileActionListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Util;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'docuseal_integration';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        // Register event listeners
        $context->registerEventListener(NodeCreatedEvent::class, FileActionListener::class);
    }

    public function boot(IBootContext $context): void
    {
        // Register file actions script
        Util::addScript(self::APP_ID, 'docuseal_integration-fileactions');
        Util::addStyle(self::APP_ID, 'docuseal_integration-main');
    }
}
