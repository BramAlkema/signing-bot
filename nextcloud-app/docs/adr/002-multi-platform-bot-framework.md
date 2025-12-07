# ADR-002: Multi-Platform Bot Framework Architecture

## Status

Accepted

## Date

2024-12-04

## Context

The DocuSeal Integration needed to support bot interactions from multiple messaging platforms:

- Matrix (via Application Service API)
- Signal (potential future support via signal-cli)
- Potentially others (Slack, Telegram, etc.)

We needed a design that:
1. Allows platform-specific transport handling
2. Shares command logic across platforms
3. Is easy to extend with new platforms
4. Handles platform-specific features (attachments, mentions)

## Decision

We implemented a **Driver-based Bot Framework** with the following components:

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      BotFramework                           │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐     │
│  │ HelpCommand │    │DocuSealCmd  │    │ Future...   │     │
│  └─────────────┘    └─────────────┘    └─────────────┘     │
│         │                  │                  │             │
│         └──────────────────┼──────────────────┘             │
│                            ▼                                │
│                    CommandInterface                         │
│                    - matches(Message)                       │
│                    - handle(Message, Response)              │
└─────────────────────────────────────────────────────────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
       ┌────────────┐ ┌────────────┐ ┌────────────┐
       │MatrixDriver│ │SignalDriver│ │Future...   │
       └────────────┘ └────────────┘ └────────────┘
              │              │              │
              ▼              ▼              ▼
       ┌────────────┐ ┌────────────┐ ┌────────────┐
       │ Matrix API │ │ signal-cli │ │ Other API  │
       └────────────┘ └────────────┘ └────────────┘
```

### Core Components

#### 1. Message (Value Object)
```php
class Message {
    platform: string      // 'matrix', 'signal', etc.
    sender: string        // Platform-specific sender ID
    text: string          // Message content
    roomId: ?string       // Room/group ID (if applicable)
    attachments: ?array   // Files attached to message
    timestamp: int
    raw: array            // Original platform-specific data
}
```

#### 2. Response (Builder)
```php
class Response {
    text(string $content)           // Add text message
    attachment(string $path, ...)   // Add file attachment
    getMessages(): array            // Get all messages to send
    getAttachments(): array         // Get all attachments
    hasContent(): bool              // Check if anything to send
}
```

#### 3. DriverInterface
```php
interface DriverInterface {
    getPlatform(): string
    send(Message $original, Response $response): bool
    sendTo(string $recipient, Response $response): bool
}
```

#### 4. CommandInterface
```php
interface CommandInterface {
    getName(): string
    getDescription(): string
    matches(Message $message): bool
    handle(Message $message, Response $response): void
}
```

## Implementation Details

### Message Parsing

Each driver is responsible for parsing platform-specific events into the unified Message format:

**MatrixDriver::parseEvent()**
- Extracts sender, room_id, content from m.room.message events
- Handles mxc:// attachment URIs
- Filters out bot's own messages

**SignalDriver** (conceptual)
- Would parse signal-cli JSON output
- Handle phone number as sender
- Process local file attachments

### Command Routing

The BotFramework iterates through registered commands and calls `matches()`:

```php
foreach ($this->commands as $command) {
    if ($command->matches($message)) {
        $command->handle($message, $response);
        return $response;
    }
}
```

Commands use the Message's `getCommand()` method which extracts commands starting with `/` or `!`:

```php
// Message: "/docuseal user@email.com"
$message->getCommand()    // "docuseal"
$message->getArguments()  // "user@email.com"
```

### Response Delivery

After command execution, the framework uses the appropriate driver to send the response:

```php
$driver = $this->getDriver($message->getPlatform());
$driver->send($message, $response);
```

The driver handles platform-specific formatting and API calls.

## Consequences

### Positive

- **Extensible**: Add new platforms by implementing DriverInterface
- **Testable**: Commands can be tested without platform dependencies
- **DRY**: Command logic is written once, works everywhere
- **Flexible**: Platform-specific features accessible via raw message data

### Negative

- **Abstraction cost**: Some platform features may not fit the abstraction
- **Complexity**: More interfaces and classes than a single-platform solution

### Trade-offs

- We chose simplicity over completeness - not all platform features are abstracted
- Commands can access `$message->getRaw()` for platform-specific data when needed

## Future Considerations

1. **Webhook-based platforms**: Slack, Discord could use similar driver pattern
2. **Two-way conversations**: Current design is request-response; stateful conversations would need session management
3. **Rich formatting**: Could extend Response to support markdown, buttons, etc.

## References

- [BotMan PHP Framework](https://botman.io/) - Inspiration for driver pattern
- [Matrix Client-Server API](https://spec.matrix.org/v1.6/client-server-api/)
