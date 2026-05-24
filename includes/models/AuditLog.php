<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Модель для логування та аудиту дій адміністратора.
 */
class AuditLog {
    public $id;
    public $admin_id;
    public $action_type;
    public $target_id;
    public $ip_address;
    public $created_at;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->admin_id = $data['admin_id'] ?? null;
        $this->action_type = $data['action_type'] ?? null;
        $this->target_id = $data['target_id'] ?? null;
        $this->ip_address = $data['ip_address'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    /**
     * Отримати IP-адресу клієнта.
     * Враховує можливі заголовки проксі.
     */
    private static function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Може містити список IP через кому, беремо перший
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        return $ip;
    }

    /**
     * Записати дію адміністратора в журнал аудиту.
     * 
     * @param string $admin_id ID адміністратора, який виконує дію
     * @param string $action_type Тип дії (наприклад, delete_paste, delete_user, approve_moderation, reject_moderation, edit_settings)
     * @param string|null $target_id ID цільового об'єкта (пасти, користувача тощо)
     * @return bool Результат виконання запиту
     */
    public static function log(string $admin_id, string $action_type, ?string $target_id = null): bool {
        try {
            $ip = self::getClientIp();
            $pdo = DB::getInstance()->getPDO();
            
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (admin_id, action_type, target_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([$admin_id, $action_type, $target_id, $ip]);
        } catch (Exception $e) {
            // Записуємо помилку в системний лог, щоб не ламати роботу сайту
            error_log("Помилка логування аудиту дій адміна: " . $e->getMessage());
            return false;
        }
    }
}
