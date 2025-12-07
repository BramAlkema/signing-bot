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
        ['name' => 'docuseal#listSubmissions', 'url' => '/api/submissions/list', 'verb' => 'GET'],
        ['name' => 'docuseal#getSubmission', 'url' => '/api/submissions/{id}', 'verb' => 'GET'],
        ['name' => 'docuseal#sendFile', 'url' => '/api/send-file', 'verb' => 'POST'],

        // Webhook route (external)
        ['name' => 'webhook#handle', 'url' => '/webhook', 'verb' => 'POST'],

        // Settings
        ['name' => 'settings#getSettings', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#setSettings', 'url' => '/api/settings', 'verb' => 'PUT'],
        ['name' => 'settings#setUserSettings', 'url' => '/api/settings/user', 'verb' => 'PUT'],
        ['name' => 'settings#testDocuSeal', 'url' => '/api/settings/test-docuseal', 'verb' => 'POST'],
        ['name' => 'settings#testMatrix', 'url' => '/api/settings/test-matrix', 'verb' => 'POST'],
        ['name' => 'settings#testSignal', 'url' => '/api/settings/test-signal', 'verb' => 'POST'],

        // Matrix Signing routes
        ['name' => 'matrix_signing#testConnection', 'url' => '/api/matrix/test', 'verb' => 'GET'],
        ['name' => 'matrix_signing#createSession', 'url' => '/api/matrix/sessions', 'verb' => 'POST'],
        ['name' => 'matrix_signing#listSessions', 'url' => '/api/matrix/sessions/list', 'verb' => 'GET'],
        ['name' => 'matrix_signing#getSession', 'url' => '/api/matrix/sessions/{sessionId}', 'verb' => 'GET'],
        ['name' => 'matrix_signing#submitSignature', 'url' => '/api/matrix/sessions/{sessionId}/sign', 'verb' => 'POST'],
        ['name' => 'matrix_signing#getInstructions', 'url' => '/api/matrix/sessions/{sessionId}/instructions', 'verb' => 'GET'],
        ['name' => 'matrix_signing#verifySignature', 'url' => '/api/matrix/verify', 'verb' => 'POST'],
        ['name' => 'matrix_signing#downloadDocument', 'url' => '/api/matrix/sessions/{sessionId}/document', 'verb' => 'GET'],

        // Matrix Application Service API (push from homeserver)
        // These routes receive events from the Matrix homeserver
        // Full path: /apps/docuseal_integration/appservice/...
        ['name' => 'matrix_app_service#transactions', 'url' => '/appservice/transactions/{txnId}', 'verb' => 'PUT'],
        ['name' => 'matrix_app_service#queryUser', 'url' => '/appservice/users/{userId}', 'verb' => 'GET'],
        ['name' => 'matrix_app_service#queryRoom', 'url' => '/appservice/rooms/{roomAlias}', 'verb' => 'GET'],
    ],
];
