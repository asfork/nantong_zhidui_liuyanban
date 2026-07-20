<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$filters = admin_filter_params($_GET);
$flash = flash_take();
$databaseError = null;
$messages = array('items' => array(), 'page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1);
$selectedMessage = null;
$replyHistory = array();
$messageLogs = array();

try {
    $repository = new AdminMessageRepository(Connection::make(app_config('db')));
    $messages = $repository->messages($filters);
    $selectedId = max(0, (int) query_value($_GET, 'selected', '0'));
    if ($selectedId === 0 && !empty($messages['items'])) {
        $selectedId = (int) $messages['items'][0]['id'];
    }
    if ($selectedId > 0) {
        $selectedMessage = $repository->findMessage($selectedId);
        if ($selectedMessage) {
            $replyHistory = $repository->replyHistory($selectedId);
            $messageLogs = $repository->messageLogs($selectedId);
        }
    }
} catch (Throwable $error) {
    $databaseError = app_config('debug') ? $error->getMessage() : '管理服务暂时不可用，请稍后重试。';
}

$returnQuery = http_build_query($filters);
$adminPageTitle = '匿名留言管理';
$adminActiveNav = 'messages';
require dirname(__DIR__, 2) . '/app/View/admin/management.php';
