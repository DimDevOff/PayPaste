<?php

// Захист від прямого веб-доступу до цього файлу
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access denied');
}

require_once __DIR__ . '/config.php';

class DB {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = DB_HOST ?: '127.0.0.1';
        $db   = DB_NAME ?: 'paypaste';
        $user = DB_USER ?: 'root';
        $pass = DB_PASS ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            error_log("Помилка підключення: " . $e->getMessage());
            die("Помилка підключення до бази даних. Перевірте логи.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO() {
        return $this->pdo;
    }
}


