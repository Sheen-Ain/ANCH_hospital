<?php
/**
 * ajax/admin/doctors.php
 * Single AJAX file for ALL doctor operations.
 *
 * Dispatch via POST field `action`:
 *   getDoctors    — list all doctors (with optional search/filter)
 *   getDoctorById — fetch single doctor for edit modal
 *   createDoctor  — insert new doctor
 *   updateDoctor  — update existing doctor
 *   deleteDoctor  — hard delete (only if no patients linked)
 *   toggleDoctor  — toggle is_active flag
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

// Route GET actions (read-only, no CSRF needed)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'getDoctors':    getDoctors();    break;
        case 'getDoctorById': getDoctorById(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

// All POST actions require CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'createDoctor': createDoctor(); break;
        case 'updateDoctor': updateDoctor(); break;
        case 'deleteDoctor': deleteDoctor(); break;
        case 'toggleDoctor': toggleDoctor(); break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getDoctors
 * Returns the full list of doctors, optionally filtered by search query or status.
 *
 * GET params:
 *   search  (string)   — searches name, specialization, designation, email, phone
 *   status  (string)   — 'active' | 'inactive' | '' (all)
 */
function getDoctors(): void
{
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "(d.name LIKE ? OR d.specialization LIKE ? OR d.designation LIKE ? OR d.email LIKE ? OR d.phone LIKE ?)";
        $like     = "%{$search}%";
        $params   = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if ($status === 'active') {
        $where[]  = "d.is_active = 1";
    } elseif ($status === 'inactive') {
        $where[]  = "d.is_active = 0";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Also fetch per-doctor today's patient count for quick stats
    $sql = "SELECT
                d.id, d.name, d.email, d.phone, d.gender,
                d.designation, d.specialization, d.fee, d.is_active,
                d.created_at,
                (SELECT COUNT(*) FROM patients p
                 WHERE p.doctor_id = d.id AND p.visit_date = CURDATE()) AS today_patients,
                (SELECT COUNT(*) FROM patients p WHERE p.doctor_id = d.id) AS total_patients
            FROM doctors d
            {$whereSql}
            ORDER BY d.is_active DESC, d.name ASC";

    $doctors = Database::fetchAll($sql, $params);

    // Format currency for display
    foreach ($doctors as &$d) {
        $d['fee_fmt']       = formatCurrency((float) $d['fee']);
        $d['created_fmt']   = formatDate($d['created_at']);
    }
    unset($d);

    jsonResponse(true, count($doctors) . ' doctor(s) found.', ['doctors' => $doctors]);
}

/**
 * getDoctorById
 * Returns a single doctor record for populating the edit modal.
 *
 * GET params: id (int)
 */
function getDoctorById(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'Invalid doctor ID.');
    }

    $doctor = Database::fetchOne(
        "SELECT id, name, email, phone, gender, designation, specialization, fee, is_active
         FROM   doctors
         WHERE  id = ?",
        [$id]
    );

    if (!$doctor) {
        jsonResponse(false, 'Doctor not found.');
    }

    jsonResponse(true, 'Doctor loaded.', ['doctor' => $doctor]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * createDoctor
 * Inserts a new doctor record.
 *
 * POST fields: name*, specialization*, fee*, designation, email, phone, gender, is_active
 */
function createDoctor(): void
{
    $data   = collectAndValidateDoctorInput();
    $userId = getCurrentUserId();

    // Check for duplicate email (if provided)
    if (!empty($data['email'])) {
        $exists = Database::fetchOne(
            "SELECT id FROM doctors WHERE email = ?",
            [$data['email']]
        );
        if ($exists) {
            jsonResponse(false, 'A doctor with this email address already exists.');
        }
    }

    $id = Database::insert(
        "INSERT INTO doctors (name, email, phone, gender, designation, specialization, fee, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $data['name'],
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['gender'] ?: null,
            $data['designation'] ?: null,
            $data['specialization'],
            $data['fee'],
            $data['is_active'],
        ]
    );

    logActivity($userId, 'doctor_created', ['id' => $id, 'name' => $data['name']]);
    jsonResponse(true, "Dr. {$data['name']} has been added successfully.", ['id' => $id]);
}

/**
 * updateDoctor
 * Updates an existing doctor record.
 *
 * POST fields: id*, name*, specialization*, fee*, designation, email, phone, gender, is_active
 */
function updateDoctor(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'Invalid doctor ID.');
    }

    // Confirm exists
    $existing = Database::fetchOne("SELECT id, name FROM doctors WHERE id = ?", [$id]);
    if (!$existing) {
        jsonResponse(false, 'Doctor not found.');
    }

    $data   = collectAndValidateDoctorInput();
    $userId = getCurrentUserId();

    // Check duplicate email (allow own email)
    if (!empty($data['email'])) {
        $dup = Database::fetchOne(
            "SELECT id FROM doctors WHERE email = ? AND id != ?",
            [$data['email'], $id]
        );
        if ($dup) {
            jsonResponse(false, 'Another doctor already has this email address.');
        }
    }

    Database::execute(
        "UPDATE doctors
         SET name=?, email=?, phone=?, gender=?, designation=?, specialization=?, fee=?, is_active=?
         WHERE id=?",
        [
            $data['name'],
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['gender'] ?: null,
            $data['designation'] ?: null,
            $data['specialization'],
            $data['fee'],
            $data['is_active'],
            $id,
        ]
    );

    logActivity($userId, 'doctor_updated', ['id' => $id, 'name' => $data['name']]);
    jsonResponse(true, "Dr. {$data['name']} has been updated successfully.");
}

/**
 * deleteDoctor
 * Hard-deletes a doctor only if they have no associated patients.
 *
 * POST fields: id*
 */
function deleteDoctor(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'Invalid doctor ID.');
    }

    $doctor = Database::fetchOne("SELECT id, name FROM doctors WHERE id = ?", [$id]);
    if (!$doctor) {
        jsonResponse(false, 'Doctor not found.');
    }

    // Safety: prevent deletion if patients are linked
    $patientCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE doctor_id = ?",
        [$id]
    )['cnt'];

    if ($patientCount > 0) {
        jsonResponse(
            false,
            "Cannot delete Dr. {$doctor['name']} — they have {$patientCount} patient record(s). " .
            "Deactivate the doctor instead."
        );
    }

    // Also check admissions
    $admCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM admissions WHERE doctor_id = ?",
        [$id]
    )['cnt'];

    if ($admCount > 0) {
        jsonResponse(
            false,
            "Cannot delete Dr. {$doctor['name']} — they have {$admCount} admission record(s). " .
            "Deactivate the doctor instead."
        );
    }

    Database::execute("DELETE FROM doctors WHERE id = ?", [$id]);

    logActivity(getCurrentUserId(), 'doctor_deleted', ['id' => $id, 'name' => $doctor['name']]);
    jsonResponse(true, "Dr. {$doctor['name']} has been deleted.");
}

/**
 * toggleDoctor
 * Flips is_active between 0 and 1 for a doctor.
 *
 * POST fields: id*
 */
function toggleDoctor(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'Invalid doctor ID.');
    }

    $doctor = Database::fetchOne(
        "SELECT id, name, is_active FROM doctors WHERE id = ?",
        [$id]
    );
    if (!$doctor) {
        jsonResponse(false, 'Doctor not found.');
    }

    $newStatus = $doctor['is_active'] ? 0 : 1;

    Database::execute(
        "UPDATE doctors SET is_active = ? WHERE id = ?",
        [$newStatus, $id]
    );

    $label = $newStatus ? 'activated' : 'deactivated';
    logActivity(getCurrentUserId(), "doctor_{$label}", ['id' => $id, 'name' => $doctor['name']]);
    jsonResponse(
        true,
        "Dr. {$doctor['name']} has been {$label}.",
        ['is_active' => $newStatus]
    );
}

// ════════════════════════════════════════════════════════════
// SHARED VALIDATION
// ════════════════════════════════════════════════════════════

/**
 * Collect and validate doctor input fields from $_POST.
 * Returns a clean array on success; calls jsonResponse(false, …) and exits on error.
 */
function collectAndValidateDoctorInput(): array
{
    $name           = trim($_POST['name']           ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $fee            = trim($_POST['fee']            ?? '0');
    $designation    = trim($_POST['designation']    ?? '');
    $email          = trim($_POST['email']          ?? '');
    $phone          = trim($_POST['phone']          ?? '');
    $gender         = trim($_POST['gender']         ?? '');
    $isActive       = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    // Required
    if (empty($name)) {
        jsonResponse(false, 'Doctor name is required.');
    }
    if (strlen($name) > 150) {
        jsonResponse(false, 'Doctor name must not exceed 150 characters.');
    }

    if (empty($specialization)) {
        jsonResponse(false, 'Specialization is required.');
    }

    $fee = (float) $fee;
    if ($fee < 0) {
        jsonResponse(false, 'Consultation fee cannot be negative.');
    }

    // Optional but validated if present
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter a valid email address.');
    }

    if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Invalid gender value.');
    }

    return compact('name', 'specialization', 'fee', 'designation', 'email', 'phone', 'gender', 'isActive')
        + ['is_active' => $isActive];
}
