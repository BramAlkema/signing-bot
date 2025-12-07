<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Tests\Unit\Bot;

use OCA\DocuSealIntegration\Bot\BotFramework;
use OCA\DocuSealIntegration\Bot\Commands\CommandInterface;
use OCA\DocuSealIntegration\Bot\Drivers\DriverInterface;
use OCA\DocuSealIntegration\Bot\Message;
use OCA\DocuSealIntegration\Bot\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BotFrameworkTest extends TestCase
{
    private BotFramework $framework;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->framework = new BotFramework($this->logger);
    }

    public function testRegisterDriver(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn('test');

        $result = $this->framework->registerDriver($driver);

        $this->assertSame($this->framework, $result);
    }

    public function testRegisterCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');

        $result = $this->framework->registerCommand($command);

        $this->assertSame($this->framework, $result);
    }

    public function testGetCommands(): void
    {
        $command1 = $this->createMock(CommandInterface::class);
        $command1->method('getName')->willReturn('help');

        $command2 = $this->createMock(CommandInterface::class);
        $command2->method('getName')->willReturn('docuseal');

        $this->framework->registerCommand($command1);
        $this->framework->registerCommand($command2);

        $commands = $this->framework->getCommands();

        $this->assertCount(2, $commands);
        $this->assertArrayHasKey('help', $commands);
        $this->assertArrayHasKey('docuseal', $commands);
    }

    public function testHandleMessageWithMatchingCommand(): void
    {
        // Create mock driver
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn('matrix');
        $driver->method('send')->willReturn(true);

        // Create mock command that matches
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('matches')->willReturn(true);
        $command->expects($this->once())->method('handle');

        $this->framework->registerDriver($driver);
        $this->framework->registerCommand($command);

        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/test',
            roomId: '!room:example.com'
        );

        $result = $this->framework->handleMessage($message);

        $this->assertTrue($result);
    }

    public function testHandleMessageWithNoMatchingCommand(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn('matrix');
        $driver->expects($this->never())->method('send');

        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('matches')->willReturn(false);
        $command->expects($this->never())->method('handle');

        $this->framework->registerDriver($driver);
        $this->framework->registerCommand($command);

        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: 'just a message'
        );

        $result = $this->framework->handleMessage($message);

        // No matching command means empty response, so nothing to send - returns true
        $this->assertTrue($result);
    }

    public function testHandleMessageWithNoDriver(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('matches')->willReturn(true);
        // Make the command add content to the response
        $command->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (Message $msg, Response $response) {
                $response->text('Test response');
            });

        $this->framework->registerCommand($command);

        $message = new Message(
            platform: 'unknown',
            sender: 'user',
            text: '/test'
        );

        // Should return false because there's no driver to send the response
        $result = $this->framework->handleMessage($message);

        $this->assertFalse($result);
    }

    public function testCommandThrowsException(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn('matrix');
        $driver->method('send')->willReturn(true);

        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('failing');
        $command->method('matches')->willReturn(true);
        $command->method('handle')->willThrowException(new \RuntimeException('Test error'));

        $this->logger->expects($this->once())->method('error');

        $this->framework->registerDriver($driver);
        $this->framework->registerCommand($command);

        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/failing',
            roomId: '!room:example.com'
        );

        $result = $this->framework->handleMessage($message);

        // Should return true because error response was sent
        $this->assertTrue($result);
    }
}
