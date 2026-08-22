#!/bin/sh

set -eu

: "${KLUBECASH_SITE_URL:=https://www.klubecash.com}"
: "${CRON_SECRET:?CRON_SECRET precisa estar definido em /etc/klubecash-worker.env}"

while true; do
  curl --fail --silent --show-error \
    --max-time 50 \
    --header "Authorization: Bearer ${CRON_SECRET}" \
    "${KLUBECASH_SITE_URL%/}/api/internal/notifications?limit=50" \
    >/dev/null 2>&1 || true
  sleep 60
done
