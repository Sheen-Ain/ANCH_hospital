<?php
/**
 * ajax/switch-role.php
 * Allows an admin to toggle into or out of the Receptionist view.
 *
 * Role-switch state is stored in $_SESSION['role_override'].
 * When set to 'receptionist', the admin sees the receptionist layout
 * and all actions are attributed as "Admin [Name] acting as Receptionist".
 *
 * Method: POST
 * Body:   csrf_token, action ('switch' | 'restore')
 * Returns: { success, message, data: { role, redirect } }
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

bootApp();
requireAdmin(); // Only admins can switch roles

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

validateCsrf(true);

$action = trim($_POST['action'] ?? '');

switch ($action) {
    case 'switch':
        // Admin switches INTO receptionist view
        $_SESSION['role_override'] = 'receptionist';
        logActivity(
            getCurrentUserId(),
            'role_switch_to_receptionist',
            ['admin' => $_SESSION['first_name'] . ' ' . $_SESSION['last_name']]
        );
        jsonResponse(true, 'Switched to Receptionist view.', [
            'role'     => 'receptionist',
            'redirect' => BASE_URL . '/receptionist/dashboard.php',
        ]);
        break;

    case 'restore':
        // Admin restores their own admin view
        unset($_SESSION['role_override']);
        logActivity(
            getCurrentUserId(),
            'role_switch_back_to_admin',
            ['admin' => $_SESSION['first_name'] . ' ' . $_SESSION['last_name']]
        );
        jsonResponse(true, 'Restored to Admin view.', [
            'role'     => 'admin',
            'redirect' => BASE_URL . '/admin/dashboard.php',
        ]);
        break;

    default:
        jsonResponse(false, 'Invalid action. Use "switch" or "restore".');
}
