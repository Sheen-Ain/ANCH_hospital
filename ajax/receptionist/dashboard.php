<?php
/**
 * ajax/receptionist/dashboard.php
 * Dashboard data for the receptionist view.
 * Only returns data relevant to the current receptionist's own work.
 *
 * Method: GET
 * Auth:   Receptionist (or admin in switched mode)
 *
 * Returns:
 * {
 *   stats:           { my_tokens_today, my_revenue_today, unpaid_today, active_doctors }
 *   my_patients:     [ last 10 patients registered by this receptionist today ]
 *   active_doctors:  [ all active doctors with today's token counts ]
 *   recent_admissions: [ last 3 admissions created by this receptionist ]
 * }
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireReceptionist();

$userId = getCurrentUserId();
$today  = date('Y-m-d');

// ── My tokens today ───────────────────────────────────────
$myTokensToday = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM patients
     WHERE receptionist_id = ? AND visit_date = ?",
    [$userId, $today]
)['cnt'];

// ── My revenue today (paid only) ─────────────────────────
$myRevenueRow = Database::fetchOne(
    "SELECT COALESCE(SUM(py.amount), 0) AS total
     FROM   payments py
     JOIN   patients  p ON p.id = py.patient_id
     WHERE  p.receptionist_id = ?
     AND    p.visit_date       = ?
     AND    py.status          = 'paid'",
    [$userId, $today]
);
$myRevenue = (float) ($myRevenueRow['total'] ?? 0);

// ── Unpaid today (my patients) ────────────────────────────
$unpaidToday = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt
     FROM   payments py
     JOIN   patients  p ON p.id = py.patient_id
     WHERE  p.receptionist_id = ?
     AND    p.visit_date       = ?
     AND    py.status          = 'unpaid'",
    [$userId, $today]
)['cnt'];

// ── Active doctors count ──────────────────────────────────
$activeDoctors = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM doctors WHERE is_active = 1"
)['cnt'];

// ── My patients today (last 10) ───────────────────────────
$myPatients = Database::fetchAll(
    "SELECT
         p.id, p.name, p.gender, p.visit_time, p.serial_number,
         t.token_number,
         d.name        AS doctor_name,
         d.specialization,
         py.status     AS payment_status,
         py.amount
     FROM   patients p
     JOIN   tokens   t  ON t.id  = p.token_id
     JOIN   doctors  d  ON d.id  = p.doctor_id
     LEFT JOIN payments py ON py.patient_id = p.id
     WHERE  p.receptionist_id = ?
     AND    p.visit_date       = ?
     ORDER  BY p.id DESC
     LIMIT  10",
    [$userId, $today]
);

// ── Active doctors with my token count today ──────────────
$activeDoctorsList = Database::fetchAll(
    "SELECT
         d.id, d.name, d.specialization, d.fee,
         (SELECT COUNT(*) FROM patients p
          WHERE p.doctor_id = d.id AND p.visit_date = ?)           AS today_total,
         (SELECT COUNT(*) FROM patients p
          WHERE p.doctor_id = d.id
          AND   p.receptionist_id = ?
          AND   p.visit_date = ?)                                   AS my_tokens,
         (SELECT MAX(t.token_number) FROM tokens t
          WHERE t.doctor_id = d.id AND t.token_date = ?)           AS last_token
     FROM doctors d
     WHERE d.is_active = 1
     ORDER BY today_total DESC",
    [$today, $userId, $today, $today]
);

foreach ($activeDoctorsList as &$doc) {
    $doc['fee_fmt']       = formatCurrency((float) $doc['fee']);
    $doc['next_token']    = ((int)($doc['last_token'] ?? 0)) + 1;
}
unset($doc);

// ── Recent admissions created by me (last 3) ─────────────
$recentAdmissions = Database::fetchAll(
    "SELECT
         a.id, a.patient_name, a.status, a.admitted_at, a.disease_name,
         r.room_number, r.room_type,
         d.name AS doctor_name
     FROM   admissions a
     JOIN   rooms   r ON r.id = a.room_id
     JOIN   doctors d ON d.id = a.doctor_id
     WHERE  a.created_by = ?
     ORDER  BY a.id DESC
     LIMIT  3",
    [$userId]
);

foreach ($recentAdmissions as &$adm) {
    $adm['admitted_fmt'] = formatDateTime($adm['admitted_at']);
}
unset($adm);

jsonResponse(true, 'Dashboard data loaded.', [
    'stats' => [
        'my_tokens_today'    => $myTokensToday,
        'my_revenue_today'   => $myRevenue,
        'my_revenue_fmt'     => formatCurrency($myRevenue),
        'unpaid_today'       => $unpaidToday,
        'active_doctors'     => $activeDoctors,
    ],
    'my_patients'       => $myPatients,
    'active_doctors'    => $activeDoctorsList,
    'recent_admissions' => $recentAdmissions,
]);
