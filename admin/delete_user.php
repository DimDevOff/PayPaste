<?php
include_once "check_admin.php";
include_once "../config/db.php";
include_once "../includes/models/User.php";
require_once "../includes/csrf.php";

verify_csrf();

$user_id = $_POST['id'] ?? '';

if (!empty($user_id)) {
    User::delete_by_admin($user_id); 
}

header('Location: users.php');
exit();
?>
