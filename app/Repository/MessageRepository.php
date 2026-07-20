<?php

final class MessageRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function publicCounts()
    {
        $baseWhere = "m.audit_status = 'approved'
            AND m.display_status = 'visible'
            AND m.deleted_at IS NULL";

        $sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN EXISTS (
                SELECT 1 FROM liuyan_reply r
                WHERE r.message_id = m.id AND r.deleted_at IS NULL AND r.status = 'published'
            ) THEN 1 ELSE 0 END) AS replied
            FROM liuyan_message m
            WHERE " . $baseWhere;

        $row = $this->pdo->query($sql)->fetch();
        $total = (int) $row['total'];
        $replied = (int) $row['replied'];

        return array(
            'all' => $total,
            'replied' => $replied,
            'waiting' => max(0, $total - $replied),
        );
    }

    public function publicMessages($replyStatus, $page, $perPage)
    {
        $conditions = array(
            "m.audit_status = 'approved'",
            "m.display_status = 'visible'",
            'm.deleted_at IS NULL',
        );

        if ($replyStatus === 'replied') {
            $conditions[] = "EXISTS (SELECT 1 FROM liuyan_reply rx WHERE rx.message_id = m.id AND rx.deleted_at IS NULL AND rx.status = 'published')";
        } elseif ($replyStatus === 'waiting') {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM liuyan_reply rx WHERE rx.message_id = m.id AND rx.deleted_at IS NULL AND rx.status = 'published')";
        }

        $where = implode(' AND ', $conditions);
        $countSql = 'SELECT COUNT(*) FROM liuyan_message m WHERE ' . $where;
        $total = (int) $this->pdo->query($countSql)->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                m.id,
                m.title,
                m.content,
                m.created_at,
                r.id AS reply_id,
                r.content AS reply_content,
                r.created_at AS reply_created_at
            FROM liuyan_message m
            LEFT JOIN liuyan_reply r ON r.id = (
                SELECT rr.id
                FROM liuyan_reply rr
                WHERE rr.message_id = m.id AND rr.deleted_at IS NULL AND rr.status = 'published'
                ORDER BY rr.id DESC
                LIMIT 1
            )
            WHERE " . $where . "
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT :limit OFFSET :offset";

        $statement = $this->pdo->prepare($sql);
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

    public function createPending($title, $content, $sourceIp)
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO liuyan_message
                (title, content, audit_status, display_status, source_ip, created_at, updated_at)
             VALUES
                (:title, :content, 'pending', 'visible', :source_ip, NOW(), NOW())"
        );
        $statement->execute(array(
            ':title' => $title,
            ':content' => $content,
            ':source_ip' => $sourceIp,
        ));

        return (int) $this->pdo->lastInsertId();
    }
}
