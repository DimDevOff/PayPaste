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
        
        $this->syncTags();
    }

    /**
     * Оновлення пасти адміністратором.
     */
    public function update() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            UPDATE pastes 
            SET title = ?, content = ?, is_paid = ?, is_private = ?, view_cost = ?, expires_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $this->title,
            $this->content,
            $this->is_paid ? 1 : 0,
            $this->is_private ? 1 : 0,
            $this->view_cost,
            $this->expires_at,
            $this->id
        ]);
        
        $this->syncTags();
        return true;
    }

    /**
     * Статичний метод для генерації стабільного кольору на основі назви тегу.
     */
    public static function getTagColor($tag) {
        $hash = md5($tag);
        // Беремо перші 6 символів хешу для кольору
        return '#' . substr($hash, 0, 6);
    }

    /**
     * Метод для очищення тексту від тегів #тег.
     */
    public static function stripTags($content) {
        // Вирізаємо #тег і один пробіл після нього (якщо є)
        return preg_replace('/#[\w\x{0400}-\x{04FF}]+\s?/u', '', $content);
    }

    /**
     * Повертає масив тегів пасти, відсортований за глобальною популярністю (кількістю використань на сайті).
     */
    public function getTagsByPopularity() {
        $db = DB::getInstance()->getPDO();
        $stmt = $db->prepare("
            SELECT t1.tag 
            FROM paste_tags t1
            JOIN (
                SELECT tag, COUNT(*) as global_count 
                FROM paste_tags 
                GROUP BY tag
            ) t2 ON t1.tag = t2.tag
            WHERE t1.paste_id = :pid
            ORDER BY t2.global_count DESC, t1.tag ASC
        ");
        $stmt->execute(['pid' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Синхронізація тегів пасти. Парсить теги з контенту та зберігає в БД.
     */
    public function syncTags() {
        $pdo = DB::getInstance()->getPDO();
        
        // Знаходимо всі теги у форматі #тег (літери, цифри, підкреслення)
        // Підтримуємо кирилицю
        preg_match_all('/#([\w\x{0400}-\x{04FF}]+)/u', $this->content, $matches);
        $tags = array_unique($matches[1]);

        // Видаляємо старі теги
        $stmt = $pdo->prepare("DELETE FROM paste_tags WHERE paste_id = ?");
        $stmt->execute([$this->id]);

        // Додаємо нові теги
        if (!empty($tags)) {
            $sql = "INSERT INTO paste_tags (paste_id, tag) VALUES ";
            $placeholders = [];
            $values = [];
            foreach ($tags as $tag) {
                if (mb_strlen($tag) > 50) $tag = mb_substr($tag, 0, 50);
                $placeholders[] = "(?, ?)";
                $values[] = $this->id;
                $values[] = mb_strtolower($tag);
            }
            $sql .= implode(', ', $placeholders);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }
    }

    /**
     * Отримання списку тегів для пасти.
     */
    public function getTags() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT tag FROM paste_tags WHERE paste_id = ?");
        $stmt->execute([$this->id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Отримання популярних тегів.
     */
    public static function getPopularTags($limit = 10) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            SELECT tag, COUNT(*) as count 
            FROM paste_tags 
            GROUP BY tag 
            ORDER BY count DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Пошук пасти за її унікальним ID. З ледачим видаленням.
     */
    public static function findById($id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM pastes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if (!$row) return null;
        
        $paste = self::instantiateFromRow($row);
        
        if ($paste->isExpired()) {
            self::delete_paste_by_admin($id);
            return null;
        }
        
        return $paste;
    }
    
    /**
     * Отримання списку всіх публічних та активних паст.
     * @param int $limit Максимальна кількість записів
     * @param string $category Категорія паст ('all', 'paid', 'free', 'user', 'anonymous')
     * @param string $tag Тег для фільтрації
     * @return Paste[]
     */
    public static function findAllPublic($limit = 20, $category = 'all', $tag = '') {
        $pdo = DB::getInstance()->getPDO();
        $sql = "SELECT p.* FROM pastes p";
        
        if ($tag !== '') {
            $sql .= " INNER JOIN paste_tags pt ON p.id = pt.paste_id";
        }
        
        $sql .= " WHERE p.is_private = 0 AND (p.expires_at IS NULL OR p.expires_at > NOW())";
        
        $params = [];
        
        if ($tag !== '') {
            $sql .= " AND pt.tag = :tag";
            $params[':tag'] = mb_strtolower($tag);
        }
        
        switch ($category) {
            case 'paid':
                $sql .= " AND p.is_paid = 1";
                break;
            case 'free':
                $sql .= " AND p.is_paid = 0";
                break;
            case 'user':
                $sql .= " AND p.user_id IS NOT NULL";
                break;
            case 'anonymous':
                $sql .= " AND p.user_id IS NULL";
                break;
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
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
        $stmt = $pdo->prepare("SELECT * FROM pastes WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
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
    public static function countAll($search = '', $tag = '') {
        $pdo = DB::getInstance()->getPDO();
        $sql = "SELECT COUNT(*) FROM pastes p";
        
        if ($tag !== '') {
            $sql .= " INNER JOIN paste_tags pt ON p.id = pt.paste_id";
        }
        
        $sql .= " WHERE (p.expires_at IS NULL OR p.expires_at > NOW())";
        
        $params = [];
        if ($search !== '') {
            $sql .= " AND (p.title LIKE :search OR p.content LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if ($tag !== '') {
            $sql .= " AND pt.tag = :tag";
            $params[':tag'] = mb_strtolower($tag);
        }
        
        if (empty($params)) {
            return $pdo->query($sql)->fetchColumn();
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Отримання масиву всіх паст (для адмін-панелі) з підтримкою пагінації.
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllPastes($limit = 25, $offset = 0, $search = '') {
        $pdo = DB::getInstance()->getPDO();
        // В адмінці показуємо всі, але можемо приховати протерміновані, або просто лишити як є.
        $sql = "SELECT * FROM pastes WHERE (expires_at IS NULL OR expires_at > NOW())";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (title LIKE :search_title OR content LIKE :search_content)";
            $params[':search_title'] = '%' . $search . '%';
            $params[':search_content'] = '%' . $search . '%';
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

