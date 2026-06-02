<?php

/**
 * Клас AuditLog — Логування дій адміністратора.
 *
 * Персистентність винесено в AuditLogRepository.
 */
class AuditLog {
    /** @deprecated Використовуйте Repo::auditLog()->log(...) */
    public static function log(string $admin_id, string $action_type, ?string $target_id = null): bool {
        return Repo::auditLog()->log($admin_id, $action_type, $target_id);
    }
}
