<?php

function app_config($key = null)
{
    global $appConfig;

    if ($key === null) {
        return $appConfig;
    }

    $value = $appConfig;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    return $value;
}

function base_url($path = '')
{
    $basePath = app_config('base_path');
    $path = '/' . ltrim($path, '/');

    return $basePath . ($path === '/' ? '/' : $path);
}

function asset_url($path)
{
    $path = '/' . ltrim((string) $path, '/');
    $file = dirname(__DIR__, 2) . '/public' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return base_url($path) . '?v=' . rawurlencode($version);
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function utf8_length($value)
{
    $matches = array();
    $result = preg_match_all('/./us', (string) $value, $matches);

    return $result === false ? strlen((string) $value) : $result;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid($token)
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function generate_captcha()
{
    $left = random_int(2, 9);
    $right = random_int(1, 9);
    $_SESSION['captcha_answer'] = (string) ($left + $right);
    $_SESSION['captcha_question'] = $left . ' + ' . $right . ' = ?';
}

function captcha_question()
{
    if (empty($_SESSION['captcha_question']) || empty($_SESSION['captcha_answer'])) {
        generate_captcha();
    }

    return $_SESSION['captcha_question'];
}

function captcha_is_valid($answer)
{
    return isset($_SESSION['captcha_answer'])
        && hash_equals($_SESSION['captcha_answer'], trim((string) $answer));
}

function request_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function redirect_to($path)
{
    header('Location: ' . base_url($path), true, 303);
    exit;
}

function request_is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

function flash_set($type, $message)
{
    $_SESSION['flash_message'] = array(
        'type' => $type,
        'message' => $message,
    );
}

function flash_take()
{
    if (empty($_SESSION['flash_message']) || !is_array($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return $flash;
}

function current_admin()
{
    $key = app_config('admin_session_key');

    return isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : null;
}

function admin_is_authenticated()
{
    $admin = current_admin();

    return $admin !== null && !empty($admin['id']) && isset($admin['username']);
}

function admin_login_session(array $admin)
{
    session_regenerate_id(true);
    $_SESSION[app_config('admin_session_key')] = array(
        'id' => (int) $admin['id'],
        'username' => (string) $admin['username'],
        'user_type' => (string) $admin['user_type'],
    );
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function admin_logout_session()
{
    unset($_SESSION[app_config('admin_session_key')]);
    unset($_SESSION['csrf_token']);
    session_regenerate_id(true);
}

function require_admin()
{
    if (!admin_is_authenticated()) {
        flash_set('error', '请先登录留言管理后台。');
        redirect_to('/admin/login.php');
    }
}

function query_value($source, $key, $default = '')
{
    return isset($source[$key]) && !is_array($source[$key])
        ? trim((string) $source[$key])
        : $default;
}

function valid_date_value($value)
{
    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function admin_filter_params($source)
{
    $allowedAudit = array('all', 'pending', 'approved', 'rejected');
    $allowedReply = array('all', 'unreplied', 'draft', 'replied');
    $allowedDisplay = array('all', 'visible', 'hidden');
    $allowedDeleted = array('active', 'deleted', 'all');
    $audit = query_value($source, 'audit', 'all');
    $reply = query_value($source, 'reply', 'all');
    $display = query_value($source, 'display', 'all');
    $deleted = query_value($source, 'deleted', 'active');
    $perPage = (int) query_value($source, 'per_page', '20');

    return array(
        'keyword' => query_value($source, 'keyword'),
        'audit' => in_array($audit, $allowedAudit, true) ? $audit : 'all',
        'reply' => in_array($reply, $allowedReply, true) ? $reply : 'all',
        'display' => in_array($display, $allowedDisplay, true) ? $display : 'all',
        'deleted' => in_array($deleted, $allowedDeleted, true) ? $deleted : 'active',
        'date_from' => valid_date_value(query_value($source, 'date_from')),
        'date_to' => valid_date_value(query_value($source, 'date_to')),
        'page' => max(1, (int) query_value($source, 'page', '1')),
        'per_page' => in_array($perPage, array(10, 20, 50), true) ? $perPage : 20,
    );
}

function admin_list_url(array $filters, array $changes = array())
{
    $params = array_merge($filters, $changes);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || ($key !== 'page' && $value === 'all')) {
            unset($params[$key]);
        }
    }

    return base_url('/admin/?' . http_build_query($params));
}

function admin_return_filters($value)
{
    $parsed = array();
    if (is_string($value) && strlen($value) <= 1500) {
        parse_str($value, $parsed);
    }

    return admin_filter_params($parsed);
}

function pagination_items($currentPage, $totalPages)
{
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $items = array(1);
    $start = max(2, $currentPage - 1);
    $end = min($totalPages - 1, $currentPage + 1);
    if ($start > 2) {
        $items[] = 'ellipsis';
    }
    for ($page = $start; $page <= $end; $page++) {
        $items[] = $page;
    }
    if ($end < $totalPages - 1) {
        $items[] = 'ellipsis';
    }
    $items[] = $totalPages;

    return $items;
}

function form_content_type_is_valid()
{
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';

    return strpos($contentType, 'application/x-www-form-urlencoded') === 0
        || strpos($contentType, 'multipart/form-data') === 0;
}
