<?php

final class AdminMessageRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function messages(array $filters)
    {
        list($where, $params) = $this->messageWhere($filters);
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM liuyan_message m WHERE ' . $where);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();
        $perPage = (int) $filters['per_page'];
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) $filters['page'], $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                m.id,
                m.title,
                m.content,
                m.audit_status,
                m.display_status,
                m.source_ip,
                m.created_at,
                m.updated_at,
                m.deleted_at,
                r.id AS reply_id,
                r.content AS reply_content,
                r.status AS reply_record_status,
                r.updated_at AS reply_updated_at,
                r.published_at AS reply_published_at,
                COALESCE(r.status, 'unreplied') AS reply_status
            FROM liuyan_message m
            LEFT JOIN liuyan_reply r ON r.id = (
                SELECT rr.id
                FROM liuyan_reply rr
                WHERE rr.message_id = m.id AND rr.deleted_at IS NULL
                ORDER BY rr.id DESC
                LIMIT 1
            )
            WHERE " . $where . "
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT :limit OFFSET :offset";
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array(
            'items' => $statement->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        );
    }

    public function findMessage($messageId)
    {
        $statement = $this->pdo->prepare(
            "SELECT
                m.id,
                m.title,
                m.content,
                m.audit_status,
                m.display_status,
                m.source_ip,
                m.created_at,
                m.updated_at,
                m.deleted_at,
                r.id AS reply_id,
                r.content AS reply_content,
                r.status AS reply_record_status,
                r.created_at AS reply_created_at,
                r.updated_at AS reply_updated_at,
                r.published_at AS reply_published_at,
                COALESCE(r.status, 'unreplied') AS reply_status
            FROM liuyan_message m
            LEFT JOIN liuyan_reply r ON r.id = (
                SELECT rr.id
                FROM liuyan_reply rr
                WHERE rr.message_id = m.id AND rr.deleted_at IS NULL
                ORDER BY rr.id DESC
                LIMIT 1
            )
            WHERE m.id = :id
            LIMIT 1"
        );
        $statement->execute(array(':id' => (int) $messageId));
        $message = $statement->fetch();

        return $message ?: null;
    }

    public function replyHistory($messageId, $limit = 10)
    {
        $statement = $this->pdo->prepare(
            "SELECT id, admin_id, content, status, created_at, updated_at, published_at
             FROM liuyan_reply
             WHERE message_id = :message_id AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT :limit"
        );
        $statement->bindValue(':message_id', (int) $messageId, PDO::PARAM_INT);
        $statement->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function messageLogs($messageId, $limit = 20)
    {
        $statement = $this->pdo->prepare(
            "SELECT id, admin_id, action, detail, source_ip, created_at
             FROM liuyan_operation_log
             WHERE target_type = 'message' AND target_id = :target_id
             ORDER BY id DESC
             LIMIT :limit"
        );
        $statement->bindValue(':target_id', (int) $messageId, PDO::PARAM_INT);
        $statement->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function recentLogs($page, $perPage)
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM liuyan_operation_log')->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) $page, $totalPages));
        $offset = ($page - 1) * $perPage;
        $statement = $this->pdo->prepare(
            'SELECT id, admin_id, action, target_type, target_id, detail, source_ip, created_at
             FROM liuyan_operation_log
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $statement->execute();

        return array(
            'items' => $statement->fetchAll(),
            'page' => $page,
            'total' => $total,
            'total_pages' => $totalPages,
        );
    }

    public function updateMessageStates(array $messageIds, $action, $adminId, $sourceIp)
    {
        $actionMap = array(
            'approve' => array('audit_status', 'approved', 'message_approved'),
            'reject' => array('audit_status', 'rejected', 'message_rejected'),
            'show' => array('display_status', 'visible', 'message_shown'),
            'hide' => array('display_status', 'hidden', 'message_hidden'),
            'soft_delete' => array('deleted_at', null, 'message_soft_deleted'),
            'restore' => array('deleted_at', null, 'message_restored'),
        );
        if (!isset($actionMap[$action])) {
            throw new InvalidArgumentException('不支持的留言操作。');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds), function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            throw new InvalidArgumentException('请至少选择一条留言。');
        }
        if (count($ids) > 100) {
            throw new InvalidArgumentException('单次批量操作不能超过 100 条留言。');
        }

        $this->pdo->beginTransaction();
        try {
            $changed = 0;
            foreach ($ids as $messageId) {
                $before = $this->lockMessage($messageId);
                if (!$before) {
                    continue;
                }

                if ($action === 'soft_delete') {
                    $statement = $this->pdo->prepare('UPDATE liuyan_message SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
                } elseif ($action === 'restore') {
                    $statement = $this->pdo->prepare('UPDATE liuyan_message SET deleted_at = NULL, updated_at = NOW() WHERE id = :id AND deleted_at IS NOT NULL');
                } else {
                    $column = $actionMap[$action][0];
                    $statement = $this->pdo->prepare('UPDATE liuyan_message SET ' . $column . ' = :value, updated_at = NOW() WHERE id = :id');
                    $statement->bindValue(':value', $actionMap[$action][1]);
                }
                $statement->bindValue(':id', $messageId, PDO::PARAM_INT);
                $statement->execute();
                if ($statement->rowCount() < 1) {
                    continue;
                }

                $after = $this->lockMessage($messageId);
                $this->writeLog(
                    $adminId,
                    $actionMap[$action][2],
                    'message',
                    $messageId,
                    array('before' => $this->stateSnapshot($before), 'after' => $this->stateSnapshot($after)),
                    $sourceIp
                );
                $changed++;
            }
            $this->pdo->commit();

            return $changed;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function saveReply($messageId, $adminId, $content, $mode, $sourceIp)
    {
        if (!in_array($mode, array('draft', 'published'), true)) {
            throw new InvalidArgumentException('回复状态不正确。');
        }

        $this->pdo->beginTransaction();
        try {
            $message = $this->lockMessage($messageId);
            if (!$message) {
                throw new RuntimeException('留言不存在。');
            }
            if (!empty($message['deleted_at'])) {
                throw new RuntimeException('回收站中的留言不能编辑回复，请先恢复留言。');
            }

            $replyStatement = $this->pdo->prepare(
                'SELECT id, status FROM liuyan_reply WHERE message_id = :message_id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $replyStatement->execute(array(':message_id' => (int) $messageId));
            $latest = $replyStatement->fetch();

            if ($mode === 'draft' && $latest && $latest['status'] === 'draft') {
                $statement = $this->pdo->prepare(
                    "UPDATE liuyan_reply SET admin_id = :admin_id, content = :content, updated_at = NOW()
                     WHERE id = :id"
                );
                $statement->execute(array(
                    ':admin_id' => (int) $adminId,
                    ':content' => $content,
                    ':id' => (int) $latest['id'],
                ));
                $replyId = (int) $latest['id'];
            } elseif ($mode === 'published' && $latest && $latest['status'] === 'draft') {
                $statement = $this->pdo->prepare(
                    "UPDATE liuyan_reply
                     SET admin_id = :admin_id, content = :content, status = 'published', published_at = NOW(), updated_at = NOW()
                     WHERE id = :id"
                );
                $statement->execute(array(
                    ':admin_id' => (int) $adminId,
                    ':content' => $content,
                    ':id' => (int) $latest['id'],
                ));
                $replyId = (int) $latest['id'];
            } else {
                $statement = $this->pdo->prepare(
                    "INSERT INTO liuyan_reply
                        (message_id, admin_id, content, status, published_at, created_at, updated_at)
                     VALUES
                        (:message_id, :admin_id, :content, :status, :published_at, NOW(), NOW())"
                );
                $statement->execute(array(
                    ':message_id' => (int) $messageId,
                    ':admin_id' => (int) $adminId,
                    ':content' => $content,
                    ':status' => $mode,
                    ':published_at' => $mode === 'published' ? date('Y-m-d H:i:s') : null,
                ));
                $replyId = (int) $this->pdo->lastInsertId();
            }

            $this->writeLog(
                $adminId,
                $mode === 'published' ? 'reply_published' : 'reply_draft_saved',
                'message',
                $messageId,
                array('reply_id' => $replyId, 'content_length' => utf8_length($content)),
                $sourceIp
            );
            $this->pdo->commit();

            return $replyId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function writeLoginLog($adminId, $action, $sourceIp)
    {
        $this->writeLog($adminId, $action, 'admin', $adminId, null, $sourceIp);
    }

    private function messageWhere(array $filters)
    {
        $conditions = array('1 = 1');
        $params = array();
        if ($filters['keyword'] !== '') {
            $conditions[] = '(m.title LIKE :title_keyword OR m.content LIKE :content_keyword)';
            $keyword = '%' . $filters['keyword'] . '%';
            $params[':title_keyword'] = $keyword;
            $params[':content_keyword'] = $keyword;
        }
        if ($filters['audit'] !== 'all') {
            $conditions[] = 'm.audit_status = :audit_status';
            $params[':audit_status'] = $filters['audit'];
        }
        if ($filters['display'] !== 'all') {
            $conditions[] = 'm.display_status = :display_status';
            $params[':display_status'] = $filters['display'];
        }
        if ($filters['reply'] === 'unreplied') {
            $conditions[] = 'NOT EXISTS (SELECT 1 FROM liuyan_reply rx WHERE rx.message_id = m.id AND rx.deleted_at IS NULL)';
        } elseif (in_array($filters['reply'], array('draft', 'replied'), true)) {
            $replyStatus = $filters['reply'] === 'replied' ? 'published' : 'draft';
            $conditions[] = "(SELECT rx.status FROM liuyan_reply rx WHERE rx.message_id = m.id AND rx.deleted_at IS NULL ORDER BY rx.id DESC LIMIT 1) = :reply_status";
            $params[':reply_status'] = $replyStatus;
        }
        if ($filters['deleted'] === 'active') {
            $conditions[] = 'm.deleted_at IS NULL';
        } elseif ($filters['deleted'] === 'deleted') {
            $conditions[] = 'm.deleted_at IS NOT NULL';
        }
        if ($filters['date_from'] !== '') {
            $conditions[] = 'm.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to'] !== '') {
            $conditions[] = 'm.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        return array(implode(' AND ', $conditions), $params);
    }

    private function lockMessage($messageId)
    {
        $statement = $this->pdo->prepare(
            'SELECT id, audit_status, display_status, deleted_at FROM liuyan_message WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(array(':id' => (int) $messageId));
        $message = $statement->fetch();

        return $message ?: null;
    }

    private function stateSnapshot(array $message)
    {
        return array(
            'audit_status' => $message['audit_status'],
            'display_status' => $message['display_status'],
            'deleted' => !empty($message['deleted_at']),
        );
    }

    private function writeLog($adminId, $action, $targetType, $targetId, $detail, $sourceIp)
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO liuyan_operation_log
                (admin_id, action, target_type, target_id, detail, source_ip, created_at)
             VALUES
                (:admin_id, :action, :target_type, :target_id, :detail, :source_ip, NOW())'
        );
        $statement->execute(array(
            ':admin_id' => (int) $adminId,
            ':action' => (string) $action,
            ':target_type' => (string) $targetType,
            ':target_id' => (int) $targetId,
            ':detail' => $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':source_ip' => (string) $sourceIp,
        ));
    }
}
