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
        $this->id = $id ?? 'p_' . bin2hex(random_bytes(8));
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
     * @param int $limit Максимальна кількість записів
     * @return Paste[]
     */
    public static function findAllPublic($limit = 20) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM pastes WHERE is_private = 0 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
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
    public static function countAll($search = '') {
        $pdo = DB::getInstance()->getPDO();
        if ($search !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pastes WHERE title LIKE ? OR content LIKE ?");
            $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
            return $stmt->fetchColumn();
        }
        return $pdo->query("SELECT COUNT(*) FROM pastes")->fetchColumn();
    }

    /**
     * Отримання масиву всіх паст (для адмін-панелі) з підтримкою пагінації.
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllPastes($limit = 25, $offset = 0, $search = '') {
        $pdo = DB::getInstance()->getPDO();
        $sql = "SELECT * FROM pastes";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE title LIKE :search OR content LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Видалення пасти адміністратором.
     */
    public static function delete_paste_by_admin($id) {
        $pdo = DB::getInstance()->getPDO();

        // Видалення прикріплених файлів з диска
        $uploadDir = __DIR__ . '/../../data/uploads/';
        $files = glob($uploadDir . $id . '.*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

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

    /**
     * Очищення протермінованих паст та їх файлів.
     * Викликається через Cron скриптом cron/cleanup.php.
     */
    public static function cleanupExpired() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->query("SELECT id FROM pastes WHERE expires_at IS NOT NULL AND expires_at <= NOW()");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $count = 0;
        foreach ($ids as $id) {
            if (self::delete_paste_by_admin($id)) {
                $count++;
            }
        }
        return $count;
    }
}

