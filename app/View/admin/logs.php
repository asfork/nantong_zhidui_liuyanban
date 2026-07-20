<?php
$actionLabels = array(
    'admin_login_success' => '管理员登录成功',
    'admin_login_failed' => '管理员登录失败',
    'admin_logout' => '管理员退出',
    'message_approved' => '审核通过',
    'message_rejected' => '审核驳回',
    'message_shown' => '恢复显示',
    'message_hidden' => '隐藏留言',
    'message_soft_deleted' => '移至回收站',
    'message_restored' => '恢复留言',
    'reply_draft_saved' => '保存回复草稿',
    'reply_published' => '发布回复',
);
require __DIR__ . '/_header.php';
?>
<section class="admin-panel" aria-labelledby="log-title">
    <div class="admin-title-row"><div><p class="section-kicker">安全审计</p><h1 id="log-title">操作日志</h1></div><p>共 <?= (int) $logs['total'] ?> 条记录</p></div>
    <div class="admin-table-scroll" tabindex="0" aria-label="操作日志，可横向滚动">
        <table class="admin-table admin-log-table">
            <thead><tr><th>时间</th><th>管理员</th><th>操作</th><th>对象</th><th>来源 IP</th><th>状态变化</th></tr></thead>
            <tbody>
            <?php if (empty($logs['items'])): ?><tr><td colspan="6" class="admin-empty-state">暂无操作日志。</td></tr><?php endif; ?>
            <?php foreach ($logs['items'] as $log): ?>
                <tr>
                    <td><?= e(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?></td>
                    <td>#<?= (int) $log['admin_id'] ?></td>
                    <td><?= e(isset($actionLabels[$log['action']]) ? $actionLabels[$log['action']] : $log['action']) ?></td>
                    <td><?= e($log['target_type']) ?> #<?= (int) $log['target_id'] ?></td>
                    <td><?= e($log['source_ip']) ?></td>
                    <td><code><?= e($log['detail'] ?: '—') ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($logs['total_pages'] > 1): ?>
        <nav class="admin-pagination" aria-label="日志分页">
            <?php foreach (pagination_items($logs['page'], $logs['total_pages']) as $item): ?>
                <?php if ($item === 'ellipsis'): ?><span aria-hidden="true">…</span><?php else: ?><a href="<?= e(base_url('/admin/logs.php?page=' . (int) $item)) ?>" <?= $item === $logs['page'] ? 'aria-current="page" class="is-current"' : '' ?>><?= (int) $item ?></a><?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
