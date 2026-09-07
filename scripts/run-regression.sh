#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
api_port="${BANANA_REGRESSION_API_PORT:-8010}"
web_port="${BANANA_REGRESSION_WEB_PORT:-5174}"
regression_db="${BANANA_REGRESSION_DB:-/tmp/banana-chat-regression.sqlite}"
artifact_dir="$repo_root/artifacts/regression"
api_pid=''
web_pid=''

cleanup() {
  if [[ -n "$web_pid" ]]; then kill "$web_pid" 2>/dev/null || true; fi
  if [[ -n "$api_pid" ]]; then kill "$api_pid" 2>/dev/null || true; fi
}
trap cleanup EXIT INT TERM

mkdir -p "$artifact_dir"
: > "$regression_db"

export DB_CONNECTION=sqlite
export DB_DATABASE="$regression_db"
export CACHE_STORE=array
export SESSION_DRIVER=file
export QUEUE_CONNECTION=sync
export BROADCAST_CONNECTION=log
export FILESYSTEM_DISK=local
export EMQX_APP_ID=''
export EMQX_APP_SECRET=''
export EMQX_ENDPOINT_REST=''

cd "$repo_root/apps/api"
php artisan migrate:fresh --seed --force > "$artifact_dir/seed.log"
php artisan serve --host=127.0.0.1 --port="$api_port" > "$artifact_dir/api.log" 2>&1 &
api_pid=$!

cd "$repo_root"
BANANA_API_PROXY="http://127.0.0.1:$api_port" corepack pnpm --filter @banana/web exec vite --host 127.0.0.1 --port "$web_port" > "$artifact_dir/web.log" 2>&1 &
web_pid=$!

for _ in {1..40}; do
  if curl --silent --fail "http://127.0.0.1:$api_port/api/v1/health" >/dev/null && curl --silent --fail "http://127.0.0.1:$web_port" >/dev/null; then break; fi
  sleep 0.25
done
curl --silent --fail "http://127.0.0.1:$api_port/api/v1/health" >/dev/null
curl --silent --fail "http://127.0.0.1:$web_port" >/dev/null

PLAYWRIGHT_BASE_URL="http://127.0.0.1:$web_port" \
PLAYWRIGHT_API_BASE_URL="http://127.0.0.1:$api_port" \
corepack pnpm test:regression:playwright
