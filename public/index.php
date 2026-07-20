<?php

require dirname(__DIR__) . '/app/bootstrap.php';

function valid_filter($value)
{
    return in_array($value, array('all', 'replied', 'waiting'), true) ? $value : 'all';
}

function page_url($page, $filter)
{
    $query = array('page' => (int) $page);
    if ($filter !== 'all') {
        $query['status'] = $filter;
    }

    return base_url('/?' . http_build_query($query));
}

if (isset($_GET['refresh_captcha'])) {
    generate_captcha();
    redirect_to('/#message-form');
}

$databaseError = null;
$repository = null;

try {
    $repository = new MessageRepository(Connection::make(app_config('db')));
} catch (Throwable $error) {
    $databaseError = app_config('debug') ? $error->getMessage() : '请稍后重试或联系管理员。';
}

$formErrors = array();
$formData = array(
    'title' => '',
    'content' => '',
    'agreement' => false,
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim(isset($_POST['title']) ? (string) $_POST['title'] : '');
    $formData['content'] = trim(isset($_POST['content']) ? (string) $_POST['content'] : '');
    $formData['agreement'] = isset($_POST['agreement']) && $_POST['agreement'] === '1';

    if (!csrf_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        $formErrors[] = '页面已过期，请刷新后重新提交。';
    }
    if ($formData['title'] === '' || utf8_length($formData['title']) > 30) {
        $formErrors[] = '标题必须填写，且不能超过 30 个字。';
    }
    if ($formData['content'] === '' || utf8_length($formData['content']) > 1000) {
        $formErrors[] = '留言内容必须填写，且不能超过 1000 个字。';
    }
    if (!captcha_is_valid(isset($_POST['captcha']) ? $_POST['captcha'] : '')) {
        $formErrors[] = '安全验证答案不正确。';
    }
    if (!$formData['agreement']) {
        $formErrors[] = '请阅读并同意留言规则。';
    }
    if ($repository === null) {
        $formErrors[] = '留言服务暂时不可用，当前无法提交。';
    }

    $lastSubmissionAt = isset($_SESSION['last_submission_at']) ? (int) $_SESSION['last_submission_at'] : 0;
    if ($lastSubmissionAt > 0 && time() - $lastSubmissionAt < 10) {
        $formErrors[] = '提交过于频繁，请稍后再试。';
    }

    if (empty($formErrors)) {
        $repository->createPending($formData['title'], $formData['content'], request_ip());
        $_SESSION['last_submission_at'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        generate_captcha();
        redirect_to('/?submitted=1#message-form');
    }

    generate_captcha();
}

$filter = valid_filter(isset($_GET['status']) ? (string) $_GET['status'] : 'all');
$page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
$counts = array('all' => 0, 'replied' => 0, 'waiting' => 0);
$messages = array('items' => array(), 'page' => 1, 'total' => 0, 'total_pages' => 1);

if ($repository !== null) {
    $counts = $repository->publicCounts();
    $messages = $repository->publicMessages($filter, $page, 5);
}

$submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';

require dirname(__DIR__) . '/app/View/public_board.php';
