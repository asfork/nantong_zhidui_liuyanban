#!/bin/sh

set -eu

if [ "$#" -lt 1 ]; then
    printf 'Usage: %s DEPLOYMENT_ZIP [RUN_DIRECTORY]\n' "$0" >&2
    exit 2
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
ZIP_PATH=$(CDPATH= cd -- "$(dirname -- "$1")" && pwd)/$(basename -- "$1")
RUN_ID=$(date '+%Y%m%d%H%M%S')
RUN_DIR=${2:-"$PROJECT_DIR/artifacts/deployment-rehearsal/$RUN_ID"}
IMPORT_DIR="$RUN_DIR/imported"
REPORT_FILE="$RUN_DIR/rehearsal-report.txt"
COOKIE_FILE="$RUN_DIR/cookies.txt"
LOGIN_HTML="$RUN_DIR/login.html"
ADMIN_HTML="$RUN_DIR/admin.html"
HEADERS_FILE="$RUN_DIR/headers.txt"
JSON_BODY="$RUN_DIR/json-body.txt"
JSON_HEADERS="$RUN_DIR/json-headers.txt"
HTTP_PORT=${REHEARSAL_HTTP_PORT:-18089}
PROJECT_NAME="liuyanban_rehearsal_$RUN_ID"
COMPOSE_FILE="$SCRIPT_DIR/rehearsal/docker-compose.yml"

mkdir -p "$IMPORT_DIR"
unzip -q "$ZIP_PATH" -d "$IMPORT_DIR"

APP_DIR="$IMPORT_DIR/liuyanban"
if [ ! -f "$APP_DIR/MANIFEST.sha256" ]; then
    printf 'Deployment package is missing MANIFEST.sha256.\n' >&2
    exit 1
fi

(
    cd "$APP_DIR"
    shasum -a 256 -c MANIFEST.sha256
) > "$RUN_DIR/manifest-check.txt"

export REHEARSAL_APP_DIR="$APP_DIR"
export REHEARSAL_HTTP_PORT="$HTTP_PORT"
export COMPOSE_PROJECT_NAME="$PROJECT_NAME"

docker compose -f "$COMPOSE_FILE" up -d --build

attempt=0
stable_checks=0
while [ "$stable_checks" -lt 3 ]; do
    if docker compose -f "$COMPOSE_FILE" exec -T mysql mysql \
        -uliuyanban_rehearsal -prehearsal-app-password zhidui_nantong \
        -N -e "SELECT 1" > /dev/null 2>&1; then
        stable_checks=$((stable_checks + 1))
    else
        stable_checks=0
    fi
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        docker compose -f "$COMPOSE_FILE" ps >&2
        exit 1
    fi
    sleep 1
done

attempt=0
while :; do
    if curl -fsS "http://127.0.0.1:$HTTP_PORT/liuyanban/" > "$RUN_DIR/public.html" \
        && ! grep -q '留言服务暂时不可用' "$RUN_DIR/public.html"; then
        break
    fi
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        docker compose -f "$COMPOSE_FILE" ps >&2
        exit 1
    fi
    sleep 1
done

{
    printf 'Deployment rehearsal\n'
    printf 'Run ID: %s\n' "$RUN_ID"
    printf 'Package: %s\n' "$ZIP_PATH"
    printf 'Imported app: %s\n' "$APP_DIR"
    printf 'Docker project: %s\n' "$PROJECT_NAME"
    printf 'Public URL: http://127.0.0.1:%s/liuyanban/\n' "$HTTP_PORT"
    printf 'Admin URL: http://127.0.0.1:%s/liuyanban/admin/\n' "$HTTP_PORT"
    printf '\nRuntime versions\n'
    docker compose -f "$COMPOSE_FILE" exec -T apache httpd -v
    docker compose -f "$COMPOSE_FILE" exec -T php php -v | head -n 2
    docker compose -f "$COMPOSE_FILE" exec -T mysql mysql --version
    printf '\nDeployment verification\n'
    docker compose -f "$COMPOSE_FILE" exec -T --user www-data php php deploy/verify.php
    printf '\nDatabase state\n'
    docker compose -f "$COMPOSE_FILE" exec -T mysql mysql --default-character-set=utf8mb4 \
        -uroot -prehearsal-root-password zhidui_nantong -N \
        -e "SELECT COUNT(*) AS admin_count FROM admin; SHOW TABLES LIKE 'liuyan_%'; SHOW GRANTS FOR 'liuyanban_rehearsal'@'%';"
} > "$REPORT_FILE"

curl -fsS -c "$COOKIE_FILE" -b "$COOKIE_FILE" -o "$LOGIN_HTML" "http://127.0.0.1:$HTTP_PORT/liuyanban/admin/login.php"
CSRF_TOKEN=$(perl -ne 'print "$1\n" if /name="csrf_token" value="([^"]+)"/' "$LOGIN_HTML" | head -n 1)
curl -fsS -L -c "$COOKIE_FILE" -b "$COOKIE_FILE" -o "$ADMIN_HTML" \
    --data-urlencode "csrf_token=$CSRF_TOKEN" \
    --data-urlencode "username=admin" \
    --data-urlencode "password=password" \
    "http://127.0.0.1:$HTTP_PORT/liuyanban/admin/login.php"

grep -q '网上匿名留言' "$RUN_DIR/public.html"
grep -q '匿名留言管理' "$ADMIN_HTML"

for qa_url_path in /liuyanban/ /liuyanban/assets/css/app.css /liuyanban/assets/js/app.js; do
    qa_http_code=$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:$HTTP_PORT$qa_url_path")
    if [ "$qa_http_code" != '200' ]; then
        printf 'Unexpected HTTP status %s for %s.\n' "$qa_http_code" "$qa_url_path" >&2
        exit 1
    fi
done

curl -sS -D "$HEADERS_FILE" -o /dev/null "http://127.0.0.1:$HTTP_PORT/liuyanban/"
grep -qi '^X-Content-Type-Options: nosniff' "$HEADERS_FILE"
grep -qi '^X-Frame-Options: SAMEORIGIN' "$HEADERS_FILE"
grep -qi '^Content-Security-Policy:' "$HEADERS_FILE"
grep -qi 'Set-Cookie: LIUYANBANSESSID=.*path=/liuyanban/.*HttpOnly.*SameSite=Lax' "$HEADERS_FILE"

curl -sS -D "$JSON_HEADERS" -o "$JSON_BODY" -H 'Content-Type: application/json' \
    --data '{"title":"not-created"}' "http://127.0.0.1:$HTTP_PORT/liuyanban/index.php"
JSON_STATUS=$(awk 'NR==1 {print $2}' "$JSON_HEADERS")
if [ "$JSON_STATUS" != '415' ]; then
    printf 'Expected JSON POST status 415, got %s.\n' "$JSON_STATUS" >&2
    exit 1
fi
grep -q '仅支持表单提交。' "$JSON_BODY"

BUSINESS_WRITE_RESULT=$(docker compose -f "$COMPOSE_FILE" exec -T mysql mysql \
    -uliuyanban_rehearsal -prehearsal-app-password zhidui_nantong -N \
    -e "START TRANSACTION; INSERT INTO liuyan_message (title,content,audit_status,display_status,source_ip,created_at,updated_at) VALUES ('QA privilege test','rollback','pending','visible','127.0.0.1',NOW(),NOW()); SELECT COUNT(*) FROM liuyan_message WHERE title='QA privilege test'; ROLLBACK; SELECT COUNT(*) FROM liuyan_message WHERE title='QA privilege test';" \
    2>/dev/null)
if [ "$BUSINESS_WRITE_RESULT" != "1
0" ]; then
    printf 'Business table transaction permission check failed.\n' >&2
    exit 1
fi

if docker compose -f "$COMPOSE_FILE" exec -T mysql mysql \
    -uliuyanban_rehearsal -prehearsal-app-password zhidui_nantong \
    -e "UPDATE admin SET username=username WHERE id=1" > /dev/null 2>&1; then
    printf 'Application account unexpectedly updated admin.\n' >&2
    exit 1
fi

if docker compose -f "$COMPOSE_FILE" logs --tail=200 apache php \
    | grep -Eqi 'permission denied|fatal error|uncaught'; then
    printf 'Runtime logs contain fatal or permission errors.\n' >&2
    exit 1
fi

{
    printf '\nHTTP verification\n'
    printf '[PASS] Public page loaded under /liuyanban/\n'
    printf '[PASS] Rehearsal legacy administrator logged in\n'
    printf '[PASS] Deployment package manifest verified\n'
    printf '[PASS] Static assets loaded under /liuyanban/\n'
    printf '[PASS] Security headers and isolated Session Cookie verified\n'
    printf '[PASS] JSON POST rejected with HTTP 415\n'
    printf '[PASS] Application account can write business tables\n'
    printf '[PASS] Application account cannot update admin\n'
    printf '[PASS] Apache/PHP logs contain no fatal or permission errors\n'
    printf '\nEnvironment remains running for inspection.\n'
    printf 'Stop: COMPOSE_PROJECT_NAME=%s REHEARSAL_APP_DIR=%s REHEARSAL_HTTP_PORT=%s docker compose -f %s down\n' "$PROJECT_NAME" "$APP_DIR" "$HTTP_PORT" "$COMPOSE_FILE"
    printf 'Remove including rehearsal database: COMPOSE_PROJECT_NAME=%s REHEARSAL_APP_DIR=%s REHEARSAL_HTTP_PORT=%s docker compose -f %s down -v\n' "$PROJECT_NAME" "$APP_DIR" "$HTTP_PORT" "$COMPOSE_FILE"
} >> "$REPORT_FILE"

rm -f "$COOKIE_FILE" "$LOGIN_HTML" "$ADMIN_HTML" "$RUN_DIR/public.html" \
    "$HEADERS_FILE" "$JSON_BODY" "$JSON_HEADERS"

printf '%s\n' "$REPORT_FILE"
