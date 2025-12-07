<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Tests\Unit\Bot;

use OCA\DocuSealIntegration\Bot\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testTextMessage(): void
    {
        $response = new Response();
        $response->text('Hello world');

        $messages = $response->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('text', $messages[0]['type']);
        $this->assertEquals('Hello world', $messages[0]['content']);
    }

    public function testMultipleTextMessages(): void
    {
        $response = new Response();
        $response->text('First message');
        $response->text('Second message');

        $messages = $response->getMessages();
        $this->assertCount(2, $messages);
        $this->assertEquals('First message', $messages[0]['content']);
        $this->assertEquals('Second message', $messages[1]['content']);
    }

    public function testFluentInterface(): void
    {
        $response = new Response();
        $result = $response->text('Hello');

        $this->assertSame($response, $result);
    }

    public function testAttachment(): void
    {
        $response = new Response();
        $response->attachment('/path/to/file.pdf', 'application/pdf', 'document.pdf');

        $attachments = $response->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals('/path/to/file.pdf', $attachments[0]['path']);
        $this->assertEquals('application/pdf', $attachments[0]['mime_type']);
        $this->assertEquals('document.pdf', $attachments[0]['filename']);
    }

    public function testAttachmentWithAutoFilename(): void
    {
        $response = new Response();
        $response->attachment('/path/to/some-file.pdf', 'application/pdf');

        $attachments = $response->getAttachments();
        $this->assertEquals('some-file.pdf', $attachments[0]['filename']);
    }

    public function testEmptyResponse(): void
    {
        $response = new Response();

        $this->assertEmpty($response->getMessages());
        $this->assertEmpty($response->getAttachments());
    }

    public function testCombinedTextAndAttachment(): void
    {
        $response = new Response();
        $response
            ->text('Here is your document')
            ->attachment('/tmp/doc.pdf', 'application/pdf', 'signed.pdf');

        $this->assertCount(1, $response->getMessages());
        $this->assertCount(1, $response->getAttachments());
    }
}
