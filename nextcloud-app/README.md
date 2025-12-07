# DocuSeal Integration for Nextcloud

Integrate DocuSeal document signing directly into your Nextcloud instance, with optional Matrix bot support for initiating signing workflows from chat.

## Features

### Nextcloud Integration
- **Right-click to sign**: Send PDF files to DocuSeal directly from Nextcloud Files
- **Template support**: Use existing DocuSeal templates or upload documents on-the-fly
- **Multi-recipient**: Add multiple signers to a single document
- **Auto-save**: Signed documents are automatically saved back to Nextcloud
- **Notifications**: Get Nextcloud notifications when documents are signed
- **Status tracking**: Monitor signing progress from within Nextcloud

### Matrix Bot Integration
- **Chat-based signing**: Send `/docuseal` command with attached documents
- **Multi-platform**: Extensible bot framework supports Matrix (and potentially Signal)
- **Push-based**: Uses Matrix Application Service API for real-time message handling
- **Commands**: `/help` and `/docuseal` commands available in any room with the bot

## Requirements

- Nextcloud 28 or later
- PHP 8.1 or later
- DocuSeal instance (self-hosted or cloud)
- DocuSeal API key
- (Optional) Matrix homeserver with Application Service support (Synapse, Conduit, Continuwuity)

## Installation

### From App Store (recommended)

1. Go to Nextcloud Apps
2. Search for "DocuSeal Integration"
3. Click Install

### Manual Installation

1. Clone this repository into your Nextcloud apps directory:
   ```bash
   cd /path/to/nextcloud/apps
   git clone https://github.com/ctrlaltdef/nextcloud-docuseal-app.git docuseal_integration
   ```

2. Install PHP dependencies:
   ```bash
   cd docuseal_integration
   composer install --no-dev
   ```

3. Install and build JavaScript:
   ```bash
   npm install
   npm run build
   ```

4. Enable the app:
   ```bash
   php occ app:enable docuseal_integration
   ```

## Configuration

### DocuSeal Setup

1. Go to **Settings** → **Administration** → **DocuSeal Integration**
2. Enter your DocuSeal URL (e.g., `https://api.docuseal.co` or your self-hosted URL)
3. Enter your API key
4. Copy the webhook URL and configure it in DocuSeal
5. Click **Test Connection** to verify

### Matrix Bot Setup (Optional)

To enable the Matrix bot integration:

#### 1. Generate Tokens

Generate two random tokens (hs_token and as_token):
```bash
# Generate tokens
openssl rand -hex 32  # Use for hs_token
openssl rand -hex 32  # Use for as_token
```

#### 2. Configure Nextcloud

Set the tokens in Nextcloud:
```bash
php occ config:app:set docuseal_integration matrix_homeserver --value="https://matrix.yourdomain.com"
php occ config:app:set docuseal_integration matrix_hs_token --value="YOUR_HS_TOKEN"
php occ config:app:set docuseal_integration matrix_as_token --value="YOUR_AS_TOKEN"
php occ config:app:set docuseal_integration matrix_bot_user --value="@docuseal-bot:yourdomain.com"
```

#### 3. Create Application Service Registration

Create a YAML file (e.g., `docuseal-appservice.yaml`):

```yaml
id: docuseal-bot
url: "https://your-nextcloud-domain.com"

# These must match what you configured in Nextcloud
as_token: "YOUR_AS_TOKEN"
hs_token: "YOUR_HS_TOKEN"

sender_localpart: "docuseal-bot"

namespaces:
  users:
    - exclusive: true
      regex: "@docuseal-.*:yourdomain\\.com"
  aliases: []
  rooms: []

rate_limited: false
```

#### 4. Register with Homeserver

**For Continuwuity/Conduit:**
```bash
# Copy to appservices directory
cp docuseal-appservice.yaml /path/to/conduit/data/appservices/

# Restart the homeserver
systemctl restart continuwuity
```

**For Synapse:**
Add to `homeserver.yaml`:
```yaml
app_service_config_files:
  - /path/to/docuseal-appservice.yaml
```

#### 5. Configure Reverse Proxy

The Matrix homeserver needs to reach Nextcloud. Add to your nginx config:

```nginx
# Matrix Application Service API routes
location ^~ /_matrix/app/v1/ {
    rewrite ^/_matrix/app/v1/(.*)$ /apps/docuseal_integration/appservice/$1 break;
    proxy_pass http://your-nextcloud-backend;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

#### 6. Invite Bot to Rooms

Invite `@docuseal-bot:yourdomain.com` to any room where you want to use signing commands.

## Usage

### Nextcloud File Actions

1. Navigate to your PDF file in Nextcloud Files
2. Right-click on the file
3. Select **Send to DocuSeal**
4. Add recipient(s) email addresses
5. Optionally select a template
6. Click **Send for signing**

### Matrix Bot Commands

In any Matrix room where the bot is present:

#### `/help`
Shows available commands:
```
Available commands:

/help - Show available commands
/docuseal - Send attached document for signing via DocuSeal
```

#### `/docuseal`
Send a document for signing:
```
/docuseal alice@company.com bob@client.com
```
Attach a PDF to the message and mention signer email addresses.

The bot will respond with:
- Confirmation of submission creation
- Individual signing links for each signer
- Tracking ID for the submission

### Tracking Submissions

1. Click on **DocuSeal** in the Nextcloud navigation
2. View all your submissions and their status
3. Click on a submission to see details

### Webhook Events

The app receives the following webhooks from DocuSeal:

- `form.viewed` - Document was opened
- `form.started` - Signing started
- `form.completed` - One signer completed
- `submission.completed` - All signers completed (triggers document download)

## Architecture

The app uses a modular architecture to support multiple platforms:

```
┌─────────────────────────────────────────────────────────────┐
│                    Nextcloud App                            │
├─────────────────────────────────────────────────────────────┤
│  Controllers                                                │
│  ├── DocuSealController    (Nextcloud UI integration)       │
│  ├── WebhookController     (DocuSeal webhooks)              │
│  └── MatrixAppServiceController (Matrix events)             │
├─────────────────────────────────────────────────────────────┤
│  Bot Framework                                              │
│  ├── BotFramework          (Command routing)                │
│  ├── Commands/             (HelpCommand, DocuSealCommand)   │
│  └── Drivers/              (MatrixDriver, SignalDriver)     │
├─────────────────────────────────────────────────────────────┤
│  Services                                                   │
│  ├── DocuSealService       (DocuSeal API client)            │
│  └── SignatureService      (Signature management)           │
└─────────────────────────────────────────────────────────────┘
```

See [Architecture Decision Records](docs/adr/) for detailed design decisions.

## Development

### Setup

```bash
# Clone the repository
git clone https://github.com/ctrlaltdef/nextcloud-docuseal-app.git
cd nextcloud-docuseal-app

# Install dependencies
composer install
npm install

# Build frontend (development)
npm run watch

# Build frontend (production)
npm run build
```

### Testing

```bash
# PHP tests
composer test

# Linting
composer lint
npm run lint
```

### Project Structure

```
docuseal_integration/
├── appinfo/
│   ├── info.xml              # App metadata
│   └── routes.php            # API routes
├── lib/
│   ├── AppInfo/              # App bootstrap
│   ├── Bot/                  # Bot framework
│   │   ├── Commands/         # Command handlers
│   │   ├── Drivers/          # Platform drivers
│   │   ├── BotFramework.php  # Command router
│   │   ├── Message.php       # Platform-agnostic message
│   │   └── Response.php      # Response builder
│   ├── Controller/           # API controllers
│   ├── Db/                   # Database entities
│   ├── Migration/            # Database migrations
│   ├── Service/              # Business logic
│   └── Settings/             # Admin settings
├── src/
│   ├── components/           # Vue components
│   ├── fileActions.js        # File actions integration
│   ├── main.js               # Main app entry
│   └── admin.js              # Admin settings JS
├── templates/                # PHP templates
├── docs/
│   ├── adr/                  # Architecture Decision Records
│   └── matrix-appservice.yaml # Example Matrix registration
├── tests/                    # Unit tests
├── img/                      # App icons
└── css/                      # Stylesheets
```

## API

### Internal API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/templates` | List DocuSeal templates |
| POST | `/api/submissions` | Create submission from template |
| POST | `/api/send-file` | Send file for signing |
| GET | `/api/submissions/list` | List user's submissions |
| GET | `/api/submissions/{id}` | Get submission details |
| POST | `/webhook` | Receive DocuSeal webhooks |

### Matrix Application Service Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| PUT | `/appservice/transactions/{txnId}` | Receive Matrix events |
| GET | `/appservice/users/{userId}` | User existence query |
| GET | `/appservice/rooms/{roomAlias}` | Room alias query |

## Troubleshooting

### Matrix Bot Issues

**Bot not responding:**
1. Check homeserver logs for delivery errors
2. Verify hs_token matches between Nextcloud and registration YAML
3. Check Nextcloud logs: `tail -f /path/to/nextcloud/data/nextcloud.log | grep docuseal`

**Permission errors:**
Ensure app files are owned by web server user:
```bash
chown -R www-data:www-data /path/to/nextcloud/apps/docuseal_integration
```

**JSON parse errors:**
The app uses `file_get_contents('php://input')` for body parsing. Ensure your reverse proxy preserves the request body.

### DocuSeal Issues

**Connection test fails:**
1. Verify DocuSeal URL is accessible from Nextcloud server
2. Check API key is valid
3. Look for firewall/network issues

**Webhooks not received:**
1. Ensure webhook URL is publicly accessible
2. Check DocuSeal webhook configuration
3. Verify SSL certificate is valid

## License

AGPL-3.0 - See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a PR.

## Support

- GitHub Issues: https://github.com/ctrlaltdef/nextcloud-docuseal-app/issues
- DocuSeal Documentation: https://www.docuseal.com/docs
- Matrix Spec: https://spec.matrix.org/
