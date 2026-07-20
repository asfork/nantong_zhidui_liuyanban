<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (admin_is_authenticated()) {
    redirect_to('/admin/');
}

$errors = array();
$username = '';
$flash = flash_take();

if (request_is_post()) {
    $username = query_value($_POST, 'username');
    $password = isset($_POST['password']) && !is_array($_POST['password']) ? (string) $_POST['password'] : '';
    $now = time();
    $window = (int) app_config('login_limit.window_seconds');
    $maxAttempts = (int) app_config('login_limit.max_attempts');
    $attempts = isset($_SESSION['admin_login_attempts']) && is_array($_SESSION['admin_login_attempts'])
        ? $_SESSION['admin_login_attempts']
        : array();
    $attempts = array_values(array_filter($attempts, function ($timestamp) use ($now, $window) {
        return is_int($timestamp) && $timestamp > $now - $window;
    }));

    if (!form_content_type_is_valid()) {
        $errors[] = '请求格式不正确，请刷新页面后重试。';
    } elseif (!csrf_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        $errors[] = '页面已过期，请刷新后重新登录。';
    } elseif (count($attempts) >= $maxAttempts) {
        $errors[] = '登录尝试过于频繁，请稍后再试。';
    } elseif ($username === '' || utf8_length($username) > 64 || $password === '' || strlen($password) > 200) {
        $errors[] = '用户名或密码不正确。';
    } else {
        try {
            $pdo = Connection::make(app_config('db'));
            $auth = new AdminAuth($pdo, app_config('admin'));
            $admin = $auth->attempt($username, $password);
            $repository = new AdminMessageRepository($pdo);
            if ($admin) {
                unset($_SESSION['admin_login_attempts']);
                admin_login_session($admin);
                $repository->writeLoginLog((int) $admin['id'], 'admin_login_success', request_ip());
                flash_set('success', '登录成功，欢迎进入留言管理后台。');
                redirect_to('/admin/');
            }

            $attempts[] = $now;
            $_SESSION['admin_login_attempts'] = $attempts;
            $repository->writeLoginLog(0, 'admin_login_failed', request_ip());
            $errors[] = '用户名或密码不正确。';
        } catch (Throwable $error) {
            $errors[] = app_config('debug') ? $error->getMessage() : '登录服务暂时不可用，请稍后重试。';
        }
    }
}

require dirname(__DIR__, 2) . '/app/View/admin/login.php';
