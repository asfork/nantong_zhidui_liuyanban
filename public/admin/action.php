<?php

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

if (!request_is_post() || !form_content_type_is_valid()) {
    http_response_code(405);
    exit('仅支持表单提交。');
}
if (!csrf_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    flash_set('error', '页面已过期，请重新操作。');
    redirect_to('/admin/');
}

$filters = admin_return_filters(isset($_POST['return_query']) ? $_POST['return_query'] : '');
$action = query_value($_POST, 'action');
$messageId = max(0, (int) query_value($_POST, 'message_id', '0'));
$selectedId = $messageId;
$admin = current_admin();

try {
    $repository = new AdminMessageRepository(Connection::make(app_config('db')));
    if (in_array($action, array('save_draft', 'publish_reply'), true)) {
        $content = trim(query_value($_POST, 'reply_content'));
        if ($messageId < 1) {
            throw new InvalidArgumentException('请选择要回复的留言。');
        }
        if ($content === '' || utf8_length($content) > 2000) {
            throw new InvalidArgumentException('回复内容必须填写，且不能超过 2000 个字。');
        }
        $mode = $action === 'publish_reply' ? 'published' : 'draft';
        $repository->saveReply($messageId, (int) $admin['id'], $content, $mode, request_ip());
        flash_set('success', $mode === 'published' ? '回复已发布，留言的审核和展示状态保持不变。' : '回复草稿已保存，不会在公开页面展示。');
    } else {
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array($messageId);
        $changed = $repository->updateMessageStates($ids, $action, (int) $admin['id'], request_ip());
        $labels = array(
            'approve' => '审核通过',
            'reject' => '驳回',
            'show' => '设为显示',
            'hide' => '设为隐藏',
            'soft_delete' => '移至回收站',
            'restore' => '从回收站恢复',
        );
        flash_set('success', '已完成“' . $labels[$action] . '”操作，共处理 ' . $changed . ' 条留言。');
        if ($action === 'soft_delete' && $filters['deleted'] === 'active') {
            $selectedId = 0;
        }
    }
} catch (Throwable $error) {
    flash_set('error', app_config('debug') ? $error->getMessage() : '操作未完成，请稍后重试。');
}

$changes = array('page' => $filters['page']);
if ($selectedId > 0) {
    $changes['selected'] = $selectedId;
}
redirect_to(str_replace(app_config('base_path'), '', admin_list_url($filters, $changes)));
