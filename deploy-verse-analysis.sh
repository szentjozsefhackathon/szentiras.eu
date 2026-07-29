#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_CONFIG_FILE="${DEPLOY_CONFIG_FILE:-$SCRIPT_DIRECTORY/.env.deploy}"

if [[ ! -f "$DEPLOY_CONFIG_FILE" ]]; then
    echo "Error: deployment configuration not found: $DEPLOY_CONFIG_FILE"
    exit 1
fi

source "$DEPLOY_CONFIG_FILE"

if [[ -z "${AWS_PROFILE:-}" ]]; then
    echo "Error: $DEPLOY_CONFIG_FILE must define AWS_PROFILE."
    exit 1
fi

if [[ -z "${VERSE_ANALYSIS_BUCKET:-}" ]]; then
    echo "Error: $DEPLOY_CONFIG_FILE must define VERSE_ANALYSIS_BUCKET."
    exit 1
fi

DEPLOY_SERVER="${DEPLOY_SERVER:-szentiras.eu}"
DEPLOY_PORT="${DEPLOY_PORT:-22}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_REMOTE_PATH="${DEPLOY_REMOTE_PATH:-/tmp/}"
SSH_KEY_PATH="${SSH_KEY_PATH:-$HOME/.ssh/deploy}"
VERSE_ANALYSIS_SOURCE_DIRECTORY="${VERSE_ANALYSIS_SOURCE_DIRECTORY:-$SCRIPT_DIRECTORY/storage/app/private/greek/verse-analysis/OpenGNT/hu/v1}"
VERSE_ANALYSIS_S3_PREFIX="greek/verse-analysis/OpenGNT/hu/v1"

if ! command -v aws > /dev/null 2>&1; then
    echo "Error: the AWS CLI is not installed or is not on PATH."
    exit 1
fi

if ! command -v ssh > /dev/null 2>&1; then
    echo "Error: ssh is not installed or is not on PATH."
    exit 1
fi

if [[ ! -d "$VERSE_ANALYSIS_SOURCE_DIRECTORY" ]]; then
    echo "Error: verse-analysis directory not found: $VERSE_ANALYSIS_SOURCE_DIRECTORY"
    exit 1
fi

if [[ -z "$(find "$VERSE_ANALYSIS_SOURCE_DIRECTORY" -maxdepth 1 -type f -name '*.json' -print -quit)" ]]; then
    echo "Error: no verse-analysis JSON files found in $VERSE_ANALYSIS_SOURCE_DIRECTORY."
    exit 1
fi

SSH_COMMAND=(ssh -p "$DEPLOY_PORT")
if [[ -f "$SSH_KEY_PATH" ]]; then
    SSH_COMMAND+=(-i "$SSH_KEY_PATH")
fi

SSH_TARGET="$DEPLOY_USER@$DEPLOY_SERVER"
printf -v QUOTED_REMOTE_PATH '%q' "$DEPLOY_REMOTE_PATH"
REMOTE_IMPORT_COMMAND="docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan szentiras:import-verse-analysis --disk=s3 --no-interaction"

echo "=== Deploy Greek verse analysis ==="
echo "Source : $VERSE_ANALYSIS_SOURCE_DIRECTORY"
echo "Target : s3://$VERSE_ANALYSIS_BUCKET/$VERSE_ANALYSIS_S3_PREFIX/"
echo "Server : $DEPLOY_SERVER:$DEPLOY_PORT"
echo

echo "1. Syncing verse-analysis files to S3..."
aws --profile "$AWS_PROFILE" s3 sync \
    "${VERSE_ANALYSIS_SOURCE_DIRECTORY%/}/" \
    "s3://$VERSE_ANALYSIS_BUCKET/$VERSE_ANALYSIS_S3_PREFIX/"

echo
echo "2. Validating the uploaded files on production..."
"${SSH_COMMAND[@]}" "$SSH_TARGET" \
    "cd $QUOTED_REMOTE_PATH && $REMOTE_IMPORT_COMMAND --dry-run"

echo
echo "3. Importing the uploaded files on production..."
"${SSH_COMMAND[@]}" "$SSH_TARGET" \
    "cd $QUOTED_REMOTE_PATH && $REMOTE_IMPORT_COMMAND"

echo
echo "=== Verse analysis deployed successfully ==="
