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

