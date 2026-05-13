<?php
/**
 * ajax/admin/admissions.php
 * Single AJAX file for ALL admission operations.
 *
 * Dispatch via `action`:
 *   getAdmissions      — list all admissions with search/filter
 *   getAdmissionById   — fetch single record for view/edit
 *   createAdmission    — new admission, books the room
 *   updateAdmission    — edit details (only while still admitted)
 *   dischargePatient   — set status=discharged, free the room
 *   deleteAdmission    — hard delete (only discharged, no billing records)
 *   getFormData        — returns active doctors + available rooms for dropdowns
 *
 * Auth: Admin only. (Receptionist uses receptionist/admissions.php)
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
// Receptionists need access to create/view/discharge admissions.
// Only admin can delete. requireReceptionist() passes for both roles.
requireReceptionist();

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'getAdmissions':    getAdmissions();    break;
        case 'getAdmissionById': getAdmissionById(); break;
        case 'getFormData':      getFormData();      break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'createAdmission':   createAdmission();   break;
        case 'updateAdmission':   updateAdmission();   break;
        case 'dischargePatient':  dischargePatient();  break;
        case 'deleteAdmission':   deleteAdmission();   break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getAdmissions
 * GET params: search, status ('admitted'|'discharged'|''), room_type
 */
function getAdmissions(): void
{
    $search   = trim($_GET['search']    ?? '');
    $status   = trim($_GET['status']    ?? '');
    $roomType = trim($_GET['room_type'] ?? '');

    $where  = [];
    $params = [];

    if ($search !== '') {
        $like    = "%{$search}%";
        $where[] = "(a.patient_name LIKE ? OR a.guardian_name LIKE ? OR d.name LIKE ? OR r.room_number LIKE ? OR a.disease_name LIKE ?)";
        $params  = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if (in_array($status, ['admitted', 'discharged'], true)) {
        $where[]  = "a.status = ?";
        $params[] = $status;
    }

    if (in_array($roomType, ['general', 'private', 'icu'], true)) {
        $where[]  = "r.room_type = ?";
        $params[] = $roomType;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $rows = Database::fetchAll(
        "SELECT
             a.id, a.patient_name, a.guardian_name, a.gender, a.address,
             a.disease_name, a.disease_treatment_cost, a.admission_reason,
             a.room_fee_per_day, a.admitted_at, a.discharged_at, a.status,
             d.id   AS doctor_id,   d.name AS doctor_name,
             r.id   AS room_id,     r.room_number, r.room_type,
             u.first_name AS created_by_first, u.last_name AS created_by_last,
             DATEDIFF(IFNULL(a.discharged_at, NOW()), a.admitted_at) + 1 AS days_count
         FROM admissions a
         JOIN doctors d ON d.id = a.doctor_id
         JOIN rooms   r ON r.id = a.room_id
         JOIN users   u ON u.id = a.created_by
         {$whereSql}
         ORDER BY a.id DESC",
        $params
    );

    foreach ($rows as &$row) {
        $days = max(1, (int) $row['days_count']);
        $row['days_count']           = $days;
        $row['room_total']           = $days * (float) $row['room_fee_per_day'];
        $row['estimated_total']      = $row['room_total'] + (float) $row['disease_treatment_cost'];
        $row['room_total_fmt']       = formatCurrency($row['room_total']);
        $row['treatment_cost_fmt']   = formatCurrency((float) $row['disease_treatment_cost']);
        $row['estimated_total_fmt']  = formatCurrency($row['estimated_total']);
        $row['room_fee_fmt']         = formatCurrency((float) $row['room_fee_per_day']);
        $row['admitted_fmt']         = formatDateTime($row['admitted_at']);
        $row['discharged_fmt']       = $row['discharged_at'] ? formatDateTime($row['discharged_at']) : '—';
        $row['created_by_name']      = $row['created_by_first'] . ' ' . $row['created_by_last'];
        $row['admission_ref']        = 'ADM-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
    }
    unset($row);

    // Summary counts
    $summary = [
        'total'      => count($rows),
        'admitted'   => count(array_filter($rows, fn($r) => $r['status'] === 'admitted')),
        'discharged' => count(array_filter($rows, fn($r) => $r['status'] === 'discharged')),
    ];

    jsonResponse(true, count($rows) . ' record(s) found.', [
        'admissions' => $rows,
        'summary'    => $summary,
    ]);
}

/**
 * getAdmissionById
 * GET params: id (int)
 */
function getAdmissionById(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $row = Database::fetchOne(
        "SELECT a.*,
                d.name AS doctor_name, r.room_number, r.room_type,
                u.first_name AS created_by_first, u.last_name AS created_by_last
         FROM   admissions a
         JOIN   doctors d ON d.id = a.doctor_id
         JOIN   rooms   r ON r.id = a.room_id
         JOIN   users   u ON u.id = a.created_by
         WHERE  a.id = ?",
        [$id]
    );

    if (!$row) jsonResponse(false, 'Admission not found.');

    $days = max(1, (int) ceil((strtotime($row['discharged_at'] ?? 'now') - strtotime($row['admitted_at'])) / 86400));
    $row['days_count']          = $days;
    $row['estimated_total_fmt'] = formatCurrency($days * (float)$row['room_fee_per_day'] + (float)$row['disease_treatment_cost']);
    $row['admission_ref']       = 'ADM-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
    $row['admitted_fmt']        = formatDateTime($row['admitted_at']);
    $row['discharged_fmt']      = $row['discharged_at'] ? formatDateTime($row['discharged_at']) : '—';

    jsonResponse(true, 'Loaded.', ['admission' => $row]);
}

/**
 * getFormData
 * Returns active doctors + available rooms (not currently occupied) for form dropdowns.
 * If editing an existing admission, pass current_room_id to include that room.
 * GET params: current_room_id (optional)
 */
function getFormData(): void
{
    $currentRoomId = (int) ($_GET['current_room_id'] ?? 0);

    $doctors = Database::fetchAll(
        "SELECT id, name, specialization, fee FROM doctors WHERE is_active = 1 ORDER BY name"
    );

    // Available rooms = active AND (not occupied OR is the current room being edited)
    $rooms = Database::fetchAll(
        "SELECT r.id, r.room_number, r.room_type, r.daily_fee,
                (SELECT COUNT(*) FROM admissions a
                 WHERE a.room_id = r.id AND a.status = 'admitted') AS occupied
         FROM   rooms r
         WHERE  r.is_active = 1
         ORDER  BY r.room_type, r.room_number"
    );

    // Mark rooms as available/occupied
    foreach ($rooms as &$r) {
        $r['is_occupied']  = (int) $r['occupied'] > 0;
        $r['available']    = !$r['is_occupied'] || (int) $r['id'] === $currentRoomId;
        $r['daily_fee_fmt']= formatCurrency((float) $r['daily_fee']);
    }
    unset($r);

    jsonResponse(true, 'Form data loaded.', compact('doctors', 'rooms'));
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * createAdmission
 */
function createAdmission(): void
{
    $data   = collectAndValidateAdmissionInput();
    $userId = getCurrentUserId();

    // Confirm room is available
    $roomOccupied = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM admissions WHERE room_id = ? AND status = 'admitted'",
        [$data['room_id']]
    )['cnt'];

    if ($roomOccupied > 0) {
        jsonResponse(false, 'This room is currently occupied. Please select a different room.');
    }

    // Fetch room fee at the time of admission (snapshot)
    $room = Database::fetchOne("SELECT room_number, daily_fee FROM rooms WHERE id = ?", [$data['room_id']]);
    if (!$room) jsonResponse(false, 'Selected room not found.');

    $id = Database::insert(
        "INSERT INTO admissions
            (patient_name, guardian_name, gender, address, doctor_id, room_id,
             disease_name, disease_treatment_cost, admission_reason,
             room_fee_per_day, admitted_at, status, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,  'admitted',?)",
        [
            $data['patient_name'],
            $data['guardian_name'],
            $data['gender'],
            $data['address'],
            $data['doctor_id'],
            $data['room_id'],
            $data['disease_name'],
            $data['disease_treatment_cost'],
            $data['admission_reason'],
            $room['daily_fee'],       // snapshot
            $data['admitted_at'],
            $userId,
        ]
    );

    logActivity($userId, 'admission_created', [
        'id'     => $id,
        'patient'=> $data['patient_name'],
        'room'   => $room['room_number'],
    ]);

    jsonResponse(true, "{$data['patient_name']} has been admitted successfully. Ref: ADM-" . str_pad($id, 4, '0', STR_PAD_LEFT), ['id' => $id]);
}

/**
 * updateAdmission
 * Only editable while status = 'admitted'.
 */
function updateAdmission(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $existing = Database::fetchOne(
        "SELECT id, patient_name, status, room_id FROM admissions WHERE id = ?",
        [$id]
    );
    if (!$existing) jsonResponse(false, 'Admission not found.');
    if ($existing['status'] === 'discharged') {
        jsonResponse(false, 'Cannot edit a discharged admission. Discharge records are locked.');
    }

    $data = collectAndValidateAdmissionInput();

    // If room changed, verify new room is available
    if ((int) $data['room_id'] !== (int) $existing['room_id']) {
        $roomOccupied = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM admissions WHERE room_id = ? AND status = 'admitted' AND id != ?",
            [$data['room_id'], $id]
        )['cnt'];

        if ($roomOccupied > 0) {
            jsonResponse(false, 'The newly selected room is currently occupied.');
        }

        // Refresh fee snapshot if room changed
        $room = Database::fetchOne("SELECT daily_fee FROM rooms WHERE id = ?", [$data['room_id']]);
        $roomFee = $room ? (float) $room['daily_fee'] : (float) Database::fetchOne(
            "SELECT room_fee_per_day FROM admissions WHERE id = ?", [$id]
        )['room_fee_per_day'];
    } else {
        // Keep original snapshot
        $roomFee = (float) Database::fetchOne(
            "SELECT room_fee_per_day FROM admissions WHERE id = ?", [$id]
        )['room_fee_per_day'];
    }

    Database::execute(
        "UPDATE admissions
         SET patient_name=?, guardian_name=?, gender=?, address=?,
             doctor_id=?, room_id=?, disease_name=?, disease_treatment_cost=?,
             admission_reason=?, room_fee_per_day=?, admitted_at=?
         WHERE id=?",
        [
            $data['patient_name'],
            $data['guardian_name'],
            $data['gender'],
            $data['address'],
            $data['doctor_id'],
            $data['room_id'],
            $data['disease_name'],
            $data['disease_treatment_cost'],
            $data['admission_reason'],
            $roomFee,
            $data['admitted_at'],
            $id,
        ]
    );

    logActivity(getCurrentUserId(), 'admission_updated', ['id' => $id, 'patient' => $data['patient_name']]);
    jsonResponse(true, "Admission for {$data['patient_name']} has been updated.");
}

/**
 * dischargePatient
 * POST fields: id*, discharged_at (defaults to NOW)
 */
function dischargePatient(): void
{
    $id           = (int) ($_POST['id'] ?? 0);
    $dischargedAt = trim($_POST['discharged_at'] ?? '');

    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $adm = Database::fetchOne(
        "SELECT id, patient_name, status, admitted_at FROM admissions WHERE id = ?",
        [$id]
    );
    if (!$adm)                          jsonResponse(false, 'Admission not found.');
    if ($adm['status'] === 'discharged') jsonResponse(false, 'Patient has already been discharged.');

    // Default discharge time = now
    if (empty($dischargedAt)) {
        $dischargedAt = date('Y-m-d H:i:s');
    }

    // Discharge time must be after admission time
    if (strtotime($dischargedAt) <= strtotime($adm['admitted_at'])) {
        jsonResponse(false, 'Discharge date/time must be after the admission date/time.');
    }

    Database::execute(
        "UPDATE admissions SET status='discharged', discharged_at=? WHERE id=?",
        [$dischargedAt, $id]
    );

    logActivity(getCurrentUserId(), 'patient_discharged', [
        'id'           => $id,
        'patient'      => $adm['patient_name'],
        'discharged_at'=> $dischargedAt,
    ]);

    jsonResponse(true, "{$adm['patient_name']} has been discharged successfully.");
}

/**
 * deleteAdmission
 * Admin only — receptionists may not delete admission records.
 * POST fields: id*
 */
function deleteAdmission(): void
{
    // Only admins may delete admissions
    if (!isAdmin()) {
        jsonResponse(false, 'Only administrators can delete admission records.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid ID.');

    $adm = Database::fetchOne("SELECT id, patient_name, status FROM admissions WHERE id = ?", [$id]);
    if (!$adm) jsonResponse(false, 'Admission not found.');

    if ($adm['status'] === 'admitted') {
        jsonResponse(false, "Cannot delete an active admission. Discharge the patient first.");
    }

    Database::execute("DELETE FROM admissions WHERE id = ?", [$id]);

    logActivity(getCurrentUserId(), 'admission_deleted', ['id' => $id, 'patient' => $adm['patient_name']]);
    jsonResponse(true, "Admission record for {$adm['patient_name']} has been deleted.");
}

// ════════════════════════════════════════════════════════════
// SHARED VALIDATION
// ════════════════════════════════════════════════════════════

function collectAndValidateAdmissionInput(): array
{
    $patientName   = trim($_POST['patient_name']          ?? '');
    $guardianName  = trim($_POST['guardian_name']         ?? '');
    $gender        = trim($_POST['gender']                ?? '');
    $address       = trim($_POST['address']               ?? '');
    $doctorId      = (int) ($_POST['doctor_id']           ?? 0);
    $roomId        = (int) ($_POST['room_id']             ?? 0);
    $diseaseName   = trim($_POST['disease_name']          ?? '');
    $treatmentCost = (float) ($_POST['disease_treatment_cost'] ?? 0);
    $reason        = trim($_POST['admission_reason']      ?? '');
    $admittedAt    = trim($_POST['admitted_at']           ?? '');

    if (empty($patientName))  jsonResponse(false, 'Patient name is required.');
    if (empty($guardianName)) jsonResponse(false, 'Guardian name is required.');
    if (empty($address))      jsonResponse(false, 'Address is required.');
    if (empty($diseaseName))  jsonResponse(false, 'Disease / diagnosis is required.');

    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        jsonResponse(false, 'Please select a valid gender.');
    }

    $validReasons = ['operation', 'observation', 'emergency', 'treatment', 'other'];
    if (!in_array($reason, $validReasons, true)) {
        jsonResponse(false, 'Please select a valid admission reason.');
    }

    if ($doctorId <= 0) jsonResponse(false, 'Please select the attending doctor.');
    if ($roomId   <= 0) jsonResponse(false, 'Please select a room.');

    // Verify doctor exists and is active
    $doc = Database::fetchOne("SELECT id FROM doctors WHERE id = ? AND is_active = 1", [$doctorId]);
    if (!$doc) jsonResponse(false, 'Selected doctor is not available.');

    // Verify room exists and is active
    $room = Database::fetchOne("SELECT id FROM rooms WHERE id = ? AND is_active = 1", [$roomId]);
    if (!$room) jsonResponse(false, 'Selected room is not available.');

    if ($treatmentCost < 0) jsonResponse(false, 'Treatment cost cannot be negative.');

    // admitted_at defaults to now
    if (empty($admittedAt)) {
        $admittedAt = date('Y-m-d H:i:s');
    }

    return [
        'patient_name'           => $patientName,
        'guardian_name'          => $guardianName,
        'gender'                 => $gender,
        'address'                => $address,
        'doctor_id'              => $doctorId,
        'room_id'                => $roomId,
        'disease_name'           => $diseaseName,
        'disease_treatment_cost' => $treatmentCost,
        'admission_reason'       => $reason,
        'admitted_at'            => $admittedAt,
    ];
}