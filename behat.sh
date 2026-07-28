#!/usr/bin/env bash
# Run Behat tests in Docker with APP_ENV=test.
# PHP-FPM is temporarily restarted with APP_ENV=test, then restored to APP_ENV=dev.
#
# Usage: ./behat.sh [behat arguments...]
# Example: ./behat.sh features/shop/applying_gift_card.feature
#          ./behat.sh features/shop/applying_gift_card.feature:13

set -e

COMPOSE_FILES="-f compose.yml -f compose.override.yml -f compose.test.yml"

echo "→ Restarting PHP-FPM with APP_ENV=test..."
docker compose $COMPOSE_FILES up -d --no-deps --wait php

echo "→ Ensuring Chrome and nginx are running..."
docker compose up -d --wait chrome chrome-proxy
docker compose exec nginx nginx -s reload
sleep 1

echo "→ Running Behat..."
docker compose $COMPOSE_FILES exec php vendor/bin/behat "$@"
BEHAT_EXIT=$?

echo "→ Restoring PHP-FPM with APP_ENV=dev..."
docker compose up -d --no-deps --wait php
docker compose exec nginx nginx -s reload

exit $BEHAT_EXIT
