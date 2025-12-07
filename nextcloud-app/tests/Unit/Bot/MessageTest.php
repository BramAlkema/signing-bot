<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Tests\Unit\Bot;

use OCA\DocuSealIntegration\Bot\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testBasicMessageCreation(): void
    {
        $message = new Message(
            platform: 'matrix',
            sender: '@user:matrix.example.com',
            text: 'Hello world',
            roomId: '!room:matrix.example.com'
        );

        $this->assertEquals('matrix', $message->getPlatform());
        $this->assertEquals('@user:matrix.example.com', $message->getSender());
        $this->assertEquals('Hello world', $message->getText());
        $this->assertEquals('!room:matrix.example.com', $message->getRoomId());
    }

    public function testGetCommandWithSlash(): void
    {
        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/docuseal user@email.com',
            roomId: '!room:example.com'
        );

        $this->assertEquals('docuseal', $message->getCommand());
    }

    public function testGetCommandWithExclamation(): void
    {
        $message = new Message(
            platform: 'signal',
            sender: '+31612345678',
            text: '!help'
        );

        $this->assertEquals('help', $message->getCommand());
    }

    public function testGetCommandReturnsNullForPlainMessage(): void
    {
        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: 'Just a regular message'
        );

        $this->assertNull($message->getCommand());
    }

    public function testGetMentionedUsersWithEmails(): void
    {
        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/docuseal alice@company.com bob@client.org'
        );

        $mentioned = $message->getMentionedUsers();
        $this->assertContains('alice@company.com', $mentioned);
        $this->assertContains('bob@client.org', $mentioned);
    }

    public function testGetMentionedUsersWithMatrixIds(): void
    {
        $message = new Message(
            platform: 'matrix',
            sender: '@sender:example.com',
            text: 'Hello @alice:matrix.org and @bob:example.com'
        );

        $mentioned = $message->getMentionedUsers();
        $this->assertContains('@alice:matrix.org', $mentioned);
        $this->assertContains('@bob:example.com', $mentioned);
    }

    public function testHasAttachments(): void
    {
        $messageWithAttachment = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/docuseal',
            attachments: [
                ['url' => 'mxc://example.com/abc123', 'filename' => 'doc.pdf']
            ]
        );

        $messageWithoutAttachment = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/docuseal'
        );

        $this->assertTrue($messageWithAttachment->hasAttachments());
        $this->assertFalse($messageWithoutAttachment->hasAttachments());
    }

    public function testGetAttachments(): void
    {
        $attachments = [
            ['url' => 'mxc://example.com/abc123', 'filename' => 'doc.pdf', 'mimetype' => 'application/pdf']
        ];

        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: '/docuseal',
            attachments: $attachments
        );

        $this->assertEquals($attachments, $message->getAttachments());
    }

    public function testTimestamp(): void
    {
        $timestamp = time();
        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: 'test',
            timestamp: $timestamp
        );

        $this->assertEquals($timestamp, $message->getTimestamp());
    }

    public function testRawData(): void
    {
        $raw = ['event_id' => '$abc123', 'type' => 'm.room.message'];
        $message = new Message(
            platform: 'matrix',
            sender: '@user:example.com',
            text: 'test',
            raw: $raw
        );

        $this->assertEquals($raw, $message->getRaw());
    }
}
