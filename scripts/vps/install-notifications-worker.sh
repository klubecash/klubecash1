#!/bin/sh

set -eu

if [ "$(id -u)" -ne 0 ]; then
  echo "Execute este instalador como root." >&2
  exit 1
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
install -d -m 0755 /opt/klubecash
install -m 0755 "$SCRIPT_DIR/klubecash-notifications-worker.sh" /opt/klubecash/klubecash-notifications-worker.sh
install -m 0644 "$SCRIPT_DIR/klubecash-notifications-worker.service" /etc/systemd/system/klubecash-notifications-worker.service

if [ ! -f /etc/klubecash-worker.env ]; then
  install -m 0600 /dev/null /etc/klubecash-worker.env
  printf '%s\n' 'KLUBECASH_SITE_URL=https://www.klubecash.com' 'CRON_SECRET=SUBSTITUA_PELO_SEGREDO_DA_VERCEL' > /etc/klubecash-worker.env
  echo "Preencha /etc/klubecash-worker.env antes de iniciar o servico." >&2
  exit 2
fi

systemctl daemon-reload
systemctl enable --now klubecash-notifications-worker.service
systemctl --no-pager --full status klubecash-notifications-worker.service
