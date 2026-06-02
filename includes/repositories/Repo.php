<?php
/**
 * Фасад для доступу до репозиторіїв.
 *
 * Це тимчасовий міст до повноцінного DI-контейнера.
 * Використання: Repo::users()->findById($id) замість User::findById($id).
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/PasteRepository.php';
require_once __DIR__ . '/TransactionRepository.php';
require_once __DIR__ . '/OrderRepository.php';
require_once __DIR__ . '/PasskeyRepository.php';
require_once __DIR__ . '/AuditLogRepository.php';

class Repo {
    /** @var array<string, object> */
    private static array $instances = [];

    /**
     * Отримати або створити репозиторій за класом.
     */
    private static function get(string $class): object {
        if (!isset(self::$instances[$class])) {
            $pdo = DB::getInstance()->getPDO();
            self::$instances[$class] = new $class($pdo);
        }
        return self::$instances[$class];
    }

    public static function users(): UserRepository {
        return self::get(UserRepository::class);
    }

    public static function pastes(): PasteRepository {
        return self::get(PasteRepository::class);
    }

    public static function transactions(): TransactionRepository {
        return self::get(TransactionRepository::class);
    }

    public static function orders(): OrderRepository {
        return self::get(OrderRepository::class);
    }

    public static function passkeys(): PasskeyRepository {
        return self::get(PasskeyRepository::class);
    }

    public static function auditLog(): AuditLogRepository {
        return self::get(AuditLogRepository::class);
    }

    /**
     * Скинути всі репозиторії (для тестування).
     */
    public static function reset(): void {
        self::$instances = [];
    }
}
