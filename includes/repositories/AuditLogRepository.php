<?php

/**
 * Репозиторій для логування аудиту дій адміністратора.
 */
class AuditLogRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Отримати IP-адресу клієнта.
     */
    private function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        return $ip;
    }

    /**
     * Записати дію адміністратора в журнал аудиту.
     */
    public function log(string $admin_id, string $action_type, ?string $target_id = null): bool {
        try {
            $ip = $this->getClientIp();
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (admin_id, action_type, target_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$admin_id, $action_type, $target_id, $ip]);
        } catch (Exception $e) {
            error_log("Помилка логування аудиту дій адміна: " . $e->getMessage());
            return false;
        }
    }
}
