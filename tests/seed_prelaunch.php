<?php

require dirname(__DIR__) . '/app/bootstrap.php';

const QA_PREFIX = 'QA150-';
const QA_COUNT = 150;

function qa_delete_existing(PDO $pdo)
{
    $statement = $pdo->prepare('SELECT id FROM liuyan_message WHERE title LIKE :prefix');
    $statement->execute(array(':prefix' => QA_PREFIX . '%'));
    $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if (empty($ids)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $logStatement = $pdo->prepare(
        "DELETE FROM liuyan_operation_log WHERE target_type = 'message' AND target_id IN (" . $placeholders . ')'
    );
    $logStatement->execute($ids);

    $replyStatement = $pdo->prepare('DELETE FROM liuyan_reply WHERE message_id IN (' . $placeholders . ')');
    $replyStatement->execute($ids);

    $messageStatement = $pdo->prepare('DELETE FROM liuyan_message WHERE id IN (' . $placeholders . ')');
    $messageStatement->execute($ids);

    return count($ids);
}

function qa_label($value)
{
    $labels = array(
        'pending' => '待审核',
        'approved' => '已通过',
        'rejected' => '已驳回',
        'unreplied' => '未回复',
        'draft' => '回复草稿',
        'published' => '已回复',
        'published_then_draft' => '已发布后新草稿',
        'visible' => '显示',
        'hidden' => '隐藏',
        'active' => '正常',
        'deleted' => '回收站',
    );

    return isset($labels[$value]) ? $labels[$value] : $value;
}

try {
    $pdo = Connection::make(app_config('db'));
    $pdo->beginTransaction();

    $removed = qa_delete_existing($pdo);
    if (in_array('--cleanup', $argv, true)) {
        $pdo->commit();
        echo 'Removed ' . $removed . " prelaunch QA messages.\n";
        exit(0);
    }

    $adminId = (int) $pdo->query('SELECT id FROM admin ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($adminId < 1) {
        throw new RuntimeException('Development admin account is required before seeding QA data.');
    }

    $combinations = array();
    foreach (array('pending', 'approved', 'rejected') as $audit) {
        foreach (array('unreplied', 'draft', 'published', 'published_then_draft') as $reply) {
            foreach (array('visible', 'hidden') as $display) {
                foreach (array('active', 'deleted') as $deleted) {
                    $combinations[] = array(
                        'audit' => $audit,
                        'reply' => $reply,
                        'display' => $display,
                        'deleted' => $deleted,
                    );
                }
            }
        }
    }

    $messageStatement = $pdo->prepare(
        'INSERT INTO liuyan_message
            (title, content, audit_status, display_status, source_ip, created_at, updated_at, deleted_at)
         VALUES
            (:title, :content, :audit_status, :display_status, :source_ip, :created_at, :updated_at, :deleted_at)'
    );
    $replyStatement = $pdo->prepare(
        'INSERT INTO liuyan_reply
            (message_id, admin_id, content, status, published_at, created_at, updated_at, deleted_at)
         VALUES
            (:message_id, :admin_id, :content, :status, :published_at, :created_at, :updated_at, :deleted_at)'
    );

    $start = new DateTimeImmutable('2026-07-22 08:00:00', new DateTimeZone('Asia/Shanghai'));
    for ($index = 1; $index <= QA_COUNT; $index++) {
        $combination = $combinations[($index - 1) % count($combinations)];
        $createdAt = $start->modify('+' . ($index - 1) . ' minutes');
        $updatedAt = $createdAt->modify('+5 minutes');
        $deletedAt = $combination['deleted'] === 'deleted'
            ? $createdAt->modify('+90 minutes')->format('Y-m-d H:i:s')
            : null;
        $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $title = QA_PREFIX . $number . ' ' . qa_label($combination['audit']);
        $content = sprintf(
            '上线前测试留言 %s；审核=%s；回复=%s；展示=%s；范围=%s。',
            $number,
            qa_label($combination['audit']),
            qa_label($combination['reply']),
            qa_label($combination['display']),
            qa_label($combination['deleted'])
        );
        if ($index === 1) {
            $content .= ' 唯一检索词-QA150-CONTENT-ONLY。';
        } elseif ($index === 17) {
            $content .= ' 转义样本：<script>alert("qa")</script> & "双引号" \'单引号\'。';
        } elseif ($index === 21) {
            $content .= ' 四字节字符样本：𠮷😀，中文标点：“测试”、（验收）。';
        } elseif ($index === 25) {
            $content .= " 多行样本第一行。\n第二行用于验证换行展示。";
        }

        $sourceIp = $index % 10 === 0
            ? '2001:db8::' . dechex($index)
            : '10.20.' . (int) floor(($index - 1) / 250) . '.' . (($index - 1) % 250 + 1);

        $messageStatement->execute(array(
            ':title' => $title,
            ':content' => $content,
            ':audit_status' => $combination['audit'],
            ':display_status' => $combination['display'],
            ':source_ip' => $sourceIp,
            ':created_at' => $createdAt->format('Y-m-d H:i:s'),
            ':updated_at' => $updatedAt->format('Y-m-d H:i:s'),
            ':deleted_at' => $deletedAt,
        ));
        $messageId = (int) $pdo->lastInsertId();

        if ($combination['reply'] === 'draft') {
            $replyAt = $createdAt->modify('+15 minutes')->format('Y-m-d H:i:s');
            $replyStatement->execute(array(
                ':message_id' => $messageId,
                ':admin_id' => $adminId,
                ':content' => 'QA 草稿回复 ' . $number . '，不得出现在公开留言板。',
                ':status' => 'draft',
                ':published_at' => null,
                ':created_at' => $replyAt,
                ':updated_at' => $replyAt,
                ':deleted_at' => null,
            ));
        } elseif ($combination['reply'] === 'published' || $combination['reply'] === 'published_then_draft') {
            $publishedAt = $createdAt->modify('+15 minutes')->format('Y-m-d H:i:s');
            $replyStatement->execute(array(
                ':message_id' => $messageId,
                ':admin_id' => $adminId,
                ':content' => 'QA 已发布回复 ' . $number . '，公开条件满足时应默认展开显示。',
                ':status' => 'published',
                ':published_at' => $publishedAt,
                ':created_at' => $publishedAt,
                ':updated_at' => $publishedAt,
                ':deleted_at' => null,
            ));

            if ($combination['reply'] === 'published_then_draft') {
                $draftAt = $createdAt->modify('+30 minutes')->format('Y-m-d H:i:s');
                $replyStatement->execute(array(
                    ':message_id' => $messageId,
                    ':admin_id' => $adminId,
                    ':content' => 'QA 新版草稿 ' . $number . '，后台显示草稿，但公开页继续显示上一版已发布回复。',
                    ':status' => 'draft',
                    ':published_at' => null,
                    ':created_at' => $draftAt,
                    ':updated_at' => $draftAt,
                    ':deleted_at' => null,
                ));
            }
        }

        if ($index % 24 === 0) {
            $deletedReplyAt = $createdAt->modify('+45 minutes');
            $replyStatement->execute(array(
                ':message_id' => $messageId,
                ':admin_id' => $adminId,
                ':content' => 'QA 已删除回复历史 ' . $number . '，任何页面均不应作为当前回复读取。',
                ':status' => 'draft',
                ':published_at' => null,
                ':created_at' => $deletedReplyAt->format('Y-m-d H:i:s'),
                ':updated_at' => $deletedReplyAt->format('Y-m-d H:i:s'),
                ':deleted_at' => $deletedReplyAt->modify('+1 minute')->format('Y-m-d H:i:s'),
            ));
        }
    }

    $pdo->commit();
    echo 'Generated ' . QA_COUNT . ' deterministic prelaunch QA messages';
    echo ' across ' . count($combinations) . " state combinations.\n";
    echo 'Prefix: ' . QA_PREFIX . "\n";
    echo 'Removed before insert: ' . $removed . "\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[FAIL] ' . $error->getMessage() . "\n");
    exit(1);
}
