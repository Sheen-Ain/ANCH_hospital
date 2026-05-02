<?php
/**
 * ajax/admin/patients.php
 * Admin-only patient record browser — all dates, all receptionists.
 *
 * Dispatch via `action`:
 *   getPatients     — paginated list with search/filter (incl. returned)
 *   getPatientById  — full record for detail modal
 *   getDoctors      — active doctors with next token numbers (for generate token modal)
 *   getNextToken    — preview next token for doctor + date
 *   generateToken   — create token + patient + payment (admin version)
 *   updatePayment   — update payment status and method for any patient
 *   markReturned    — toggle patient returned/active status
 *   deletePatient   — admin hard-delete (no date restriction)
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
        case 'getPatients':       getPatients();       break;
        case 'getPatientById':    getPatientById();    break;
        case 'getPatientForEdit': getPatientForEdit(); break;
        case 'getDoctors':        getDoctors();        break;
        case 'getNextToken':      getNextToken();      break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'generateToken': generateToken(); break;
        case 'updatePatient': updatePatient(); break;
        case 'updatePayment': updatePayment(); break;
        case 'markReturned':  markReturned();  break;
        case 'deletePatient': deletePatient(); break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// GET HANDLERS

function getPatients(): void
{
    $search       = trim($_GET['search']        ?? '');
    $dateFrom     = trim($_GET['date_from']     ?? '');
    $dateTo       = trim($_GET['date_to']       ?? '');
    $doctorId     = (int) ($_GET['doctor_id']   ?? 0);
    $statusFilter = trim($_GET['status_filter'] ?? '');
    $page         = max(1, (int) ($_GET['page']     ?? 1));
    $perPage      = min(200, max(10, (int) ($_GET['per_page'] ?? 50)));
    $offset       = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $like    = "%{$search}%";
        $where[] = "(p.name LIKE ? OR p.phone LIKE ? OR d.name LIKE ?)";
        array_push($params, $like, $like, $like);
    }
    if ($dateFrom !== '') { $where[] = "p.visit_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo   !== '') { $where[] = "p.visit_date <= ?"; $params[] = $dateTo;   }
    if ($doctorId  > 0)  { $where[] = "p.doctor_id = ?";   $params[] = $doctorId; }

    if ($statusFilter === 'returned') {
        $where[] = "p.notes LIKE '[RETURNED]%'";
    } elseif (in_array($statusFilter, ['paid', 'unpaid'], true)) {
        $where[] = "py.status = ? AND (p.notes IS NULL OR p.notes NOT LIKE '[RETURNED]%')";
        $params[] = $statusFilter;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM   patients p
         JOIN   doctors d ON d.id = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         {$whereSql}",
        $params
    )['cnt'];

    $rows = Database::fetchAll(
        "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.serial_number,
             t.token_number,
             d.id   AS doctor_id, d.name AS doctor_name, d.specialization,
             py.status AS payment_status, py.amount, py.payment_method,
             CONCAT(u.first_name,' ',u.last_name) AS receptionist_name
         FROM   patients p
         JOIN   tokens   t  ON t.id  = p.token_id
         JOIN   doctors  d  ON d.id  = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         JOIN   users    u  ON u.id  = p.receptionist_id
         {$whereSql}
         ORDER  BY p.visit_date DESC, p.id DESC
         LIMIT  ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    foreach ($rows as &$r) {
        $r['visit_status']   = (str_starts_with($r['notes'] ?? '', '[RETURNED]')) ? 'returned' : 'active';
        $r['notes_display']  = ltrim(str_replace('[RETURNED]', '', $r['notes'] ?? ''));
        $r['visit_date_fmt'] = formatDate($r['visit_date']);
        $r['visit_time_fmt'] = formatTime($r['visit_time']);
        $r['amount_fmt']     = formatCurrency((float)($r['amount'] ?? 0));
    }
    unset($r);

    jsonResponse(true, "{$total} patient(s) found.", [
        'patients'    => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / $perPage),
    ]);
}

function getPatientById(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $row = Database::fetchOne(
        "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.serial_number,
             t.token_number,
             d.name   AS doctor_name, d.specialization,
             py.status AS payment_status, py.amount, py.payment_method,
             CONCAT(u.first_name,' ',u.last_name) AS receptionist_name
         FROM   patients p
         JOIN   tokens   t  ON t.id  = p.token_id
         JOIN   doctors  d  ON d.id  = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         JOIN   users    u  ON u.id  = p.receptionist_id
         WHERE  p.id = ?",
        [$id]
    );

    if (!$row) jsonResponse(false, 'Patient not found.');

    $row['visit_status']   = (str_starts_with($row['notes'] ?? '', '[RETURNED]')) ? 'returned' : 'active';
    $row['notes_display']  = ltrim(str_replace('[RETURNED]', '', $row['notes'] ?? ''));
    $row['visit_date_fmt'] = formatDate($row['visit_date']);
    $row['visit_time_fmt'] = formatTime($row['visit_time']);
    $row['amount_fmt']     = formatCurrency((float)($row['amount'] ?? 0));
    $row['token_display']  = getSetting('token_prefix', 'TKN') . '-' . $row['token_number'];
    $row['receipt_html']   = renderReceiptHTML($id);

    jsonResponse(true, 'Patient loaded.', ['patient' => $row]);
}

/**
 * getPatientForEdit
 * Returns raw patient fields for the edit form (no receipt HTML, includes doctor_id).
 * GET params: id
 */
function getPatientForEdit(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $row = Database::fetchOne(
        "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.doctor_id,
             t.token_number,
             d.name AS doctor_name,
             py.status AS payment_status, py.amount, py.payment_method
         FROM   patients p
         JOIN   tokens   t  ON t.id  = p.token_id
         JOIN   doctors  d  ON d.id  = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         WHERE  p.id = ?",
        [$id]
    );

    if (!$row) jsonResponse(false, 'Patient not found.');

    jsonResponse(true, 'Patient loaded.', ['patient' => $row]);
}

/**
 * updatePatient
 * Admin can edit any patient record with no date restriction.
 * POST fields: id*, doctor_id*, name*, gender*, visit_datetime*,
 *              age, phone, address, notes, payment_status, payment_method
 */
function updatePatient(): void
{
    $id            = (int)  ($_POST['id']            ?? 0);
    $doctorId      = (int)  ($_POST['doctor_id']     ?? 0);
    $name          = trim(  $_POST['name']           ?? '');
    $gender        = trim(  $_POST['gender']         ?? '');
    $visitDatetime = trim(  $_POST['visit_datetime'] ?? '');
    $age           = trim(  $_POST['age']            ?? '');
    $phone         = trim(  $_POST['phone']          ?? '');
    $address       = trim(  $_POST['address']        ?? '');
    $notes         = trim(  $_POST['notes']          ?? '');
    $paymentStatus = trim(  $_POST['payment_status'] ?? '');
    $paymentMethod = trim(  $_POST['payment_method'] ?? '');

    if ($id <= 0)       jsonResponse(false, 'Invalid patient ID.');
    if (empty($name))   jsonResponse(false, 'Patient name is required.');
    if ($doctorId <= 0) jsonResponse(false, 'Please select a doctor.');
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Please select a valid gender.');
    }
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

    if ($age !== '' && (!ctype_digit($age) || (int)$age < 0 || (int)$age > 150)) {
        jsonResponse(false, 'Age must be a number between 0 and 150.');
    }

    if (!in_array($paymentStatus, ['paid', 'unpaid'], true)) {
        jsonResponse(false, 'Invalid payment status.');
    }
    $validMethods = ['cash', 'card', 'insurance', 'online'];
    if ($paymentStatus === 'paid' && !in_array($paymentMethod, $validMethods, true)) {
        jsonResponse(false, 'Please select a valid payment method.');
    }

    $patient = Database::fetchOne(
        "SELECT id, name, token_id, notes FROM patients WHERE id = ?", [$id]
    );
    if (!$patient) jsonResponse(false, 'Patient not found.');

    $doctor = Database::fetchOne(
        "SELECT id, name FROM doctors WHERE id = ? AND is_active = 1", [$doctorId]
    );
    if (!$doctor) jsonResponse(false, 'Selected doctor is not available.');

    // Preserve [RETURNED] marker in notes unless we are marking paid
    $existingNotes = $patient['notes'] ?? '';
    $isReturned    = str_starts_with($existingNotes, '[RETURNED]');

    if ($isReturned && $paymentStatus !== 'paid') {
        $finalNotes = '[RETURNED]' . ($notes !== '' ? $notes : '');
    } else {
        $finalNotes = $notes !== '' ? $notes : null;
    }

    Database::beginTransaction();
    try {
        Database::execute(
            "UPDATE patients
             SET name=?, age=?, phone=?, gender=?, address=?, notes=?,
                 doctor_id=?, visit_date=?, visit_time=?
             WHERE id=?",
            [
                $name,
                $age !== '' ? (int)$age : null,
                $phone   ?: null,
                $gender,
                $address ?: null,
                $finalNotes,
                $doctorId,
                $visitDate,
                $visitTime,
                $id,
            ]
        );

        // Keep token's doctor_id + date in sync
        Database::execute(
            "UPDATE tokens SET doctor_id=?, token_date=? WHERE id=?",
            [$doctorId, $visitDate, $patient['token_id']]
        );

        Database::execute(
            "UPDATE payments SET status=?, payment_method=? WHERE patient_id=?",
            [
                $paymentStatus,
                $paymentStatus === 'paid' ? $paymentMethod : null,
                $id,
            ]
        );

        Database::commit();
    } catch (Exception $e) {
        Database::rollback();
        error_log('admin updatePatient error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update patient. Please try again.');
    }

    logActivity(getCurrentUserId(), 'admin_patient_updated', [
        'id'     => $id,
        'name'   => $name,
        'doctor' => $doctor['name'],
    ]);

    jsonResponse(true, "{$name}'s record has been updated successfully.");
}

function getDoctors(): void
{
    $visitDate = trim($_GET['visit_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
        $visitDate = date('Y-m-d');
    }

    $doctors = Database::fetchAll(
        "SELECT
             d.id, d.name, d.specialization, d.designation, d.fee, d.gender,
             (SELECT MAX(t.token_number) FROM tokens t
              WHERE  t.doctor_id = d.id AND t.token_date = ?) AS last_token,
             (SELECT COUNT(*) FROM patients p
              WHERE  p.doctor_id = d.id AND p.visit_date = ?) AS date_patients
         FROM doctors d
         WHERE d.is_active = 1
         ORDER BY d.name ASC",
        [$visitDate, $visitDate]
    );

    $maxTokens = (int) getSetting('max_tokens_per_day', '200');
    $prefix    = getSetting('token_prefix', 'TKN');

    foreach ($doctors as &$d) {
        $next              = ((int)($d['last_token'] ?? 0)) + 1;
        $d['next_token']   = $next;
        $d['token_display']= $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
        $d['fee_fmt']      = formatCurrency((float) $d['fee']);
        $d['tokens_left']  = max(0, $maxTokens - (int) $d['date_patients']);
        $d['is_full']      = (int) $d['date_patients'] >= $maxTokens;
    }
    unset($d);

    jsonResponse(true, count($doctors) . ' doctor(s).', [
        'doctors'    => $doctors,
        'max_tokens' => $maxTokens,
        'prefix'     => $prefix,
        'visit_date' => $visitDate,
    ]);
}

function getNextToken(): void
{
    $doctorId  = (int) ($_GET['doctor_id']  ?? 0);
    $visitDate = trim($_GET['visit_date']   ?? date('Y-m-d'));

    if ($doctorId <= 0) jsonResponse(false, 'Invalid doctor ID.');
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

// POST HANDLERS

function generateToken(): void
{
    $userId        = getCurrentUserId();
    $doctorId      = (int)  ($_POST['doctor_id']       ?? 0);
    $name          = trim(  $_POST['name']             ?? '');
    $gender        = trim(  $_POST['gender']           ?? '');
    $visitDatetime = trim(  $_POST['visit_datetime']   ?? '');
    $age           = trim(  $_POST['age']              ?? '');
    $phone         = trim(  $_POST['phone']            ?? '');
    $address       = trim(  $_POST['address']          ?? '');
    $notes         = trim(  $_POST['notes']            ?? '');
    $paymentStatus = trim(  $_POST['payment_status']   ?? 'unpaid');
    $paymentMethod = trim(  $_POST['payment_method']   ?? '');

    if ($doctorId <= 0) jsonResponse(false, 'Please select a doctor.');
    if (empty($name))   jsonResponse(false, 'Patient name is required.');
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Please select the patient gender.');
    }
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

    if ($age !== '' && (!ctype_digit($age) || (int)$age < 0 || (int)$age > 150)) {
        jsonResponse(false, 'Age must be a number between 0 and 150.');
    }

    if (!in_array($paymentStatus, ['paid', 'unpaid'], true)) $paymentStatus = 'unpaid';

    $validMethods = ['cash', 'card', 'insurance', 'online'];
    if ($paymentStatus === 'paid') {
        if (!in_array($paymentMethod, $validMethods, true)) {
            jsonResponse(false, 'Please select a payment method for a paid visit.');
        }
    } else {
        $paymentMethod = null;
    }

    $doctor = Database::fetchOne(
        "SELECT id, name, fee FROM doctors WHERE id = ? AND is_active = 1",
        [$doctorId]
    );
    if (!$doctor) jsonResponse(false, 'Selected doctor is not available.');

    $maxTokens = (int) getSetting('max_tokens_per_day', '200');
    $dateCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM patients WHERE doctor_id = ? AND visit_date = ?",
        [$doctorId, $visitDate]
    )['cnt'];

    if ($dateCount >= $maxTokens) {
        jsonResponse(false, "Dr. {$doctor['name']}'s list for " . date('d/m/Y', strtotime($visitDate)) . " is full (max {$maxTokens}).");
    }

    Database::beginTransaction();
    try {
        $nextToken = getNextTokenNumber($doctorId, $visitDate);
        $tokenId   = Database::insert(
            "INSERT INTO tokens (token_number, doctor_id, token_date) VALUES (?, ?, ?)",
            [$nextToken, $doctorId, $visitDate]
        );
        $patientId = Database::insert(
            "INSERT INTO patients
                (token_id, serial_number, name, age, phone, gender,
                 address, doctor_id, receptionist_id, visit_date, visit_time, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tokenId, $nextToken, $name,
                $age !== '' ? (int)$age : null,
                $phone ?: null, $gender, $address ?: null,
                $doctorId, $userId, $visitDate, $visitTime, $notes ?: null,
            ]
        );
        Database::insert(
            "INSERT INTO payments (patient_id, amount, status, payment_method) VALUES (?, ?, ?, ?)",
            [$patientId, (float) $doctor['fee'], $paymentStatus, $paymentMethod]
        );
        Database::commit();
    } catch (Exception $e) {
        Database::rollback();
        error_log('admin generateToken error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to generate token. Please try again.');
    }

    $prefix       = getSetting('token_prefix', 'TKN');
    $tokenDisplay = $prefix . '-' . str_pad($nextToken, 3, '0', STR_PAD_LEFT);

    logActivity($userId, 'admin_token_generated', [
        'patient_id' => $patientId,
        'token'      => $tokenDisplay,
        'doctor'     => $doctor['name'],
        'visit_date' => $visitDate,
    ]);

    jsonResponse(true, "Token {$tokenDisplay} generated for {$name}.", [
        'token_id'      => $tokenId,
        'patient_id'    => $patientId,
        'token_number'  => $nextToken,
        'token_display' => $tokenDisplay,
        'visit_date'    => $visitDate,
    ]);
}

function updatePayment(): void
{
    $patientId = (int)  ($_POST['patient_id']    ?? 0);
    $status    = trim(  $_POST['payment_status'] ?? '');
    $method    = trim(  $_POST['payment_method'] ?? '');

    if ($patientId <= 0) jsonResponse(false, 'Invalid patient ID.');
    if (!in_array($status, ['paid', 'unpaid'], true)) jsonResponse(false, 'Invalid payment status.');

    $validMethods = ['cash', 'card', 'insurance', 'online'];
    if ($status === 'paid' && !in_array($method, $validMethods, true)) {
        jsonResponse(false, 'Please select a valid payment method.');
    }

    $payment = Database::fetchOne("SELECT id FROM payments WHERE patient_id = ?", [$patientId]);
    if (!$payment) jsonResponse(false, 'Payment record not found.');

    Database::execute(
        "UPDATE payments SET status=?, payment_method=? WHERE patient_id=?",
        [$status, $status === 'paid' ? $method : null, $patientId]
    );

    // If marking as paid, also remove any [RETURNED] marker
    $visitStatus = 'active';
    if ($status === 'paid') {
        $patient = Database::fetchOne("SELECT notes FROM patients WHERE id = ?", [$patientId]);
        if ($patient && str_starts_with($patient['notes'] ?? '', '[RETURNED]')) {
            $cleanNotes = ltrim(str_replace('[RETURNED]', '', $patient['notes']));
            Database::execute("UPDATE patients SET notes = ? WHERE id = ?", [$cleanNotes ?: null, $patientId]);
        }
    }

    logActivity(getCurrentUserId(), 'admin_payment_updated', [
        'patient_id' => $patientId,
        'status'     => $status,
        'method'     => $method,
    ]);

    jsonResponse(true, 'Payment updated successfully.', [
        'payment_status' => $status,
        'payment_method' => $status === 'paid' ? $method : null,
        'visit_status'   => $visitStatus,
    ]);
}

function markReturned(): void
{
    $patientId  = (int)  ($_POST['patient_id']  ?? 0);
    $markAction = trim(  $_POST['mark_action']  ?? 'return');

    if ($patientId <= 0) jsonResponse(false, 'Invalid patient ID.');

    $patient = Database::fetchOne("SELECT id, name, notes FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) jsonResponse(false, 'Patient not found.');

    if ($markAction === 'return') {
        if (!str_starts_with($patient['notes'] ?? '', '[RETURNED]')) {
            $newNotes = '[RETURNED]' . ($patient['notes'] ?? '');
            Database::execute("UPDATE patients SET notes = ? WHERE id = ?", [$newNotes, $patientId]);
        }
        Database::execute(
            "UPDATE payments SET status='unpaid', payment_method=NULL WHERE patient_id=?",
            [$patientId]
        );
        logActivity(getCurrentUserId(), 'admin_patient_returned', ['id' => $patientId, 'name' => $patient['name']]);
        jsonResponse(true, "{$patient['name']} marked as Returned.", ['visit_status' => 'returned']);
    } else {
        $cleanNotes = ltrim(str_replace('[RETURNED]', '', $patient['notes'] ?? ''));
        Database::execute("UPDATE patients SET notes = ? WHERE id = ?", [$cleanNotes ?: null, $patientId]);
        logActivity(getCurrentUserId(), 'admin_patient_activated', ['id' => $patientId, 'name' => $patient['name']]);
        jsonResponse(true, "{$patient['name']} reverted to Active.", ['visit_status' => 'active']);
    }
}

function deletePatient(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid patient ID.');

    $p = Database::fetchOne("SELECT id, name, token_id FROM patients WHERE id = ?", [$id]);
    if (!$p) jsonResponse(false, 'Patient not found.');

    Database::execute("DELETE FROM tokens WHERE id = ?", [$p['token_id']]);

    logActivity(getCurrentUserId(), 'admin_patient_deleted', ['id' => $id, 'name' => $p['name']]);
    jsonResponse(true, "{$p['name']}'s record has been deleted.");
}