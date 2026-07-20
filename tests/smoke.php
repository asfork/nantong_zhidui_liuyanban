<?php

require dirname(__DIR__) . '/app/bootstrap.php';

function smoke_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

$pdo = Connection::make(app_config('db'));
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
smoke_assert(strpos($version, '5.7.26') === 0, 'MySQL 版本为 5.7.26');

$columns = $pdo->query("SHOW COLUMNS FROM liuyan_reply WHERE Field IN ('status', 'published_at')")->fetchAll();
smoke_assert(count($columns) === 2, '回复草稿和发布时间字段已迁移');

$publicRepository = new MessageRepository($pdo);
$publicMessages = $publicRepository->publicMessages('all', 1, 100);
$stateStatement = $pdo->prepare('SELECT audit_status, display_status, deleted_at FROM liuyan_message WHERE id = :id');
$publicStatesAreValid = true;
foreach ($publicMessages['items'] as $message) {
    $stateStatement->execute(array(':id' => (int) $message['id']));
    $state = $stateStatement->fetch();
    if (!$state || $state['audit_status'] !== 'approved' || $state['display_status'] !== 'visible' || $state['deleted_at'] !== null) {
        $publicStatesAreValid = false;
        break;
    }
}
smoke_assert($publicStatesAreValid, '公开留言查询隔离待审核、隐藏和已删除内容');

$auth = new AdminAuth($pdo, app_config('admin'));
$admin = $auth->attempt('admin', 'password');
smoke_assert($admin !== null && (int) $admin['id'] > 0, '开发管理员 bcrypt 登录可用');

$filters = admin_filter_params(array());
$repository = new AdminMessageRepository($pdo);
$messages = $repository->messages($filters);
smoke_assert(isset($messages['items'], $messages['total'], $messages['total_pages']), '管理列表查询可用');

fwrite(STDOUT, 'Smoke test complete.' . PHP_EOL);
