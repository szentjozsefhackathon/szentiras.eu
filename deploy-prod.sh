#!/bin/bash

# Exit on error
set -e

# Load deployment configuration
if [ -f .env.deploy ]; then
    source .env.deploy
else
    echo "Error: .env.deploy not found. Create it with deployment configuration."
    exit 1
fi

# Default values if not set in .env.deploy
DEPLOY_SERVER=${DEPLOY_SERVER:-szentiras.eu}
DEPLOY_PORT=${DEPLOY_PORT:-22}
DEPLOY_USER=${DEPLOY_USER:-deploy}
DEPLOY_REMOTE_PATH=${DEPLOY_REMOTE_PATH:-/tmp/}
SSH_KEY_PATH=${SSH_KEY_PATH:-~/.ssh/deploy}

# Parse command line arguments
EXEC=0
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--exec)
            EXEC=1
            shift
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [-e|--exec]"
            exit 1
            ;;
    esac
done

# Get git short hash for filename
if git rev-parse --git-dir > /dev/null 2>&1; then
    GIT_SHORT_HASH=$(git rev-parse --short HEAD)
else
    GIT_SHORT_HASH="unknown"
    echo "Warning: Not in a git repository. Using 'unknown' for hash."
fi

# Local file paths
DIR=dist
FILE_NAME="szeu-app-prod-$GIT_SHORT_HASH.tar.gz"
LOCAL_FILE_PATH="$DIR/$FILE_NAME"

# Check if the image file exists locally
if [ ! -f "$LOCAL_FILE_PATH" ]; then
    echo "Error: Docker image file not found: $LOCAL_FILE_PATH"
    echo "Run ./build-prod-image.sh first to build and upload the image."
    exit 1
fi

echo "=== Deployment to $DEPLOY_SERVER:$DEPLOY_PORT ==="
echo "Git commit: $GIT_SHORT_HASH"
echo "Local file: $LOCAL_FILE_PATH"
echo "Remote path: $DEPLOY_REMOTE_PATH"
echo

# Build SSH command with optional key
SSH_CMD="ssh -p $DEPLOY_PORT"
if [ -f "$SSH_KEY_PATH" ]; then
    SSH_CMD="$SSH_CMD -i $SSH_KEY_PATH"
fi
SSH_TARGET="$DEPLOY_USER@$DEPLOY_SERVER"

# 1. Check if remote directory exists, create if needed
echo "1. Checking remote directory..."
$SSH_CMD "$SSH_TARGET" "mkdir -p $DEPLOY_REMOTE_PATH"

if [ $? -ne 0 ]; then
    echo "❌ Failed to create remote directory!"
    exit 1
fi
echo "   ✅ Remote directory ready: $DEPLOY_REMOTE_PATH"

# 2. Upload the Docker image (only if not already present with same size)
echo "2. Uploading Docker image..."
SCP_CMD="scp -P $DEPLOY_PORT"
if [ -f "$SSH_KEY_PATH" ]; then
    SCP_CMD="$SCP_CMD -i $SSH_KEY_PATH"
fi

# Get local file size
LOCAL_FILE_SIZE=$(stat -f%z "$LOCAL_FILE_PATH" 2>/dev/null || stat -c%s "$LOCAL_FILE_PATH" 2>/dev/null)

# Check if remote file exists and compare sizes
REMOTE_FILE_PATH="$DEPLOY_REMOTE_PATH/$FILE_NAME"
REMOTE_FILE_SIZE=$($SSH_CMD "$SSH_TARGET" "if [ -f $REMOTE_FILE_PATH ]; then stat -f%z $REMOTE_FILE_PATH 2>/dev/null || stat -c%s $REMOTE_FILE_PATH 2>/dev/null; else echo '0'; fi")

if [ "$LOCAL_FILE_SIZE" = "$REMOTE_FILE_SIZE" ] && [ "$REMOTE_FILE_SIZE" != "0" ]; then
    echo "   ℹ️  File already uploaded (same name and size: $REMOTE_FILE_SIZE bytes)"
    echo "   Skipping upload..."
else
    echo "   Uploading: $LOCAL_FILE_PATH ($LOCAL_FILE_SIZE bytes)"
    $SCP_CMD "$LOCAL_FILE_PATH" "$SSH_TARGET:$DEPLOY_REMOTE_PATH"

    if [ $? -ne 0 ]; then
        echo "❌ Upload failed!"
        exit 1
    fi
    echo "   ✅ Upload successful"
fi

# 3. Load Docker image on server
echo "3. Loading Docker image on server..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker load -i $FILE_NAME"

if [ $? -ne 0 ]; then
    echo "❌ Docker load failed!"
    exit 1
fi
echo "   ✅ Docker image loaded"

# 4. Check for .env.prod on server
echo "4. Checking for environment configuration..."
ENV_CHECK=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && if [ -f .env.prod ]; then echo 'exists'; else echo 'missing'; fi")

if [ "$ENV_CHECK" = "missing" ]; then
    echo "   ⚠️  Warning: .env.prod not found on server at $DEPLOY_REMOTE_PATH"
    echo "   The deployment will proceed, but ensure .env.prod exists for proper configuration."
    echo "   You can create it manually or upload it with:"
    echo "     scp -P $DEPLOY_PORT .env.prod $SSH_TARGET:$DEPLOY_REMOTE_PATH/"
fi

# 5. Check for docker-compose.prod.yml on server
echo "5. Ensuring docker-compose.prod.yml is up to date on server..."
COMPOSE_CHECK=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && if [ -f docker-compose.prod.yml ]; then echo 'exists'; else echo 'missing'; fi")

if [ "$COMPOSE_CHECK" = "missing" ]; then
    echo "   Uploading docker-compose.prod.yml..."
    $SCP_CMD "docker-compose.prod.yml" "$SSH_TARGET:$DEPLOY_REMOTE_PATH/"
    echo "   ✅ docker-compose.prod.yml uploaded"
else
    # Compare local and remote checksums
    LOCAL_CHECKSUM=$(md5sum docker-compose.prod.yml | awk '{print $1}')
    REMOTE_CHECKSUM=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && md5sum docker-compose.prod.yml | awk '{print \$1}'")
    
    if [ "$LOCAL_CHECKSUM" != "$REMOTE_CHECKSUM" ]; then
        echo "   ⚠️  Local docker-compose.prod.yml differs from remote version"
        echo "   Local checksum:  $LOCAL_CHECKSUM"
        echo "   Remote checksum: $REMOTE_CHECKSUM"
        echo
        read -p "   Upload and use local version? (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            echo "   Uploading docker-compose.prod.yml..."
            $SCP_CMD "docker-compose.prod.yml" "$SSH_TARGET:$DEPLOY_REMOTE_PATH/"
            echo "   ✅ docker-compose.prod.yml uploaded"
        else
            echo "   ❌ Deployment aborted: docker-compose.prod.yml mismatch"
            exit 1
        fi
    else
        echo "   ✅ docker-compose.prod.yml is up to date"
    fi
fi

# 6. Restart app services (keep traefik running to minimise downtime)
echo "6. Restarting app services (traefik kept running)..."

# Put application into maintenance mode before fiddling with the services to prevent errors for users during the transition. If app container isn't running, just continue with the deploy.
echo "   Putting application into maintenance mode..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec -T app php artisan down 2>/dev/null || echo '   ℹ️  App container not running or already in maintenance mode'"

# Bring up infrastructure services first (database, redis, sphinx) if not already running
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && APP_DOMAIN=szentiras.eu docker compose -f docker-compose.prod.yml --env-file .env.prod up -d database redis sphinx memcached"

# Stop only the app-layer containers so traefik keeps serving (returns 502 briefly, not 404)
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml stop horizon app 2>/dev/null || true"
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml rm -f horizon app 2>/dev/null || true"

# Run migrations before starting app-layer services
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && APP_DOMAIN=szentiras.eu docker compose -f docker-compose.prod.yml --env-file .env.prod run --rm --no-deps migrator"

if [ $? -ne 0 ]; then
    echo "❌ Migrations failed! Aborting deployment."
    exit 1
fi
echo "   ✅ Migrations complete"

# Start horizon; app depends_on horizon being healthy before it accepts traffic
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && APP_DOMAIN=szentiras.eu docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --no-deps horizon"

echo "   Waiting for horizon to become healthy..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && timeout 120 bash -c 'until docker compose -f docker-compose.prod.yml ps horizon | grep -q \"healthy\"; do sleep 3; done'" || {
    echo "   ⚠️  Horizon did not become healthy within 120s — check logs"
}

$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && APP_DOMAIN=szentiras.eu docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --no-deps app"

if [ $? -ne 0 ]; then
    echo "❌ Docker compose up failed!"
    echo "   Check logs with: ssh -p $DEPLOY_PORT $SSH_TARGET 'cd $DEPLOY_REMOTE_PATH && docker compose logs'"
    exit 1
fi

# Remove orphaned containers from previous deploys (but not traefik)
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && APP_DOMAIN=szentiras.eu docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --remove-orphans 2>/dev/null || true"

echo "   ✅ Containers started successfully"

# Bring application out of maintenance mode
echo "   Bringing application out of maintenance mode..."
for i in {1..10}; do
    if $SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec -T app php artisan up 2>/dev/null"; then
        echo "   ✅ Maintenance mode disabled"
        break
    else
        if [ $i -eq 10 ]; then
            echo "   ⚠️  Failed to disable maintenance mode after 10 attempts"
        else
            sleep 3
        fi
    fi
done

# 7. Generate the static sitemap so Apache can serve public/sitemap.xml directly (no PHP/DB per request)
echo "7. Generating sitemap..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec -T app php artisan szentiras:generate-sitemap && docker compose -f docker-compose.prod.yml exec -T app ln -sf public/sitemap.xml public/sitemap-copy.xml" \
    && echo "   ✅ Sitemap generated" \
    || echo "   ⚠️  Sitemap generation failed — the app will fall back to building it on demand"

# 8. Purge the Cloudflare CDN so cached full-page HTML (menus, layout, chrome)
# reflects the newly deployed code. Non-fatal: a stale edge cache must not abort
# a deployment. Skipped automatically if Cloudflare is not configured.
echo "8. Purging CDN cache..."
$SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec -T app php artisan cdn:purge" \
    && echo "   ✅ CDN cache purged" \
    || echo "   ⚠️  CDN purge failed — pages may serve stale chrome until the cache expires or is purged manually"

# 9. Verify services are running
echo "9. Verifying services..."
sleep 5
SERVICES=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml ps --services")
echo "   Running services:"
for service in $SERVICES; do
    STATUS=$($SSH_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml ps $service --format '{{.Status}}'")
    echo "   - $service: $STATUS"
done

if [ $EXEC -eq 1 ]; then
    echo
    echo "=== Executing into container app ==="
    SSH_TTY_CMD="$SSH_CMD -t"
    $SSH_TTY_CMD "$SSH_TARGET" "cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml exec app bash"
fi

echo
echo "=== Deployment Complete ==="
echo "✅ Application deployed successfully to $DEPLOY_SERVER"
echo
echo "Next steps:"
echo "1. Check application logs:"
echo "   ssh -p $DEPLOY_PORT $SSH_TARGET 'cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml logs -f'"
echo
echo "2. Monitor container status:"
echo "   ssh -p $DEPLOY_PORT $SSH_TARGET 'cd $DEPLOY_REMOTE_PATH && docker compose -f docker-compose.prod.yml ps'"
echo
echo "3. View Traefik dashboard (if enabled):"
echo "   http://$DEPLOY_SERVER:8080"
echo
echo "4. Clean up old images (optional):"
echo "   ssh -p $DEPLOY_PORT $SSH_TARGET 'docker image prune -f'"
echo
echo "The application should be available at: https://$DEPLOY_SERVER"