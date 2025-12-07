<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Tests\Unit\Controller;

use OCA\DocuSealIntegration\BotSDK\BotRegistry;
use OCA\DocuSealIntegration\BotSDK\Drivers\MatrixDriver;
use OCA\DocuSealIntegration\Controller\MatrixAppServiceController;
use OCP\AppFramework\Http;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test stub for IRequest that supports the magic 'put' property
 */
class TestRequest implements IRequest
{
    private array $headers = [];
    private array $params = [];
    public mixed $put = [];

    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function setParam(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }

    public function getHeader(string $name): string
    {
        return $this->headers[$name] ?? '';
    }

    public function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getMethod(): string { return 'PUT'; }
    public function getUploadedFile(string $key) { return null; }
    public function getEnv(string $key) { return ''; }
    public function getCookie(string $key) { return ''; }
    public function passesCSRFCheck(): bool { return true; }
    public function passesStrictCookieCheck(): bool { return true; }
    public function passesLaxCookieCheck(): bool { return true; }
    public function getId(): string { return 'test'; }
    public function getRemoteAddress(): string { return '127.0.0.1'; }
    public function getServerProtocol(): string { return 'HTTP/1.1'; }
    public function getHttpProtocol(): string { return 'https'; }
    public function getRequestUri(): string { return '/test'; }
    public function getRawPathInfo(): string { return '/test'; }
    public function getPathInfo() { return '/test'; }
    public function getScriptName(): string { return 'index.php'; }
    public function isUserAgent(array $agent): bool { return false; }
    public function getInsecureServerHost(): string { return 'localhost'; }
    public function getServerHost(): string { return 'localhost'; }
    public function getFormat(): ?string { return 'json'; }
}

/**
 * Testable subclass that allows injecting request body
 */
class TestableMatrixAppServiceController extends MatrixAppServiceController
{
    public array $testRequestBody = [];

    protected function getRequestBody(): array
    {
        return $this->testRequestBody;
    }
}

class MatrixAppServiceControllerTest extends TestCase
{
    private TestableMatrixAppServiceController $controller;
    private TestRequest $request;
    private IConfig $config;
    private MatrixDriver $matrixDriver;
    private BotRegistry $botRegistry;
    private LoggerInterface $logger;
    private ICache $cache;
    private ICacheFactory $cacheFactory;

    protected function setUp(): void
    {
        $this->request = new TestRequest();
        $this->config = $this->createMock(IConfig::class);
        // Return default values for config lookups
        $this->config->method('getAppValue')
            ->willReturnCallback(function ($app, $key, $default) {
                return $default;
            });

        $this->matrixDriver = $this->createMock(MatrixDriver::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Mock bot registry (prevents auto-discovery in tests)
        $this->botRegistry = $this->createMock(BotRegistry::class);
        $this->botRegistry->method('all')->willReturn([]);
        $this->botRegistry->method('handleMessage')->willReturn(false);

        // Mock cache (Redis-backed in production)
        $this->cache = $this->createMock(ICache::class);
        // Default: cache miss (transaction not seen before)
        $this->cache->method('get')->willReturn(null);

        $this->cacheFactory = $this->createMock(ICacheFactory::class);
        $this->cacheFactory->method('createDistributed')
            ->with('matrix_txn')
            ->willReturn($this->cache);

        $this->controller = new TestableMatrixAppServiceController(
            'docuseal_integration',
            $this->request,
            $this->config,
            $this->logger,
            $this->cacheFactory,
            null, // container
            null, // clientService
            $this->matrixDriver,
            $this->botRegistry
        );
    }

    public function testQueryUserWithInvalidToken(): void
    {
        $this->request->setHeader('Authorization', 'Bearer invalid_token');

        $this->matrixDriver->method('verifyHsToken')
            ->with('invalid_token')
            ->willReturn(false);

        $response = $this->controller->queryUser('@test:example.com');

        $this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertEquals('M_FORBIDDEN', $response->getData()['errcode']);
    }

    public function testQueryUserWithValidToken(): void
    {
        $this->request->setHeader('Authorization', 'Bearer valid_token');

        $this->matrixDriver->method('verifyHsToken')
            ->with('valid_token')
            ->willReturn(true);

        $response = $this->controller->queryUser('@test:example.com');

        // We don't do lazy user creation, so always return NOT_FOUND
        $this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
        $this->assertEquals('M_NOT_FOUND', $response->getData()['errcode']);
    }

    public function testQueryUserWithMissingAuthHeader(): void
    {
        // No auth header, no query param
        $response = $this->controller->queryUser('@test:example.com');

        $this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testQueryUserWithMalformedAuthHeader(): void
    {
        $this->request->setHeader('Authorization', 'Basic sometoken');

        $response = $this->controller->queryUser('@test:example.com');

        $this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testQueryUserWithQueryParamToken(): void
    {
        $this->request->setParam('access_token', 'valid_query_token');

        $this->matrixDriver->method('verifyHsToken')
            ->with('valid_query_token')
            ->willReturn(true);

        $response = $this->controller->queryUser('@test:example.com');

        // Valid token via query param should work
        $this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
        $this->assertEquals('M_NOT_FOUND', $response->getData()['errcode']);
    }

    public function testQueryRoomWithInvalidToken(): void
    {
        $this->request->setHeader('Authorization', 'Bearer invalid');

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(false);

        $response = $this->controller->queryRoom('#test:example.com');

        $this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testQueryRoomWithValidToken(): void
    {
        $this->request->setHeader('Authorization', 'Bearer valid');

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(true);

        $response = $this->controller->queryRoom('#test:example.com');

        // We don't do lazy room creation
        $this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
        $this->assertEquals('M_NOT_FOUND', $response->getData()['errcode']);
    }

    public function testTransactionsWithInvalidToken(): void
    {
        $this->request->setHeader('Authorization', 'Bearer bad_token');

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Matrix appservice: invalid hs_token');

        $response = $this->controller->transactions('txn123');

        $this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testTransactionsWithValidTokenAndNoEvents(): void
    {
        $this->request->setHeader('Authorization', 'Bearer valid_token');
        $this->controller->testRequestBody = ['events' => []];

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(true);

        $response = $this->controller->transactions('txn123');

        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
        $this->assertEquals([], $response->getData());
    }

    public function testTransactionsIdempotency(): void
    {
        // Create fresh mocks for this test to track cache calls
        $cache = $this->createMock(ICache::class);
        $cacheFactory = $this->createMock(ICacheFactory::class);
        $cacheFactory->method('createDistributed')->willReturn($cache);

        $controller = new TestableMatrixAppServiceController(
            'docuseal_integration',
            $this->request,
            $this->config,
            $this->logger,
            $cacheFactory,
            null, // container
            null, // clientService
            $this->matrixDriver,
            $this->botRegistry
        );

        $this->request->setHeader('Authorization', 'Bearer valid_token');
        $controller->testRequestBody = ['events' => []];

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(true);

        // First call - cache returns null (not seen), second call returns true (seen)
        $cache->expects($this->exactly(2))
            ->method('get')
            ->with('txn_idempotent')
            ->willReturnOnConsecutiveCalls(null, true);

        $cache->expects($this->once())
            ->method('set')
            ->with('txn_idempotent', true, 3600);

        // First call processes the transaction
        $response1 = $controller->transactions('txn_idempotent');
        $this->assertEquals(Http::STATUS_OK, $response1->getStatus());

        // Second call with same txnId returns early (idempotent)
        $response2 = $controller->transactions('txn_idempotent');
        $this->assertEquals(Http::STATUS_OK, $response2->getStatus());
    }

    public function testTransactionsWithMessageEvent(): void
    {
        $this->request->setHeader('Authorization', 'Bearer valid_token');

        $messageEvent = [
            'type' => 'm.room.message',
            'sender' => '@user:example.com',
            'room_id' => '!room:example.com',
            'content' => [
                'msgtype' => 'm.text',
                'body' => '/help'
            ],
            'event_id' => '$event123',
            'origin_server_ts' => 1699999999000
        ];

        $this->controller->testRequestBody = ['events' => [$messageEvent]];

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(true);

        // Mock parseEvent to return a Message
        $this->matrixDriver->method('parseEvent')
            ->willReturn(new \OCA\DocuSealIntegration\BotSDK\Message(
                platform: 'matrix',
                sender: '@user:example.com',
                text: '/help',
                roomId: '!room:example.com'
            ));

        $this->logger->expects($this->atLeastOnce())->method('info');

        $response = $this->controller->transactions('txn_with_event');

        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }

    public function testTransactionsWithNonMessageEvent(): void
    {
        $this->request->setHeader('Authorization', 'Bearer valid_token');

        $memberEvent = [
            'type' => 'm.room.member',
            'sender' => '@user:example.com',
            'room_id' => '!room:example.com',
            'content' => ['membership' => 'join'],
            'event_id' => '$event456'
        ];

        $this->controller->testRequestBody = ['events' => [$memberEvent]];

        $this->matrixDriver->method('verifyHsToken')
            ->willReturn(true);

        // parseEvent returns null for non-message events
        $this->matrixDriver->method('parseEvent')
            ->willReturn(null);

        $response = $this->controller->transactions('txn_member');

        $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    }
}
