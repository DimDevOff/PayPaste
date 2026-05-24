<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/Paste.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/CreditService.php';

/**
 * Сервіс для управління пастами.
 * Відповідає за створення, розблокування, розрахунок вартості та бізнес-логіку паст.
 */
class PasteService {

    /**
     * Створює нову пасту з усіма перевірками та фінансовими операціями.
     * Якщо паста платна — списує кредити за створення.
     *
     * @param array $data Дані з форми (title, content, is_private, is_paid, view_cost, expires_in, language, tags)
     * @param string|null $userId ID автора (null для аноніма)
     * @param bool $isPendingRewrite Чи позначити для AI-переписування
     * @return Paste Створена паста
     * @throws Exception При помилці валідації або БД
     */
    public static function create(array $data, ?string $userId = null, bool $isPendingRewrite = false, string $moderationStatus = 'pending'): Paste {
        $title = trim($data['title'] ?? 'Без назви');
        if ($title === '') $title = 'Без назви';

        if (mb_strlen($title) > 255) {
            throw new Exception("Назва пасти занадто довга!");
        }

        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            throw new Exception("Паста не може бути порожньою!");
        }

        if (mb_strlen($content) > 100000) {
            throw new Exception("Контент пасти занадто великий!");
        }

        $isPrivate = (isset($data['is_private']) && $data['is_private'] == '1');
        $isPaid = (isset($data['is_paid']) && $data['is_paid'] == '1');
        $language = trim($data['language'] ?? 'plaintext');

        $viewCost = 0;
        $user = null;

        if ($isPaid) {
            if (!$userId) {
                throw new Exception("Тільки авторизовані користувачі можуть створювати платні пасти!");
            }

            $user = User::findById($userId);
            if (!$user) {
                throw new Exception("Користувача не знайдено!");
            }

            $creationCost = CreditService::calculateCreationCost($content);

            if (!CreditService::hasEnoughCredits($user, $creationCost)) {
                throw new Exception("Недостатньо кредитів для створення! Потрібно {$creationCost}, а у вас {$user->credits}.");
            }

            $viewCost = isset($data['view_cost']) ? (int)$data['view_cost'] : 0;
            if ($viewCost <= 0) {
                throw new Exception("Вкажіть коректну вартість перегляду!");
            }
        }

        // Обчислення TTL
        $expiresAt = null;
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 0;
        if ($expiresIn < 0) {
            throw new Exception("Час життя пасти не може бути від'ємним!");
        }
        if ($expiresIn > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiresIn * 60));
        }

        try {
            // Списання кредитів за створення платної пасти
            if ($isPaid && $user) {
                CreditService::deduct(
                    $user,
                    CreditService::calculateCreationCost($content),
                    'creation_fee',
                    'Плата за створення платної пасти'
                );
            }

            $paste = new Paste($title, $content, $userId, $isPaid, $viewCost, $isPrivate, null, null, $expiresAt, $isPendingRewrite, $moderationStatus, null, $language);
            $paste->save();

            // Синхронізація тегів
            $tagsInput = $data['tags'] ?? '';
            $paste->syncTags($tagsInput);

            // Обробка завантаження файлу
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                self::handleFileUpload($paste->id, $_FILES['attachment']);
            }

            return $paste;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Розблоковує доступ до платної пасти для користувача.
     * Списує кредити у покупця, нараховує автору, додає пасту до розблокованих.
     *
     * @param string $pasteId ID пасти
     * @param string $userId ID покупця
     * @return array Результат операції ['success' => bool, 'message' => string]
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public static function unlock(string $pasteId, string $userId): array {
        $paste = Paste::findById($pasteId);
        if (!$paste) {
            throw new Exception("Пасту не знайдено!");
        }

        if ($paste->isExpired()) {
            throw new Exception("Термін дії цієї пасти закінчився!");
        }

        $user = User::findById($userId);
        if (!$user) {
            throw new Exception("Користувача не знайдено!");
        }

        // Перевірка чи це автор або вже куплено
        if ($user->id === $paste->user_id || $user->hasUnlocked($pasteId)) {
            return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
        }

        if (!CreditService::hasEnoughCredits($user, $paste->view_cost)) {
            return ['success' => false, 'message' => "Не вистачає кредитів для покупки доступу!"];
        }

        $author = $paste->user_id ? User::findById($paste->user_id) : null;
        return CreditService::purchasePasteAccess($user, $author, $pasteId, $paste->view_cost);
    }

    /**
     * Перевіряє, чи має користувач доступ до пасти.
     *
     * @param Paste $paste Паста
     * @param User|null $user Користувач (null для гостя)
     * @return bool true якщо доступ дозволено
     */
    public static function canAccess(Paste $paste, ?User $user): bool {
        if (!$paste->is_paid && !$paste->is_private) {
            return true; // Публічна безкоштовна
        }

        $isAuthor = $user && $user->id === $paste->user_id;
        $isAdmin = $user && $user->role === 'admin';
        $hasUnlocked = $user && $user->hasUnlocked($paste->id);

        return $isAuthor || $isAdmin || $hasUnlocked || !$paste->is_paid;
    }

    /**
     * Перевіряє, чи заблокована паста для користувача (потрібна оплата).
     *
     * @param Paste $paste Паста
     * @param User|null $user Користувач
     * @return bool true якщо паста заблокована
     */
    public static function isLocked(Paste $paste, ?User $user): bool {
        if (!$paste->is_paid) {
            return false;
        }
        $isAuthor = $user && $user->id === $paste->user_id;
        $isAdmin = $user && $user->role === 'admin';
        $hasUnlocked = $user && $user->hasUnlocked($paste->id);

        return !$isAuthor && !$isAdmin && !$hasUnlocked;
    }

    /**
     * Обробляє завантаження файлу для пасти.
     *
     * @param string $pasteId ID пасти
     * @param array $file Дані з $_FILES
     * @return string Шлях до збереженого файлу
     * @throws Exception При помилці завантаження або недопустимому файлі
     */
    public static function handleFileUpload(string $pasteId, array $file): string {
        $maxSize = 5 * 1024 * 1024; // 5 МБ
        if ($file['size'] > $maxSize) {
            throw new Exception("Файл занадто великий! Максимум 5 МБ.");
        }

        $uploadDir = __DIR__ . '/../../data/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = $file['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $detectedMime = mime_content_type($file['tmp_name']);
        $allowedMimeTypes = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/x-zip'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain']
        ];

        if (!isset($allowedMimeTypes[$fileExt]) || !in_array($detectedMime, $allowedMimeTypes[$fileExt])) {
            throw new Exception("Недопустимий контент файлу для розширення .$fileExt (виявлено: $detectedMime)");
        }

        $targetFile = $uploadDir . $pasteId . '.' . $fileExt;
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            throw new Exception("Не вдалося зберегти файл.");
        }

        return $targetFile;
    }

    /**
     * Видаляє пасту та прикріплені файли.
     *
     * @param string $pasteId ID пасти
     * @param string|null $userId ID користувача (для перевірки прав, null = адмін)
     * @return bool Успішність операції
     * @throws Exception При помилці або відсутності прав
     */
    public static function delete(string $pasteId, ?string $userId = null): bool {
        $paste = Paste::findById($pasteId);
        if (!$paste) {
            throw new Exception("Пасту не знайдено!");
        }

        // Перевірка прав (власник або адмін)
        if ($userId !== null && $paste->user_id !== $userId) {
            $user = User::findById($userId);
            if (!$user || $user->role !== 'admin') {
                throw new Exception("У вас немає прав для видалення цієї пасти!");
            }
        }

        // Видалення прикріплених файлів з диска
        $uploadDir = __DIR__ . '/../../data/uploads/';
        $files = glob($uploadDir . $pasteId . '.*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM pastes WHERE id = ?");
        $stmt->execute([$pasteId]);
        return true;
    }

    /**
     * Перемикає приватність пасти (публічна ↔ приватна).
     *
     * @param string $pasteId ID пасти
     * @param string $userId ID власника
     * @return bool Новий стан приватності
     * @throws Exception При помилці або відсутності прав
     */
    public static function toggleVisibility(string $pasteId, string $userId): bool {
        $paste = Paste::findById($pasteId);
        if (!$paste) {
            throw new Exception("Пасту не знайдено!");
        }

        if ($paste->user_id !== $userId) {
            throw new Exception("У вас немає прав для зміни цієї пасти!");
        }

        $paste->is_private = !$paste->is_private;
        $paste->save();

        return $paste->is_private;
    }

    /**
     * Очищує протерміновані пасти та їх прикріплені файли.
     * Рекомендується викликати через Cron.
     *
     * @return int Кількість видалених паст
     */
    public static function cleanupExpired(): int {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT id FROM pastes WHERE expires_at IS NOT NULL AND expires_at <= NOW()");
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($ids as $id) {
            try {
                self::delete($id, null); // null = адмін/система
                $count++;
            } catch (Exception $e) {
                // Ігноруємо помилки при масовому очищенні
            }
        }
        return $count;
    }
}
