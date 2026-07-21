<?php

require dirname(__DIR__) . '/app/bootstrap.php';

$title = 'QA-E2E-匿名提交';
$pdo = Connection::make(app_config('db'));
$statement = $pdo->prepare(
    'SELECT id, audit_status, display_status, source_ip, deleted_at
     FROM liuyan_message
     WHERE title = :title
     ORDER BY id DESC'
);
$statement->execute(array(':title' => $title));
$messages = $statement->fetchAll();
$passed = true;

if (count($messages) !== 1) {
    echo '[FAIL] 端到端提交应生成且仅生成 1 条留言，实际为 ' . count($messages) . " 条。\n";
    $passed = false;
} else {
    $message = $messages[0];
    $stateIsCorrect = $message['audit_status'] === 'pending'
        && $message['display_status'] === 'visible'
        && $message['deleted_at'] === null
        && $message['source_ip'] !== '';
    echo ($stateIsCorrect ? '[PASS]' : '[FAIL]')
        . " 匿名提交进入待审核、默认显示、未删除状态并记录来源 IP。\n";
    $passed = $passed && $stateIsCorrect;

    $publicStatement = $pdo->prepare(
        "SELECT COUNT(*) FROM liuyan_message
         WHERE id = :id
         AND audit_status = 'approved'
         AND display_status = 'visible'
         AND deleted_at IS NULL"
    );
    $publicStatement->execute(array(':id' => (int) $message['id']));
    $notPublic = (int) $publicStatement->fetchColumn() === 0;
    echo ($notPublic ? '[PASS]' : '[FAIL]') . " 待审核留言不会进入公开集合。\n";
    $passed = $passed && $notPublic;
}

$pdo->beginTransaction();
try {
    $idsStatement = $pdo->prepare('SELECT id FROM liuyan_message WHERE title = :title');
    $idsStatement->execute(array(':title' => $title));
    $ids = array_map('intval', $idsStatement->fetchAll(PDO::FETCH_COLUMN));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $logs = $pdo->prepare(
            "DELETE FROM liuyan_operation_log WHERE target_type = 'message' AND target_id IN (" . $placeholders . ')'
        );
        $logs->execute($ids);
        $replies = $pdo->prepare('DELETE FROM liuyan_reply WHERE message_id IN (' . $placeholders . ')');
        $replies->execute($ids);
        $delete = $pdo->prepare('DELETE FROM liuyan_message WHERE id IN (' . $placeholders . ')');
        $delete->execute($ids);
    }
    $pdo->commit();
    echo '[PASS] 已清理端到端提交产生的临时留言。' . "\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '[FAIL] 临时留言清理失败：' . $error->getMessage() . "\n";
    exit(1);
}

exit($passed ? 0 : 1);
