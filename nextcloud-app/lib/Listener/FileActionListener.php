<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use Psr\Log\LoggerInterface;

/**
 * Listener for file creation events
 *
 * Currently a placeholder - file actions are handled via JavaScript.
 * This could be extended to auto-detect PDF uploads or trigger workflows.
 *
 * @template-implements IEventListener<NodeCreatedEvent>
 */
class FileActionListener implements IEventListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Event $event): void
    {
        if (!$event instanceof NodeCreatedEvent) {
            return;
        }

        $node = $event->getNode();

        // Only process PDF files
        if ($node->getMimeType() !== 'application/pdf') {
            return;
        }

        // Placeholder for future functionality:
        // - Auto-detect contracts based on filename patterns
        // - Trigger signing workflows
        // - Send notifications for uploaded documents

        $this->logger->debug('PDF file created: ' . $node->getPath());
    }
}
