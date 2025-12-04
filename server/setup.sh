#!/bin/bash
set -e

# Dendrite Matrix Server Setup
# Usage: ./setup.sh matrix.yourdomain.com

SERVER_NAME="${1:-matrix.localhost}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== Dendrite Matrix Server Setup ==="
echo "Server name: $SERVER_NAME"
echo ""

# Create directories
mkdir -p "$SCRIPT_DIR/data"
mkdir -p "$SCRIPT_DIR/config"

# Generate signing key if not exists
if [ ! -f "$SCRIPT_DIR/config/matrix_key.pem" ]; then
    echo "Generating Matrix signing key..."
    docker run --rm -v "$SCRIPT_DIR/config:/etc/dendrite" \
        matrixdotorg/dendrite-monolith:latest \
        /usr/bin/generate-keys -private-key /etc/dendrite/matrix_key.pem
    echo "✓ Signing key generated"
fi

# Generate registration secret
REGISTRATION_SECRET=$(openssl rand -hex 32)

# Create .env file
cat > "$SCRIPT_DIR/.env" << EOF
SERVER_NAME=$SERVER_NAME
MATRIX_URL=https://$SERVER_NAME
REGISTRATION_SECRET=$REGISTRATION_SECRET
EOF
echo "✓ Environment file created"

# Update config with server name
sed -i.bak "s|\${SERVER_NAME:-matrix.localhost}|$SERVER_NAME|g" "$SCRIPT_DIR/config/dendrite.yaml"
sed -i.bak "s|\${REGISTRATION_SECRET}|$REGISTRATION_SECRET|g" "$SCRIPT_DIR/config/dendrite.yaml"
rm -f "$SCRIPT_DIR/config/dendrite.yaml.bak"

# Update Element config
sed -i.bak "s|\${SERVER_NAME:-matrix.localhost}|$SERVER_NAME|g" "$SCRIPT_DIR/element-config.json"
sed -i.bak "s|\${MATRIX_URL:-https://matrix.localhost}|https://$SERVER_NAME|g" "$SCRIPT_DIR/element-config.json"
rm -f "$SCRIPT_DIR/element-config.json.bak"

echo "✓ Configuration updated"

# Start services
echo ""
echo "Starting Dendrite..."
docker compose up -d

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Dendrite API:  http://localhost:8008"
echo "Element Web:   http://localhost:8009"
echo ""
echo "To create an admin user:"
echo "  docker exec dendrite /usr/bin/create-account -config /etc/dendrite/dendrite.yaml -username admin -admin"
echo ""
echo "Registration secret (for bots): $REGISTRATION_SECRET"
echo ""
echo "Next steps:"
echo "1. Configure nginx reverse proxy (see nginx.conf)"
echo "2. Set up SSL certificates"
echo "3. Create bot account for document signing"
