<?php

require dirname(__DIR__) . '/app/bootstrap.php';

const PRELAUNCH_PREFIX = 'QA150-';
const PRELAUNCH_COUNT = 150;

$failures = array();
$metrics = array();

function prelaunch_check($condition, $label, $detail = '')
{
    global $failures;
    if ($condition) {
        echo '[PASS] ' . $label . "\n";
        return;
    }

    $message = $label . ($detail !== '' ? ' — ' . $detail : '');
    $failures[] = $message;
    echo '[FAIL] ' . $message . "\n";
}

function prelaunch_scalar(PDO $pdo, $sql, array $params = array())
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function prelaunch_ids(PDO $pdo, $sql, array $params = array())
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    sort($ids);

    return $ids;
}

function prelaunch_public_expected_ids(PDO $pdo, $replyFilter)
{
    $conditions = array(
        'm.title LIKE :prefix',
        "m.audit_status = 'approved'",
        "m.display_status = 'visible'",
        'm.deleted_at IS NULL',
    );
    if ($replyFilter === 'replied') {
        $conditions[] = "EXISTS (
            SELECT 1 FROM liuyan_reply r
            WHERE r.message_id = m.id AND r.deleted_at IS NULL AND r.status = 'published'
        )";
    } elseif ($replyFilter === 'waiting') {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM liuyan_reply r
            WHERE r.message_id = m.id AND r.deleted_at IS NULL AND r.status = 'published'
        )";
    }

    return prelaunch_ids(
        $pdo,
        'SELECT m.id FROM liuyan_message m WHERE ' . implode(' AND ', $conditions),
        array(':prefix' => PRELAUNCH_PREFIX . '%')
    );
}

function prelaunch_collect_public_qa_ids(MessageRepository $repository, $filter)
{
    $first = $repository->publicMessages($filter, 1, 10);
    $ids = array();
    for ($page = 1; $page <= $first['total_pages']; $page++) {
        $result = $page === 1 ? $first : $repository->publicMessages($filter, $page, 10);
        prelaunch_check(count($result['items']) <= 10, '公开页每页不超过 10 条（' . $filter . ' 第 ' . $page . ' 页）');
        foreach ($result['items'] as $message) {
            if (strpos($message['title'], PRELAUNCH_PREFIX) === 0) {
                $ids[] = (int) $message['id'];
            }
        }
    }
    sort($ids);

    return $ids;
}

try {
    $pdo = Connection::make(app_config('db'));
    $messageRepository = new MessageRepository($pdo);
    $adminRepository = new AdminMessageRepository($pdo);

    $qaTotal = (int) prelaunch_scalar(
        $pdo,
        'SELECT COUNT(*) FROM liuyan_message WHERE title LIKE :prefix',
        array(':prefix' => PRELAUNCH_PREFIX . '%')
    );
    prelaunch_check($qaTotal === PRELAUNCH_COUNT, '测试留言总数严格等于 150', 'actual=' . $qaTotal);

    $combinationSql = "SELECT COUNT(*) FROM (
        SELECT
            m.audit_status,
            m.display_status,
            IF(m.deleted_at IS NULL, 'active', 'deleted') AS deleted_scope,
            CASE
                WHEN NOT EXISTS (
                    SELECT 1 FROM liuyan_reply r0
                    WHERE r0.message_id = m.id AND r0.deleted_at IS NULL
                ) THEN 'unreplied'
                WHEN EXISTS (
                    SELECT 1 FROM liuyan_reply rp
                    WHERE rp.message_id = m.id AND rp.deleted_at IS NULL AND rp.status = 'published'
                ) AND (
                    SELECT rl.status FROM liuyan_reply rl
                    WHERE rl.message_id = m.id AND rl.deleted_at IS NULL
                    ORDER BY rl.id DESC LIMIT 1
                ) = 'draft' THEN 'published_then_draft'
                WHEN (
                    SELECT rl.status FROM liuyan_reply rl
                    WHERE rl.message_id = m.id AND rl.deleted_at IS NULL
                    ORDER BY rl.id DESC LIMIT 1
                ) = 'draft' THEN 'draft'
                ELSE 'published'
            END AS reply_scenario
        FROM liuyan_message m
        WHERE m.title LIKE :prefix
        GROUP BY m.id
    ) states
    GROUP BY audit_status, reply_scenario, display_status, deleted_scope";
    $combinationStatement = $pdo->prepare($combinationSql);
    $combinationStatement->execute(array(':prefix' => PRELAUNCH_PREFIX . '%'));
    $combinationCounts = array_map('intval', $combinationStatement->fetchAll(PDO::FETCH_COLUMN));
    prelaunch_check(count($combinationCounts) === 48, '审核×回复场景×展示×删除范围共 48 种组合齐全');
    prelaunch_check(!empty($combinationCounts) && min($combinationCounts) >= 3, '每种状态组合至少包含 3 条留言');
    $metrics['state_combination_min'] = empty($combinationCounts) ? 0 : min($combinationCounts);
    $metrics['state_combination_max'] = empty($combinationCounts) ? 0 : max($combinationCounts);

    $deletedReplyCount = (int) prelaunch_scalar(
        $pdo,
        'SELECT COUNT(*) FROM liuyan_reply r
         INNER JOIN liuyan_message m ON m.id = r.message_id
         WHERE m.title LIKE :prefix AND r.deleted_at IS NOT NULL',
        array(':prefix' => PRELAUNCH_PREFIX . '%')
    );
    prelaunch_check($deletedReplyCount >= 6, '包含已删除回复历史且当前回复查询会忽略它', 'actual=' . $deletedReplyCount);

    $unicodeContent = (string) prelaunch_scalar(
        $pdo,
        'SELECT content FROM liuyan_message WHERE title LIKE :title LIMIT 1',
        array(':title' => PRELAUNCH_PREFIX . '021%')
    );
    prelaunch_check(strpos($unicodeContent, '𠮷😀') !== false, 'utf8mb4 四字节字符保存完整');

    $publicCounts = $messageRepository->publicCounts();
    $expectedGlobalTotal = (int) prelaunch_scalar(
        $pdo,
        "SELECT COUNT(*) FROM liuyan_message
         WHERE audit_status = 'approved' AND display_status = 'visible' AND deleted_at IS NULL"
    );
    $expectedGlobalReplied = (int) prelaunch_scalar(
        $pdo,
        "SELECT COUNT(*) FROM liuyan_message m
         WHERE m.audit_status = 'approved' AND m.display_status = 'visible' AND m.deleted_at IS NULL
         AND EXISTS (
             SELECT 1 FROM liuyan_reply r
             WHERE r.message_id = m.id AND r.deleted_at IS NULL AND r.status = 'published'
         )"
    );
    prelaunch_check($publicCounts['all'] === $expectedGlobalTotal, '公开留言总数统计准确');
    prelaunch_check($publicCounts['replied'] === $expectedGlobalReplied, '公开已回复统计准确');
    prelaunch_check($publicCounts['waiting'] === $expectedGlobalTotal - $expectedGlobalReplied, '公开待回复统计准确');
    $metrics['public_counts'] = $publicCounts;

    foreach (array('all', 'replied', 'waiting') as $publicFilter) {
        $expectedIds = prelaunch_public_expected_ids($pdo, $publicFilter);
        $actualIds = prelaunch_collect_public_qa_ids($messageRepository, $publicFilter);
        prelaunch_check(
            $actualIds === $expectedIds,
            '公开页“' . $publicFilter . '”仅展示审核通过、显示、未删除的正确集合',
            'expected=' . count($expectedIds) . ', actual=' . count($actualIds)
        );
        $metrics['qa_public_' . $publicFilter] = count($actualIds);
    }

    $allAdminFilters = admin_filter_params(array(
        'keyword' => PRELAUNCH_PREFIX,
        'deleted' => 'all',
        'per_page' => '20',
    ));
    $allAdminMessages = $adminRepository->messages($allAdminFilters);
    prelaunch_check($allAdminMessages['total'] === PRELAUNCH_COUNT, '后台关键词可检索全部 150 条测试留言');
    prelaunch_check(count($allAdminMessages['items']) === 20, '后台默认每页返回 20 条');
    prelaunch_check($allAdminMessages['total_pages'] === 8, '后台 150 条、每页 20 条时共 8 页');

    $lastPageFilters = $allAdminFilters;
    $lastPageFilters['page'] = 999;
    $lastPageMessages = $adminRepository->messages($lastPageFilters);
    prelaunch_check($lastPageMessages['page'] === 8 && count($lastPageMessages['items']) === 10, '后台越界页码收敛到第 8 页并显示末页 10 条');

    $fiftyFilters = $allAdminFilters;
    $fiftyFilters['per_page'] = 50;
    $fiftyMessages = $adminRepository->messages($fiftyFilters);
    prelaunch_check(count($fiftyMessages['items']) === 50 && $fiftyMessages['total_pages'] === 3, '后台每页 50 条分页准确');

    $missingAdminCombinations = array();
    foreach (array('pending', 'approved', 'rejected') as $audit) {
        foreach (array('unreplied', 'draft', 'replied') as $reply) {
            foreach (array('visible', 'hidden') as $display) {
                foreach (array('active', 'deleted') as $deleted) {
                    $filters = admin_filter_params(array(
                        'keyword' => PRELAUNCH_PREFIX,
                        'audit' => $audit,
                        'reply' => $reply,
                        'display' => $display,
                        'deleted' => $deleted,
                        'per_page' => '50',
                    ));
                    $result = $adminRepository->messages($filters);
                    if ($result['total'] < 3) {
                        $missingAdminCombinations[] = $audit . '/' . $reply . '/' . $display . '/' . $deleted;
                    }
                }
            }
        }
    }
    prelaunch_check(
        empty($missingAdminCombinations),
        '后台 36 组审核×回复×展示×数据范围组合筛选均有正确结果',
        implode(', ', $missingAdminCombinations)
    );

    $contentSearch = $adminRepository->messages(admin_filter_params(array(
        'keyword' => 'QA150-CONTENT-ONLY',
        'deleted' => 'all',
    )));
    prelaunch_check($contentSearch['total'] === 1, '后台关键词能唯一命中留言正文');

    $injectionSearch = $adminRepository->messages(admin_filter_params(array(
        'keyword' => "' OR 1=1 --",
        'deleted' => 'all',
    )));
    prelaunch_check($injectionSearch['total'] === 0, '后台关键词 SQL 注入字符串不会扩大结果集');

    $dateSearch = $adminRepository->messages(admin_filter_params(array(
        'keyword' => PRELAUNCH_PREFIX,
        'deleted' => 'all',
        'date_from' => '2026-07-22',
        'date_to' => '2026-07-22',
        'per_page' => '50',
    )));
    prelaunch_check($dateSearch['total'] === PRELAUNCH_COUNT, '后台起止日期包含当天全部 150 条测试留言');

    $invalidFilters = admin_filter_params(array(
        'audit' => 'invalid',
        'reply' => 'invalid',
        'display' => 'invalid',
        'deleted' => 'invalid',
        'date_from' => '2026-02-31',
        'per_page' => '999',
        'page' => '-5',
    ));
    prelaunch_check(
        $invalidFilters['audit'] === 'all'
        && $invalidFilters['reply'] === 'all'
        && $invalidFilters['display'] === 'all'
        && $invalidFilters['deleted'] === 'active'
        && $invalidFilters['date_from'] === ''
        && $invalidFilters['per_page'] === 20
        && $invalidFilters['page'] === 1,
        '非法筛选值、日期、每页条数和页码安全回退'
    );

    $adminId = (int) prelaunch_scalar($pdo, 'SELECT id FROM admin ORDER BY id ASC LIMIT 1');
    $stateTargetId = (int) prelaunch_scalar(
        $pdo,
        'SELECT id FROM liuyan_message WHERE title = :title LIMIT 1',
        array(':title' => PRELAUNCH_PREFIX . '001 待审核')
    );
    prelaunch_check($stateTargetId > 0, '找到状态流转测试留言');
    if ($stateTargetId > 0) {
        try {
            $actions = array('approve', 'hide', 'soft_delete', 'restore', 'show', 'reject');
            foreach ($actions as $action) {
                $changed = $adminRepository->updateMessageStates(array($stateTargetId), $action, $adminId, '127.0.0.1');
                prelaunch_check($changed === 1, '单条状态操作成功：' . $action);
            }
            $logCount = (int) prelaunch_scalar(
                $pdo,
                'SELECT COUNT(*) FROM liuyan_operation_log
                 WHERE target_type = \'message\' AND target_id = :target_id
                 AND action IN (
                     \'message_approved\', \'message_hidden\', \'message_soft_deleted\',
                     \'message_restored\', \'message_shown\', \'message_rejected\'
                 )',
                array(':target_id' => $stateTargetId)
            );
            prelaunch_check($logCount >= 6, '审核、隐藏、删除、恢复操作均写入日志');
        } finally {
            $restoreStatement = $pdo->prepare(
                "UPDATE liuyan_message
                 SET audit_status = 'pending', display_status = 'visible', deleted_at = NULL
                 WHERE id = :id"
            );
            $restoreStatement->execute(array(':id' => $stateTargetId));
        }

        try {
            $draftId = $adminRepository->saveReply($stateTargetId, $adminId, 'QA 临时草稿回复', 'draft', '127.0.0.1');
            $draftStatus = (string) prelaunch_scalar(
                $pdo,
                'SELECT status FROM liuyan_reply WHERE id = :id',
                array(':id' => $draftId)
            );
            prelaunch_check($draftStatus === 'draft', '管理员回复草稿保存成功');
            $publishedId = $adminRepository->saveReply($stateTargetId, $adminId, 'QA 临时已发布回复', 'published', '127.0.0.1');
            $publishedRow = $pdo->prepare('SELECT status, published_at FROM liuyan_reply WHERE id = :id');
            $publishedRow->execute(array(':id' => $publishedId));
            $publishedReply = $publishedRow->fetch();
            prelaunch_check(
                $publishedId === $draftId
                && $publishedReply
                && $publishedReply['status'] === 'published'
                && $publishedReply['published_at'] !== null,
                '草稿原记录可发布且写入发布时间'
            );
            $replyLogCount = (int) prelaunch_scalar(
                $pdo,
                "SELECT COUNT(*) FROM liuyan_operation_log
                 WHERE target_type = 'message' AND target_id = :target_id
                 AND action IN ('reply_draft_saved', 'reply_published')",
                array(':target_id' => $stateTargetId)
            );
            prelaunch_check($replyLogCount >= 2, '保存草稿与发布回复均写入操作日志');
        } finally {
            $cleanupReply = $pdo->prepare('DELETE FROM liuyan_reply WHERE message_id = :message_id');
            $cleanupReply->execute(array(':message_id' => $stateTargetId));
        }
    }

    $batchIds = prelaunch_ids(
        $pdo,
        "SELECT id FROM liuyan_message
         WHERE title LIKE :prefix AND display_status = 'visible' AND deleted_at IS NULL
         ORDER BY id ASC LIMIT 3",
        array(':prefix' => PRELAUNCH_PREFIX . '%')
    );
    if (count($batchIds) === 3) {
        $batchHidden = $adminRepository->updateMessageStates($batchIds, 'hide', $adminId, '127.0.0.1');
        $batchShown = $adminRepository->updateMessageStates($batchIds, 'show', $adminId, '127.0.0.1');
        prelaunch_check($batchHidden === 3 && $batchShown === 3, '批量隐藏和恢复显示各处理 3 条留言');
    } else {
        prelaunch_check(false, '找到 3 条批量操作测试留言');
    }

    $limitRejected = false;
    try {
        $adminRepository->updateMessageStates(range(1, 101), 'hide', $adminId, '127.0.0.1');
    } catch (InvalidArgumentException $error) {
        $limitRejected = strpos($error->getMessage(), '100') !== false;
    }
    prelaunch_check($limitRejected, '单次批量操作超过 100 条时被拒绝');

    $finalQaTotal = (int) prelaunch_scalar(
        $pdo,
        'SELECT COUNT(*) FROM liuyan_message WHERE title LIKE :prefix',
        array(':prefix' => PRELAUNCH_PREFIX . '%')
    );
    prelaunch_check($finalQaTotal === PRELAUNCH_COUNT, '状态与回复操作测试后仍保留完整 150 条测试留言');

    echo "\nMetrics:\n";
    echo json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $error) {
    $failures[] = 'Unhandled exception: ' . $error->getMessage();
    echo '[FAIL] Unhandled exception: ' . $error->getMessage() . "\n";
}

echo "\nPrelaunch result: " . (empty($failures) ? 'PASS' : 'FAIL') . "\n";
if (!empty($failures)) {
    echo "Failures:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
