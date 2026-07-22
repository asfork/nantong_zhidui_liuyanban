#!/bin/sh

set -eu

BASE_URL="${1:-http://127.0.0.1:8088/liuyanban}"
QA_ADMIN_USERNAME="${QA_ADMIN_USERNAME:-admin}"
QA_ADMIN_PASSWORD="${QA_ADMIN_PASSWORD:-password}"
QA_EXPECTED_PUBLIC_REPLIED="${QA_EXPECTED_PUBLIC_REPLIED:-11}"
QA_EXPECTED_PUBLIC_WAITING="${QA_EXPECTED_PUBLIC_WAITING:-14}"
QA_TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$QA_TMP_DIR"' EXIT HUP INT TERM

PASS_COUNT=0
FAIL_COUNT=0

pass_test() {
    PASS_COUNT=$((PASS_COUNT + 1))
    printf '[PASS] %s\n' "$1"
}

fail_test() {
    FAIL_COUNT=$((FAIL_COUNT + 1))
    printf '[FAIL] %s\n' "$1"
}

expect_equal() {
    if [ "$1" = "$2" ]; then
        pass_test "$3"
    else
        fail_test "$3 (expected=$2 actual=$1)"
    fi
}

expect_contains() {
    if grep -Fq "$2" "$1"; then
        pass_test "$3"
    else
        fail_test "$3"
    fi
}

expect_not_contains() {
    if grep -Fq "$2" "$1"; then
        fail_test "$3"
    else
        pass_test "$3"
    fi
}

PUBLIC_HEADERS="$QA_TMP_DIR/public-headers.txt"
PUBLIC_BODY="$QA_TMP_DIR/public-body.html"
PUBLIC_CODE="$(curl -sS -D "$PUBLIC_HEADERS" -o "$PUBLIC_BODY" -w '%{http_code}' "$BASE_URL/")"
expect_equal "$PUBLIC_CODE" "200" "公开留言板返回 HTTP 200"
expect_contains "$PUBLIC_HEADERS" "X-Content-Type-Options: nosniff" "公开页启用 nosniff"
expect_contains "$PUBLIC_HEADERS" "X-Frame-Options: SAMEORIGIN" "公开页限制跨站框架嵌入"
expect_contains "$PUBLIC_HEADERS" "Referrer-Policy: same-origin" "公开页限制 Referer"
expect_contains "$PUBLIC_HEADERS" "Content-Security-Policy: default-src 'self'" "公开页发送 CSP"

PUBLIC_ALL="$QA_TMP_DIR/public-all-pages.html"
: > "$PUBLIC_ALL"
for PAGE in 1 2 3; do
    curl -fsS "$BASE_URL/?page=$PAGE" >> "$PUBLIC_ALL"
done

expect_contains "$PUBLIC_ALL" "QA150-017 已通过" "审核通过、显示、未删除留言公开展示"
expect_not_contains "$PUBLIC_ALL" "QA150-001 待审核" "待审核留言不公开"
expect_not_contains "$PUBLIC_ALL" "QA150-018 已通过" "回收站留言不公开"
expect_not_contains "$PUBLIC_ALL" "QA150-019 已通过" "隐藏留言不公开"
expect_not_contains "$PUBLIC_ALL" "QA150-033 已驳回" "已驳回留言不公开"
expect_not_contains "$PUBLIC_ALL" "QA 草稿回复 021" "回复草稿不公开"
expect_contains "$PUBLIC_ALL" "QA 已发布回复 025" "已发布回复公开展示"
expect_contains "$PUBLIC_ALL" "QA 已发布回复 029" "存在新草稿时继续公开上一版已发布回复"
expect_not_contains "$PUBLIC_ALL" "QA 新版草稿 029" "已发布后的新草稿不会提前公开"
expect_contains "$PUBLIC_ALL" "<details class=\"reply-panel\" open>" "已回复留言默认展开"
expect_not_contains "$PUBLIC_ALL" "<script>alert(\"qa\")</script>" "留言中的 script 标签不会原样输出"
expect_contains "$PUBLIC_ALL" "&lt;script&gt;alert(&quot;qa&quot;)&lt;/script&gt;" "留言中的危险 HTML 已按上下文转义"
expect_contains "$PUBLIC_ALL" "𠮷😀" "公开页正确输出 utf8mb4 四字节字符"
expect_contains "$PUBLIC_ALL" "第二行用于验证换行展示。" "多行中文留言内容完整输出"

REPLIED_BODY="$QA_TMP_DIR/replied.html"
WAITING_BODY="$QA_TMP_DIR/waiting.html"
curl -fsS "$BASE_URL/?status=replied" -o "$REPLIED_BODY"
curl -fsS "$BASE_URL/?status=waiting" -o "$WAITING_BODY"
expect_contains "$REPLIED_BODY" "已回复 <span>($QA_EXPECTED_PUBLIC_REPLIED)</span>" "公开已回复筛选数量与数据库一致"
expect_contains "$WAITING_BODY" "待回复 <span>($QA_EXPECTED_PUBLIC_WAITING)</span>" "公开待回复筛选数量与数据库一致"

ASSET_CODE="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/assets/images/reply-chevron-down.png")"
expect_equal "$ASSET_CODE" "200" "回复箭头静态资源可访问"

JSON_CODE="$(curl -sS -o "$QA_TMP_DIR/json-response.txt" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '{}' "$BASE_URL/")"
expect_equal "$JSON_CODE" "415" "公开提交拒绝 JSON Content-Type"

UNAUTH_HEADERS="$QA_TMP_DIR/unauth-admin-headers.txt"
UNAUTH_CODE="$(curl -sS -D "$UNAUTH_HEADERS" -o /dev/null -w '%{http_code}' "$BASE_URL/admin/")"
expect_equal "$UNAUTH_CODE" "303" "未登录访问管理页使用 See Other 重定向"
expect_contains "$UNAUTH_HEADERS" "/liuyanban/admin/login.php" "未登录管理页跳转到独立登录页"

COOKIE_JAR="$QA_TMP_DIR/admin-cookies.txt"
LOGIN_HEADERS="$QA_TMP_DIR/login-headers.txt"
LOGIN_BODY="$QA_TMP_DIR/login.html"
LOGIN_GET_CODE="$(curl -sS -c "$COOKIE_JAR" -D "$LOGIN_HEADERS" -o "$LOGIN_BODY" -w '%{http_code}' "$BASE_URL/admin/login.php")"
expect_equal "$LOGIN_GET_CODE" "200" "管理员登录页返回 HTTP 200"
expect_contains "$LOGIN_HEADERS" "LIUYANBANSESSID=" "管理员使用独立 Session Cookie"
expect_contains "$LOGIN_HEADERS" "path=/liuyanban/" "Session Cookie Path 限制在 /liuyanban/"
expect_contains "$LOGIN_HEADERS" "HttpOnly" "Session Cookie 启用 HttpOnly"
expect_contains "$LOGIN_HEADERS" "SameSite=Lax" "Session Cookie 启用 SameSite=Lax"

CSRF_TOKEN="$(sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' "$LOGIN_BODY" | sed -n '1p')"
if [ -n "$CSRF_TOKEN" ]; then
    pass_test "登录页包含 CSRF Token"
else
    fail_test "登录页包含 CSRF Token"
fi

LOGIN_POST_HEADERS="$QA_TMP_DIR/login-post-headers.txt"
LOGIN_POST_CODE="$(curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -D "$LOGIN_POST_HEADERS" -o /dev/null -w '%{http_code}' \
    -X POST \
    --data-urlencode "csrf_token=$CSRF_TOKEN" \
    --data-urlencode "username=$QA_ADMIN_USERNAME" \
    --data-urlencode "password=$QA_ADMIN_PASSWORD" \
    "$BASE_URL/admin/login.php")"
expect_equal "$LOGIN_POST_CODE" "303" "测试管理员可登录后台"
expect_contains "$LOGIN_POST_HEADERS" "/liuyanban/admin/" "登录成功跳转管理页"

ADMIN_BODY="$QA_TMP_DIR/admin.html"
ADMIN_CODE="$(curl -sS -b "$COOKIE_JAR" -o "$ADMIN_BODY" -w '%{http_code}' "$BASE_URL/admin/?keyword=QA150-&deleted=all&per_page=50")"
expect_equal "$ADMIN_CODE" "200" "登录后管理页返回 HTTP 200"
expect_contains "$ADMIN_BODY" "共 150 条" "管理页展示 150 条测试留言总数"
expect_contains "$ADMIN_BODY" "QA150-150" "管理页按时间倒序显示最新测试留言"
expect_contains "$ADMIN_BODY" "待审核" "管理页展示待审核状态"
expect_contains "$ADMIN_BODY" "回复草稿" "管理页展示回复草稿状态"
expect_contains "$ADMIN_BODY" "回收站" "管理页展示回收站状态"

ACTION_GET_CODE="$(curl -sS -b "$COOKIE_JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/admin/action.php")"
expect_equal "$ACTION_GET_CODE" "405" "登录后以 GET 调用管理写接口被拒绝"

BAD_CSRF_HEADERS="$QA_TMP_DIR/bad-csrf-headers.txt"
BAD_CSRF_CODE="$(curl -sS -b "$COOKIE_JAR" -D "$BAD_CSRF_HEADERS" -o /dev/null -w '%{http_code}' \
    -X POST \
    --data-urlencode "csrf_token=invalid" \
    --data-urlencode "message_id=1" \
    --data-urlencode "action=hide" \
    "$BASE_URL/admin/action.php")"
expect_equal "$BAD_CSRF_CODE" "303" "错误 CSRF Token 的管理写请求被中止并重定向"
expect_contains "$BAD_CSRF_HEADERS" "/liuyanban/admin/" "CSRF 失败返回管理页"

printf '\nHTTP prelaunch result: %s (%s passed, %s failed)\n' \
    "$(if [ "$FAIL_COUNT" -eq 0 ]; then printf PASS; else printf FAIL; fi)" \
    "$PASS_COUNT" "$FAIL_COUNT"

if [ "$FAIL_COUNT" -ne 0 ]; then
    exit 1
fi
