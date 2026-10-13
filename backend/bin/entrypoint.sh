#!/bin/sh
set -eu
attempt=1
until php bin/console.php migrate; do
  if [ "$attempt" -ge 30 ]; then
    echo "Banco de dados indisponível após 30 tentativas." >&2
    exit 1
  fi
  attempt=$((attempt + 1))
  sleep 2
done
if [ "${DEMO_SEED:-false}" = "true" ]; then php bin/console.php seed; fi
exec php bin/hyperf.php start
