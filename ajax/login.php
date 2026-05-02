<?php
/**
 * ajax/login.php
 * Handles the login form POST via AJAX.
 *
 * Expected POST fields:
 *   csrf_token  — CSRF protection token
 *   email       — User email address
 *   password    — Plain-text password (verified against bcrypt hash)
 *   remember_me — Optional checkbox value
 *
 * Returns: { success, message, data: { redirect } }
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

bootApp();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

// ── CSRF Validation ──────────────────────────────────────────
validateCsrf(true);

// ── Input ────────────────────────────────────────────────────
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

// ── Basic server-side validation ─────────────────────────────
if (empty($email) || empty($password)) {
    jsonResponse(false, 'Email and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.');
}

// ── Rate limiting (simple: max 10 attempts per IP per 15 min) ─
// Uses session-based counter — lightweight, no extra DB table needed.
$ipKey = 'login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
if (!isset($_SESSION[$ipKey])) {
    $_SESSION[$ipKey] = ['count' => 0, 'first' => time()];
}

$attempts = &$_SESSION[$ipKey];

// Reset window after 15 minutes
if (time() - $attempts['first'] > 900) {
    $attempts = ['count' => 0, 'first' => time()];
}

if ($attempts['count'] >= 10) {
    $waitMins = ceil((900 - (time() - $attempts['first'])) / 60);
    jsonResponse(false, "Too many login attempts. Please wait {$waitMins} minute(s) and try again.");
}

// ── Fetch user from DB ───────────────────────────────────────
$user = Database::fetchOne(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.password,
            u.is_active, u.profile_image,
            r.name AS role_name, r.id AS role_id
     FROM   users u
     JOIN   roles r ON r.id = u.role_id
     WHERE  u.email = ?
     LIMIT  1",
    [$email]
);

// ── Verify ───────────────────────────────────────────────────
if (!$user || !password_verify($password, $user['password'])) {
    $attempts['count']++;
    logActivity(0, 'login_failed', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    jsonResponse(false, 'Invalid email or password. Please try again.');
}

if (!(int) $user['is_active']) {
    jsonResponse(false, 'Your account has been deactivated. Please contact the administrator.');
}

// ── Success — build session ──────────────────────────────────
// Clear rate-limit counter on success
unset($_SESSION[$ipKey]);

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

$_SESSION['user_id']       = (int)  $user['id'];
$_SESSION['role_id']       = (int)  $user['role_id'];
$_SESSION['role_name']     =        $user['role_name'];
$_SESSION['first_name']    =        $user['first_name'];
$_SESSION['last_name']     =        $user['last_name'];
$_SESSION['email']         =        $user['email'];
$_SESSION['profile_image'] =        $user['profile_image'];
$_SESSION['last_activity'] = time();
unset($_SESSION['role_override']); // clear any previous role switch

// ── Update last_seen ─────────────────────────────────────────
updateLastSeen((int) $user['id']);

// ── Log success ──────────────────────────────────────────────
logActivity(
    (int) $user['id'],
    'login_success',
    ['role' => $user['role_name'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']
);

// ── Determine redirect URL ───────────────────────────────────
$redirect = $user['role_name'] === 'admin'
    ? BASE_URL . '/admin/dashboard.php'
    : BASE_URL . '/receptionist/dashboard.php';

jsonResponse(true, 'Login successful. Redirecting…', ['redirect' => $redirect]);
