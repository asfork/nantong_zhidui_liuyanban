<?php
$auditLabels = array('pending' => '待审核', 'approved' => '已通过', 'rejected' => '已驳回');
$replyLabels = array('unreplied' => '未回复', 'draft' => '回复草稿', 'published' => '已回复');
$displayLabels = array('visible' => '显示', 'hidden' => '隐藏');
$logLabels = array(
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

<section class="admin-panel" aria-labelledby="management-title">
    <div class="admin-title-row">
        <div>
            <p class="section-kicker">留言处理中心</p>
            <h1 id="management-title">匿名留言管理</h1>
        </div>
        <p>公开条件：审核通过 + 对外显示 + 未删除</p>
    </div>

    <form method="get" action="<?= e(base_url('/admin/')) ?>" class="admin-filter-form">
        <div class="admin-filter-field admin-filter-keyword">
            <label for="keyword">关键词</label>
            <input id="keyword" name="keyword" type="search" maxlength="100" value="<?= e($filters['keyword']) ?>" placeholder="搜索标题或留言内容">
        </div>
        <div class="admin-filter-field">
            <label for="audit">审核状态</label>
            <select id="audit" name="audit">
                <option value="all">全部</option>
                <?php foreach ($auditLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['audit'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="reply">回复状态</label>
            <select id="reply" name="reply">
                <option value="all">全部</option>
                <option value="unreplied" <?= $filters['reply'] === 'unreplied' ? 'selected' : '' ?>>未回复</option>
                <option value="draft" <?= $filters['reply'] === 'draft' ? 'selected' : '' ?>>回复草稿</option>
                <option value="replied" <?= $filters['reply'] === 'replied' ? 'selected' : '' ?>>已回复</option>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="display">展示状态</label>
            <select id="display" name="display">
                <option value="all">全部</option>
                <?php foreach ($displayLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['display'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="deleted">数据范围</label>
            <select id="deleted" name="deleted">
                <option value="active" <?= $filters['deleted'] === 'active' ? 'selected' : '' ?>>正常留言</option>
                <option value="deleted" <?= $filters['deleted'] === 'deleted' ? 'selected' : '' ?>>回收站</option>
                <option value="all" <?= $filters['deleted'] === 'all' ? 'selected' : '' ?>>全部</option>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="date-from">开始日期</label>
            <input id="date-from" name="date_from" type="date" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="admin-filter-field">
            <label for="date-to">结束日期</label>
            <input id="date-to" name="date_to" type="date" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="admin-filter-field admin-filter-per-page">
            <label for="per-page">每页</label>
            <select id="per-page" name="per_page">
                <?php foreach (array(10, 20, 50) as $size): ?>
                    <option value="<?= (int) $size ?>" <?= $filters['per_page'] === $size ? 'selected' : '' ?>><?= (int) $size ?> 条</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter-actions">
            <button type="submit" class="admin-button admin-button-primary">查询</button>
            <a class="admin-button admin-button-secondary" href="<?= e(base_url('/admin/')) ?>">清空筛选</a>
        </div>
    </form>
</section>

<section class="admin-panel admin-list-panel" aria-labelledby="message-table-title">
    <div class="batch-toolbar">
        <div>
            <strong id="message-table-title">留言列表</strong>
            <span>共 <?= (int) $messages['total'] ?> 条</span>
        </div>
        <form id="batch-form" method="post" action="<?= e(base_url('/admin/action.php')) ?>" class="batch-form" data-batch-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
            <span data-selected-count>已选择 0 条</span>
            <label for="batch-action" class="visually-hidden">批量操作</label>
            <select id="batch-action" name="action" data-batch-action>
                <option value="">选择批量操作</option>
                <option value="approve">审核通过</option>
                <option value="reject">审核驳回</option>
                <option value="show">设为显示</option>
                <option value="hide">设为隐藏</option>
                <option value="soft_delete">移至回收站</option>
                <option value="restore">从回收站恢复</option>
            </select>
            <button type="submit" class="admin-button admin-button-primary" disabled data-batch-submit>应用</button>
        </form>
    </div>

    <div class="admin-table-scroll" tabindex="0" aria-label="留言列表，可横向滚动">
        <table class="admin-table">
            <thead>
            <tr>
                <th class="select-column"><input type="checkbox" aria-label="选择本页全部留言" data-select-all></th>
                <th>编号</th>
                <th class="message-column">留言内容</th>
                <th>提交时间</th>
                <th>审核状态</th>
                <th>回复状态</th>
                <th>展示状态</th>
                <th class="operation-column">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($messages['items'])): ?>
                <tr><td colspan="8" class="admin-empty-state">没有符合当前筛选条件的留言。</td></tr>
            <?php endif; ?>
            <?php foreach ($messages['items'] as $message): ?>
                <?php
                $isSelected = $selectedMessage && (int) $selectedMessage['id'] === (int) $message['id'];
                $replyStatus = $message['reply_status'];
                ?>
                <tr class="<?= $isSelected ? 'is-selected' : '' ?> <?= $message['deleted_at'] ? 'is-deleted' : '' ?>" <?= $isSelected ? 'aria-selected="true"' : '' ?>>
                    <td class="select-column">
                        <input type="checkbox" name="ids[]" value="<?= (int) $message['id'] ?>" form="batch-form" aria-label="选择留言 <?= (int) $message['id'] ?>" data-row-select>
                    </td>
                    <td>#<?= (int) $message['id'] ?></td>
                    <td class="message-column">
                        <a class="message-title-link" href="<?= e(admin_list_url($filters, array('selected' => (int) $message['id']))) ?>"><?= e($message['title']) ?></a>
                        <p><?= e($message['content']) ?></p>
                    </td>
                    <td><time datetime="<?= e(date('c', strtotime($message['created_at']))) ?>"><?= e(date('Y-m-d H:i', strtotime($message['created_at']))) ?></time></td>
                    <td><span class="admin-status status-audit-<?= e($message['audit_status']) ?>"><?= e($auditLabels[$message['audit_status']]) ?></span></td>
                    <td><span class="admin-status status-reply-<?= e($replyStatus) ?>"><?= e($replyLabels[$replyStatus]) ?></span></td>
                    <td>
                        <?php if ($message['deleted_at']): ?>
                            <span class="admin-status status-deleted">回收站</span>
                        <?php else: ?>
                            <span class="admin-status status-display-<?= e($message['display_status']) ?>"><?= e($displayLabels[$message['display_status']]) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="operation-column"><a class="admin-button admin-button-small <?= $isSelected ? 'admin-button-primary' : 'admin-button-secondary' ?>" href="<?= e(admin_list_url($filters, array('selected' => (int) $message['id']))) ?>"><?= $isSelected ? '处理中' : '处理' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($messages['total_pages'] > 1): ?>
        <nav class="admin-pagination" aria-label="留言分页">
            <?php foreach (pagination_items($messages['page'], $messages['total_pages']) as $item): ?>
                <?php if ($item === 'ellipsis'): ?>
                    <span aria-hidden="true">…</span>
                <?php else: ?>
                    <a href="<?= e(admin_list_url($filters, array('page' => $item))) ?>" <?= $item === $messages['page'] ? 'aria-current="page" class="is-current"' : '' ?>><?= (int) $item ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</section>

<section class="admin-panel admin-detail-panel" aria-labelledby="current-message-title">
    <?php if ($selectedMessage): ?>
        <div class="detail-heading">
            <div>
                <p class="section-kicker">正在处理 #<?= (int) $selectedMessage['id'] ?></p>
                <h2 id="current-message-title"><?= e($selectedMessage['title']) ?></h2>
            </div>
            <div class="detail-statuses">
                <span class="admin-status status-audit-<?= e($selectedMessage['audit_status']) ?>"><?= e($auditLabels[$selectedMessage['audit_status']]) ?></span>
                <span class="admin-status status-reply-<?= e($selectedMessage['reply_status']) ?>"><?= e($replyLabels[$selectedMessage['reply_status']]) ?></span>
                <?php if ($selectedMessage['deleted_at']): ?>
                    <span class="admin-status status-deleted">回收站</span>
                <?php else: ?>
                    <span class="admin-status status-display-<?= e($selectedMessage['display_status']) ?>"><?= e($displayLabels[$selectedMessage['display_status']]) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-grid">
            <article class="message-detail-copy">
                <dl class="message-facts">
                    <div><dt>提交时间</dt><dd><?= e(date('Y-m-d H:i:s', strtotime($selectedMessage['created_at']))) ?></dd></div>
                    <div><dt>来源 IP</dt><dd><?= e($selectedMessage['source_ip']) ?> <span>仅管理员可见</span></dd></div>
                </dl>
                <h3>留言内容</h3>
                <p><?= nl2br(e($selectedMessage['content'])) ?></p>

                <div class="state-actions" aria-label="留言状态操作">
                    <?php if (!$selectedMessage['deleted_at']): ?>
                        <form method="post" action="<?= e(base_url('/admin/action.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>"><input type="hidden" name="action" value="approve">
                            <button class="admin-button <?= $selectedMessage['audit_status'] === 'approved' ? 'admin-button-disabled' : 'admin-button-success' ?>" type="submit" <?= $selectedMessage['audit_status'] === 'approved' ? 'disabled' : '' ?>>审核通过</button>
                        </form>
                        <form method="post" action="<?= e(base_url('/admin/action.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>"><input type="hidden" name="action" value="reject">
                            <button class="admin-button admin-button-secondary" type="submit" <?= $selectedMessage['audit_status'] === 'rejected' ? 'disabled' : '' ?>>审核驳回</button>
                        </form>
                        <form method="post" action="<?= e(base_url('/admin/action.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>"><input type="hidden" name="action" value="<?= $selectedMessage['display_status'] === 'visible' ? 'hide' : 'show' ?>">
                            <button class="admin-button admin-button-secondary" type="submit"><?= $selectedMessage['display_status'] === 'visible' ? '设为隐藏' : '恢复显示' ?></button>
                        </form>
                        <form method="post" action="<?= e(base_url('/admin/action.php')) ?>" data-confirm="确认将这条留言移至回收站吗？公开页面将不再展示，之后可以恢复。">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>"><input type="hidden" name="action" value="soft_delete">
                            <button class="admin-button admin-button-danger-secondary" type="submit">移至回收站</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(base_url('/admin/action.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>"><input type="hidden" name="action" value="restore">
                            <button class="admin-button admin-button-success" type="submit">从回收站恢复</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>

            <article class="reply-editor">
                <div class="reply-editor-heading">
                    <h3>管理员回复</h3>
                    <?php if ($selectedMessage['reply_updated_at']): ?><span>最后保存：<?= e(date('Y-m-d H:i', strtotime($selectedMessage['reply_updated_at']))) ?></span><?php endif; ?>
                </div>
                <form method="post" action="<?= e(base_url('/admin/action.php')) ?>" data-reply-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
                    <input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>">
                    <label for="reply-content" class="visually-hidden">回复内容</label>
                    <textarea id="reply-content" name="reply_content" maxlength="2000" rows="8" required <?= $selectedMessage['deleted_at'] ? 'disabled' : '' ?> aria-describedby="reply-help reply-count"><?= e($selectedMessage['reply_content']) ?></textarea>
                    <div class="reply-help-row"><span id="reply-help">回复与审核、展示状态相互独立；发布回复不会自动公开留言。</span><output id="reply-count" for="reply-content"><?= utf8_length($selectedMessage['reply_content']) ?>/2000</output></div>
                    <div class="reply-actions">
                        <button type="submit" name="action" value="save_draft" class="admin-button admin-button-secondary" <?= $selectedMessage['deleted_at'] ? 'disabled' : '' ?>>保存草稿</button>
                        <button type="submit" name="action" value="publish_reply" class="admin-button admin-button-primary" <?= $selectedMessage['deleted_at'] ? 'disabled' : '' ?> data-publish-reply>发布回复</button>
                    </div>
                </form>
            </article>
        </div>

        <div class="history-grid">
            <details>
                <summary>回复历史（<?= count($replyHistory) ?>）</summary>
                <?php if (empty($replyHistory)): ?><p class="history-empty">暂无回复记录。</p><?php endif; ?>
                <?php foreach ($replyHistory as $reply): ?>
                    <article class="history-item">
                        <div><strong><?= $reply['status'] === 'published' ? '已发布回复' : '回复草稿' ?></strong><time><?= e(date('Y-m-d H:i', strtotime($reply['updated_at']))) ?></time></div>
                        <p><?= nl2br(e($reply['content'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </details>
            <details>
                <summary>本留言操作记录（<?= count($messageLogs) ?>）</summary>
                <?php if (empty($messageLogs)): ?><p class="history-empty">暂无操作记录。</p><?php endif; ?>
                <?php foreach ($messageLogs as $log): ?>
                    <article class="history-item history-log"><div><strong><?= e(isset($logLabels[$log['action']]) ? $logLabels[$log['action']] : $log['action']) ?></strong><time><?= e(date('Y-m-d H:i', strtotime($log['created_at']))) ?></time></div><p>管理员 #<?= (int) $log['admin_id'] ?> · 来源 <?= e($log['source_ip']) ?></p></article>
                <?php endforeach; ?>
            </details>
        </div>
    <?php else: ?>
        <div class="admin-empty-state"><h2 id="current-message-title">尚未选择留言</h2><p>从上方列表中选择“处理”，即可查看全文并进行审核或回复。</p></div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
