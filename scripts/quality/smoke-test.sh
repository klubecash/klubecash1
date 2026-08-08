#!/usr/bin/env bash

set -euo pipefail

base_url="${BASE_URL:-https://www.klubecash.com}"
failures=0

check_status() {
  local path="$1"
  local expected="$2"
  local actual
  actual="$(curl --silent --show-error --output /dev/null --max-time 40 --write-out '%{http_code}' "${base_url}${path}")"

  if [[ "$actual" != "$expected" ]]; then
    echo "FALHA ${path}: esperado ${expected}, recebido ${actual}" >&2
    failures=$((failures + 1))
  else
    echo "OK ${path}: ${actual}"
  fi
}

check_status '/' '200'
check_status '/login' '200'
check_status '/registro' '200'
check_status '/assets/images/logo.png' '200'
check_status '/api/health' '200'
check_status '/cliente/dashboard' '302'
check_status '/store/dashboard' '302'
check_status '/admin/dashboard' '302'
check_status '/config/database.php' '404'

if [[ "$failures" -ne 0 ]]; then
  echo "Smoke test falhou em ${failures} verificação(ões)." >&2
  exit 1
fi

echo "OK: smoke test concluído em ${base_url}."
