#!/bin/bash

# SSH Deploy Key Setup Script
# This script helps configure SSH key-based authentication for deployments

set -e

echo "=== SSH Deploy Key Setup ==="
echo

# Check if SSH key already exists
SSH_KEY_PATH="$HOME/.ssh/deploy"

if [ -f "$SSH_KEY_PATH" ]; then
    echo "✅ SSH key already exists at $SSH_KEY_PATH"
else
    echo "Generating SSH key..."
    mkdir -p "$HOME/.ssh"
    ssh-keygen -t ed25519 -f "$SSH_KEY_PATH" -N "" -C "deploy@$(hostname)"
    chmod 600 "$SSH_KEY_PATH"
    chmod 644 "$SSH_KEY_PATH.pub"
    echo "✅ SSH key generated at $SSH_KEY_PATH"
fi

echo
echo "Public key content (add this to your server's ~/.ssh/authorized_keys):"
echo "---"
cat "$SSH_KEY_PATH.pub"
echo "---"
echo

# Create .env.deploy if it doesn't exist
if [ ! -f ".env.deploy" ]; then
    echo "Creating .env.deploy configuration file..."
    cat > .env.deploy << 'EOF'
# Deployment Configuration
DEPLOY_SERVER=szentiras.eu
DEPLOY_PORT=22
DEPLOY_USER=deploy
DEPLOY_REMOTE_PATH=/tmp/
SSH_KEY_PATH=~/.ssh/deploy
AWS_PROFILE=
VERSE_ANALYSIS_BUCKET=
EOF
    echo "✅ Created .env.deploy"
    echo
    echo "⚠️  Please update .env.deploy with your actual deployment details:"
    cat .env.deploy
else
    echo "✅ .env.deploy already exists"
fi

echo
echo "=== Next Steps ==="
echo "1. Copy the public key above and add it to your server:"
echo "   ssh deploy@szentiras.eu 'mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys'"
echo "   (Then paste the public key content)"
echo
echo "2. Test the connection:"
echo "   ssh -i $SSH_KEY_PATH -p 22 deploy@szentiras.eu 'echo SSH works!'"
echo
echo "3. Once verified, you can run deployments without password prompts:"
echo "   ./deploy-prod.sh"
echo
