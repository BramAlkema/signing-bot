# DocuSeal Integration for Nextcloud

Integrate DocuSeal document signing directly into your Nextcloud instance.

## Features

- **Right-click to sign**: Send PDF files to DocuSeal directly from Nextcloud Files
- **Template support**: Use existing DocuSeal templates or upload documents on-the-fly
- **Multi-recipient**: Add multiple signers to a single document
- **Auto-save**: Signed documents are automatically saved back to Nextcloud
- **Notifications**: Get Nextcloud notifications when documents are signed
- **Status tracking**: Monitor signing progress from within Nextcloud

## Requirements

- Nextcloud 28 or later
- PHP 8.1 or later
- DocuSeal instance (self-hosted or cloud)
- DocuSeal API key

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

1. Go to **Settings** → **Administration** → **DocuSeal Integration**
2. Enter your DocuSeal URL (e.g., `https://api.docuseal.co` or your self-hosted URL)
3. Enter your API key
4. Copy the webhook URL and configure it in DocuSeal
5. Click **Test Connection** to verify

## Usage

### Sending a Document for Signing

1. Navigate to your PDF file in Nextcloud Files
2. Right-click on the file
3. Select **Send to DocuSeal**
4. Add recipient(s) email addresses
5. Optionally select a template
6. Click **Send for signing**

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
│   ├── info.xml          # App metadata
│   └── routes.php        # API routes
├── lib/
│   ├── AppInfo/          # App bootstrap
│   ├── Controller/       # API controllers
│   ├── Db/               # Database entities
│   ├── Migration/        # Database migrations
│   ├── Service/          # Business logic
│   └── Settings/         # Admin settings
├── src/
│   ├── components/       # Vue components
│   ├── fileActions.js    # File actions integration
│   ├── main.js           # Main app entry
│   └── admin.js          # Admin settings JS
├── templates/            # PHP templates
├── img/                  # App icons
└── css/                  # Stylesheets
```

## API

The app exposes the following internal API endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/templates` | List DocuSeal templates |
| POST | `/api/submissions` | Create submission from template |
| POST | `/api/send-file` | Send file for signing |
| GET | `/api/submissions` | List user's submissions |
| GET | `/api/submissions/{id}` | Get submission details |
| POST | `/webhook` | Receive DocuSeal webhooks |

## License

AGPL-3.0 - See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a PR.

## Support

- GitHub Issues: https://github.com/ctrlaltdef/nextcloud-docuseal-app/issues
- DocuSeal Documentation: https://www.docuseal.com/docs
