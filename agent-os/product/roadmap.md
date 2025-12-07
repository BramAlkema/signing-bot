# Signing Bot - Product Roadmap

## Phase 1: Foundation (Complete)

**Status**: Done

### Matrix Integration
- [x] Matrix Application Service API integration
- [x] MatrixDriver for sending/receiving messages
- [x] Room-based signing workflows
- [x] Media upload/download (mxc://)

### DocuSeal Integration
- [x] API client for templates and submissions
- [x] Webhook support for completion events
- [x] Multi-recipient signing support
- [x] Status tracking

### Bot Framework
- [x] Extensible command system (`/sign`, `/status`, `/templates`, `/help`)
- [x] Driver abstraction for multi-platform support
- [x] Auto-discovery of bot plugins
- [x] Attachment handling

### Nextcloud App
- [x] Right-click file action for PDFs
- [x] Admin settings UI
- [x] Submission tracking database
- [x] Nextcloud notifications

---

## Phase 2: Multi-Messenger Expansion

**Status**: In Progress

### Signal Integration (Complete)
- [x] SignalDriver implementation via signal-cli JSON-RPC
- [x] Unix socket connection to signal-cli daemon
- [x] Phone number authentication
- [x] Attachment support
- [x] Message send/receive

### Telegram Integration
- [ ] TelegramDriver implementation
- [ ] Bot API integration
- [ ] Phone number authentication
- [ ] Inline keyboard for template selection

### Discord Integration
- [ ] DiscordDriver implementation
- [ ] Slash commands registration
- [ ] Webhook-based responses
- [ ] Server/channel management

### Slack Integration
- [ ] SlackDriver implementation
- [ ] Slack App with slash commands
- [ ] OAuth flow for workspace installation
- [ ] Block Kit UI for rich messages

---

## Phase 3: Enhanced Features

**Status**: Planned

### Workflow Improvements
- [ ] Multi-signer sequential workflows
- [ ] Reminder notifications for pending signatures
- [ ] Template variables from chat context
- [ ] Bulk signing operations

### Security & Compliance
- [ ] SSH/GPG cryptographic signing verification
- [ ] Audit logging for all signing events
- [ ] Document hash verification
- [ ] Signing certificate support

### User Experience
- [ ] Unified status dashboard across messengers
- [ ] Natural language signing requests
- [ ] Quick-reply buttons for common actions
- [ ] Mobile-optimized interactions

---

## Priority Order

1. ~~**Signal**~~ - Complete
2. **Telegram** - Phone number ready, similar API model
3. **Discord** - Straightforward webhook integration
4. **Slack** - Enterprise complexity, lower priority
