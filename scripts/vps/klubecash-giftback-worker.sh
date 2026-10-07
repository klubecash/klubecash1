#!/bin/sh
set -eu
: "${KLUBECASH_SITE_URL:=https://www.klubecash.com}"
: "${CRON_SECRET:?CRON_SECRET precisa estar configurado}"
while true; do
  curl --fail --silent --show-error --max-time 50 \
    --header "Authorization: Bearer ${CRON_SECRET}" \
    "${KLUBECASH_SITE_URL%/}/api/internal/giftback-expiration?limit=100" || true
  sleep 60
done
