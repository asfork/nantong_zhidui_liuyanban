<?php

final class Connection
{
    public static function make(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $pdo = new PDO($dsn, $config['username'], $config['password'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
        $timezone = isset($config['timezone']) ? (string) $config['timezone'] : '+08:00';
        if (!preg_match('/^[+-](?:0\d|1[0-4]):[0-5]\d$/', $timezone)) {
            throw new RuntimeException('数据库时区偏移配置不合法。');
        }
        $statement = $pdo->prepare('SET time_zone = :timezone');
        $statement->execute(array(':timezone' => $timezone));

        return $pdo;
    }
}
