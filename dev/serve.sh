#!/bin/bash
# Run the legacy site locally against the local MySQL copy (overrides the production values in .env).
cd "$(dirname "$0")/.."
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=fadpaydikarangex DB_USER=ikarange DB_PASS=ikarange_local
export APP_URL=http://localhost:8080/ SESSION_SECURE=false
exec php -S localhost:8080 -t legacy/ikarange/public dev/router.php
