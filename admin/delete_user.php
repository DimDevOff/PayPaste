<?php
include_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../config/db.php";
include_once __DIR__ . "/../includes/models/User.php";
require_once __DIR__ . "/../includes/csrf.php";

verify_csrf();

$user_id = $_POST['id'] ?? '';

if (!empty($user_id)) {
    User::delete_by_admin($user_id); 
}

header('Location: users.php');
exit();
?>

