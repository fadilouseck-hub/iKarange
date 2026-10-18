#!/bin/bash
# Deploys I'KARANGE website (back-office) changes to the server over SSH/SFTP.
#
#   dev/deploy-website.sh user@host [remote_site_root]
#
# Run it yourself: SSH asks for the password/key here, in your terminal. Nothing secret is stored or printed.
# Safety:
#  - every server file about to be replaced must match a version committed in git; if one was edited on the server
#    outside git, nothing is uploaded and the script stops (so a server-only change is never overwritten silently);
#  - the files are backed up on the server before upload, and syntax-checked after;
#  - smoke test: login page, new page (redirects to login) and its API (401 without a session).
set -euo pipefail

TARGET="${1:?usage: dev/deploy-website.sh user@host [remote_site_root]}"
REMOTE_DIR="${2:-/home/modeling/ikarange}"
SITE_URL="${SITE_URL:-https://ikarange.mcedge.sn}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
LOCAL="$REPO/legacy/ikarange"
STAMP="$(date +%Y%m%d-%H%M%S)"

# Files of the website changes, relative to the site root.
FILES=(
  src/router.php
  src/bootstrap.php
  src/core/AccountDeletion.php
  src/pages/gestion-acces.php
  src/pages/applications-mobiles.php
  src/api/applications-mobiles/_guard.php
  src/api/applications-mobiles/feedback.php
  src/api/applications-mobiles/souscriptions.php
  src/api/applications-mobiles/suppressions.php
  database/process_deletion_request.php
)

if [ -n "$(git -C "$REPO" status --porcelain -- "${FILES[@]/#/legacy/ikarange/}")" ]; then
  echo "✗ Commit the website files first (uncommitted changes in the files to deploy)."; exit 1
fi

CONTROL="/tmp/ikarange-web-deploy-$$"
SSH=(ssh -o ControlMaster=auto -o ControlPath="$CONTROL" -o ControlPersist=120)
trap 'ssh -o ControlPath="$CONTROL" -O exit "$TARGET" >/dev/null 2>&1 || true' EXIT

echo "→ Checking $TARGET:$REMOTE_DIR"
"${SSH[@]}" "$TARGET" "test -f '$REMOTE_DIR/src/router.php' && test -f '$REMOTE_DIR/.env' && php -v | head -1" \
  || { echo "✗ $REMOTE_DIR does not look like the I'KARANGE site root (pass it as the 2nd argument)"; exit 1; }

echo "→ Checking that the server files match git (no server-only edits)"
remote_hashes="$("${SSH[@]}" "$TARGET" "cd '$REMOTE_DIR' && for f in ${FILES[*]}; do if [ -f \"\$f\" ]; then echo \"\$(sha256sum \"\$f\" | cut -d' ' -f1) \$f\"; fi; done")"
drift=0
while read -r hash file; do
  [ -z "$file" ] && continue
  known=0
  for commit in $(git -C "$REPO" log --format=%H -- "legacy/ikarange/$file"); do
    if [ "$(git -C "$REPO" show "$commit:legacy/ikarange/$file" 2>/dev/null | shasum -a 256 | cut -d' ' -f1)" = "$hash" ]; then
      known=1; break
    fi
  done
  if [ "$known" = 0 ]; then echo "  ✗ $file was changed on the server outside git"; drift=1; fi
done <<< "$remote_hashes"
if [ "$drift" = 1 ]; then
  echo "✗ Nothing uploaded. Download those files, commit them, then deploy again."; exit 1
fi

echo "→ Backing up files that will change"
"${SSH[@]}" "$TARGET" "cd '$REMOTE_DIR' && mkdir -p backups && tar czf backups/pre-website-$STAMP.tgz \$(for f in ${FILES[*]}; do test -f \"\$f\" && echo \"\$f\"; done)"

echo "→ Uploading"
"${SSH[@]}" "$TARGET" "cd '$REMOTE_DIR' && mkdir -p src/core src/api/applications-mobiles database"
for f in "${FILES[@]}"; do
  scp -o ControlPath="$CONTROL" -q "$LOCAL/$f" "$TARGET:$REMOTE_DIR/$f"
done

echo "→ Syntax check"
"${SSH[@]}" "$TARGET" "cd '$REMOTE_DIR' && for f in ${FILES[*]}; do php -l \"\$f\" >/dev/null || { echo \"✗ \$f\"; exit 1; }; done"

echo "→ Smoke test"
login=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/login")
page=$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/applications-mobiles")
api=$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$SITE_URL/api/applications-mobiles/feedback")
echo "  GET /login → $login · GET /applications-mobiles → $page · GET /api/applications-mobiles/feedback → $api"
if [ "$login" = "200" ] && [ "$page" = "302" ] && [ "$api" = "401" ]; then
  echo "✓ Website updated. Backup: $REMOTE_DIR/backups/pre-website-$STAMP.tgz"
else
  echo "✗ Unexpected answer. Restore with: tar xzf backups/pre-website-$STAMP.tgz (in $REMOTE_DIR)"
  exit 1
fi
