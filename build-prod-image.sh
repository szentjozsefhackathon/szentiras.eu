#!/bin/bash

if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️  Warning: You have uncommitted changes. Build may not be reproducible."
fi

# Exit on error
set -e

# Load deployment configuration
if [ -f .env.deploy ]; then
    source .env.deploy
else
    echo "Warning: .env.deploy not found. Using default values or interactive authentication."
fi

# Default values if not set in .env.deploy
DEPLOY_SERVER=${DEPLOY_SERVER:-szentiras.eu}
DEPLOY_PORT=${DEPLOY_PORT:-22}
DEPLOY_USER=${DEPLOY_USER:-deploy}
DEPLOY_REMOTE_PATH=${DEPLOY_REMOTE_PATH:-/tmp/}
SSH_KEY_PATH=${SSH_KEY_PATH:-~/.ssh/deploy}

if git rev-parse --git-dir > /dev/null 2>&1; then
    GIT_SHORT_HASH=$(git rev-parse --short HEAD)
    GIT_FULL_HASH=$(git rev-parse HEAD)
else
    GIT_SHORT_HASH="unknown"
    GIT_FULL_HASH="unknown"
    echo "Warning: Not in a git repository. Using 'unknown' for hash."
fi

DIR=dist
mkdir -p $DIR/deploy/production

FILE_PATH=$DIR/szeu-app-prod-$GIT_SHORT_HASH.tar.gz
echo "Build target: $FILE_PATH"
echo "Git commit: $GIT_SHORT_HASH ($GIT_FULL_HASH)"
echo

# Check if image already exists for this commit
if [ -f "$FILE_PATH" ]; then
    echo "✅ Image already built for commit $GIT_SHORT_HASH."
    echo "Skipping Docker build."
else
    echo "🔄 Building Docker image for commit $GIT_SHORT_HASH..."
    
    # Build with git hash as build argument
    APP_DOMAIN=szentiras.eu GIT_COMMIT_HASH=$GIT_FULL_HASH docker compose -f docker-compose.prod.yml build --pull app
    docker save szeu-app-prod:latest | gzip > "$FILE_PATH"
    
    echo "✅ Docker image saved to $FILE_PATH"
fi

echo
FILE_SIZE=$(stat -c%s "$FILE_PATH" 2>/dev/null || echo "0")
echo "File size: $(numfmt --to=iec $FILE_SIZE)"
echo

# Upload to remote server
echo "Uploading to $DEPLOY_SERVER:$DEPLOY_PORT..."
echo "Remote path: $DEPLOY_REMOTE_PATH"

if [ -f "$SSH_KEY_PATH" ]; then
    SCP_CMD="scp -P $DEPLOY_PORT -i $SSH_KEY_PATH $FILE_PATH $DEPLOY_USER@$DEPLOY_SERVER:$DEPLOY_REMOTE_PATH"
else
    SCP_CMD="scp -P $DEPLOY_PORT $FILE_PATH $DEPLOY_USER@$DEPLOY_SERVER:$DEPLOY_REMOTE_PATH"
fi

echo "Executing: $SCP_CMD"
$SCP_CMD

if [ $? -eq 0 ]; then
    echo "✅ Upload successful!"
    echo "File uploaded to: $DEPLOY_SERVER:$DEPLOY_REMOTE_PATH$(basename $FILE_PATH)"
else
    echo "❌ Upload failed with exit code: $?"
    exit 1
fi

ls -t dist/szeu-app-prod-*.tar.gz | tail -n +6 | xargs rm -f 2>/dev/null || true

