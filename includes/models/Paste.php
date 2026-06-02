<?php
/**
 * Клас Paste — Доменна модель пасти.
 *
 * Містить лише дані та бізнес-логіку.
 * Персистентність (SQL) винесено в PasteRepository.
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
    public $is_pending_rewrite;
    public $moderation_status;
    public $moderation_result;
    public $language;

    public function __construct(
        $title,
        $content,
        $user_id = null,
        $is_paid = false,
        $view_cost = 0,
        $is_private = false,
        $id = null,
        $created_at = null,
        $expires_at = null,
        $is_pending_rewrite = false,
        $moderation_status = 'pending',
        $moderation_result = null,
        $language = 'plaintext'
    ) {
        $this->title = trim($title);
        $this->content = trim($content);
        $this->user_id = $user_id;
        $this->is_paid = (bool)$is_paid;
        $this->view_cost = (int)$view_cost;
        $this->is_private = (bool)$is_private;
        $this->id = $id ?? 'p_' . bin2hex(random_bytes(8));
        $this->created_at = $created_at ?? date('Y-m-d H:i:s');
        $this->expires_at = $expires_at;
        $this->is_pending_rewrite = (bool)$is_pending_rewrite;
        $this->moderation_status = $moderation_status ?: 'pending';
        $this->moderation_result = $moderation_result;
        $this->language = $language ?: 'plaintext';
    }

    // ── Бізнес-логіка ──

    /** Перевіряє, чи закінчився термін дії пасти. */
    public function isExpired(): bool {
        if ($this->expires_at === null) return false;
        return strtotime($this->expires_at) <= time();
    }

    /** Генерація стабільного кольору для тегу на основі його назви. */
    public static function getTagColor(string $tag): string {
        return '#' . substr(md5($tag), 0, 6);
    }

    /** Видаляє #теги з контенту. */
    public static function stripTags(string $content): string {
        return preg_replace('/#[\w\x{0400}-\x{04FF}]+\s?/u', '', $content);
    }

    // ── Персистентність (делегує до Repository) ──

    /** @deprecated Використовуйте Repo::pastes()->save($paste) */
    public function save(): void {
        Repo::pastes()->save($this);
    }

    /** @deprecated Використовуйте Repo::pastes()->update($paste) */
    public function update(): void {
        Repo::pastes()->update($this);
    }

    /** @deprecated Використовуйте Repo::pastes()->getTags($this->id) */
    public function getTags(): array {
        return Repo::pastes()->getTags($this->id);
    }

    /** @deprecated Використовуйте Repo::pastes()->getTagsByPopularity($this->id) */
    public function getTagsByPopularity(): array {
        return Repo::pastes()->getTagsByPopularity($this->id);
    }

    /** @deprecated Використовуйте Repo::pastes()->syncTags($this->id, $tagsInput) */
    public function syncTags(string $tagsInput = ''): void {
        Repo::pastes()->syncTags($this->id, $tagsInput);
    }

    // ── Статичні методи для зворотної сумісності ──

    /** @deprecated */
    public static function findById($id): ?self {
        return Repo::pastes()->findById($id);
    }

    /** @deprecated */
    public static function findAllPublic($limit = 20, $category = 'all', $tag = ''): array {
        return Repo::pastes()->findAllPublic($limit, $category, $tag);
    }

    /** @deprecated */
    public static function findByUserId($user_id): array {
        return Repo::pastes()->findByUserId($user_id);
    }

    /** @deprecated */
    public static function countAll($search = '', $tag = ''): int {
        return Repo::pastes()->countAll($search, $tag);
    }

    /** @deprecated */
    public static function getAllPastes($limit = 25, $offset = 0, $search = ''): array {
        return Repo::pastes()->getAllPastes($limit, $offset, $search);
    }

    /** @deprecated */
    public static function getPopularTags($limit = 10): array {
        return Repo::pastes()->getPopularTags($limit);
    }
}
