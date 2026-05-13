<?php
/**
 * auth/logout.php
 * Destroys the user session and redirects to the login page.
 * Logs the logout action before destroying the session.
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout before we destroy the session
if (!empty($_SESSION['user_id'])) {
    // Load minimal settings needed for logActivity
    require_once BASE_PATH . '/includes/settings.php';
    logActivity(
        (int) $_SESSION['user_id'],
        'logout',
        ['name' => ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')]
    );
}

// Completely destroy the session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redirect to login
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
