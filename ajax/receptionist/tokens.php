<?php
/**
 * ajax/receptionist/tokens.php
 * Single AJAX file for ALL token generation and patient registration operations.
 *
 * Dispatch via `action`:
 *   getDoctors        — active doctors list with today's next token number
 *   getNextToken      — preview next token number for a specific doctor
 *   generateToken     — create token + patient record + payment record (atomic)
 *   updatePatient     — edit patient details (same day only)
 *   updatePayment     — mark paid/unpaid, set payment method
 *   deletePatient     — remove patient + token (same day only, no restrictions)
 *   getPatientById    — single patient record for edit modal
 *   getTodayPatients  — all patients for today filtered by doctor/payment status
 *
 * Auth: Receptionist (or admin in switched mode).
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
        case 'getDoctors':       getDoctors();       break;
        case 'getNextToken':     getNextToken();     break;
        case 'getPatientById':   getPatientById();   break;
        case 'getTodayPatients': getTodayPatients(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'generateToken':  generateToken();  break;
        case 'updatePatient':  updatePatient();  break;
        case 'updatePayment':  updatePayment();  break;
        case 'markReturned':   markReturned();   break;
        case 'deletePatient':  deletePatient();  break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getDoctors
 * Returns all active doctors with their next token number for today.
 */
function getDoctors(): void
{
    $today   = date('Y-m-d');
    $doctors = Database::fetchAll(
        "SELECT
             d.id, d.name, d.specialization, d.designation, d.fee, d.gender,
             (SELECT MAX(t.token_number)
              FROM   tokens t
              WHERE  t.doctor_id = d.id AND t.token_date = ?) AS last_token,
             (SELECT COUNT(*)
              FROM   patients p
              WHERE  p.doctor_id = d.id AND p.visit_date = ?) AS today_patients
         FROM doctors d
         WHERE d.is_active = 1
         ORDER BY d.name ASC",
        [$today, $today]
    );

    $maxTokens = (int) getSetting('max_tokens_per_day', '200');
    $prefix    = getSetting('token_prefix', 'TKN');

    foreach ($doctors as &$d) {
        $next              = ((int)($d['last_token'] ?? 0)) + 1;
        $d['next_token']   = $next;
        $d['token_display']= $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
        $d['fee_fmt']      = formatCurrency((float) $d['fee']);
        $d['tokens_left']  = max(0, $maxTokens - (int) $d['today_patients']);
        $d['is_full']      = (int) $d['today_patients'] >= $maxTokens;
    }
    unset($d);

    jsonResponse(true, count($doctors) . ' active doctor(s).', [
        'doctors'    => $doctors,
        'max_tokens' => $maxTokens,
        'prefix'     => $prefix,
        'today'      => $today,
    ]);
}

/**
 * getNextToken
 * Returns the next token number for a specific doctor on a specific appointment date.
 * Token numbering resets per (doctor + appointment date).
 * GET params: doctor_id, visit_date (YYYY-MM-DD, defaults to today)
 */
function getNextToken(): void
{
    $doctorId  = (int) ($_GET['doctor_id']  ?? 0);
    $visitDate = trim( $_GET['visit_date']  ?? date('Y-m-d'));

    if ($doctorId <= 0) jsonResponse(false, 'Invalid doctor ID.');

    // Validate + fallback
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
        $visitDate = date('Y-m-d');
    }

    $doctor = Database::fetchOne(
        "SELECT id, name, specialization, fee FROM doctors WHERE id = ? AND is_active = 1",
        [$doctorId]
    );
    if (!$doctor) jsonResponse(false, 'Doctor not found or inactive.');

    $prefix    = getSetting('token_prefix', 'TKN');
    $max       = (int) getSetting('max_tokens_per_day', '200');
    $next      = getNextTokenNumber($doctorId, $visitDate);
    $dateCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE doctor_id = ? AND visit_date = ?",
        [$doctorId, $visitDate]
    )['cnt'];

    if ($dateCount >= $max) {
        jsonResponse(false, "Dr. {$doctor['name']}'s list for " . date('d/m/Y', strtotime($visitDate)) . " is full (max {$max}).");
    }

    jsonResponse(true, 'Next token loaded.', [
        'next_token'    => $next,
        'token_display' => $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT),
        'doctor'        => $doctor,
        'fee'           => (float) $doctor['fee'],
        'fee_fmt'       => formatCurrency((float) $doctor['fee']),
        'date_count'    => $dateCount,
        'tokens_left'   => $max - $dateCount,
        'visit_date'    => $visitDate,
    ]);
}

/**
 * getPatientById
 * GET params: id
 */
function getPatientById(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid patient ID.');

    $patient = Database::fetchOne(
        "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.serial_number, p.doctor_id,
             t.token_number,
             py.status AS payment_status, py.amount, py.payment_method
         FROM   patients p
         JOIN   tokens   t  ON t.id  = p.token_id
         LEFT JOIN payments py ON py.patient_id = p.id
         WHERE  p.id = ?",
        [$id]
    );

    if (!$patient) jsonResponse(false, 'Patient not found.');

    jsonResponse(true, 'Patient loaded.', ['patient' => $patient]);
}

/**
 * getTodayPatients
 * Returns ALL patients registered today (any receptionist).
 * GET params: doctor_id (optional), payment_status ('paid'|'unpaid'|'')
 */
function getTodayPatients(): void
{
    $today        = date('Y-m-d');
    $doctorId     = (int) ($_GET['doctor_id']     ?? 0);
    $statusFilter = trim( $_GET['status_filter']  ?? '');

    $where  = ["p.visit_date = ?"];
    $params = [$today];

    if ($doctorId > 0) {
        $where[]  = "p.doctor_id = ?";
        $params[] = $doctorId;
    }

    // status_filter: 'paid' | 'unpaid' | 'returned'
    if ($statusFilter === 'returned') {
        $where[] = "p.notes LIKE '[RETURNED]%'";
    } elseif (in_array($statusFilter, ['paid', 'unpaid'], true)) {
        $where[] = "py.status = ? AND (p.notes IS NULL OR p.notes NOT LIKE '[RETURNED]%')";
        $params[] = $statusFilter;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $patients = Database::fetchAll(
        "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.serial_number, p.receptionist_id, p.doctor_id,
             t.token_number,
             d.name        AS doctor_name,
             d.specialization,
             py.status     AS payment_status,
             py.amount,
             py.payment_method,
             CONCAT(u.first_name, ' ', u.last_name) AS created_by_name
         FROM   patients p
         JOIN   tokens   t  ON t.id  = p.token_id
         JOIN   doctors  d  ON d.id  = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         JOIN   users    u  ON u.id  = p.receptionist_id
         {$whereSql}
         ORDER  BY p.id DESC",
        $params
    );

    $currentUserId = getCurrentUserId();
    foreach ($patients as &$p) {
        $p['visit_time_fmt'] = formatTime($p['visit_time']);
        $p['amount_fmt']     = formatCurrency((float)($p['amount'] ?? 0));
        $p['is_mine']        = (int)$p['receptionist_id'] === $currentUserId;
        $p['visit_status']   = str_starts_with($p['notes'] ?? '', '[RETURNED]') ? 'returned' : 'active';
    }
    unset($p);

    // ── Stats (scoped to same doctor filter, NOT status filter — always show all stats) ──
    $statsWhere  = ["p.visit_date = ?"];
    $statsParams = [$today];
    if ($doctorId > 0) { $statsWhere[] = "p.doctor_id = ?"; $statsParams[] = $doctorId; }
    $statsBase = "FROM patients p LEFT JOIN payments py ON py.patient_id = p.id WHERE " . implode(' AND ', $statsWhere);

    $statsPaid     = (int)   Database::fetchOne("SELECT COUNT(*) AS c {$statsBase} AND py.status='paid' AND (p.notes IS NULL OR p.notes NOT LIKE '[RETURNED]%')", $statsParams)['c'];
    $statsUnpaid   = (int)   Database::fetchOne("SELECT COUNT(*) AS c {$statsBase} AND py.status='unpaid' AND (p.notes IS NULL OR p.notes NOT LIKE '[RETURNED]%')", $statsParams)['c'];
    $statsReturned = (int)   Database::fetchOne("SELECT COUNT(*) AS c {$statsBase} AND p.notes LIKE '[RETURNED]%'", $statsParams)['c'];
    $statsRevenue  = (float) Database::fetchOne("SELECT COALESCE(SUM(py.amount),0) AS t {$statsBase} AND py.status='paid'", $statsParams)['t'];
    $statsPending  = (float) Database::fetchOne("SELECT COALESCE(SUM(py.amount),0) AS t {$statsBase} AND py.status='unpaid' AND (p.notes IS NULL OR p.notes NOT LIKE '[RETURNED]%')", $statsParams)['t'];
    $statsTotal    = $statsPaid + $statsUnpaid + $statsReturned;

    jsonResponse(true, count($patients) . ' patient(s).', [
        'patients' => $patients,
        'stats'    => [
            'total'    => $statsTotal,
            'paid'     => $statsPaid,
            'unpaid'   => $statsUnpaid,
            'returned' => $statsReturned,
            'revenue'  => formatCurrency($statsRevenue),
            'pending'  => formatCurrency($statsPending),
        ],
    ]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * generateToken
 * Creates: tokens row → patients row → payments row — all in one transaction.
 *
 * Business rule: Token is generated TODAY but assigned to the APPOINTMENT DATE
 * (visit_datetime). Token numbering resets per (doctor + appointment date).
 *
 * POST fields:
 *   doctor_id*      — attending doctor
 *   name*           — patient name
 *   gender*         — male|female|other
 *   visit_datetime* — datetime-local value (appointment date + time)
 *   age             — optional
 *   phone           — optional
 *   address         — optional
 *   notes           — optional
 *   payment_status  — 'paid' | 'unpaid' (default unpaid)
 *   payment_method  — cash|card|insurance|online (required if paid)
 */
function generateToken(): void
{
    $userId = getCurrentUserId();

    // ── Input collection ──────────────────────────────────────
    $doctorId       = (int)  ($_POST['doctor_id']       ?? 0);
    $name           = trim(  $_POST['name']             ?? '');
    $gender         = trim(  $_POST['gender']           ?? '');
    $visitDatetime  = trim(  $_POST['visit_datetime']   ?? '');
    $age            = trim(  $_POST['age']              ?? '');
    $phone          = trim(  $_POST['phone']            ?? '');
    $address        = trim(  $_POST['address']          ?? '');
    $notes          = trim(  $_POST['notes']            ?? '');
    $paymentStatus  = trim(  $_POST['payment_status']   ?? 'unpaid');
    $paymentMethod  = trim(  $_POST['payment_method']   ?? '');

    // ── Validation ────────────────────────────────────────────
    if ($doctorId <= 0) jsonResponse(false, 'Please select a doctor.');
    if (empty($name))   jsonResponse(false, 'Patient name is required.');

    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Please select the patient\'s gender.');
    }

    // Parse appointment datetime
    if (empty($visitDatetime)) {
        jsonResponse(false, 'Please select an appointment date and time.');
    }

    try {
        $dtObj     = new DateTime($visitDatetime);
        $visitDate = $dtObj->format('Y-m-d');
        $visitTime = $dtObj->format('H:i:s');
    } catch (Exception $e) {
        jsonResponse(false, 'Invalid appointment date/time format.');
    }

    // Age
    if ($age !== '' && (!ctype_digit($age) || (int)$age < 0 || (int)$age > 150)) {
        jsonResponse(false, 'Age must be a number between 0 and 150.');
    }

    // Payment
    if (!in_array($paymentStatus, ['paid', 'unpaid'], true)) {
        $paymentStatus = 'unpaid';
    }

    $validMethods = ['cash', 'card', 'insurance', 'online'];
    if ($paymentStatus === 'paid') {
        if (!in_array($paymentMethod, $validMethods, true)) {
            jsonResponse(false, 'Please select a payment method for a paid visit.');
        }
    } else {
        $paymentMethod = null;
    }

    // ── Doctor + max tokens check (per appointment date) ──────
    $doctor = Database::fetchOne(
        "SELECT id, name, fee FROM doctors WHERE id = ? AND is_active = 1",
        [$doctorId]
    );
    if (!$doctor) jsonResponse(false, 'Selected doctor is not available.');

    $maxTokens     = (int) getSetting('max_tokens_per_day', '200');
    $dateCount     = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE doctor_id = ? AND visit_date = ?",
        [$doctorId, $visitDate]
    )['cnt'];

    if ($dateCount >= $maxTokens) {
        jsonResponse(
            false,
            "Dr. {$doctor['name']}'s appointment list for " .
            date('d/m/Y', strtotime($visitDate)) .
            " is full (max {$maxTokens} tokens)."
        );
    }

    // ── Atomic: token + patient + payment ─────────────────────
    Database::beginTransaction();
    try {
        // Token number resets per (doctor + appointment date)
        $nextToken = getNextTokenNumber($doctorId, $visitDate);

        // Insert token with appointment date
        $tokenId = Database::insert(
            "INSERT INTO tokens (token_number, doctor_id, token_date)
             VALUES (?, ?, ?)",
            [$nextToken, $doctorId, $visitDate]
        );

        // Insert patient with appointment date + time
        $patientId = Database::insert(
            "INSERT INTO patients
                (token_id, serial_number, name, age, phone, gender,
                 address, doctor_id, receptionist_id, visit_date, visit_time, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tokenId,
                $nextToken,
                $name,
                $age !== '' ? (int)$age : null,
                $phone   ?: null,
                $gender,
                $address ?: null,
                $doctorId,
                $userId,
                $visitDate,
                $visitTime,
                $notes ?: null,
            ]
        );

        // Insert payment
        Database::insert(
            "INSERT INTO payments (patient_id, amount, status, payment_method)
             VALUES (?, ?, ?, ?)",
            [
                $patientId,
                (float) $doctor['fee'],
                $paymentStatus,
                $paymentMethod,
            ]
        );

        Database::commit();
    } catch (Exception $e) {
        Database::rollback();
        error_log('generateToken error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to generate token. Please try again.');
    }

    $prefix       = getSetting('token_prefix', 'TKN');
    $tokenDisplay = $prefix . '-' . str_pad($nextToken, 3, '0', STR_PAD_LEFT);

    logActivity($userId, 'token_generated', [
        'patient_id'   => $patientId,
        'token'        => $tokenDisplay,
        'doctor'       => $doctor['name'],
        'visit_date'   => $visitDate,
    ]);

    jsonResponse(true, "Token {$tokenDisplay} generated for {$name}.", [
        'token_id'      => $tokenId,
        'patient_id'    => $patientId,
        'token_number'  => $nextToken,
        'token_display' => $tokenDisplay,
        'visit_date'    => $visitDate,
        'next_token'    => $nextToken + 1,
    ]);
}

/**
 * updatePatient
 * Edit patient details. Same-day only — other days require admin.
 * POST fields: id*, name*, gender*, age, phone, address, notes, doctor_id*
 */
function updatePatient(): void
{
    $id       = (int) ($_POST['id']        ?? 0);
    $name     = trim( $_POST['name']       ?? '');
    $gender   = trim( $_POST['gender']     ?? '');
    $age      = trim( $_POST['age']        ?? '');
    $phone    = trim( $_POST['phone']      ?? '');
    $address  = trim( $_POST['address']    ?? '');
    $notes    = trim( $_POST['notes']      ?? '');
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);

    if ($id <= 0)       jsonResponse(false, 'Invalid patient ID.');
    if (empty($name))   jsonResponse(false, 'Patient name is required.');
    if ($doctorId <= 0) jsonResponse(false, 'Please select a doctor.');

    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Please select a valid gender.');
    }

    // Verify this receptionist owns this patient record
    $existing = Database::fetchOne(
        "SELECT id, visit_date, receptionist_id FROM patients WHERE id = ?",
        [$id]
    );
    if (!$existing) jsonResponse(false, 'Patient not found.');

    // Only the receptionist who created it (or admin) may edit
    if ((int)$existing['receptionist_id'] !== getCurrentUserId() && !isAdmin()) {
        jsonResponse(false, 'You can only edit patients you registered.');
    }

    Database::execute(
        "UPDATE patients
         SET name=?, gender=?, age=?, phone=?, address=?, notes=?, doctor_id=?
         WHERE id=?",
        [
            $name,
            $gender,
            $age !== '' ? (int)$age : null,
            $phone   ?: null,
            $address ?: null,
            $notes   ?: null,
            $doctorId,
            $id,
        ]
    );

    // Also update the linked payment amount if doctor changed
    $doctor = Database::fetchOne("SELECT fee FROM doctors WHERE id = ?", [$doctorId]);
    if ($doctor) {
        Database::execute(
            "UPDATE payments SET amount = ? WHERE patient_id = ?",
            [(float) $doctor['fee'], $id]
        );
    }

    logActivity(getCurrentUserId(), 'patient_updated', ['id' => $id, 'name' => $name]);
    jsonResponse(true, "{$name}'s record has been updated.");
}

/**
 * updatePayment
 * Toggle paid/unpaid and set payment method.
 * POST fields: patient_id*, payment_status*, payment_method
 */
function updatePayment(): void
{
    $patientId     = (int)  ($_POST['patient_id']     ?? 0);
    $paymentStatus = trim(  $_POST['payment_status']  ?? '');
    $paymentMethod = trim(  $_POST['payment_method']  ?? '');

    if ($patientId <= 0) jsonResponse(false, 'Invalid patient ID.');

    if (!in_array($paymentStatus, ['paid', 'unpaid'], true)) {
        jsonResponse(false, 'Invalid payment status.');
    }

    $validMethods = ['cash', 'card', 'insurance', 'online'];
    if ($paymentStatus === 'paid' && !in_array($paymentMethod, $validMethods, true)) {
        jsonResponse(false, 'Please select a payment method.');
    }

    // Verify the payment record exists
    $payment = Database::fetchOne(
        "SELECT id FROM payments WHERE patient_id = ?",
        [$patientId]
    );
    if (!$payment) jsonResponse(false, 'Payment record not found.');

    Database::execute(
        "UPDATE payments
         SET status = ?, payment_method = ?
         WHERE patient_id = ?",
        [
            $paymentStatus,
            $paymentStatus === 'paid' ? $paymentMethod : null,
            $patientId,
        ]
    );

    $label = $paymentStatus === 'paid' ? 'marked as Paid' : 'marked as Unpaid';
    logActivity(getCurrentUserId(), 'payment_updated', [
        'patient_id' => $patientId,
        'status'     => $paymentStatus,
        'method'     => $paymentMethod ?: null,
    ]);

    jsonResponse(true, "Payment has been {$label}.", [
        'payment_status' => $paymentStatus,
        'payment_method' => $paymentMethod ?: null,
    ]);
}

/**
 * markReturned
 * Toggle a patient's returned/active visit status.
 * Only the registering receptionist may mark their own patients.
 * POST fields: patient_id*, mark_action ('return' | 'activate')
 */
function markReturned(): void
{
    $patientId  = (int)  ($_POST['patient_id']  ?? 0);
    $markAction = trim(  $_POST['mark_action']  ?? 'return');

    if ($patientId <= 0) jsonResponse(false, 'Invalid patient ID.');

    $patient = Database::fetchOne(
        "SELECT id, name, notes, receptionist_id FROM patients WHERE id = ?",
        [$patientId]
    );
    if (!$patient) jsonResponse(false, 'Patient not found.');

    // Only the registering receptionist (or admin) may mark returned
    if ((int)$patient['receptionist_id'] !== getCurrentUserId() && !isAdmin()) {
        jsonResponse(false, 'You can only update patients you registered.');
    }

    if ($markAction === 'return') {
        if (!str_starts_with($patient['notes'] ?? '', '[RETURNED]')) {
            $newNotes = '[RETURNED]' . ($patient['notes'] ?? '');
            Database::execute("UPDATE patients SET notes = ? WHERE id = ?", [$newNotes, $patientId]);
        }
        Database::execute(
            "UPDATE payments SET status='unpaid', payment_method=NULL WHERE patient_id=?",
            [$patientId]
        );
        logActivity(getCurrentUserId(), 'patient_returned', ['id' => $patientId, 'name' => $patient['name']]);
        jsonResponse(true, "{$patient['name']} marked as Returned.", ['visit_status' => 'returned']);
    } else {
        $cleanNotes = ltrim(str_replace('[RETURNED]', '', $patient['notes'] ?? ''));
        Database::execute("UPDATE patients SET notes = ? WHERE id = ?", [$cleanNotes ?: null, $patientId]);
        logActivity(getCurrentUserId(), 'patient_activated', ['id' => $patientId, 'name' => $patient['name']]);
        jsonResponse(true, "{$patient['name']} reverted to Active.", ['visit_status' => 'active']);
    }
}

/**
 * deletePatient
 * Removes patient + token + payment records (CASCADE handles DB).
 * POST fields: id*
 */
function deletePatient(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid patient ID.');

    $patient = Database::fetchOne(
        "SELECT id, name, receptionist_id, token_id FROM patients WHERE id = ?",
        [$id]
    );
    if (!$patient) jsonResponse(false, 'Patient not found.');

    // Only the registering receptionist or admin may delete
    if ((int)$patient['receptionist_id'] !== getCurrentUserId() && !isAdmin()) {
        jsonResponse(false, 'You can only delete patients you registered.');
    }

    // Delete token (CASCADE removes patient + payment via FK)
    Database::execute("DELETE FROM tokens WHERE id = ?", [$patient['token_id']]);

    logActivity(getCurrentUserId(), 'patient_deleted', [
        'id'   => $id,
        'name' => $patient['name'],
    ]);

    jsonResponse(true, "{$patient['name']}'s record has been deleted.");
}