#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
status=0

while IFS= read -r -d '' php_file; do
  if ! php -l "$php_file" >/dev/null; then
    status=1
  fi
done < <(find "$project_root" -type f -name '*.php' \
  -not -path '*/node_modules/*' \
  -not -path '*/vendor/*' \
  -not -path '*/.git/*' -print0)

if [[ "$status" -ne 0 ]]; then
  echo 'Falha: pelo menos um arquivo PHP contém erro de sintaxe.' >&2
  exit "$status"
fi

echo 'OK: todos os arquivos PHP passaram na validação de sintaxe.'
