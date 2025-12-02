<?php

declare(strict_types=1);

return [
    'routes' => [
        // Page routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#submissions', 'url' => '/submissions', 'verb' => 'GET'],

        // DocuSeal API routes
        ['name' => 'docuseal#getTemplates', 'url' => '/api/templates', 'verb' => 'GET'],
        ['name' => 'docuseal#createSubmission', 'url' => '/api/submissions', 'verb' => 'POST'],
        ['name' => 'docuseal#getSubmission', 'url' => '/api/submissions/{id}', 'verb' => 'GET'],
        ['name' => 'docuseal#listSubmissions', 'url' => '/api/submissions', 'verb' => 'GET'],
        ['name' => 'docuseal#sendFile', 'url' => '/api/send-file', 'verb' => 'POST'],

        // Webhook route (external)
        ['name' => 'webhook#handle', 'url' => '/webhook', 'verb' => 'POST'],

        // Settings
        ['name' => 'settings#getSettings', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#setSettings', 'url' => '/api/settings', 'verb' => 'PUT'],
    ],
];
