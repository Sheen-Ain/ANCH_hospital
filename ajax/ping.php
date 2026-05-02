<?php
/**
 * ajax/ping.php
 * Called by startPing() in common.js every 60 seconds.
 * Updates users.last_seen for the logged-in user.
 * Powers the "Online Now" admin dashboard widget.
 *
 * Method: POST (to avoid being cached by browser or CDN)
 * Returns: { success, message }
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

bootApp();

// Must be logged in
if (empty($_SESSION['user_id'])) {
    // Session expired — return 401 so client JS can redirect to login if desired
    http_response_code(401);
    jsonResponse(false, 'Not authenticated.');
}

// Validate CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

validateCsrf(true);

// Update last_seen
updateLastSeen((int) $_SESSION['user_id']);

// Also reset the PHP session activity timer
$_SESSION['last_activity'] = time();

jsonResponse(true, 'Pong.');
