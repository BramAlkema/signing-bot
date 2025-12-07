<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Controller;

use OCA\DocuSealIntegration\BotSDK\BotLoader;
use OCA\DocuSealIntegration\BotSDK\BotRegistry;
use OCA\DocuSealIntegration\BotSDK\Drivers\MatrixDriver;
use OCP\AppFramework\Controller;
use OCP\IAppContainer;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\ICache;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Matrix Application Service Controller
 *
 * Receives events from Matrix homeserver via PUT /transactions/{txnId}
 * Routes messages to registered bots via BotRegistry.
 */
class MatrixAppServiceController extends Controller
{
    private const TXN_CACHE_TTL = 3600; // 1 hour TTL for transaction deduplication
    private ICache $txnCache;
    private MatrixDriver $matrixDriver;
    private ?BotRegistry $botRegistry = null;

    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
        private LoggerInterface $logger,
        ICacheFactory $cacheFactory,
        private ?IAppContainer $container = null,
        ?IClientService $clientService = null,
        ?MatrixDriver $matrixDriver = null,
        ?BotRegistry $botRegistry = null,
    ) {
        parent::__construct($appName, $request);
        $this->txnCache = $cacheFactory->createDistributed('matrix_txn');

        // Use injected driver or create one (allows testing)
        if ($matrixDriver !== null) {
            $this->matrixDriver = $matrixDriver;
        } else {
            $clientService = $clientService ?? \OC::$server->get(IClientService::class);
            $this->matrixDriver = new MatrixDriver(
                $this->config,
                $clientService,
                $this->logger,
                $appName
            );
        }

        // Use injected registry or auto-discover bots
        $this->botRegistry = $botRegistry;
    }

    /**
     * Verify the hs_token from Authorization header or query parameter
     */
    private function verifyAuth(): bool
    {
        $authHeader = $this->request->getHeader('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->matrixDriver->verifyHsToken($token);
        }

        $token = $this->request->getParam('access_token');
        if ($token) {
            return $this->matrixDriver->verifyHsToken($token);
        }

        return false;
    }

    /**
     * PUT /appservice/transactions/{txnId}
     *
     * Receive events from homeserver and route to bots
     */
    #[PublicPage]
    #[NoCSRFRequired]
    public function transactions(string $txnId): JSONResponse
    {
        if (!$this->verifyAuth()) {
            $this->logger->warning('Matrix appservice: invalid hs_token');
            return new JSONResponse(
                ['errcode' => 'M_FORBIDDEN', 'error' => 'Invalid hs_token'],
                Http::STATUS_FORBIDDEN
            );
        }

        // Idempotency check
        if ($this->txnCache->get($txnId) !== null) {
            return new JSONResponse([]);
        }

        $body = $this->getRequestBody();
        $events = $body['events'] ?? [];

        $this->logger->debug('Matrix appservice received transaction', [
            'txn_id' => $txnId,
            'event_count' => count($events),
        ]);

        // Get bot registry (auto-discovers bots)
        $registry = $this->getBotRegistry();

        // Process each event
        foreach ($events as $event) {
            try {
                $message = $this->matrixDriver->parseEvent($event);

                if ($message !== null) {
                    $this->logger->info('Matrix message received', [
                        'sender' => $message->getSender(),
                        'room' => $message->getRoomId(),
                        'text' => $message->getText(),
                    ]);

                    // Route to all registered bots
                    $registry->handleMessage($message);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process Matrix event', [
                    'event_id' => $event['event_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark transaction as processed
        $this->txnCache->set($txnId, true, self::TXN_CACHE_TTL);

        return new JSONResponse([]);
    }

    /**
     * GET /appservice/users/{userId}
     */
    #[PublicPage]
    #[NoCSRFRequired]
    public function queryUser(string $userId): JSONResponse
    {
        if (!$this->verifyAuth()) {
            return new JSONResponse(
                ['errcode' => 'M_FORBIDDEN', 'error' => 'Invalid hs_token'],
                Http::STATUS_FORBIDDEN
            );
        }

        return new JSONResponse(
            ['errcode' => 'M_NOT_FOUND', 'error' => 'User not found'],
            Http::STATUS_NOT_FOUND
        );
    }

    /**
     * GET /appservice/rooms/{roomAlias}
     */
    #[PublicPage]
    #[NoCSRFRequired]
    public function queryRoom(string $roomAlias): JSONResponse
    {
        if (!$this->verifyAuth()) {
            return new JSONResponse(
                ['errcode' => 'M_FORBIDDEN', 'error' => 'Invalid hs_token'],
                Http::STATUS_FORBIDDEN
            );
        }

        return new JSONResponse(
            ['errcode' => 'M_NOT_FOUND', 'error' => 'Room not found'],
            Http::STATUS_NOT_FOUND
        );
    }

    /**
     * Get the bot registry, auto-discovering bots if needed
     */
    private function getBotRegistry(): BotRegistry
    {
        if ($this->botRegistry !== null) {
            return $this->botRegistry;
        }

        // Auto-discover bots
        $container = $this->container ?? \OC::$server->getAppContainer($this->appName);
        $loader = new BotLoader($container, $this->logger, $this->config, $this->appName);
        $this->botRegistry = $loader->loadAll();
        $this->botRegistry->setDriver($this->matrixDriver);

        // Inject Matrix driver into commands that need it
        foreach ($this->botRegistry->all() as $bot) {
            foreach ($bot->getCommands() as $command) {
                if (method_exists($command, 'setMatrixDriver')) {
                    $command->setMatrixDriver($this->matrixDriver);
                }
            }
        }

        return $this->botRegistry;
    }

    /**
     * Get request body - separate method for testability
     */
    protected function getRequestBody(): array
    {
        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody, true);
        return is_array($body) ? $body : [];
    }
}
