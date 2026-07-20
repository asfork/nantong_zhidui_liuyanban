<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

if (!request_is_post() || !form_content_type_is_valid() || !csrf_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    http_response_code(400);
    exit('退出请求无效，请返回后重试。');
}

$admin = current_admin();
try {
    $repository = new AdminMessageRepository(Connection::make(app_config('db')));
    $repository->writeLoginLog((int) $admin['id'], 'admin_logout', request_ip());
} catch (Throwable $error) {
    // 退出不应因日志写入失败而阻塞。
}

admin_logout_session();
flash_set('success', '您已安全退出留言管理后台。');
redirect_to('/admin/login.php');
