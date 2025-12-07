# ADR-001: Matrix Application Service Integration

## Status

Accepted

## Date

2024-12-04

## Context

The DocuSeal Integration app needed to support document signing workflows initiated from Matrix chat rooms. Users should be able to:

1. Send `/docuseal` commands in Matrix rooms to initiate document signing
2. Attach documents directly in Matrix and have them processed
3. Receive signing links and status updates in the chat

We needed to decide how to integrate with Matrix homeservers to receive and respond to messages.

## Decision Drivers

- **Push vs Poll**: Real-time message handling without constant polling
- **Scalability**: Handle multiple rooms and users efficiently
- **Security**: Authenticate requests from the homeserver
- **Simplicity**: Minimize dependencies and complexity
- **Compatibility**: Work with Conduit/Continuwuity and Synapse homeservers

## Considered Options

### Option 1: Matrix Client SDK (Polling)

Use a Matrix client library to connect as a regular user and poll for messages.

**Pros:**
- Well-documented client libraries available
- Works like any Matrix user

**Cons:**
- Requires persistent connection or frequent polling
- Need to manage connection state, reconnection logic
- Higher resource usage
- Not designed for bots/integrations

### Option 2: Matrix Application Service API (Push)

Register as a Matrix Application Service (appservice) to receive events pushed from the homeserver.

**Pros:**
- Push-based: homeserver sends events to us
- No polling, no persistent connections
- Built for bots and bridges
- Efficient and scalable
- Clear authentication model (hs_token/as_token)

**Cons:**
- Requires homeserver configuration (registration YAML)
- More complex initial setup
- Need to handle HTTP endpoints

### Option 3: Matrix Webhook Bridge

Use an external webhook bridge to translate Matrix events.

**Pros:**
- Simpler integration
- Just handle webhooks

**Cons:**
- Additional dependency/service to run
- Less control over event filtering
- Additional point of failure

## Decision

We chose **Option 2: Matrix Application Service API** because:

1. **Push-based architecture** eliminates polling overhead and provides real-time message handling
2. **Native Matrix integration** - appservices are the intended way to build bots and bridges
3. **Efficient resource usage** - no persistent connections, just HTTP endpoints
4. **Clear security model** - token-based authentication between homeserver and app
5. **Future-proof** - can extend to user/room management if needed

## Implementation

### Architecture

```
┌─────────────────┐     PUT /transactions/{txnId}     ┌──────────────────────┐
│                 │ ──────────────────────────────────▶│                      │
│   Continuwuity  │                                    │  Nextcloud App       │
│   Homeserver    │ ◀──────────────────────────────────│  (MatrixAppService   │
│                 │     PUT /_matrix/client/v3/...     │   Controller)        │
└─────────────────┘                                    └──────────────────────┘
        │                                                        │
        │  m.room.message events                                 │
        ▼                                                        ▼
┌─────────────────┐                                    ┌──────────────────────┐
│  Matrix Rooms   │                                    │  BotFramework        │
│  (Users send    │                                    │  - HelpCommand       │
│   /docuseal)    │                                    │  - DocuSealCommand   │
└─────────────────┘                                    └──────────────────────┘
```

### Components

1. **MatrixAppServiceController** (`lib/Controller/MatrixAppServiceController.php`)
   - Receives `PUT /appservice/transactions/{txnId}` from homeserver
   - Verifies `hs_token` authentication
   - Parses events and routes to BotFramework
   - Handles idempotency via transaction ID tracking

2. **MatrixDriver** (`lib/Bot/Drivers/MatrixDriver.php`)
   - Parses Matrix events into platform-agnostic Message objects
   - Sends responses back via Matrix Client-Server API
   - Handles media upload/download for attachments

3. **BotFramework** (`lib/Bot/BotFramework.php`)
   - Platform-agnostic command routing
   - Registers commands (HelpCommand, DocuSealCommand)
   - Dispatches messages to matching commands

4. **Application Service Registration** (`docs/matrix-appservice.yaml`)
   - Defines bot user (`@docuseal-bot:matrix.server.com`)
   - Specifies tokens for authentication
   - Declares user namespace for bot accounts

### Request Flow

1. User sends `/help` in Matrix room
2. Homeserver pushes event to `PUT /appservice/transactions/{txnId}`
3. Controller verifies `hs_token` from query parameter
4. Event is parsed into Message object
5. BotFramework routes to HelpCommand
6. Response is sent via `PUT /_matrix/client/v3/rooms/{roomId}/send/m.room.message/{txnId}`

### Authentication

- **hs_token**: Homeserver uses this to authenticate requests to the app
- **as_token**: App uses this to authenticate requests to the homeserver

Tokens are stored in Nextcloud's app configuration:
```bash
occ config:app:set docuseal_integration matrix_hs_token --value="..."
occ config:app:set docuseal_integration matrix_as_token --value="..."
```

### Key Technical Decisions

1. **JSON Body Parsing**: Nextcloud's `$this->request->put` magic getter doesn't include JSON body content for PUT requests. We use `file_get_contents('php://input')` directly.

2. **Token Verification**: Matrix spec allows token in Authorization header or query parameter. We support both, with query parameter as primary (used by Continuwuity).

3. **Idempotency**: Transaction IDs are tracked in static array to handle duplicate deliveries.

## Consequences

### Positive

- Real-time message handling without polling
- Clean separation between Matrix transport and bot logic
- Extensible to other platforms (Signal driver also implemented)
- Minimal resource usage - just HTTP request handlers

### Negative

- Requires homeserver admin access to register appservice
- Initial setup is more complex than client-based approach
- File permissions must be carefully managed (www-data ownership)

### Risks

- **Homeserver dependency**: If homeserver is misconfigured, events won't be delivered
- **Token management**: Tokens must be kept in sync between homeserver and app

## References

- [Matrix Application Service API](https://spec.matrix.org/v1.6/application-service-api/)
- [Continuwuity Documentation](https://continuwuity.org/documentation.html)
- [Nextcloud App Development](https://docs.nextcloud.com/server/latest/developer_manual/)
