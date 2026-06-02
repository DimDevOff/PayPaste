<?php
require_once __DIR__ . '/../models/Paste.php';

/**
 * Репозиторій для роботи з пастами.
 * Відокремлює SQL-запити від бізнес-логіки моделі Paste.
 */
class PasteRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ── Factory ──

    public function instantiateFromRow(?array $row): ?Paste {
        if (!$row) return null;
        return new Paste(
            $row['title'],
            $row['content'],
            $row['user_id'],
            $row['is_paid'],
            $row['view_cost'],
            $row['is_private'],
            $row['id'],
            $row['created_at'],
            $row['expires_at'],
            $row['is_pending_rewrite'],
            $row['moderation_status'] ?? 'pending',
            $row['moderation_result'] ?? null,
            $row['language'] ?? 'plaintext'
        );
    }

    // ── Пошук ──

    public function findById(string $id): ?Paste {
        $stmt = $this->pdo->prepare("SELECT * FROM pastes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) return null;

        $paste = $this->instantiateFromRow($row);

        if ($paste->isExpired()) {
            return null;
        }

        return $paste;
    }

    /**
     * @return Paste[]
     */
    public function findAllPublic(int $limit = 20, string $category = 'all', string $tag = ''): array {
        $sql = "SELECT p.* FROM pastes p";

        if ($tag !== '') {
            $sql .= " INNER JOIN paste_tags pt ON p.id = pt.paste_id";
        }

        $sql .= " WHERE p.is_private = 0 AND p.is_pending_rewrite = 0 AND p.moderation_status = 'approved' AND (p.expires_at IS NULL OR p.expires_at > NOW())";

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

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = $this->instantiateFromRow($row);
        }
        return $result;
    }

    /**
     * @return Paste[]
     */
    public function findByUserId(string $user_id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM pastes WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = $this->instantiateFromRow($row);
        }
        return $result;
    }

    // ── Збереження ──

    public function save(Paste $paste): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO pastes (id, title, content, user_id, is_paid, is_private, view_cost, created_at, expires_at, is_pending_rewrite, moderation_status, moderation_result, language)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                content = VALUES(content),
                user_id = VALUES(user_id),
                is_paid = VALUES(is_paid),
                is_private = VALUES(is_private),
                view_cost = VALUES(view_cost),
                expires_at = VALUES(expires_at),
                is_pending_rewrite = VALUES(is_pending_rewrite),
                moderation_status = VALUES(moderation_status),
                moderation_result = VALUES(moderation_result),
                language = VALUES(language)
        ");
        $stmt->execute([
            $paste->id,
            $paste->title,
            $paste->content,
            $paste->user_id,
            $paste->is_paid ? 1 : 0,
            $paste->is_private ? 1 : 0,
            $paste->view_cost,
            $paste->created_at,
            $paste->expires_at,
            $paste->is_pending_rewrite ? 1 : 0,
            $paste->moderation_status,
            $paste->moderation_result,
            $paste->language
        ]);
    }

    public function update(Paste $paste): void {
        $stmt = $this->pdo->prepare("
            UPDATE pastes 
            SET title = ?, content = ?, is_paid = ?, is_private = ?, view_cost = ?, 
                expires_at = ?, language = ?, is_pending_rewrite = ?, 
                moderation_status = ?, moderation_result = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $paste->title,
            $paste->content,
            $paste->is_paid ? 1 : 0,
            $paste->is_private ? 1 : 0,
            $paste->view_cost,
            $paste->expires_at,
            $paste->language,
            $paste->is_pending_rewrite ? 1 : 0,
            $paste->moderation_status,
            $paste->moderation_result,
            $paste->id
        ]);
    }

    // ── Теги ──

    /**
     * @return string[]
     */
    public function getTags(string $pasteId): array {
        $stmt = $this->pdo->prepare("SELECT tag FROM paste_tags WHERE paste_id = ?");
        $stmt->execute([$pasteId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return string[]
     */
    public function getTagsByPopularity(string $pasteId): array {
        $stmt = $this->pdo->prepare("
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
        $stmt->execute(['pid' => $pasteId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Синхронізація тегів — парсить з контенту та зберігає в БД.
     */
    public function syncTags(string $pasteId, string $tagsInput = ''): void {
        $tags = preg_split('/[,\s]+/', $tagsInput, -1, PREG_SPLIT_NO_EMPTY);

        $final_tags = array_map(function ($tag) {
            return mb_strtolower(trim(ltrim($tag, '#')));
        }, $tags);

        $final_tags = array_unique(array_filter($final_tags));

        // Видаляємо старі теги
        $stmt = $this->pdo->prepare("DELETE FROM paste_tags WHERE paste_id = ?");
        $stmt->execute([$pasteId]);

        // Додаємо нові теги
        if (!empty($final_tags)) {
            $sql = "INSERT INTO paste_tags (paste_id, tag) VALUES ";
            $placeholders = [];
            $values = [];
            foreach ($final_tags as $tag) {
                if (mb_strlen($tag) > 50) $tag = mb_substr($tag, 0, 50);
                $placeholders[] = "(?, ?)";
                $values[] = $pasteId;
                $values[] = $tag;
            }
            $sql .= implode(', ', $placeholders);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        }
    }

    /**
     * @return array rows with tag + count
     */
    public function getPopularTags(int $limit = 10): array {
        $stmt = $this->pdo->prepare("
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

    // ── Агрегатні / адмін ──

    public function countAll(string $search = '', string $tag = ''): int {
        $sql = "SELECT COUNT(*) FROM pastes p";

        if ($tag !== '') {
            $sql .= " INNER JOIN paste_tags pt ON p.id = pt.paste_id";
        }

        $sql .= " WHERE p.is_pending_rewrite = 0 AND p.moderation_status = 'approved' AND (p.expires_at IS NULL OR p.expires_at > NOW())";

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
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * @return array rows from DB (not Paste objects; for admin panel)
     */
    public function getAllPastes(int $limit = 25, int $offset = 0, string $search = ''): array {
        $sql = "SELECT * FROM pastes WHERE (expires_at IS NULL OR expires_at > NOW())";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (title LIKE :search_title OR content LIKE :search_content)";
            $params[':search_title'] = '%' . $search . '%';
            $params[':search_content'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
