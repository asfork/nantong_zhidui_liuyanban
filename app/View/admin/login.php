<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理员登录 - 留言管理后台</title>
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/admin.css')) ?>">
</head>
<body class="admin-body login-body">
<a class="skip-link" href="#login-main">跳到登录表单</a>
<main id="login-main" class="login-shell">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-heading">
            <p class="section-kicker">独立留言板</p>
            <h1 id="login-title">管理员登录</h1>
            <p>使用已授权的老系统管理员账号登录。留言板不会修改管理员账号或密码。</p>
        </div>

        <?php if ($flash): ?>
            <div class="admin-alert admin-alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert-error" role="alert" tabindex="-1" data-auto-focus>
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('/admin/login.php')) ?>" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field-group">
                <label for="username">用户名</label>
                <input id="username" name="username" type="text" maxlength="64" autocomplete="username" required value="<?= e($username) ?>">
            </div>
            <div class="field-group">
                <label for="password">密码</label>
                <input id="password" name="password" type="password" maxlength="200" autocomplete="current-password" required>
            </div>
            <button type="submit" class="primary-button login-submit">登录留言管理后台</button>
        </form>
        <a class="login-back-link" href="<?= e(base_url('/')) ?>">返回公开留言板</a>
    </section>
</main>
<script src="<?= e(base_url('/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
