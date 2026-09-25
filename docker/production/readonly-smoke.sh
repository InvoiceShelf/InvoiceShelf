#!/bin/bash
# Boots the production image the way a hosting provider runs it: read-only
# root filesystem, no .env, every capability dropped, managed mode, installed
# headlessly. Only storage/ (a volume) and a few tmpfs mounts are writable.
#
#   docker/production/readonly-smoke.sh <image>
#
# CI runs it on every change to docker/production; it also runs locally.

set -euo pipefail

image="${1:?usage: $0 <image>}"
name=invoiceshelf-readonly-smoke
port="${SMOKE_PORT:-8089}"
base="http://127.0.0.1:$port"
password="smoke-$(openssl rand -hex 12)"

cleanup() {
    docker rm -f "$name" >/dev/null 2>&1 || true
    docker volume rm -f "$name-storage" >/dev/null 2>&1 || true
}

fail() {
    echo "FAIL: $1"
    docker logs "$name" 2>&1 | tail -n 80 || true
    exit 1
}

trap cleanup EXIT
cleanup

# The base image renders its nginx config from templates when it boots, which
# a read-only root cannot take, and nginx opens its compiled-in error log
# before reading that config. An image built for a read-only root renders the
# config at build time and sends that log to stderr, as this one does.
docker build --quiet --tag "$name" - >/dev/null <<EOF
FROM $image
USER root
RUN S6_INITIALIZED=true /etc/entrypoint.d/10-init-webserver-config.sh \\
    && ln -sf /dev/stderr /var/log/nginx/error.log
USER www-data
EOF

docker run --detach --name "$name" \
    --read-only \
    --tmpfs /run:rw,exec,nosuid,nodev,size=16m,uid=82,gid=82 \
    --tmpfs /tmp:rw,nosuid,nodev,size=64m \
    --tmpfs /var/cache/nginx:rw,nosuid,nodev,size=16m,uid=82,gid=82 \
    --tmpfs /var/www/html/bootstrap/cache:rw,nosuid,nodev,size=16m,uid=82,gid=82 \
    --cap-drop ALL \
    --security-opt no-new-privileges \
    --volume "$name-storage:/var/www/html/storage" \
    --env APP_NAME=InvoiceShelf \
    --env APP_KEY="base64:$(openssl rand -base64 32)" \
    --env APP_URL="$base" \
    --env INVOICESHELF_DOTENV=false \
    --env INVOICESHELF_MANAGED=true \
    --env S6_READ_ONLY_ROOT=1 \
    --env SCHEDULER_ENABLED=false \
    --env DB_JOURNAL_MODE=wal \
    --env DB_SYNCHRONOUS=normal \
    --env MAIL_MAILER=log \
    --publish "127.0.0.1:$port:8080" \
    "$name" >/dev/null

for _ in $(seq 1 60); do
    curl -fsS -o /dev/null "$base/up" 2>/dev/null && break
    sleep 2
done
curl -fsS -o /dev/null "$base/up" || fail "/up never answered"
echo "ok: booted"

docker exec "$name" php artisan invoiceshelf:install \
    --admin-email=smoke@example.com \
    --admin-password="$password" \
    --company="Smoke Test" \
    --currency=EUR \
    --timezone=UTC \
    --language=en \
    --no-interaction >/dev/null || fail "headless install"
echo "ok: installed"

curl -fsS -o /dev/null "$base/login" || fail "/login"
echo "ok: login page"

token=$(curl -fsS -X POST "$base/api/v1/auth/login" \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d "{\"username\":\"smoke@example.com\",\"password\":\"$password\",\"device_name\":\"smoke\"}" \
    | sed -n 's/.*"token":"\([^"]*\)".*/\1/p') || fail "token login"
[ -n "$token" ] || fail "token login returned no token"
echo "ok: signed in"

curl -fsS -o /dev/null "$base/api/v1/bootstrap" \
    -H 'Accept: application/json' -H "Authorization: Bearer $token" -H 'company: 1' \
    || fail "/api/v1/bootstrap"
echo "ok: bootstrap"

# A provider-owned setting answers 403 on a managed install.
status=$(curl -s -o /dev/null -w '%{http_code}' "$base/api/v1/disks" \
    -H 'Accept: application/json' -H "Authorization: Bearer $token" -H 'company: 1')
[ "$status" = 403 ] || fail "/api/v1/disks answered $status, expected 403 in managed mode"
echo "ok: managed mode"

docker exec "$name" php artisan invoiceshelf:catch-up --force >/dev/null || fail "catch-up"
echo "ok: daily sweeps"

if docker logs "$name" 2>&1 | grep -i 'read-only file system'; then
    fail "something tried to write outside the writable mounts"
fi
echo "PASS"
