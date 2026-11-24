#!/usr/bin/env bash
# Start the disposable store, wait for the PrestaShop auto-installer, then install Mega Builder.
# Usage: PS_IMAGE=prestashop/prestashop:9.2-apache tests/e2e/setup-store.sh
set -euo pipefail

cd "$(dirname "$0")"
COMPOSE=(docker compose -f docker-compose.yml)

"${COMPOSE[@]}" up -d

echo "Waiting for PrestaShop to finish installing (this takes a few minutes)..."
for i in $(seq 1 120); do
  code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/ || true)
  if [ "$code" = "200" ]; then
    echo "Storefront answered 200 after ~$((i * 5))s"
    break
  fi
  if [ "$i" = "120" ]; then
    echo "PrestaShop did not come up in 10 minutes" >&2
    "${COMPOSE[@]}" logs --tail=200 prestashop >&2
    exit 1
  fi
  sleep 5
done

# Install as www-data so cache/log files stay writable by Apache.
"${COMPOSE[@]}" exec -T -u www-data prestashop php bin/console prestashop:module install megabuilder
"${COMPOSE[@]}" exec -T -u www-data prestashop php bin/console prestashop:module list --no-interaction 2>/dev/null | grep -i megabuilder || true

# The bind-mounted module folder belongs to the CI user; let Apache write media uploads.
"${COMPOSE[@]}" exec -T prestashop sh -c 'mkdir -p modules/megabuilder/views/img/uploads && chmod -R a+rwX modules/megabuilder/views/img/uploads'

echo "Store ready: http://localhost:8080 (back office: /admin-dev)"
