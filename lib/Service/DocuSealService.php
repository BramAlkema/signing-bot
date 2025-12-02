<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Service;

use OCA\DocuSealIntegration\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class DocuSealService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(
        private IConfig $config,
        private IClientService $clientService,
        private LoggerInterface $logger,
    ) {
        $this->apiUrl = $this->config->getAppValue(
            Application::APP_ID,
            'docuseal_url',
            'https://api.docuseal.co'
        );
        $this->apiKey = $this->config->getAppValue(
            Application::APP_ID,
            'api_key',
            ''
        );
    }

    /**
     * Get all templates from DocuSeal
     */
    public function getTemplates(): array
    {
        $response = $this->apiRequest('GET', '/templates');
        return $response['data'] ?? $response;
    }

    /**
     * Get a specific template
     */
    public function getTemplate(int $templateId): array
    {
        return $this->apiRequest('GET', "/templates/{$templateId}");
    }

    /**
     * Create a submission from a template
     */
    public function createSubmission(
        int $templateId,
        array $submitters,
        bool $sendEmail = true,
        ?string $message = null
    ): array {
        $payload = [
            'template_id' => $templateId,
            'send_email' => $sendEmail,
            'submitters' => $this->formatSubmitters($submitters),
        ];

        if ($message) {
            $payload['message'] = [
                'subject' => 'Please sign this document',
                'body' => $message,
            ];
        }

        return $this->apiRequest('POST', '/submissions', $payload);
    }

    /**
     * Create submission with a document file (creates template on the fly)
     */
    public function sendFileForSigning(
        string $fileContent,
        string $fileName,
        array $submitters,
        bool $sendEmail = true,
        ?int $templateId = null
    ): array {
        // If no template ID, first upload the document as a template
        if (!$templateId) {
            $template = $this->createTemplateFromFile($fileContent, $fileName);
            $templateId = $template['id'];
        }

        return $this->createSubmission($templateId, $submitters, $sendEmail);
    }

    /**
     * Create a template from a PDF file
     */
    public function createTemplateFromFile(string $fileContent, string $fileName): array
    {
        $base64Content = base64_encode($fileContent);

        $payload = [
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'documents' => [
                [
                    'name' => $fileName,
                    'file' => "data:application/pdf;base64,{$base64Content}",
                ],
            ],
        ];

        return $this->apiRequest('POST', '/templates', $payload);
    }

    /**
     * Get submission status
     */
    public function getSubmission(int $submissionId): array
    {
        return $this->apiRequest('GET', "/submissions/{$submissionId}");
    }

    /**
     * List submissions with optional filters
     */
    public function listSubmissions(array $filters = []): array
    {
        $query = http_build_query($filters);
        $endpoint = '/submissions' . ($query ? "?{$query}" : '');
        return $this->apiRequest('GET', $endpoint);
    }

    /**
     * Archive a submission
     */
    public function archiveSubmission(int $submissionId): array
    {
        return $this->apiRequest('DELETE', "/submissions/{$submissionId}");
    }

    /**
     * Download signed documents for a submission
     */
    public function downloadDocuments(int $submissionId): array
    {
        $submission = $this->getSubmission($submissionId);
        $documents = [];

        foreach ($submission['documents'] ?? [] as $doc) {
            if (isset($doc['url'])) {
                $client = $this->clientService->newClient();
                $response = $client->get($doc['url']);
                $documents[] = [
                    'name' => $doc['name'] ?? 'document.pdf',
                    'content' => $response->getBody(),
                ];
            }
        }

        return $documents;
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $this->getTemplates();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('DocuSeal connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Format submitters array for DocuSeal API
     */
    private function formatSubmitters(array $submitters): array
    {
        return array_map(function ($submitter) {
            return [
                'email' => $submitter['email'],
                'name' => $submitter['name'] ?? null,
                'role' => $submitter['role'] ?? 'Signer',
                'phone' => $submitter['phone'] ?? null,
                'values' => $submitter['values'] ?? [],
            ];
        }, $submitters);
    }

    /**
     * Make an API request to DocuSeal
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('DocuSeal API key not configured');
        }

        $client = $this->clientService->newClient();
        $url = rtrim($this->apiUrl, '/') . '/api' . $endpoint;

        $options = [
            'headers' => [
                'X-Auth-Token' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        try {
            $this->logger->debug('DocuSeal API request', [
                'method' => $method,
                'url' => $url,
            ]);

            switch (strtoupper($method)) {
                case 'GET':
                    $response = $client->get($url, $options);
                    break;
                case 'POST':
                    $response = $client->post($url, $options);
                    break;
                case 'PUT':
                    $response = $client->put($url, $options);
                    break;
                case 'DELETE':
                    $response = $client->delete($url, $options);
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
            }

            $body = $response->getBody();
            return json_decode($body, true) ?? [];
        } catch (\Exception $e) {
            $this->logger->error('DocuSeal API error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
