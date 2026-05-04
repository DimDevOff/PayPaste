<?php
require_once __DIR__ . '/../includes/bootstrap.php';
try {
    $pdo = DB::getInstance()->getPDO();
    $pdo->exec("ALTER TABLE pastes ADD COLUMN is_pending_rewrite BOOLEAN DEFAULT FALSE;");
    echo "SUCCESS: Column added.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
