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

$pdo->beginTransaction();
try {
    $messageStatement = $pdo->prepare(
        "INSERT INTO liuyan_message
            (title, content, audit_status, display_status, source_ip, created_at, updated_at)
         VALUES
            (:title, :content, 'approved', 'visible', :source_ip, :created_at, :updated_at)"
    );
    $messageStatement->execute(array(
        ':title' => 'QA 标题关键词回归测试',
        ':content' => '正文命中唯一短语；该记录仅存在于测试事务中。',
        ':source_ip' => '127.0.0.1',
        ':created_at' => '2026-01-01 09:00:00',
        ':updated_at' => '2026-01-01 11:00:00',
    ));
    $testMessageId = (int) $pdo->lastInsertId();

    $titleKeywordMessages = $repository->messages(admin_filter_params(array('keyword' => '标题关键词回归')));
    $contentKeywordMessages = $repository->messages(admin_filter_params(array('keyword' => '正文命中唯一短语')));
    $titleKeywordMatched = false;
    $contentKeywordMatched = false;
    foreach ($titleKeywordMessages['items'] as $message) {
        if ((int) $message['id'] === $testMessageId) {
            $titleKeywordMatched = true;
            break;
        }
    }
    foreach ($contentKeywordMessages['items'] as $message) {
        if ((int) $message['id'] === $testMessageId) {
            $contentKeywordMatched = true;
            break;
        }
    }
    smoke_assert($titleKeywordMatched && $contentKeywordMatched, '管理列表关键词可同时检索标题和内容');

    $replyStatement = $pdo->prepare(
        "INSERT INTO liuyan_reply
            (message_id, admin_id, content, status, published_at, created_at, updated_at)
         VALUES
            (:message_id, :admin_id, :content, 'published', :published_at, :created_at, :updated_at)"
    );
    $replyStatement->execute(array(
        ':message_id' => $testMessageId,
        ':admin_id' => (int) $admin['id'],
        ':content' => '先保存草稿，稍后发布。',
        ':published_at' => '2026-01-01 11:00:00',
        ':created_at' => '2026-01-01 10:00:00',
        ':updated_at' => '2026-01-01 11:00:00',
    ));

    $publishedMessages = $publicRepository->publicMessages('replied', 1, 1000);
    $publishedAtMatched = false;
    foreach ($publishedMessages['items'] as $message) {
        if ((int) $message['id'] === $testMessageId
            && $message['reply_published_at'] === '2026-01-01 11:00:00') {
            $publishedAtMatched = true;
            break;
        }
    }
    smoke_assert($publishedAtMatched, '公开回复时间使用 published_at 而不是草稿创建时间');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

fwrite(STDOUT, 'Smoke test complete.' . PHP_EOL);
