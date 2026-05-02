<?php
/**
 * ajax/admin/receptionists.php
 * Single AJAX file for ALL receptionist (user) operations.
 *
 * Dispatch via `action` field:
 *   getReceptionists    — list all receptionists with optional search/status filter
 *   getReceptionistById — fetch single record for edit modal
 *   createReceptionist  — insert new user with role_id=2
 *   updateReceptionist  — update name, contact, gender, status; optionally reset password
 *   deleteReceptionist  — hard delete (blocked if they have patient records)
 *   toggleReceptionist  — flip is_active
 *   resetPassword       — admin sets a new password for a receptionist
 *
 * Auth: Admin only.
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireAdmin();

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'getReceptionists':    getReceptionists();    break;
        case 'getReceptionistById': getReceptionistById(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'createReceptionist': createReceptionist(); break;
        case 'updateReceptionist': updateReceptionist(); break;
        case 'deleteReceptionist': deleteReceptionist(); break;
        case 'toggleReceptionist': toggleReceptionist(); break;
        case 'resetPassword':      resetPassword();      break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getReceptionists
 * GET params: search, status ('active'|'inactive'|'')
 */
function getReceptionists(): void
{
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where  = ["u.role_id = 2"];
    $params = [];

    if ($search !== '') {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $like    = "%{$search}%";
        $params  = array_merge($params, [$like, $like, $like, $like]);
    }

    if ($status === 'active') {
        $where[] = "u.is_active = 1";
    } elseif ($status === 'inactive') {
        $where[] = "u.is_active = 0";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $rows = Database::fetchAll(
        "SELECT
             u.id, u.first_name, u.last_name, u.email, u.phone,
             u.gender, u.is_active, u.profile_image,
             u.last_seen, u.created_at,
             (SELECT COUNT(*) FROM patients p WHERE p.receptionist_id = u.id) AS total_patients,
             (SELECT COUNT(*) FROM patients p
              WHERE  p.receptionist_id = u.id
              AND    p.visit_date = CURDATE())                                 AS today_patients
         FROM users u
         {$whereSql}
         ORDER BY u.is_active DESC, u.first_name ASC",
        $params
    );

    $threshold = (int) getSetting('online_threshold_mins', '5');

    foreach ($rows as &$r) {
        $r['full_name']    = trim($r['first_name'] . ' ' . $r['last_name']);
        $r['created_fmt']  = formatDate($r['created_at']);
        $r['is_online']    = $r['last_seen']
            && strtotime($r['last_seen']) >= strtotime("-{$threshold} minutes");
    }
    unset($r);

    jsonResponse(true, count($rows) . ' receptionist(s) found.', ['receptionists' => $rows]);
}

/**
 * getReceptionistById
 * GET params: id (int)
 */
function getReceptionistById(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $rec = Database::fetchOne(
        "SELECT id, first_name, last_name, email, phone, gender, is_active, profile_image
         FROM   users
         WHERE  id = ? AND role_id = 2",
        [$id]
    );

    if (!$rec) jsonResponse(false, 'Receptionist not found.');

    jsonResponse(true, 'Loaded.', ['receptionist' => $rec]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * createReceptionist
 * POST fields: first_name*, last_name*, email*, password*, phone, gender, is_active
 */
function createReceptionist(): void
{
    $data = collectAndValidateInput(true); // true = password required

    // Duplicate email check
    $exists = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
    if ($exists) jsonResponse(false, 'A user with this email address already exists.');

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);

    $id = Database::insert(
        "INSERT INTO users (role_id, first_name, last_name, email, phone, password, gender, is_active)
         VALUES (2, ?, ?, ?, ?, ?, ?, ?)",
        [
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone']   ?: null,
            $hash,
            $data['gender']  ?: null,
            $data['is_active'],
        ]
    );

    logActivity(getCurrentUserId(), 'receptionist_created', [
        'id'    => $id,
        'name'  => $data['first_name'] . ' ' . $data['last_name'],
    ]);

    jsonResponse(true, "{$data['first_name']} {$data['last_name']} has been added as a receptionist.", ['id' => $id]);
}

/**
 * updateReceptionist
 * POST fields: id*, first_name*, last_name*, email*, phone, gender, is_active
 * (password is NOT updated here — use resetPassword action)
 */
function updateReceptionist(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $existing = Database::fetchOne(
        "SELECT id, first_name, last_name FROM users WHERE id = ? AND role_id = 2",
        [$id]
    );
    if (!$existing) jsonResponse(false, 'Receptionist not found.');

    $data = collectAndValidateInput(false); // password NOT required

    // Duplicate email — allow own
    $dup = Database::fetchOne(
        "SELECT id FROM users WHERE email = ? AND id != ?",
        [$data['email'], $id]
    );
    if ($dup) jsonResponse(false, 'Another user already has this email address.');

    Database::execute(
        "UPDATE users
         SET first_name=?, last_name=?, email=?, phone=?, gender=?, is_active=?
         WHERE id=?",
        [
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone']  ?: null,
            $data['gender'] ?: null,
            $data['is_active'],
            $id,
        ]
    );

    logActivity(getCurrentUserId(), 'receptionist_updated', [
        'id'   => $id,
        'name' => $data['first_name'] . ' ' . $data['last_name'],
    ]);

    jsonResponse(true, "{$data['first_name']} {$data['last_name']} has been updated successfully.");
}

/**
 * deleteReceptionist
 * Blocked if they have any patient or admission records.
 * POST fields: id*
 */
function deleteReceptionist(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    // Prevent self-deletion
    if ($id === getCurrentUserId()) {
        jsonResponse(false, 'You cannot delete your own account.');
    }

    $rec = Database::fetchOne(
        "SELECT id, first_name, last_name FROM users WHERE id = ? AND role_id = 2",
        [$id]
    );
    if (!$rec) jsonResponse(false, 'Receptionist not found.');

    $fullName = $rec['first_name'] . ' ' . $rec['last_name'];

    $patientCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE receptionist_id = ?", [$id]
    )['cnt'];

    if ($patientCount > 0) {
        jsonResponse(false, "Cannot delete {$fullName} — they have {$patientCount} patient record(s). Deactivate instead.");
    }

    $admCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM admissions WHERE created_by = ?", [$id]
    )['cnt'];

    if ($admCount > 0) {
        jsonResponse(false, "Cannot delete {$fullName} — they have {$admCount} admission record(s). Deactivate instead.");
    }

    Database::execute("DELETE FROM users WHERE id = ?", [$id]);

    logActivity(getCurrentUserId(), 'receptionist_deleted', ['id' => $id, 'name' => $fullName]);
    jsonResponse(true, "{$fullName} has been deleted.");
}

/**
 * toggleReceptionist
 * POST fields: id*
 */
function toggleReceptionist(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    if ($id === getCurrentUserId()) {
        jsonResponse(false, 'You cannot deactivate your own account.');
    }

    $rec = Database::fetchOne(
        "SELECT id, first_name, last_name, is_active FROM users WHERE id = ? AND role_id = 2",
        [$id]
    );
    if (!$rec) jsonResponse(false, 'Receptionist not found.');

    $newStatus = $rec['is_active'] ? 0 : 1;
    $fullName  = $rec['first_name'] . ' ' . $rec['last_name'];

    Database::execute("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $id]);

    $label = $newStatus ? 'activated' : 'deactivated';
    logActivity(getCurrentUserId(), "receptionist_{$label}", ['id' => $id, 'name' => $fullName]);
    jsonResponse(true, "{$fullName} has been {$label}.", ['is_active' => $newStatus]);
}

/**
 * resetPassword
 * Admin sets a new password for a receptionist.
 * POST fields: id*, new_password*, confirm_password*
 */
function resetPassword(): void
{
    $id              = (int) ($_POST['id']              ?? 0);
    $newPassword     = trim($_POST['new_password']      ?? '');
    $confirmPassword = trim($_POST['confirm_password']  ?? '');

    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $rec = Database::fetchOne(
        "SELECT id, first_name, last_name FROM users WHERE id = ? AND role_id = 2",
        [$id]
    );
    if (!$rec) jsonResponse(false, 'Receptionist not found.');

    if (strlen($newPassword) < 6) {
        jsonResponse(false, 'Password must be at least 6 characters.');
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, 'Passwords do not match.');
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    Database::execute("UPDATE users SET password = ? WHERE id = ?", [$hash, $id]);

    $fullName = $rec['first_name'] . ' ' . $rec['last_name'];
    logActivity(getCurrentUserId(), 'receptionist_password_reset', ['id' => $id, 'name' => $fullName]);
    jsonResponse(true, "Password for {$fullName} has been reset successfully.");
}

// ════════════════════════════════════════════════════════════
// SHARED VALIDATION
// ════════════════════════════════════════════════════════════

/**
 * Collect and validate user input from $_POST.
 * @param bool $passwordRequired  True for create, false for update.
 */
function collectAndValidateInput(bool $passwordRequired): array
{
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $password  = trim($_POST['password']   ?? '');
    $gender    = trim($_POST['gender']     ?? '');
    $isActive  = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    if (empty($firstName)) jsonResponse(false, 'First name is required.');
    if (empty($lastName))  jsonResponse(false, 'Last name is required.');
    if (empty($email))     jsonResponse(false, 'Email address is required.');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter a valid email address.');
    }

    if ($passwordRequired) {
        if (empty($password)) jsonResponse(false, 'Password is required.');
        if (strlen($password) < 6) jsonResponse(false, 'Password must be at least 6 characters.');
    }

    if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Invalid gender value.');
    }

    return compact('firstName', 'lastName', 'email', 'phone', 'password', 'gender', 'isActive')
        + ['first_name' => $firstName, 'last_name' => $lastName, 'is_active' => $isActive];
}
