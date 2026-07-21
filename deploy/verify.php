<?php

require dirname(__DIR__) . '/app/bootstrap.php';

$failures = 0;

function deployment_check($condition, $message)
{
    global $failures;

    if ($condition) {
        fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
        return;
    }

    $failures++;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

function deployment_identifier($value)
{
    if (!is_string($value) || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new RuntimeException('管理员表字段映射包含非法标识符。');
    }

    return '`' . $value . '`';
}

deployment_check(PHP_VERSION === '7.3.4', 'PHP 版本精确为 7.3.4，当前：' . PHP_VERSION);
foreach (array('PDO', 'pdo_mysql', 'session', 'json', 'openssl') as $extension) {
    deployment_check(extension_loaded($extension), 'PHP 扩展已启用：' . $extension);
}
deployment_check(app_config('env') === 'production', 'APP_ENV=production');
deployment_check(app_config('debug') === false, 'APP_DEBUG 已关闭');
deployment_check(app_config('base_path') === '/liuyanban', '基础路径为 /liuyanban');
deployment_check(app_config('session_name') === 'LIUYANBANSESSID', '使用独立 Session Cookie 名称');
deployment_check(is_dir(dirname(__DIR__) . '/var/log') && is_writable(dirname(__DIR__) . '/var/log'), 'var/log 可写');
deployment_check(is_dir(dirname(__DIR__) . '/var/session') && is_writable(dirname(__DIR__) . '/var/session'), 'var/session 可写');

try {
    $pdo = Connection::make(app_config('db'));
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    deployment_check(strpos($version, '5.7.26') === 0, 'MySQL 版本为 5.7.26，当前：' . $version);
    deployment_check((string) $pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+08:00', '数据库会话时区为 +08:00');

    foreach (array('liuyan_message', 'liuyan_reply', 'liuyan_operation_log') as $table) {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(array(':table_name' => $table));
        deployment_check((int) $statement->fetchColumn() === 1, '业务表存在：' . $table);
    }

    $adminConfig = app_config('admin');
    $adminTable = deployment_identifier($adminConfig['table']);
    $adminId = deployment_identifier($adminConfig['id_column']);
    $adminUsername = deployment_identifier($adminConfig['username_column']);
    $adminPassword = deployment_identifier($adminConfig['password_column']);
    $adminType = deployment_identifier($adminConfig['type_column']);
    $adminStatement = $pdo->query(
        'SELECT ' . $adminId . ', ' . $adminUsername . ', ' . $adminPassword . ', ' . $adminType
        . ' FROM ' . $adminTable . ' LIMIT 1'
    );
    deployment_check($adminStatement !== false, '管理员表及字段映射可只读访问');

    $replyColumns = $pdo->query("SHOW COLUMNS FROM liuyan_reply WHERE Field IN ('status', 'published_at')")->fetchAll();
    deployment_check(count($replyColumns) === 2, '回复状态和发布时间字段存在');
} catch (Throwable $error) {
    $failures++;
    fwrite(STDERR, '[FAIL] 数据库检查失败：' . $error->getMessage() . PHP_EOL);
}

if ($failures > 0) {
    fwrite(STDERR, 'Deployment verification failed with ' . $failures . ' error(s).' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Deployment verification complete.' . PHP_EOL);
