# Claude Code Notes

Project-specific notes and workflows for this Nextcloud app.

## Testing on Remote Server (dtda-server)

The app runs in a Docker container on the VPS. Here's how to deploy and test changes:

### Deploy and Test Workflow

```bash
# 1. Create tarball of the app (from project root)
# COPYFILE_DISABLE=1 prevents macOS from including ._ resource fork files
COPYFILE_DISABLE=1 tar -cvf /tmp/docuseal_integration.tar --exclude='node_modules' --exclude='.git' --exclude='vendor' -C /Users/ynse/projects/matrix/nextcloud-app .

# 2. Copy to server
scp /tmp/docuseal_integration.tar dtda-server:/tmp/

# 3. SSH to server and deploy safely (disables app during deploy to prevent crashes)
ssh dtda-server "
  docker exec nextcloud-proper-php-1 php occ app:disable docuseal_integration 2>/dev/null || true && \
  docker cp /tmp/docuseal_integration.tar nextcloud-proper-php-1:/tmp/ && \
  docker exec nextcloud-proper-php-1 sh -c '
    rm -rf /var/www/html/custom_apps/docuseal_integration/* && \
    tar -xf /tmp/docuseal_integration.tar -C /var/www/html/custom_apps/docuseal_integration/ && \
    chown -R www-data:www-data /var/www/html/custom_apps/docuseal_integration && \
    find /var/www/html/custom_apps/docuseal_integration -type d -exec chmod 755 {} \; && \
    find /var/www/html/custom_apps/docuseal_integration -type f -exec chmod 644 {} \;
  ' && \
  docker exec nextcloud-proper-php-1 php occ app:enable docuseal_integration
"
```

### Run Tests

```bash
# Run all tests with --testdox for readable output
ssh dtda-server "docker exec -w /var/www/html/custom_apps/docuseal_integration nextcloud-proper-php-1 php vendor/bin/phpunit --testdox"

# Run specific test file
ssh dtda-server "docker exec -w /var/www/html/custom_apps/docuseal_integration nextcloud-proper-php-1 php vendor/bin/phpunit tests/Unit/Controller/MatrixAppServiceControllerTest.php --testdox"
```

### Quick File Update (single file)

```bash
# For quick single-file updates without full deploy:
scp /Users/ynse/projects/matrix/nextcloud-app/path/to/file.php dtda-server:/tmp/
ssh dtda-server "docker cp /tmp/file.php nextcloud-proper-php-1:/var/www/html/custom_apps/docuseal_integration/path/to/"
```

### Install Dependencies (if needed)

```bash
ssh dtda-server "docker exec -w /var/www/html/custom_apps/docuseal_integration nextcloud-proper-php-1 composer install"
```

## Multi-Bot SDK Architecture

The app uses a Bot SDK for creating multiple independent bots:

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
    ├── DocuSealBot/             # DocuSeal signing bot
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

### Creating a New Bot

1. Create bot directory: `lib/Bots/MyBot/Commands/`
2. Create bot class extending `AbstractBot` (filename must end in `Bot.php`)
3. Create commands extending `AbstractCommand`
4. Add bot user to Matrix appservice YAML

**That's it!** Bots are auto-discovered from `lib/Bots/*/`. No manual registration needed.

### Enabling/Disabling Bots

Configure via Nextcloud app settings:
- `bots_disabled = ["example"]` - Disable specific bots
- `bots_enabled = ["docuseal"]` - Only enable listed bots (empty = all enabled)

See `lib/BotSDK/README.md` for full documentation.

## Container Details

- Container name: `nextcloud-proper-php-1`
- App path in container: `/var/www/html/custom_apps/docuseal_integration/`
- PHP version: 8.3.28
- PHPUnit version: 10.5.59
