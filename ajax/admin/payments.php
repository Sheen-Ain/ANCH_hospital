<?php
/**
 * ajax/admin/payments.php
 * Admin payments overview.
 *
 * Dispatch via `action`:
 *   getPayments     — all payments with filters + revenue summary
 *   updatePayment   — admin updates payment status/method for any patient
 *   getRevenueStats — summary stats for the revenue dashboard cards
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
        case 'getPayments':     getPayments();     break;
        case 'getRevenueStats': getRevenueStats(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'updatePayment': updatePayment(); break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getPayments
 * GET params: search, date_from, date_to, doctor_id, payment_status, page, per_page
 */
function getPayments(): void
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
        $where[] = "(p.name LIKE ? OR d.name LIKE ?)";
        array_push($params, $like, $like);
    }
    if ($dateFrom !== '') { $where[] = "p.visit_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo   !== '') { $where[] = "p.visit_date <= ?"; $params[] = $dateTo;   }
    if ($doctorId   > 0)  { $where[] = "p.doctor_id = ?";  $params[] = $doctorId;  }
    if (in_array($paymentStatus, ['paid','unpaid'], true)) {
        $where[]  = "py.status = ?";
        $params[] = $paymentStatus;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM   patients p
         JOIN   doctors  d  ON d.id = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         {$whereSql}",
        $params
    )['cnt'];

    // Revenue totals for current filter
    $revRow = Database::fetchOne(
        "SELECT
             COALESCE(SUM(CASE WHEN py.status='paid' THEN py.amount ELSE 0 END), 0) AS paid_total,
             COALESCE(SUM(CASE WHEN py.status='unpaid' THEN py.amount ELSE 0 END), 0) AS unpaid_total
         FROM patients p
         JOIN doctors d ON d.id = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         {$whereSql}",
        $params
    );

    $rows = Database::fetchAll(
        "SELECT
             py.id AS payment_id,
             p.id  AS patient_id,
             p.name, p.visit_date, p.visit_time, p.gender,
             t.token_number,
             d.name  AS doctor_name,
             py.amount, py.status AS payment_status, py.payment_method,
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

    jsonResponse(true, "{$total} record(s).", [
        'payments'     => $rows,
        'total'        => $total,
        'page'         => $page,
        'per_page'     => $perPage,
        'total_pages'  => (int) ceil($total / $perPage),
        'paid_total'   => (float) ($revRow['paid_total']   ?? 0),
        'unpaid_total' => (float) ($revRow['unpaid_total'] ?? 0),
        'paid_fmt'     => formatCurrency((float)($revRow['paid_total']   ?? 0)),
        'unpaid_fmt'   => formatCurrency((float)($revRow['unpaid_total'] ?? 0)),
    ]);
}

/**
 * getRevenueStats
 * Summary cards: today, this week, this month, all-time.
 */
function getRevenueStats(): void
{
    $q = "SELECT COALESCE(SUM(py.amount), 0) AS total
          FROM payments py
          JOIN patients p ON p.id = py.patient_id
          WHERE py.status = 'paid'";

    $today   = (float) Database::fetchOne($q . " AND p.visit_date = CURDATE()")['total'];
    $week    = (float) Database::fetchOne($q . " AND p.visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")['total'];
    $month   = (float) Database::fetchOne($q . " AND p.visit_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")['total'];
    $allTime = (float) Database::fetchOne($q)['total'];

    $totalPatients = (int) Database::fetchOne("SELECT COUNT(*) AS cnt FROM patients")['cnt'];
    $paidCount     = (int) Database::fetchOne("SELECT COUNT(*) AS cnt FROM payments WHERE status='paid'")['cnt'];

    jsonResponse(true, 'Revenue stats loaded.', [
        'today'          => formatCurrency($today),
        'week'           => formatCurrency($week),
        'month'          => formatCurrency($month),
        'all_time'       => formatCurrency($allTime),
        'total_patients' => $totalPatients,
        'paid_count'     => $paidCount,
        'unpaid_count'   => $totalPatients - $paidCount,
    ]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * updatePayment
 * Admin can update any payment record regardless of date.
 * POST fields: patient_id*, payment_status*, payment_method
 */
function updatePayment(): void
{
    $patientId     = (int)  ($_POST['patient_id']    ?? 0);
    $status        = trim(  $_POST['payment_status'] ?? '');
    $method        = trim(  $_POST['payment_method'] ?? '');

    if ($patientId <= 0) jsonResponse(false, 'Invalid patient ID.');
    if (!in_array($status, ['paid','unpaid'], true)) jsonResponse(false, 'Invalid payment status.');

    $validMethods = ['cash','card','insurance','online'];
    if ($status === 'paid' && !in_array($method, $validMethods, true)) {
        jsonResponse(false, 'Please select a payment method.');
    }

    $payment = Database::fetchOne("SELECT id FROM payments WHERE patient_id = ?", [$patientId]);
    if (!$payment) jsonResponse(false, 'Payment record not found.');

    Database::execute(
        "UPDATE payments SET status=?, payment_method=? WHERE patient_id=?",
        [$status, $status === 'paid' ? $method : null, $patientId]
    );

    logActivity(getCurrentUserId(), 'admin_payment_updated', [
        'patient_id' => $patientId,
        'status'     => $status,
    ]);

    $label = $status === 'paid' ? 'marked as Paid' : 'marked as Unpaid';
    jsonResponse(true, "Payment has been {$label}.", ['payment_status' => $status]);
}
