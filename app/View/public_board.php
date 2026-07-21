<?php
$filterLabels = array(
    'all' => '全部',
    'replied' => '已回复',
    'waiting' => '待回复',
);
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>网上匿名留言</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">跳到主要内容</a>

<main id="main-content" class="page-shell">
    <section class="notice-panel" aria-labelledby="page-title">
        <div class="notice-copy">
            <p class="section-kicker">政民互动</p>
            <h1 id="page-title">网上匿名留言</h1>
            <p>欢迎通过本平台留言。请遵守相关法律法规，严禁发布违法违规、涉密、侮辱诽谤及虚假信息。</p>
            <p>留言提交后将进入后台审核；审核通过后公开展示。系统记录来源 IP，仅用于安全审计，不对外公开。</p>
        </div>
        <a class="primary-button notice-action" href="#message-form">我要留言</a>
    </section>

    <?php if ($databaseError !== null): ?>
        <section class="alert alert-error" role="alert">
            <strong>留言服务暂时不可用。</strong>
            <span><?= e($databaseError) ?></span>
        </section>
    <?php endif; ?>

    <?php if ($submitted): ?>
        <section class="alert alert-success" role="status" tabindex="-1" data-auto-focus>
            <strong>留言已提交，正在等待审核。</strong>
            <span>审核通过后将在公开留言列表中展示，请勿重复提交。</span>
        </section>
    <?php endif; ?>

    <section class="message-board" aria-labelledby="message-list-title">
        <div class="board-toolbar">
            <h2 id="message-list-title" class="visually-hidden">公开留言列表</h2>
            <nav class="filter-tabs" aria-label="留言回复状态">
                <?php foreach ($filterLabels as $key => $label): ?>
                    <?php
                    $query = $key === 'all' ? '' : '?status=' . rawurlencode($key);
                    $isActive = $filter === $key;
                    ?>
                    <a
                        class="filter-tab<?= $isActive ? ' is-active' : '' ?>"
                        href="<?= e(base_url('/' . $query)) ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <?= e($label) ?> <span>(<?= (int) $counts[$key] ?>)</span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <span class="sort-label">按时间倒序</span>
        </div>

        <div class="message-list">
            <?php if (empty($messages['items'])): ?>
                <div class="empty-state">
                    <h3>暂时没有符合条件的公开留言</h3>
                    <p>你可以切换筛选条件，或提交一条新的匿名留言。</p>
                </div>
            <?php endif; ?>

            <?php foreach ($messages['items'] as $message): ?>
                <?php $hasReply = !empty($message['reply_id']); ?>
                <article class="message-card">
                    <div class="message-meta">
                        <img
                            class="anonymous-avatar"
                            src="<?= e(asset_url('/assets/images/anonymous-avatar.png')) ?>"
                            width="64"
                            height="64"
                            alt=""
                            aria-hidden="true"
                        >
                        <div class="anonymous-meta-copy">
                            <span class="anonymous-label">匿名留言</span>
                            <span class="message-number">编号 #<?= str_pad((string) $message['id'], 6, '0', STR_PAD_LEFT) ?></span>
                        </div>
                    </div>
                    <div class="message-body">
                        <div class="message-heading">
                            <div>
                                <h3><?= e($message['title']) ?></h3>
                                <p class="message-content"><?= nl2br(e($message['content'])) ?></p>
                            </div>
                            <span class="status-badge <?= $hasReply ? 'status-replied' : 'status-waiting' ?>">
                                <?= $hasReply ? '已回复' : '待回复' ?>
                            </span>
                        </div>
                        <time datetime="<?= e(date('c', strtotime($message['created_at']))) ?>">
                            提交于 <?= e(date('Y-m-d H:i', strtotime($message['created_at']))) ?>
                        </time>

                        <?php if ($hasReply): ?>
                            <details class="reply-panel">
                                <summary>查看回复（1）</summary>
                                <div class="reply-content">
                                    <h4>管理员回复</h4>
                                    <p><?= nl2br(e($message['reply_content'])) ?></p>
                                    <time datetime="<?= e(date('c', strtotime($message['reply_published_at']))) ?>">
                                        回复于 <?= e(date('Y-m-d H:i', strtotime($message['reply_published_at']))) ?>
                                    </time>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($messages['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="留言分页">
                <?php if ($messages['page'] > 1): ?>
                    <a href="<?= e(page_url($messages['page'] - 1, $filter)) ?>">上一页</a>
                <?php endif; ?>

                <?php foreach (pagination_items($messages['page'], $messages['total_pages']) as $item): ?>
                    <?php if ($item === 'ellipsis'): ?>
                        <span class="pagination-ellipsis" aria-hidden="true">…</span>
                    <?php else: ?>
                        <a
                            href="<?= e(page_url($item, $filter)) ?>"
                            <?= $item === $messages['page'] ? 'aria-current="page" class="is-current"' : '' ?>
                        ><?= (int) $item ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($messages['page'] < $messages['total_pages']): ?>
                    <a href="<?= e(page_url($messages['page'] + 1, $filter)) ?>">下一页</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section id="message-form" class="form-panel" aria-labelledby="form-title">
        <div class="form-heading">
            <div>
                <p class="section-kicker">匿名提交</p>
                <h2 id="form-title">提交匿名留言</h2>
            </div>
            <p>带“必填”的项目必须填写</p>
        </div>

        <?php if (!empty($formErrors)): ?>
            <div class="alert alert-error" role="alert" tabindex="-1" data-auto-focus>
                <strong>留言暂未提交，请检查以下内容：</strong>
                <ul>
                    <?php foreach ($formErrors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/index.php#message-form')) ?>" class="message-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="field-group">
                <div class="field-label-row">
                    <label for="title">标题 <span>必填</span></label>
                    <output id="title-count" for="title"><?= utf8_length($formData['title']) ?>/30</output>
                </div>
                <input
                    id="title"
                    name="title"
                    type="text"
                    maxlength="30"
                    required
                    autocomplete="off"
                    value="<?= e($formData['title']) ?>"
                    aria-describedby="title-help title-count"
                >
                <small id="title-help">请用简洁标题概括留言内容，不超过 30 个字。</small>
            </div>

            <div class="field-group">
                <div class="field-label-row">
                    <label for="content">留言内容 <span>必填</span></label>
                    <output id="content-count" for="content"><?= utf8_length($formData['content']) ?>/1000</output>
                </div>
                <textarea
                    id="content"
                    name="content"
                    maxlength="1000"
                    rows="7"
                    required
                    aria-describedby="content-help content-count"
                ><?= e($formData['content']) ?></textarea>
                <small id="content-help">请描述具体问题或建议，避免包含姓名、电话等个人信息。</small>
            </div>

            <fieldset class="captcha-fieldset">
                <legend>安全验证 <span>必填</span></legend>
                <div class="captcha-row">
                    <label class="captcha-question" for="captcha"><?= e(captcha_question()) ?></label>
                    <input
                        id="captcha"
                        name="captcha"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                        aria-describedby="captcha-help"
                    >
                    <a class="secondary-button" href="<?= e(base_url('/?refresh_captcha=1#message-form')) ?>">换一道题</a>
                </div>
                <small id="captcha-help">请输入算式答案，用于减少自动重复提交。</small>
            </fieldset>

            <label class="agreement-row">
                <input type="checkbox" name="agreement" value="1" required <?= $formData['agreement'] ? 'checked' : '' ?>>
                <span>我已阅读并同意留言规则，确认内容不包含个人敏感信息。</span>
            </label>

            <div class="form-actions">
                <button type="submit" class="primary-button" <?= $databaseError !== null ? 'disabled' : '' ?>>
                    提交留言（匿名发布）
                </button>
                <p>提交后不会立即公开，请等待管理员审核。</p>
            </div>
        </form>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <p>网上匿名留言 · 内网服务</p>
        <p>建议使用 Chromium 系列浏览器访问</p>
    </div>
</footer>

<script src="<?= e(asset_url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
