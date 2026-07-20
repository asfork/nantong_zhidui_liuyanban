<?php

final class AdminAuth
{
    private $pdo;
    private $config;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function attempt($username, $password)
    {
        $table = $this->identifier($this->config['table']);
        $idColumn = $this->identifier($this->config['id_column']);
        $usernameColumn = $this->identifier($this->config['username_column']);
        $passwordColumn = $this->identifier($this->config['password_column']);
        $typeColumn = $this->identifier($this->config['type_column']);

        $sql = sprintf(
            'SELECT `%s` AS id, `%s` AS username, `%s` AS password_hash, `%s` AS user_type FROM `%s` WHERE `%s` = :username LIMIT 1',
            $idColumn,
            $usernameColumn,
            $passwordColumn,
            $typeColumn,
            $table,
            $usernameColumn
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array(':username' => $username));
        $row = $statement->fetch();

        $hash = $row ? (string) $row['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $passwordMatches = $this->verifyPassword($password, $hash);
        if (!$row || !$passwordMatches || !$this->typeAllowed($row['user_type'])) {
            return null;
        }

        return array(
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'user_type' => (string) $row['user_type'],
        );
    }

    private function verifyPassword($password, $hash)
    {
        $driver = strtolower((string) $this->config['password_driver']);
        if ($driver === 'bcrypt') {
            return password_verify($password, $hash);
        }
        if ($driver === 'md5') {
            return hash_equals(strtolower($hash), md5($password));
        }

        throw new RuntimeException('不支持的管理员密码校验驱动：' . $driver);
    }

    private function typeAllowed($type)
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) $this->config['allowed_types'])), 'strlen');

        return in_array((string) $type, $allowed, true);
    }

    private function identifier($value)
    {
        $value = (string) $value;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new RuntimeException('管理员字段映射配置不合法。');
        }

        return $value;
    }
}
