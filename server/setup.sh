#!/bin/bash
set -e

SERVER_NAME="${1:-matrix.localhost}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== Dendrite Setup ==="
echo "Server: $SERVER_NAME"

mkdir -p "$SCRIPT_DIR/data" "$SCRIPT_DIR/config"

# Generate key
if [ ! -f "$SCRIPT_DIR/config/matrix_key.pem" ]; then
    docker run --rm -v "$SCRIPT_DIR/config:/etc/dendrite" \
        matrixdotorg/dendrite-monolith:latest \
        /usr/bin/generate-keys -private-key /etc/dendrite/matrix_key.pem
fi

# Generate config
REGISTRATION_SECRET=$(openssl rand -hex 32)

cat > "$SCRIPT_DIR/config/dendrite.yaml" << EOF
version: 2
global:
  server_name: $SERVER_NAME
  private_key: /etc/dendrite/matrix_key.pem
  database:
    connection_string: file:/var/dendrite/dendrite.db
client_api:
  registration_disabled: false
  registration_shared_secret: "$REGISTRATION_SECRET"
media_api:
  base_path: /var/dendrite/media
  max_file_size_bytes: 52428800
sync_api:
  search:
    enabled: true
    index_path: /var/dendrite/searchindex
EOF

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
REGISTRATION_SECRET=$REGISTRATION_SECRET
EOF

docker compose up -d

echo ""
echo "Done! Dendrite: http://localhost:8008 | Element: http://localhost:8009"
echo "Create user: docker exec dendrite /usr/bin/create-account -config /etc/dendrite/dendrite.yaml -username admin -admin"
