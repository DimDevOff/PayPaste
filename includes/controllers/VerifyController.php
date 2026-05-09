<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../services/AuthService.php';

class VerifyController {
    public function handleRequest() {
        global $user;
        
        if (!$user) {
            header('Location: login.php');
            exit;
        }

        if ($user->email_verified) {
            header('Location: index.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();

            $action = $_POST['action'] ?? '';

            if ($action === 'verify_code') {
                $code = trim($_POST['code'] ?? '');
                
                if (empty($code)) {
                    $_SESSION['error_msg'] = 'Введіть код підтвердження.';
                    return;
                }

                try {
                    AuthService::verifyEmail($user, $code);
                    $_SESSION['success_msg'] = 'Пошту успішно підтверджено!';
                    header('Location: index.php');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error_msg'] = 'Неправильний або протермінований код.';
                }
            } 
            elseif ($action === 'resend_code') {
                $code = AuthService::generateVerificationCode($user);
                $result = Mailer::enqueueVerificationEmail($user->email, $code);

                if ($result['success']) {
                    $_SESSION['success_msg'] = 'Новий код поставлено у чергу відправки. Перевірте пошту через хвилину.';
                } else {
                    $_SESSION['error_msg'] = $result['error'] ?? 'Помилка постановки листа у чергу.';
                }
                
                // PRG pattern
                header('Location: verify.php');
                exit;
            }
        }
    }
}
