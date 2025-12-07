<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Tests\E2E;

use OCA\DocuSealIntegration\Bot\BotFramework;
use OCA\DocuSealIntegration\Bot\Commands\DocuSealCommand;
use OCA\DocuSealIntegration\Bot\Commands\HelpCommand;
use OCA\DocuSealIntegration\Bot\Drivers\MatrixDriver;
use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;
use OCA\DocuSealIntegration\Service\DocuSealService;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * End-to-end tests for the Matrix signing flow
 *
 * These tests verify the complete flow from receiving a Matrix event
 * to generating the appropriate response, with external services mocked.
 */
class MatrixSigningFlowTest extends TestCase
{
    private LoggerInterface $logger;
    private IConfig $config;
    private IClientService $clientService;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(IConfig::class);
        $this->clientService = $this->createMock(IClientService::class);

        // Configure default Matrix settings
        $this->config->method('getAppValue')
            ->willReturnCallback(function ($app, $key, $default) {
                $values = [
                    'matrix_homeserver' => 'https://matrix.example.com',
                    'matrix_as_token' => 'test_as_token_12345',
                    'matrix_hs_token' => 'test_hs_token_67890',
                    'matrix_bot_user' => '@docuseal-bot:example.com',
                    'docuseal_url' => 'https://api.docuseal.co',
                    'api_key' => 'test_api_key',
                ];
                return $values[$key] ?? $default;
            });
    }

    /**
     * Test the complete /help command flow
     */
    public function testHelpCommandFlow(): void
    {
        // Create the bot framework with all components
        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        $framework->registerDriver($matrixDriver);
        $framework->registerCommand(new HelpCommand($framework));

        // Simulate a Matrix event with /help command
        $event = $this->createMatrixEvent(
            sender: '@alice:example.com',
            roomId: '!testroom:example.com',
            body: '/help'
        );

        // Parse the event into a Message
        $message = $this->parseMatrixEvent($event);

        // Process through the framework
        $response = $framework->process($message);

        // Verify the response contains help text
        $this->assertTrue($response->hasContent());
        $messages = $response->getMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Available commands', $messages[0]['content']);
        $this->assertStringContainsString('/help', $messages[0]['content']);
    }

    /**
     * Test the complete /docuseal command flow with attachment
     */
    public function testDocuSealCommandFlowWithAttachment(): void
    {
        // Create mock DocuSeal service
        $docuSealService = $this->createMock(DocuSealService::class);
        $docuSealService->expects($this->once())
            ->method('sendFileForSigning')
            ->with(
                $this->equalTo('PDF_CONTENT_HERE'),
                $this->equalTo('contract.pdf'),
                $this->callback(function ($submitters) {
                    return count($submitters) === 2
                        && $submitters[0]['email'] === 'alice@company.com'
                        && $submitters[1]['email'] === 'bob@client.com';
                })
            )
            ->willReturn([
                'id' => 12345,
                'status' => 'pending',
                'submitters' => [
                    [
                        'email' => 'alice@company.com',
                        'embed_src' => 'https://docuseal.co/sign/abc123',
                    ],
                    [
                        'email' => 'bob@client.com',
                        'embed_src' => 'https://docuseal.co/sign/def456',
                    ],
                ],
            ]);

        // Create the bot framework
        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        // Mock media download for attachment
        $matrixDriver->method('downloadMedia')
            ->with('mxc://example.com/attachment123')
            ->willReturn('PDF_CONTENT_HERE');

        $framework->registerDriver($matrixDriver);
        $framework->registerCommand(new HelpCommand($framework));

        $docuSealCommand = new DocuSealCommand($docuSealService, $this->logger);
        $docuSealCommand->setMatrixDriver($matrixDriver);
        $framework->registerCommand($docuSealCommand);

        // Simulate a Matrix event with /docuseal command and attachment
        $event = $this->createMatrixEvent(
            sender: '@bob:example.com',
            roomId: '!signing-room:example.com',
            body: '/docuseal alice@company.com bob@client.com',
            attachment: [
                'url' => 'mxc://example.com/attachment123',
                'filename' => 'contract.pdf',
                'mimetype' => 'application/pdf',
                'size' => 12345,
            ]
        );

        // Parse the event
        $message = $this->parseMatrixEvent($event);

        // Process through the framework
        $response = $framework->process($message);

        // Verify the response
        $this->assertTrue($response->hasContent());
        $messages = $response->getMessages();

        // Should have success message with signing links
        $content = $messages[count($messages) - 1]['content'];
        $this->assertStringContainsString('Document sent for signing', $content);
        $this->assertStringContainsString('12345', $content); // Tracking ID
        $this->assertStringContainsString('alice@company.com', $content);
        $this->assertStringContainsString('bob@client.com', $content);
    }

    /**
     * Test /docuseal command without attachment shows error
     */
    public function testDocuSealCommandWithoutAttachmentShowsError(): void
    {
        $docuSealService = $this->createMock(DocuSealService::class);
        $docuSealService->expects($this->never())->method('sendFileForSigning');

        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        $framework->registerDriver($matrixDriver);
        $docuSealCommand = new DocuSealCommand($docuSealService, $this->logger);
        $framework->registerCommand($docuSealCommand);

        // Event without attachment
        $event = $this->createMatrixEvent(
            sender: '@bob:example.com',
            roomId: '!room:example.com',
            body: '/docuseal alice@company.com'
        );

        $message = $this->parseMatrixEvent($event);
        $response = $framework->process($message);

        $this->assertTrue($response->hasContent());
        $messages = $response->getMessages();
        $this->assertStringContainsString('attach a document', $messages[0]['content']);
    }

    /**
     * Test /docuseal command without email addresses shows error
     */
    public function testDocuSealCommandWithoutEmailShowsError(): void
    {
        $docuSealService = $this->createMock(DocuSealService::class);
        $docuSealService->expects($this->never())->method('sendFileForSigning');

        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        $framework->registerDriver($matrixDriver);
        $docuSealCommand = new DocuSealCommand($docuSealService, $this->logger);
        $framework->registerCommand($docuSealCommand);

        // Event with attachment but no email
        $event = $this->createMatrixEvent(
            sender: '@bob:example.com',
            roomId: '!room:example.com',
            body: '/docuseal',
            attachment: [
                'url' => 'mxc://example.com/file',
                'filename' => 'doc.pdf',
                'mimetype' => 'application/pdf',
                'size' => 1000,
            ]
        );

        $message = $this->parseMatrixEvent($event);
        $response = $framework->process($message);

        $this->assertTrue($response->hasContent());
        $messages = $response->getMessages();
        $this->assertStringContainsString('signer email', $messages[0]['content']);
    }

    /**
     * Test that non-command messages are ignored
     */
    public function testRegularMessagesAreIgnored(): void
    {
        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        $framework->registerDriver($matrixDriver);
        $framework->registerCommand(new HelpCommand($framework));

        // Regular chat message, not a command
        $event = $this->createMatrixEvent(
            sender: '@alice:example.com',
            roomId: '!room:example.com',
            body: 'Hello everyone!'
        );

        $message = $this->parseMatrixEvent($event);
        $response = $framework->process($message);

        // Should not generate any response
        $this->assertFalse($response->hasContent());
    }

    /**
     * Test that bot ignores its own messages
     */
    public function testBotIgnoresOwnMessages(): void
    {
        $framework = new BotFramework($this->logger);

        // Create driver that identifies bot user
        $matrixDriver = new MatrixDriver(
            $this->config,
            $this->clientService,
            $this->logger
        );

        $framework->registerDriver($matrixDriver);
        $framework->registerCommand(new HelpCommand($framework));

        // Message from the bot itself
        $event = [
            'type' => 'm.room.message',
            'sender' => '@docuseal-bot:example.com', // Bot's own user
            'room_id' => '!room:example.com',
            'content' => [
                'msgtype' => 'm.text',
                'body' => '/help',
            ],
            'event_id' => '$event123',
            'origin_server_ts' => 1733400000000,
        ];

        $message = $matrixDriver->parseEvent($event);

        // Should return null - bot ignores its own messages
        $this->assertNull($message);
    }

    /**
     * Test DocuSeal API error is handled gracefully
     */
    public function testDocuSealApiErrorHandling(): void
    {
        $docuSealService = $this->createMock(DocuSealService::class);
        $docuSealService->method('sendFileForSigning')
            ->willThrowException(new \RuntimeException('API rate limit exceeded'));

        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        $matrixDriver->method('downloadMedia')
            ->willReturn('PDF_CONTENT');

        $framework->registerDriver($matrixDriver);
        $docuSealCommand = new DocuSealCommand($docuSealService, $this->logger);
        $docuSealCommand->setMatrixDriver($matrixDriver);
        $framework->registerCommand($docuSealCommand);

        $event = $this->createMatrixEvent(
            sender: '@alice:example.com',
            roomId: '!room:example.com',
            body: '/docuseal test@example.com',
            attachment: [
                'url' => 'mxc://example.com/file',
                'filename' => 'doc.pdf',
                'mimetype' => 'application/pdf',
                'size' => 1000,
            ]
        );

        $message = $this->parseMatrixEvent($event);
        $response = $framework->process($message);

        // Should return error message to user
        $this->assertTrue($response->hasContent());
        $messages = $response->getMessages();
        $this->assertStringContainsString('Failed', $messages[count($messages) - 1]['content']);
        $this->assertStringContainsString('rate limit', $messages[count($messages) - 1]['content']);
    }

    /**
     * Test full message send flow (framework → driver)
     */
    public function testFullMessageSendFlow(): void
    {
        $framework = new BotFramework($this->logger);

        // Create a driver that tracks sent messages
        $sentMessages = [];
        $matrixDriver = $this->createMock(MatrixDriver::class);
        $matrixDriver->method('getPlatform')->willReturn('matrix');
        $matrixDriver->method('send')
            ->willReturnCallback(function ($message, $response) use (&$sentMessages) {
                $sentMessages[] = [
                    'room_id' => $message->getRoomId(),
                    'messages' => $response->getMessages(),
                ];
                return true;
            });

        $framework->registerDriver($matrixDriver);
        $framework->registerCommand(new HelpCommand($framework));

        $event = $this->createMatrixEvent(
            sender: '@alice:example.com',
            roomId: '!target-room:example.com',
            body: '/help'
        );

        $message = $this->parseMatrixEvent($event);

        // Use handleMessage which sends via driver
        $result = $framework->handleMessage($message);

        $this->assertTrue($result);
        $this->assertCount(1, $sentMessages);
        $this->assertEquals('!target-room:example.com', $sentMessages[0]['room_id']);
    }

    /**
     * Test multiple commands are registered and matched correctly
     */
    public function testMultipleCommandsRouting(): void
    {
        $docuSealService = $this->createMock(DocuSealService::class);

        $framework = new BotFramework($this->logger);
        $matrixDriver = $this->createMatrixDriverMock();

        $framework->registerDriver($matrixDriver);
        $framework->registerCommand(new HelpCommand($framework));
        $framework->registerCommand(new DocuSealCommand($docuSealService, $this->logger));

        // Test /help routes to HelpCommand
        $helpEvent = $this->createMatrixEvent(
            sender: '@alice:example.com',
            roomId: '!room:example.com',
            body: '/help'
        );
        $helpMessage = $this->parseMatrixEvent($helpEvent);
        $helpResponse = $framework->process($helpMessage);

        $this->assertTrue($helpResponse->hasContent());
        $this->assertStringContainsString('Available commands', $helpResponse->getMessages()[0]['content']);

        // Test /docuseal routes to DocuSealCommand (shows attachment error)
        $docuSealEvent = $this->createMatrixEvent(
            sender: '@alice:example.com',
            roomId: '!room:example.com',
            body: '/docuseal test@example.com'
        );
        $docuSealMessage = $this->parseMatrixEvent($docuSealEvent);
        $docuSealResponse = $framework->process($docuSealMessage);

        $this->assertTrue($docuSealResponse->hasContent());
        $this->assertStringContainsString('attach a document', $docuSealResponse->getMessages()[0]['content']);
    }

    /**
     * Test Matrix event types are filtered correctly
     */
    public function testOnlyMessageEventsAreProcessed(): void
    {
        $matrixDriver = new MatrixDriver(
            $this->config,
            $this->clientService,
            $this->logger
        );

        // Member event (join/leave) should be ignored
        $memberEvent = [
            'type' => 'm.room.member',
            'sender' => '@alice:example.com',
            'room_id' => '!room:example.com',
            'content' => ['membership' => 'join'],
            'event_id' => '$event123',
        ];

        $this->assertNull($matrixDriver->parseEvent($memberEvent));

        // State event should be ignored
        $stateEvent = [
            'type' => 'm.room.name',
            'sender' => '@alice:example.com',
            'room_id' => '!room:example.com',
            'content' => ['name' => 'New Room Name'],
            'event_id' => '$event456',
        ];

        $this->assertNull($matrixDriver->parseEvent($stateEvent));

        // Message event should be processed
        $messageEvent = [
            'type' => 'm.room.message',
            'sender' => '@alice:example.com',
            'room_id' => '!room:example.com',
            'content' => [
                'msgtype' => 'm.text',
                'body' => 'Hello',
            ],
            'event_id' => '$event789',
            'origin_server_ts' => 1733400000000,
        ];

        $message = $matrixDriver->parseEvent($messageEvent);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('Hello', $message->getText());
    }

    // ==================== Helper Methods ====================

    /**
     * Create a Matrix driver mock with common configurations
     */
    private function createMatrixDriverMock(): MatrixDriver
    {
        $driver = $this->createMock(MatrixDriver::class);
        $driver->method('getPlatform')->willReturn('matrix');
        $driver->method('send')->willReturn(true);
        return $driver;
    }

    /**
     * Create a simulated Matrix event
     */
    private function createMatrixEvent(
        string $sender,
        string $roomId,
        string $body,
        ?array $attachment = null
    ): array {
        $event = [
            'type' => 'm.room.message',
            'sender' => $sender,
            'room_id' => $roomId,
            'event_id' => '$event_' . bin2hex(random_bytes(8)),
            'origin_server_ts' => (int)(microtime(true) * 1000),
            'content' => [
                'msgtype' => $attachment ? 'm.file' : 'm.text',
                'body' => $body,
            ],
        ];

        if ($attachment) {
            $event['content']['url'] = $attachment['url'];
            $event['content']['info'] = [
                'mimetype' => $attachment['mimetype'],
                'size' => $attachment['size'],
            ];
            $event['content']['filename'] = $attachment['filename'];
        }

        return $event;
    }

    /**
     * Parse a Matrix event into a Message (simulates MatrixDriver::parseEvent)
     */
    private function parseMatrixEvent(array $event): Message
    {
        $content = $event['content'];
        $attachments = null;

        if (in_array($content['msgtype'], ['m.file', 'm.image', 'm.video', 'm.audio'])) {
            $attachments = [[
                'url' => $content['url'] ?? null,
                'mxc_uri' => $content['url'] ?? null,
                'filename' => $content['filename'] ?? $content['body'] ?? 'attachment',
                'mimetype' => $content['info']['mimetype'] ?? 'application/octet-stream',
                'size' => $content['info']['size'] ?? 0,
            ]];
        }

        return new Message(
            platform: 'matrix',
            sender: $event['sender'],
            text: $content['body'],
            roomId: $event['room_id'],
            attachments: $attachments,
            timestamp: isset($event['origin_server_ts']) ? (int)($event['origin_server_ts'] / 1000) : null,
            raw: $event,
        );
    }
}
