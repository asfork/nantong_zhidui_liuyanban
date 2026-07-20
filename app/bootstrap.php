<?php

$appConfig = require dirname(__DIR__) . '/config/app.php';

date_default_timezone_set($appConfig['timezone']);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name($appConfig['session_name']);
session_set_cookie_params(0, $appConfig['base_path'] . '/', '', false, true);
session_start();

require_once __DIR__ . '/Support/functions.php';
require_once __DIR__ . '/Database/Connection.php';
require_once __DIR__ . '/Repository/MessageRepository.php';

