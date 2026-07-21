<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminPageTitle) ?> - 留言管理后台</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">跳到主要内容</a>
<header class="admin-header">
    <div class="admin-header-inner">
        <a class="admin-brand" href="<?= e(base_url('/admin/')) ?>">留言管理后台</a>
        <div class="admin-account">
            <span>管理员：<?= e(current_admin()['username']) ?></span>
            <nav class="admin-nav" aria-label="后台导航">
                <a href="<?= e(base_url('/')) ?>">公开留言板</a>
                <a href="<?= e(base_url('/admin/')) ?>" <?= $adminActiveNav === 'messages' ? 'aria-current="page"' : '' ?>>留言管理</a>
                <a href="<?= e(base_url('/admin/logs.php')) ?>" <?= $adminActiveNav === 'logs' ? 'aria-current="page"' : '' ?>>操作日志</a>
            </nav>
            <form method="post" action="<?= e(base_url('/admin/logout.php')) ?>" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="admin-header-button">退出</button>
            </form>
        </div>
    </div>
</header>
<main id="admin-main" class="admin-shell">
    <div class="admin-breadcrumb">当前位置：留言管理后台 &gt; <?= e($adminPageTitle) ?></div>

    <?php if ($flash): ?>
        <div class="admin-alert admin-alert-<?= e($flash['type']) ?>" role="status" tabindex="-1" data-auto-focus>
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($databaseError !== null): ?>
        <div class="admin-alert admin-alert-error" role="alert"><?= e($databaseError) ?></div>
    <?php endif; ?>
