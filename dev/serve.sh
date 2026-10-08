#!/bin/bash
# Run the legacy site locally against the local MySQL copy (overrides the production values in .env).
cd "$(dirname "$0")/.."
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=fadpaydikarangex DB_USER=ikarange DB_PASS=ikarange_local
export APP_URL=http://localhost:8080/ SESSION_SECURE=false
# Development-only key for the mobile API (QR tokens, vault encryption). Production uses its own MOBILE_SECRET in .env.
export MOBILE_SECRET=dev-only-mobile-secret-0123456789abcdef0123456789abcdef
exec php -S localhost:8080 -t legacy/ikarange/public dev/router.php
