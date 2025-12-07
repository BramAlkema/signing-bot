# Signing Bot - Tech Stack

## Backend

| Component | Technology | Version |
|-----------|------------|---------|
| Language | PHP | 8.1+ |
| Framework | Nextcloud OCP | 28-32 |
| Database | SQLite / PostgreSQL | Via Nextcloud |
| Testing | PHPUnit | 10.5+ |
| Static Analysis | Psalm | 5.0+ |

## Frontend

| Component | Technology |
|-----------|------------|
| Framework | Vue.js |
| Build Tool | Webpack |
| Styling | Nextcloud component library |

## Messenger Integrations

| Platform | Protocol | Status |
|----------|----------|--------|
| Matrix | Application Service API | Complete |
| Signal | signal-cli (JSON-RPC over Unix socket) | Complete |
| Telegram | Bot API | Planned |
| Discord | Gateway API / Webhooks | Planned |
| Slack | Web API / Events API | Planned |

## External Services

| Service | Purpose |
|---------|---------|
| DocuSeal | Document signing backend (self-hosted) |
| Nextcloud | File storage, user management, notifications |

## Infrastructure

| Component | Technology |
|-----------|------------|
| Containerization | Docker |
| Web Server | nginx (reverse proxy) |
| SSL | Let's Encrypt |

## Bot Architecture

```
┌─────────────────────────────────────────────────────┐
│                    BotRegistry                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │
│  │ DocuSealBot │  │  ExampleBot │  │  Future...  │  │
│  └─────────────┘  └─────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────┘
                         │
                    DriverInterface
                         │
    ┌────────────────────┼────────────────────┐
    │                    │                    │
┌───▼───┐          ┌─────▼─────┐        ┌─────▼─────┐
│Matrix │          │  Signal   │        │ Telegram  │
│Driver │          │  Driver   │        │  Driver   │
└───────┘          └───────────┘        └───────────┘
```

## Key Dependencies

### PHP (Composer)
- `nextcloud/ocp` - Nextcloud app framework
- `guzzlehttp/guzzle` - HTTP client for API calls

### JavaScript (npm)
- `vue` - Frontend framework
- `webpack` - Module bundler
- `@nextcloud/*` - Nextcloud Vue components

## Development Tools

- **Version Control**: Git
- **Code Quality**: Psalm, PHP_CodeSniffer
- **Testing**: PHPUnit
- **CI/CD**: GitHub Actions (planned)
