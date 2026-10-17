#!/bin/bash
# Deploys the Assur Plus mobile API to the I'KARANGE server over SSH/SFTP.
#
#   dev/deploy-mobile-api.sh user@host [remote_site_root]
#
# Run it yourself: SSH asks for the password/key here, in your terminal. Nothing secret is stored or printed.
# It uploads the API files, migrations and the apps' public legal pages, runs the additive installer (new tables,
# router hooks, MOBILE_SECRET), and smoke-tests the API and the pages.
set -euo pipefail

TARGET="${1:?usage: dev/deploy-mobile-api.sh user@host [remote_site_root]}"
REMOTE_DIR="${2:-/home/modeling/ikarange}"
SITE_URL="${SITE_URL:-https://ikarange.mcedge.sn}"
LOCAL="$(cd "$(dirname "$0")/../legacy/ikarange" && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"

# One connection for every step (one password prompt).
CONTROL="/tmp/ikarange-deploy-$$"
SSH=(ssh -o ControlMaster=auto -o ControlPath="$CONTROL" -o ControlPersist=120)
trap 'ssh -o ControlPath="$CONTROL" -O exit "$TARGET" >/dev/null 2>&1 || true' EXIT

echo "→ Checking $TARGET:$REMOTE_DIR"
"${SSH[@]}" "$TARGET" "test -f '$REMOTE_DIR/src/router.php' && test -f '$REMOTE_DIR/.env' && php -v | head -1" \
  || { echo "✗ $REMOTE_DIR does not look like the I'KARANGE site root (pass it as the 2nd argument)"; exit 1; }

echo "→ Backing up files that will change"
"${SSH[@]}" "$TARGET" "cd '$REMOTE_DIR' && mkdir -p backups && tar czf backups/pre-mobile-api-$STAMP.tgz src/router.php \$(test -d src/api/mobile && echo src/api/mobile)"

echo "→ Uploading"
"${SSH[@]}" "$TARGET" "mkdir -p '$REMOTE_DIR/src/api/mobile' '$REMOTE_DIR/src/pages/public'"
scp -o ControlPath="$CONTROL" -q "$LOCAL/src/api/mobile/index.php" "$LOCAL/src/api/mobile/assistant.php" "$LOCAL/src/api/mobile/subscription.php" "$TARGET:$REMOTE_DIR/src/api/mobile/"
scp -o ControlPath="$CONTROL" -q "$LOCAL/database/008_mobile_api.sql" "$LOCAL/database/009_feedback_assistant.sql" "$LOCAL/database/010_online_subscription.sql" "$LOCAL/database/install_mobile_api.php" "$LOCAL/database/create_subscription_tester.php" "$LOCAL/database/process_deletion_request.php" "$TARGET:$REMOTE_DIR/database/"
scp -o ControlPath="$CONTROL" -q "$LOCAL/src/pages/public/legal.php" "$LOCAL/src/pages/public/privacy.php" "$LOCAL/src/pages/public/account-deletion.php" "$TARGET:$REMOTE_DIR/src/pages/public/"
# Operator legal details: uploaded only if the server has none yet (the owner fills it on the server or here).
"${SSH[@]}" "$TARGET" "test -f '$REMOTE_DIR/config/public_legal.php'" || scp -o ControlPath="$CONTROL" -q "$LOCAL/config/public_legal.php" "$TARGET:$REMOTE_DIR/config/"

echo "→ Installing (tables, router hook, secret)"
"${SSH[@]}" "$TARGET" "cd '$REMOTE_DIR' && php -l src/api/mobile/index.php && php -l src/api/mobile/assistant.php && php -l src/api/mobile/subscription.php && php -l src/pages/public/privacy.php && php -l src/pages/public/account-deletion.php && php database/install_mobile_api.php && php -l src/router.php"

echo "→ Smoke test"
code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/api/mobile/v1/me")
body=$(curl -s "$SITE_URL/api/mobile/v1/me")
echo "  GET /api/mobile/v1/me → $code $body"
if [ "$code" = "401" ] && echo "$body" | grep -q '"unauthorized"'; then
  echo "✓ Mobile API is live. Backup: $REMOTE_DIR/backups/pre-mobile-api-$STAMP.tgz"
  for page in confidentialite suppression-compte; do
    echo "  GET /assurplus/$page → $(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/assurplus/$page")"
  done
else
  echo "✗ Unexpected answer. Restore with: tar xzf backups/pre-mobile-api-$STAMP.tgz (in $REMOTE_DIR)"
  exit 1
fi
