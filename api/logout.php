<?php
require_once 'session_helper.php';
$role = $_GET['role'] ?? null;
start_role_session($role);

// Destroy all session array values
$_SESSION = array();

// Wipe specific session cookies ensuring complete termination
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the actual session object
session_destroy();

// Route them to login page safely
header("Location: ../login.php");
exit;
