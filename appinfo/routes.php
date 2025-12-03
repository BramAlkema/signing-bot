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

        // Matrix Signing routes
        ['name' => 'matrix_signing#testConnection', 'url' => '/api/matrix/test', 'verb' => 'GET'],
        ['name' => 'matrix_signing#createSession', 'url' => '/api/matrix/sessions', 'verb' => 'POST'],
        ['name' => 'matrix_signing#getSession', 'url' => '/api/matrix/sessions/{sessionId}', 'verb' => 'GET'],
        ['name' => 'matrix_signing#listSessions', 'url' => '/api/matrix/sessions', 'verb' => 'GET'],
        ['name' => 'matrix_signing#submitSignature', 'url' => '/api/matrix/sessions/{sessionId}/sign', 'verb' => 'POST'],
        ['name' => 'matrix_signing#getInstructions', 'url' => '/api/matrix/sessions/{sessionId}/instructions', 'verb' => 'GET'],
        ['name' => 'matrix_signing#verifySignature', 'url' => '/api/matrix/verify', 'verb' => 'POST'],
        ['name' => 'matrix_signing#downloadDocument', 'url' => '/api/matrix/sessions/{sessionId}/document', 'verb' => 'GET'],
    ],
];
