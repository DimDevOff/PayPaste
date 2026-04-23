<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Модель для роботи з пастами (текстовими фрагментами).
 */
class Paste {
    public $id;
    public $title;
    public $content;
    public $user_id;
    public $is_paid;
    public $is_private;
    public $view_cost;
    public $created_at;
    public $expires_at;

    /**
     * Конструктор пасти.
     */
    public function __construct($title, $content, $user_id = null, $is_paid = false, $view_cost = 0, $is_private = false, $id = null, $created_at = null, $expires_at = null) {
        $this->title = trim($title);
        $this->content = trim($content);
        $this->user_id = $user_id;
        $this->is_paid = (bool)$is_paid;
        $this->view_cost = (int)$view_cost;
        $this->is_private = (bool)$is_private;
        $this->id = $id ?? uniqid('p_');
        $this->created_at = $created_at ?? date('Y-m-d H:i:s');
        $this->expires_at = $expires_at;
    }

    /**
     * Створення об'єкта Paste з рядка бази даних.
     */
    private static function instantiateFromRow($row) {
        if (!$row) return null;
        return new self($row['title'], $row['content'], $row['user_id'], $row['is_paid'], $row['view_cost'], $row['is_private'], $row['id'], $row['created_at'], $row['expires_at']);
    }

    /**
     * Перевірка чи закінчився термін дії пасти.
     */
    public function isExpired() {
        if ($this->expires_at === null) return false;
        return strtotime($this->expires_at) <= time();
    }

    /**
     * Збереження пасти в базу даних (Insert або Update).
     */
    public function save() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO pastes (id, title, content, user_id, is_paid, is_private, view_cost, created_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                content = VALUES(content),
                user_id = VALUES(user_id),
                is_paid = VALUES(is_paid),
                is_private = VALUES(is_private),
                view_cost = VALUES(view_cost),
                expires_at = VALUES(expires_at)
        ");
        $stmt->execute([
            $this->id,
            $this->title,
            $this->content,
            $this->user_id,
            $this->is_paid ? 1 : 0,
            $this->is_private ? 1 : 0,
            $this->view_cost,
            $this->created_at,
            $this->expires_at
        ]);
    }

    /**
     * Пошук пасти за її унікальним ID.
     */
    public static function findById($id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM pastes WHERE id = ?");
        $stmt->execute([$id]);
        return self::instantiateFromRow($stmt->fetch());
    }
    
    /**
     * Отримання списку всіх публічних та активних паст.
     */
    public static function findAllPublic() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->query("SELECT * FROM pastes WHERE is_private = 0 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = self::instantiateFromRow($row);
        }
        return $result;
    }

    /**
     * Отримання всіх паст конкретного користувача.
     */
    public static function findByUserId($user_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM pastes WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = self::instantiateFromRow($row);
        }
        return $result;
    }

    /**
     * Підрахунок загальної кількості паст у системі.
     */
    public static function countAll() {
        $pdo = DB::getInstance()->getPDO();
        return $pdo->query("SELECT COUNT(*) FROM pastes")->fetchColumn();
    }

    /**
     * Отримання масиву всіх паст (для адмін-панелі).
     */
    public static function getAllPastes() {
        $pdo = DB::getInstance()->getPDO();
        return $pdo->query("SELECT * FROM pastes")->fetchAll();
    }

    /**
     * Видалення пасти адміністратором.
     */
    public static function delete_paste_by_admin($id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM pastes WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }

    /**
     * Видалення поточної пасти.
     */
    public function delete() {
        self::delete_paste_by_admin($this->id);
    }
}

