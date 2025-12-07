# Bot SDK

A simple, extensible framework for building chat bots that work across multiple platforms.

## Quick Start: Create a New Bot

### 1. Create Bot Directory

```bash
mkdir -p lib/Bots/MyBot/Commands
```

### 2. Create Your Bot Class

```php
<?php
// lib/Bots/MyBot/MyBot.php

namespace OCA\DocuSealIntegration\Bots\MyBot;

use OCA\DocuSealIntegration\BotSDK\AbstractBot;
use OCA\DocuSealIntegration\BotSDK\Commands\HelpCommand;
use OCA\DocuSealIntegration\Bots\MyBot\Commands\GreetCommand;
use Psr\Log\LoggerInterface;

class MyBot extends AbstractBot
{
    public function __construct(
        LoggerInterface $logger,
        private string $botUserId = '@my-bot:matrix.example.com',
    ) {
        parent::__construct($logger);
    }

    public function getId(): string
    {
        return 'mybot';
    }

    public function getName(): string
    {
        return 'My Bot';
    }

    public function getBotUserId(): string
    {
        return $this->botUserId;
    }

    public function getDescription(): string
    {
        return 'A friendly greeting bot';
    }

    protected function registerCommands(): void
    {
        $this->addCommand(new HelpCommand($this));
        $this->addCommand(new GreetCommand());
    }
}
```

### 3. Create Commands

```php
<?php
// lib/Bots/MyBot/Commands/GreetCommand.php

namespace OCA\DocuSealIntegration\Bots\MyBot\Commands;

use OCA\DocuSealIntegration\BotSDK\Commands\AbstractCommand;
use OCA\DocuSealIntegration\BotSDK\Message;
use OCA\DocuSealIntegration\BotSDK\Response;

class GreetCommand extends AbstractCommand
{
    protected string $name = 'greet';
    protected string $description = 'Greet someone';
    protected array $aliases = ['hi', 'hello'];

    public function handle(Message $message, Response $response): void
    {
        $name = $message->getArguments() ?: 'friend';
        $response->text("Hello, {$name}! 👋");
    }
}
```

### 4. Register Bot User with Matrix

Add to your appservice YAML:

```yaml
namespaces:
  users:
    - exclusive: true
      regex: "@my-bot:yourdomain\\.com"
```

That's it! Your bot is **auto-discovered** and responds to `/greet`, `/hi`, `/hello`, and `/help`.

> **Auto-Discovery**: Any class ending in `Bot.php` in `lib/Bots/*/` is automatically loaded. No manual registration needed!

---

## SDK Components

### Message

Platform-agnostic message representation:

```php
$message->getText();           // Raw message text
$message->getCommand();        // Command name (without /)
$message->getArguments();      // Everything after command
$message->getArgumentList();   // Arguments as array
$message->getSender();         // Who sent it
$message->getRoomId();         // Room/channel ID
$message->getPlatform();       // 'matrix', 'signal', etc.
$message->hasAttachments();    // Check for files
$message->getAttachments();    // Get file info
$message->getMentionedUsers(); // Extract @mentions and emails
```

### Response

Fluent response builder:

```php
$response->text('Hello!');                    // Plain text
$response->markdown('**Bold** text');         // Formatted
$response->error('Something went wrong');     // Error message
$response->attachment('/path/to/file.pdf', 'application/pdf');
$response->attachmentFromContent($bytes, 'report.pdf', 'application/pdf');
```

### AbstractCommand

Base class for commands:

```php
class MyCommand extends AbstractCommand
{
    protected string $name = 'mycommand';
    protected string $description = 'Does something';
    protected array $aliases = ['mc', 'my'];  // Optional

    public function handle(Message $message, Response $response): void
    {
        // Your logic here
    }
}
```

### AbstractBot

Base class for bots:

```php
class MyBot extends AbstractBot
{
    public function getId(): string;          // Unique ID
    public function getName(): string;        // Display name
    public function getBotUserId(): string;   // Matrix user ID
    public function getDescription(): string; // For /help

    protected function registerCommands(): void
    {
        $this->addCommand(new SomeCommand());
    }

    // Optional: filter which messages to handle
    public function shouldHandle(Message $message): bool
    {
        // Default: handle all messages except from self
        return parent::shouldHandle($message);
    }
}
```

### BotRegistry

Manages multiple bots:

```php
$registry = new BotRegistry($logger);
$registry->setDriver($matrixDriver);
$registry->register($bot1);
$registry->register($bot2);

// Route message to all bots
$registry->handleMessage($message);

// Get specific bot
$bot = $registry->get('docuseal');

// Get all commands across all bots
$commands = $registry->getAllCommands();
```

---

## Drivers

### MatrixDriver

Built-in Matrix support:

```php
$driver = new MatrixDriver($config, $clientService, $logger);
$driver->sendMessage($roomId, 'Hello!');
$driver->sendFile($roomId, $mxcUri, 'file.pdf', 'application/pdf', $size);
$driver->uploadMedia($content, 'file.pdf', 'application/pdf');
$driver->downloadMedia('mxc://server/mediaId');
$driver->joinRoom('!roomid:server');
```

### Adding New Drivers

Implement `DriverInterface`:

```php
class TelegramDriver implements DriverInterface
{
    public function getPlatform(): string
    {
        return 'telegram';
    }

    public function parseEvent(array $event): ?Message
    {
        // Parse Telegram update into Message
    }

    public function send(Message $original, Response $response): bool
    {
        // Send response via Telegram API
    }

    public function sendTo(string $recipient, Response $response): bool
    {
        // Send direct message
    }
}
```

---

## Directory Structure

```
lib/
├── BotSDK/                      # The SDK
│   ├── AbstractBot.php          # Base bot class
│   ├── BotInterface.php         # Bot interface
│   ├── BotRegistry.php          # Multi-bot manager
│   ├── Message.php              # Message representation
│   ├── Response.php             # Response builder
│   ├── Commands/
│   │   ├── CommandInterface.php
│   │   ├── AbstractCommand.php
│   │   └── HelpCommand.php      # Built-in /help
│   └── Drivers/
│       ├── DriverInterface.php
│       └── MatrixDriver.php
│
└── Bots/                        # Your bots
    ├── DocuSealBot/
    │   ├── DocuSealBot.php
    │   └── Commands/
    │       ├── SignCommand.php
    │       ├── StatusCommand.php
    │       └── TemplatesCommand.php
    └── ExampleBot/              # Template bot
        ├── ExampleBot.php
        └── Commands/
            ├── PingCommand.php
            ├── EchoCommand.php
            └── TimeCommand.php
```

---

## Tips

### Dependency Injection

Pass services to your bot constructor:

```php
class WeatherBot extends AbstractBot
{
    public function __construct(
        LoggerInterface $logger,
        private WeatherService $weather,
        private string $botUserId,
    ) {
        parent::__construct($logger);
    }

    protected function registerCommands(): void
    {
        $this->addCommand(new ForecastCommand($this->weather));
    }
}
```

### Async Operations

For long-running tasks, send an immediate response then process:

```php
public function handle(Message $message, Response $response): void
{
    $response->text('Processing... I\'ll update you when done.');

    // Queue the actual work (implement your own queue)
    $this->queue->push(new ProcessDocumentJob($message));
}
```

### Error Handling

The SDK catches exceptions and sends error messages automatically, but you can handle them yourself:

```php
public function handle(Message $message, Response $response): void
{
    try {
        $result = $this->riskyOperation();
        $response->text("Success: {$result}");
    } catch (SpecificException $e) {
        $response->text("Could not complete: {$e->getMessage()}");
        $this->logger->warning('Operation failed', ['error' => $e]);
    }
}
```

### Testing

Commands are easy to unit test:

```php
public function testGreetCommand(): void
{
    $command = new GreetCommand();
    $message = new Message('matrix', '@user:test', '/greet World');
    $response = new Response();

    $command->handle($message, $response);

    $this->assertStringContainsString('Hello, World', $response->getFullText());
}
```
