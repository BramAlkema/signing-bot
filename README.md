# Dendrite Document Signing

Matrix-based document signing using Dendrite (Go) server + Nextcloud integration.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  Nextcloud                    Dendrite (Matrix)                │
│  ┌───────────────────┐       ┌───────────────────┐             │
│  │ Files (PDFs)      │       │ Signing Rooms     │             │
│  │                   │──────▶│ Identity Verify   │             │
│  │ DocuSeal App      │       │ Audit Trail       │             │
│  └───────────────────┘       └───────────────────┘             │
│           │                           │                         │
│           │                           │                         │
│           ▼                           ▼                         │
│  ┌───────────────────┐       ┌───────────────────┐             │
│  │ User signs with   │       │ Element Web       │             │
│  │ SSH/GPG key       │       │ (verification)    │             │
│  └───────────────────┘       └───────────────────┘             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Components

```
dendrite/
├── server/              # Dendrite Matrix server
│   ├── docker-compose.yml
│   ├── config/
│   │   └── dendrite.yaml
│   ├── element-config.json
│   ├── nginx.conf
│   └── setup.sh
│
└── nextcloud-app/       # Nextcloud integration app
    ├── appinfo/
    ├── lib/
    │   ├── Controller/
    │   ├── Service/
    │   │   ├── MatrixService.php
    │   │   └── SignatureService.php
    │   └── Db/
    └── src/
```

## Quick Start

### 1. Deploy Dendrite Server

```bash
cd server

# Setup with your domain
./setup.sh matrix.ctrlaltdef.eu

# Creates:
# - Signing keys
# - Configuration
# - Starts Docker containers
```

### 2. Configure Nginx

```bash
# Add the nginx.conf to your server
sudo cp nginx.conf /etc/nginx/sites-available/matrix
sudo ln -s /etc/nginx/sites-available/matrix /etc/nginx/sites-enabled/
sudo certbot --nginx -d matrix.ctrlaltdef.eu -d element.ctrlaltdef.eu
sudo systemctl reload nginx
```

### 3. Create Bot Account

```bash
# Create signing bot
docker exec dendrite /usr/bin/create-account \
    -config /etc/dendrite/dendrite.yaml \
    -username docuseal-bot \
    -admin

# Get access token
curl -X POST "https://matrix.ctrlaltdef.eu/_matrix/client/v3/login" \
    -H "Content-Type: application/json" \
    -d '{"type":"m.login.password","user":"docuseal-bot","password":"YOUR_PASSWORD"}'
```

### 4. Install Nextcloud App

```bash
cd nextcloud-app

# Install dependencies
composer install --no-dev
npm install && npm run build

# Copy to Nextcloud
cp -r . /path/to/nextcloud/apps/docuseal_integration/

# Enable
php occ app:enable docuseal_integration
php occ upgrade
```

### 5. Configure Nextcloud App

In Nextcloud Admin → DocuSeal Integration:

- Matrix Homeserver: `https://matrix.ctrlaltdef.eu`
- Bot Access Token: (from step 3)
- Bot User ID: `@docuseal-bot:ctrlaltdef.eu`

## Signing Flow

1. **Create Session**: Right-click PDF → "Sign with Matrix"
2. **Add Signers**: Enter Matrix IDs (`@alice:server`, `@bob:server`)
3. **Room Created**: Encrypted Matrix room with document
4. **Verify Identity**: Signers verify each other (emoji verification in Element)
5. **Sign Document**: Each signer:
   ```bash
   # Sign document hash with SSH key
   echo -n 'DOCUMENT_HASH' | ssh-keygen -Y sign -f ~/.ssh/id_ed25519 -n document -
   ```
6. **Submit Signature**: Paste signature in app
7. **Complete**: All signatures posted to room as immutable proof

## Resource Usage

Dendrite is extremely lightweight:

| Component | RAM | CPU | Disk |
|-----------|-----|-----|------|
| Dendrite | ~50MB | Minimal | SQLite |
| Element | ~20MB | Minimal | None |
| **Total** | **~70MB** | **<1%** | **<100MB** |

Compare to Synapse: 500MB+ RAM, PostgreSQL required.

## Verification

Anyone can verify a signature:

```bash
# Create allowed_signers file
echo "signer@doc ssh-ed25519 AAAA..." > allowed_signers

# Verify
ssh-keygen -Y verify -f allowed_signers -I signer@doc -n document -s signature.sig < hash.txt
```

## Federation (Optional)

Dendrite supports Matrix federation. To enable:

1. Open port 8448
2. Add SRV record: `_matrix._tcp.ctrlaltdef.eu → matrix.ctrlaltdef.eu:443`
3. Users from other Matrix servers can join signing rooms

## License

AGPL-3.0
