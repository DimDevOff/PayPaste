<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії
require_once __DIR__ . '/../models/Paste.php'; // Завантаження моделі пасти
require_once __DIR__ . '/../models/User.php'; // Завантаження моделі користувача
require_once __DIR__ . '/../services/PasteService.php'; // Завантаження PasteService
require_once __DIR__ . '/../services/CreditService.php'; // Завантаження CreditService
require_once __DIR__ . '/../csrf.php'; // Завантаження CSRF
require_once __DIR__ . '/../RateLimiter.php'; // Завантаження RateLimiter
require_once __DIR__ . '/../Moderation.php'; // Завантаження Модерації
require_once __DIR__ . '/../Queue.php'; // Завантаження Черги

class PasteController { // Клас контролера паст
    public function handleRequest() { // Обробка запитів
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Перевірка методу запиту
            verify_csrf(); // Перевірка CSRF
            if (isset($_POST['action']) && $_POST['action'] === 'create_paste') { // Створення пасти
                $this->create($_POST);
            } elseif (isset($_POST['action']) && $_POST['action'] === 'unlock_paste') { // Розблокування пасти
                $this->unlock($_POST);
            } elseif (isset($_POST['action']) && $_POST['action'] === 'rewrite_and_publish') { // Перефразування AI
                $this->rewriteAndPublish($_POST);
            }
        }
    }

    /*
    Функція створення пасти.
    Перед початком роботи перевіряє чи назва пасти не занадто довга.
    Потім встановлює налаштування пасти.
    Потім перевіряє чи паста не порожня.
    Потім перевіряє чи контент пасти не занадто великий.
    Потім перевіряє чи користувач має достатньо кредитів для створення платної пасти.
    
    Якщо всі перевірки пройдені, то користувач створює пасту.
    Модерація через OpenAI виконується асинхронно через чергу.
    */
    private function create($data, $is_pending_rewrite = false) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        if (!RateLimiter::checkAction('create_paste', 5, 60, ['user_id' => $user_id, 'ip_limit' => 120])) {
            $_SESSION['error'] = "Занадто багато спроб створення паст. Спробуйте пізніше.";
            header("Location: create.php");
            exit;
        }

        $_SESSION['old_input'] = $data; // Збереження введених даних для UX

        // --- МОДЕРАЦІЯ: лише локальна перевірка синхронно ---
        $content = trim($data['content'] ?? '');
        $skip_moderation = $is_pending_rewrite;
        if (!$skip_moderation) {
            // Локальна перевірка (швидка, без зовнішніх API)
            $localViolations = Moderation::localCheck($content);
            if ($localViolations) {
                $_SESSION['error'] = "Текст не пройшов автоматичну модерацію та містить заборонений контент!";
                $_SESSION['moderation_failed'] = true;
                $_SESSION['flagged_categories'] = $localViolations;
                header("Location: create.php");
                exit;
            }
        }
        // Очищаємо прапорець модерації, якщо все добре або ми її пропустили
        unset($_SESSION['moderation_failed']);
        unset($_SESSION['flagged_categories']);

        try {
            // Якщо це NOT a rewrite і NOT skip_moderation — ставимо статус "pending"
            // і enqueue-мо задачу модерації через OpenAI
            $moderationStatus = 'approved'; // За замовчуванням
            $needsAsyncModeration = false;

            if (!$skip_moderation && !$is_pending_rewrite) {
                // Локальна перевірка пройшла, але потрібна зовнішня через OpenAI
                $moderationStatus = 'pending';
                $needsAsyncModeration = true;
            }

            $paste = PasteService::create($data, $user_id, $is_pending_rewrite, $moderationStatus);

            // Постановка задачі модерації у чергу (асинхронна перевірка через OpenAI)
            if ($needsAsyncModeration) {
                Queue::push(
                    Queue::TYPE_MODERATION_CHECK,
                    [
                        'paste_id' => $paste->id,
                        'content'  => $content
                    ],
                    'mod_check:' . $paste->id // Ідемпотентність: одна перевірка на пасту
                );
            }

            // Постановка задачі AI-переписування у чергу
            if ($is_pending_rewrite) {
                Queue::push(
                    Queue::TYPE_MODERATION_REWRITE,
                    [
                        'paste_id' => $paste->id,
                        'content'  => $content
                    ],
                    'mod_rewrite:' . $paste->id
                );
            }

            unset($_SESSION['old_input']); // Очищення введених даних при успіху

            if ($needsAsyncModeration) {
                $_SESSION['success'] = "Пасту створено! Вона проходить перевірку модерації та незабаром стане доступною.";
            }

            header("Location: view.php?id=" . $paste->id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Помилка створення пасти: " . $e->getMessage();
            header("Location: create.php");
            exit;
        }
    }

    /*
    Функція розблокування пасти.
    Перед початком роботи перевіряє чи користувач авторизований.
    Потім перевіряє чи користувач вже має доступ до цієї пасти.
    Потім перевіряє чи достатньо кредитів для покупки.
    Потім списує кредити у покупця та нараховує їх автору.
    Якщо все пройшло успішно, то доступ до пасти відкривається.
    */
    private function unlock($data) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        if (!RateLimiter::checkAction('unlock_paste', 10, 60, ['user_id' => $user_id, 'ip_limit' => 200])) {
            $_SESSION['error'] = "Занадто багато спроб розблокування. Спробуйте пізніше.";
            header("Location: index.php");
            exit;
        }

        $paste_id = $data['paste_id'] ?? null;
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error'] = "Увійдіть, щоб купувати доступ!";
            header("Location: login.php");
            return;
        }

        try {
            $result = PasteService::unlock($paste_id, $_SESSION['user_id']);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
            header("Location: view.php?id=" . $paste_id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Помилка під час транзакції: " . $e->getMessage();
            header("Location: view.php?id=" . $paste_id);
            exit;
        }
    }

    /**
     * Метод для автоматичного перефразування та публікації пасти.
     * Постановка задачі у чергу замість синхронного виклику Ollama.
     */
    private function rewriteAndPublish($data) {
        if (!isset($_SESSION['user_id']) && isset($data['is_paid']) && $data['is_paid']) {
            $_SESSION['error'] = "Тільки авторизовані користувачі можуть створювати платні пасти!";
            header("Location: login.php");
            exit;
        }

        $content = $data['content'] ?? '';
        if (empty($content)) {
            $_SESSION['error'] = "Відсутній текст для перефразування!";
            header("Location: create.php");
            exit;
        }
        // Замість очікування, ми створюємо пасту зі статусом "в черзі"
        // skip_moderation визначається автоматично через $is_pending_rewrite=true
        // Очищаємо помилки модерації
        unset($_SESSION['moderation_failed']);
        unset($_SESSION['flagged_categories']);
        
        // Створюємо пасту, позначену для переписування
        $this->create($data, true);
    }
}
