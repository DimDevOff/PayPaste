<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії
require_once __DIR__ . '/../models/Paste.php'; // Завантаження моделі пасти
require_once __DIR__ . '/../models/User.php'; // Завантаження моделі користувача
require_once __DIR__ . '/../csrf.php'; // Завантаження CSRF

class PasteController { // Клас контролера паст
    public function handleRequest() { // Обробка запитів
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Перевірка методу запиту
            verify_csrf(); // Перевірка CSRF
            if (isset($_POST['action']) && $_POST['action'] === 'create_paste') { // Створення пасти
                $this->create($_POST);
            } elseif (isset($_POST['action']) && $_POST['action'] === 'unlock_paste') { // Розблокування пасти
                $this->unlock($_POST);
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
    */
    private function create($data) {
        $_SESSION['old_input'] = $data; // Збереження введених даних для UX
        
        $title = trim($data['title'] ?? 'Без назви');
        if ($title === '') $title = 'Без назви';
        
        if (mb_strlen($title) > 255) {
            $_SESSION['error'] = "Назва пасти занадто довга!";
            header("Location: create.php");
            exit;
        }

        $content = trim($data['content'] ?? '');
        $is_private = isset($data['is_private']) ? true : false;
        $is_paid = isset($data['is_paid']) ? true : false;
        
        $user_id = null;
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }

        if (empty($content)) {
            $_SESSION['error'] = "Паста не може бути порожньою!";
            header("Location: create.php");
            exit;
        }

        if (mb_strlen($content) > 100000) {
            $_SESSION['error'] = "Контент пасти занадто великий!";
            header("Location: create.php");
            exit;
        }

        $view_cost = 0;

        if ($is_paid) {
            if (!$user_id) {
                $_SESSION['error'] = "Тільки авторизовані користувачі можуть створювати платні пасти!";
                header("Location: login.php");
                exit;
            }
            $user = User::findById($user_id);
            $length = mb_strlen($content);
            $creation_cost = ceil($length / 10);
            
            if ($user->credits < $creation_cost) {
                $_SESSION['error'] = "Недостатньо кредитів для створення! Потрібно $creation_cost, а у вас {$user->credits}.";
                header("Location: create.php");
                exit;
            }
            
            $view_cost = isset($data['view_cost']) ? (int)$data['view_cost'] : 0;
            if ($view_cost <= 0) {
                $_SESSION['error'] = "Вкажіть коректну вартість перегляду!";
                header("Location: create.php");
                exit;
            }

        // Списання кредитів за створення
            $user->credits -= $creation_cost;
            $user->save();
            
            // Фіксація транзакції (creation_fee)
            require_once __DIR__ . '/../models/Transaction.php';
            $tx = new Transaction([
                'user_id' => $user->id,
                'amount' => -$creation_cost,
                'type' => 'creation_fee',
                'description' => 'Плата за створення платної пасти'
            ]);
            $tx->save();
        }

        // Обчислення часу життя пасти (TTL)
        $expires_at = null;
        $expires_in = isset($data['expires_in']) ? (int)$data['expires_in'] : 0;
        
        if ($expires_in < 0) {
            $_SESSION['error'] = "Час життя пасти не може бути від'ємним!";
            header("Location: create.php");
            exit;
        }
        
        if ($expires_in > 0) {
            $expires_at = date('Y-m-d H:i:s', time() + ($expires_in * 60));
        }

        $pdo = DB::getInstance()->getPDO();
        try {
            $pdo->beginTransaction(); // Початок транзакції для створення пасти

            if ($is_paid) {
                // Списання кредитів за створення
                $user->credits -= $creation_cost;
                $user->save();
                
                // Фіксація транзакції (creation_fee)
                require_once __DIR__ . '/../models/Transaction.php';
                $tx = new Transaction([
                    'user_id' => $user->id,
                    'amount' => -$creation_cost,
                    'type' => 'creation_fee',
                    'description' => 'Плата за створення платної пасти'
                ]);
                $tx->save();
            }

            $paste = new Paste($title, $content, $user_id, $is_paid, $view_cost, $is_private, null, null, $expires_at);
            $paste->save();
            
            if ($is_paid && isset($tx)) {
                 $tx->related_paste_id = $paste->id;
                 $tx->save(); // Оновимо збережену транзакцію з айдішником пасти
            }

            // Обробка завантаження файлу
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                // Перевірка розміру файлу (максимум 5 МБ)
                $maxSize = 5 * 1024 * 1024; // 5 МБ
                if ($_FILES['attachment']['size'] > $maxSize) {
                    throw new Exception("Файл занадто великий! Максимум 5 МБ.");
                }
                
                $uploadDir = __DIR__ . '/../../data/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = $_FILES['attachment']['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // Перевірка MIME-типу
                $detectedMime = mime_content_type($_FILES['attachment']['tmp_name']);
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

                $targetFile = $uploadDir . $paste->id . '.' . $fileExt;
                if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                    throw new Exception("Не вдалося зберегти файл.");
                }
            }

            $pdo->commit(); // Успішне завершення транзакції
            unset($_SESSION['old_input']); // Очищення введених даних при успіху
            header("Location: view.php?id=" . $paste->id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // Відкат змін у разі помилки
            }
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
        $paste_id = $data['paste_id'] ?? null;
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error'] = "Увійдіть, щоб купувати доступ!";
            header("Location: login.php");
            return;
        }
        
        $paste = Paste::findById($paste_id);
        if (!$paste) {
            $_SESSION['error'] = "Пасту не знайдено!";
            header("Location: index.php");
            return;
        }

        if ($paste->isExpired()) {
            $_SESSION['error'] = "Термін дії цієї пасти закінчився!";
            header("Location: index.php");
            return;
        }
        
        $user = User::findById($_SESSION['user_id']);
        
        // Закрити повторну купівлю або купівлю власної пасти
        if ($user->id === $paste->user_id || $user->hasUnlocked($paste_id)) {
            $_SESSION['error'] = "Ви вже маєте доступ до цієї пасти!";
            header("Location: view.php?id=" . $paste_id);
            exit;
        }
        
        if ($user->credits < $paste->view_cost) {
            $_SESSION['error'] = "Не вистачає кредитів для покупки доступу!";
            header("Location: view.php?id=" . $paste_id);
            return;
        }
        
        $pdo = DB::getInstance()->getPDO();
        try {
            $pdo->beginTransaction(); // Початок транзакції для покупки доступу

            require_once __DIR__ . '/../models/Transaction.php';
            
            // Списання кредитів у покупця
            $user->credits -= $paste->view_cost;
            $user->unlockPaste($paste_id);
            
            // Фіксація транзакції (purchase)
            $txBuy = new Transaction([
                'user_id' => $user->id,
                'amount' => -$paste->view_cost,
                'type' => 'purchase',
                'related_paste_id' => $paste_id,
                'description' => 'Купівля доступу до пасти'
            ]);
            $txBuy->save();
            
            // Нарахування кредитів автору пасти
            if ($paste->user_id) {
                $author = User::findById($paste->user_id);
                if ($author) {
                    $author->credits += $paste->view_cost;
                    $author->save();
                    
                    // Фіксація транзакції (sale)
                    $txSell = new Transaction([
                        'user_id' => $author->id,
                        'amount' => $paste->view_cost,
                        'type' => 'sale',
                        'related_paste_id' => $paste_id,
                        'description' => 'Продаж доступу до пасти'
                    ]);
                    $txSell->save();
                }
            }
            
            $pdo->commit(); // Успішне завершення транзакції
            
            $_SESSION['success'] = "Доступ успішно придбано!";
            header("Location: view.php?id=" . $paste_id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // Відкат змін у разі помилки
            }
            $_SESSION['error'] = "Помилка під час транзакції: " . $e->getMessage();
            header("Location: view.php?id=" . $paste_id);
            exit;
        }
    }
}
