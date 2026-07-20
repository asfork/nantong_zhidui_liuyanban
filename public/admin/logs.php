<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$flash = flash_take();
$databaseError = null;
$page = max(1, (int) query_value($_GET, 'page', '1'));
$logs = array('items' => array(), 'page' => 1, 'total' => 0, 'total_pages' => 1);
try {
    $repository = new AdminMessageRepository(Connection::make(app_config('db')));
    $logs = $repository->recentLogs($page, 30);
} catch (Throwable $error) {
    $databaseError = app_config('debug') ? $error->getMessage() : '操作日志暂时无法读取。';
}

$adminPageTitle = '操作日志';
$adminActiveNav = 'logs';
require dirname(__DIR__, 2) . '/app/View/admin/logs.php';
