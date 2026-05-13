<?php
/**
 * index.php — Web root entry point.
 * Redirects logged-in users to their dashboard, others to login.
 */
require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

bootApp();

if (!empty($_SESSION['user_id'])) {
    if (isAdmin() && !isRoleSwitched()) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/receptionist/dashboard.php');
    }
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
