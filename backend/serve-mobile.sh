#!/usr/bin/env bash
# API locale adaptée au mobile (photos / FormData / JSON).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
TMP="$ROOT/storage/app/tmp"
PORT="${PORT:-8000}"
mkdir -p "$TMP"

if lsof -nP -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "⚠  Port $PORT déjà utilisé. Processus :"
  lsof -nP -iTCP:"$PORT" -sTCP:LISTEN || true
  echo
  echo "Libérez-le puis relancez, ex. :"
  echo "  kill -9 \$(lsof -tiTCP:$PORT -sTCP:LISTEN)"
  echo "  ./serve-mobile.sh"
  echo
  echo "Ou autre port :"
  echo "  PORT=8002 ./serve-mobile.sh"
  exit 1
fi

echo "→ SIS API mobile"
echo "  post_max_size=32M  upload_tmp_dir=$TMP"
echo "  http://0.0.0.0:${PORT}  (LAN : http://<votre-ip>:${PORT})"

exec php \
  -d "post_max_size=32M" \
  -d "upload_max_filesize=32M" \
  -d "memory_limit=256M" \
  -d "upload_tmp_dir=${TMP}" \
  "$ROOT/artisan" serve --host=0.0.0.0 --port="$PORT"
