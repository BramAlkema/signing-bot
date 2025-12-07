#!/bin/bash
set -e

SERVER_NAME="${1:-matrix.localhost}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== Matrix (Continuwuity) Setup ==="
echo "Server: $SERVER_NAME"

mkdir -p "$SCRIPT_DIR/data"

# Generate registration token
REGISTRATION_TOKEN=$(openssl rand -hex 32)

cat > "$SCRIPT_DIR/element-config.json" << EOF
{
  "default_server_config": {
    "m.homeserver": {
      "base_url": "https://$SERVER_NAME",
      "server_name": "$SERVER_NAME"
    }
  }
}
EOF

cat > "$SCRIPT_DIR/.env" << EOF
SERVER_NAME=$SERVER_NAME
REGISTRATION_TOKEN=$REGISTRATION_TOKEN
EOF

docker compose up -d

echo ""
echo "Done! Matrix: http://localhost:8008 | Element: http://localhost:8009"
echo ""
echo "Registration token: $REGISTRATION_TOKEN"
echo "Register at: https://$SERVER_NAME/_matrix/client/v3/register"
