<?php

return array(
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('APP_DEBUG') === '1',
    'base_path' => rtrim(getenv('APP_BASE_PATH') ?: '/liuyanban', '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',
    'session_name' => getenv('APP_SESSION_NAME') ?: 'LIUYANBANSESSID',
    'admin_session_key' => 'liuyanban_admin',
    'login_limit' => array(
        'max_attempts' => 5,
        'window_seconds' => 300,
    ),
    'db' => array(
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'zhidui_nantong',
        'username' => getenv('DB_USERNAME') ?: 'liuyanban',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'timezone' => getenv('DB_TIMEZONE') ?: '+08:00',
    ),
    'admin' => array(
        'table' => getenv('ADMIN_TABLE') ?: 'admin',
        'id_column' => getenv('ADMIN_ID_COLUMN') ?: 'id',
        'username_column' => getenv('ADMIN_USERNAME_COLUMN') ?: 'username',
        'password_column' => getenv('ADMIN_PASSWORD_COLUMN') ?: 'password',
        'type_column' => getenv('ADMIN_TYPE_COLUMN') ?: 'user_type',
        'allowed_types' => getenv('ADMIN_ALLOWED_TYPES') ?: '1',
        'password_driver' => getenv('ADMIN_PASSWORD_DRIVER') ?: 'bcrypt',
    ),
);
