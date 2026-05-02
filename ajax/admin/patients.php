<?php
/**
 * ajax/admin/patients.php
 * Admin-only patient record browser — all dates, all receptionists.
 *
 * Dispatch via `action`:
 *   getPatients     — paginated list with search/filter
 *   getPatientById  — full record for detail modal
 *   deletePatient   — admin hard-delete (no date restriction)
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
        case 'getPatients':    getPatients();    break;
        case 'getPatientById': getPatientById(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'deletePatient': deletePatient(); break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getPatients
 * GET params:
 *   search         — name, phone, doctor name
 *   date_from      — YYYY-MM-DD
 *   date_to        — YYYY-MM-DD
 *   doctor_id      — int
 *   payment_status — 'paid'|'unpaid'|''
 *   page           — int (default 1)
 *   per_page       — int (default 50)
 */
function getPatients(): void
{
    $search        = trim($_GET['search']         ?? '');
    $dateFrom      = trim($_GET['date_from']      ?? '');
    $dateTo        = trim($_GET['date_to']        ?? '');
    $doctorId      = (int) ($_GET['doctor_id']    ?? 0);
    $paymentStatus = trim($_GET['payment_status'] ?? '');
    $page          = max(1, (int) ($_GET['page']     ?? 1));
    $perPage       = min(200, max(10, (int) ($_GET['per_page'] ?? 50)));
    $offset        = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $like    = "%{$search}%";
        $where[] = "(p.name LIKE ? OR p.phone LIKE ? OR d.name LIKE ?)";
        array_push($params, $like, $like, $like);
    }
    if ($dateFrom !== '') {
        $where[] = "p.visit_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = "p.visit_date <= ?";
        $params[] = $dateTo;
    }
    if ($doctorId > 0) {
        $where[]  = "p.doctor_id = ?";
        $params[] = $doctorId;
    }
    if (in_array($paymentStatus, ['paid', 'unpaid'], true)) {
        $where[]  = "py.status = ?";
        $params[] = $paymentStatus;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total count
    $total = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM   patients p
         JOIN   doctors d ON d.id = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         {$whereSql}",
        $params
    )['cnt'];

    // Fetch page
    $rows = Database::fetchAll(
        "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.serial_number,
             t.token_number,
             d.id   AS doctor_id,   d.name AS doctor_name, d.specialization,
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
        $r['visit_date_fmt'] = formatDate($r['visit_date']);
        $r['visit_time_fmt'] = formatTime($r['visit_time']);
        $r['amount_fmt']     = formatCurrency((float)($r['amount'] ?? 0));
    }
    unset($r);

    jsonResponse(true, "{$total} patient(s) found.", [
        'patients'   => $rows,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'total_pages'=> (int) ceil($total / $perPage),
    ]);
}

/**
 * getPatientById
 * GET params: id
 */
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

    $row['visit_date_fmt'] = formatDate($row['visit_date']);
    $row['visit_time_fmt'] = formatTime($row['visit_time']);
    $row['amount_fmt']     = formatCurrency((float)($row['amount'] ?? 0));
    $row['token_display']  = getSetting('token_prefix', 'TKN') . '-' . $row['token_number'];
    $row['receipt_html']   = renderReceiptHTML($id);

    jsonResponse(true, 'Patient loaded.', ['patient' => $row]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * deletePatient — admin hard-delete, no date restriction.
 * POST fields: id*
 */
function deletePatient(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid patient ID.');

    $p = Database::fetchOne(
        "SELECT id, name, token_id FROM patients WHERE id = ?", [$id]
    );
    if (!$p) jsonResponse(false, 'Patient not found.');

    // Cascade via FK: delete token → removes patient + payment
    Database::execute("DELETE FROM tokens WHERE id = ?", [$p['token_id']]);

    logActivity(getCurrentUserId(), 'admin_patient_deleted', [
        'id'   => $id,
        'name' => $p['name'],
    ]);
    jsonResponse(true, "{$p['name']}'s record has been deleted.");
}
