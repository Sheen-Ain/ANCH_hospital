<?php
/**
 * ajax/receptionist/profile.php
 * Single AJAX file for receptionist's own profile operations.
 *
 * Dispatch via `action`:
 *   getProfile       — return own profile data
 *   updateProfile    — update name, phone, gender, avatar
 *   changePassword   — verify current password then set new one
 *
 * Auth: Receptionist (or admin in switched mode).
 * Note: A receptionist can only edit THEIR OWN profile.
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireReceptionist();

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'getProfile': getProfile(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'updateProfile':  updateProfile();  break;
        case 'changePassword': changePassword(); break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

function getProfile(): void
{
    $userId = getCurrentUserId();

    $user = Database::fetchOne(
        "SELECT id, first_name, last_name, email, phone, gender,
                profile_image, created_at, last_seen
         FROM   users WHERE id = ?",
        [$userId]
    );

    if (!$user) jsonResponse(false, 'Profile not found.');

    $user['created_fmt']   = formatDate($user['created_at']);
    $user['last_seen_fmt'] = $user['last_seen'] ? formatDateTime($user['last_seen']) : 'Never';

    // Stats
    $today = date('Y-m-d');
    $user['total_patients'] = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE receptionist_id = ?", [$userId]
    )['cnt'];
    $user['today_patients'] = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE receptionist_id = ? AND visit_date = ?",
        [$userId, $today]
    )['cnt'];
    $user['total_admissions'] = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM admissions WHERE created_by = ?", [$userId]
    )['cnt'];

    jsonResponse(true, 'Profile loaded.', ['profile' => $user]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

function updateProfile(): void
{
    $userId    = getCurrentUserId();
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $gender    = trim($_POST['gender']     ?? '');

    if (empty($firstName)) jsonResponse(false, 'First name is required.');
    if (empty($lastName))  jsonResponse(false, 'Last name is required.');

    if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Invalid gender value.');
    }

    $newImageFilename = null;
    if (!empty($_FILES['profile_image']['name'])) {
        $filename = uploadProfileImage($_FILES['profile_image'], $userId);
        if (!$filename) {
            jsonResponse(false, 'Image upload failed. Allowed: JPG, PNG, GIF, WebP. Max 2MB.');
        }
        $newImageFilename = $filename;

        $oldImage = Database::fetchOne(
            "SELECT profile_image FROM users WHERE id = ?", [$userId]
        )['profile_image'] ?? null;

        if ($oldImage) {
            $oldPath = BASE_PATH . '/assets/uploads/profiles/' . $oldImage;
            if (file_exists($oldPath)) @unlink($oldPath);
        }
    }

    if ($newImageFilename !== null) {
        Database::execute(
            "UPDATE users SET first_name=?, last_name=?, phone=?, gender=?, profile_image=? WHERE id=?",
            [$firstName, $lastName, $phone ?: null, $gender ?: null, $newImageFilename, $userId]
        );
        $_SESSION['profile_image'] = $newImageFilename;
    } else {
        Database::execute(
            "UPDATE users SET first_name=?, last_name=?, phone=?, gender=? WHERE id=?",
            [$firstName, $lastName, $phone ?: null, $gender ?: null, $userId]
        );
    }

    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name']  = $lastName;

    logActivity($userId, 'profile_updated', ['name' => "$firstName $lastName"]);
    jsonResponse(true, 'Your profile has been updated successfully.', [
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'profile_image' => $newImageFilename,
    ]);
}

function changePassword(): void
{
    $userId          = getCurrentUserId();
    $currentPassword = trim($_POST['current_password']  ?? '');
    $newPassword     = trim($_POST['new_password']      ?? '');
    $confirmPassword = trim($_POST['confirm_password']  ?? '');

    if (empty($currentPassword)) jsonResponse(false, 'Current password is required.');
    if (strlen($newPassword) < 6) jsonResponse(false, 'New password must be at least 6 characters.');
    if ($newPassword !== $confirmPassword) jsonResponse(false, 'Passwords do not match.');

    $user = Database::fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        jsonResponse(false, 'Current password is incorrect.');
    }
    if ($currentPassword === $newPassword) {
        jsonResponse(false, 'New password must differ from your current password.');
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    Database::execute("UPDATE users SET password = ? WHERE id = ?", [$hash, $userId]);

    logActivity($userId, 'password_changed_self', []);
    jsonResponse(true, 'Your password has been changed successfully.');
}
