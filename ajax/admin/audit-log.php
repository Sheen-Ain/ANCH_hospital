<?php
/**
 * ajax/admin/audit-log.php
 * Returns recent audit log entries for the settings page activity widget.
 *
 * Method: GET
 * Params: limit (int, default 10, max 50)
 * Auth:   Admin only
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireAdmin();

$limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));

$logs = Database::fetchAll(
    "SELECT
         al.id, al.action, al.details, al.ip_address, al.created_at,
         CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS user_name
     FROM   audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER  BY al.id DESC
     LIMIT  ?",
    [$limit]
);

foreach ($logs as &$log) {
    $log['user_name']  = trim($log['user_name']) ?: 'System';
    $log['created_at'] = formatDateTime($log['created_at']);
}
unset($log);

jsonResponse(true, count($logs) . ' log entries.', ['logs' => $logs]);
