<?php
/**
 * ajax/admin/dashboard.php
 * Single endpoint for the admin dashboard.
 * Returns all stats, recent patients, online users, and recent admissions.
 *
 * Method: GET
 * Auth:   Admin only
 *
 * Returns:
 * {
 *   success: true,
 *   data: {
 *     stats:              { today_tokens, today_revenue, total_doctors, active_rooms, admitted_patients, total_receptionists }
 *     recent_patients:    [ { id, name, doctor_name, token_number, visit_time, payment_status, amount } … ]
 *     online_users:       [ { id, first_name, last_name, role_name, last_seen } … ]
 *     recent_admissions:  [ { id, patient_name, room_number, room_type, doctor_name, admitted_at, status } … ]
 *     revenue_7days:      [ { date, revenue } … ]
 *   }
 * }
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$today     = date('Y-m-d');
$threshold = getSetting('online_threshold_mins', '5');

// ── 1. Stats ─────────────────────────────────────────────────

// Today's token count (number of patients registered today)
$todayTokens = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM patients WHERE visit_date = ?",
    [$today]
)['cnt'];

// Today's revenue (paid payments for today's patients)
$todayRevRow = Database::fetchOne(
    "SELECT COALESCE(SUM(py.amount), 0) AS total
     FROM   payments py
     JOIN   patients  p ON p.id = py.patient_id
     WHERE  p.visit_date = ?
     AND    py.status    = 'paid'",
    [$today]
);
$todayRevenue = (float) ($todayRevRow['total'] ?? 0);

// Total active doctors
$totalDoctors = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM doctors WHERE is_active = 1"
)['cnt'];

// Active rooms (not occupied by admitted patient, is_active=1)
// A room is "available" if it currently has no admitted patient in it
$activeRooms = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM rooms WHERE is_active = 1"
)['cnt'];

$occupiedRooms = (int) Database::fetchOne(
    "SELECT COUNT(DISTINCT room_id) AS cnt FROM admissions WHERE status = 'admitted'"
)['cnt'];

// Currently admitted patients
$admittedPatients = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM admissions WHERE status = 'admitted'"
)['cnt'];

// Total receptionists
$totalReceptionists = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM users WHERE role_id = 2"
)['cnt'];

// Unpaid count today
$unpaidToday = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt
     FROM   payments py
     JOIN   patients  p ON p.id = py.patient_id
     WHERE  p.visit_date = ?
     AND    py.status    = 'unpaid'",
    [$today]
)['cnt'];

// ── 2. Recent Patients (last 10 today) ───────────────────────
$recentPatients = Database::fetchAll(
    "SELECT
         p.id,
         p.name,
         p.gender,
         p.visit_time,
         p.serial_number,
         t.token_number,
         d.name        AS doctor_name,
         d.specialization,
         py.status     AS payment_status,
         py.amount,
         u.first_name  AS rec_first,
         u.last_name   AS rec_last
     FROM   patients p
     JOIN   tokens   t  ON t.id  = p.token_id
     JOIN   doctors  d  ON d.id  = p.doctor_id
     LEFT JOIN payments py ON py.patient_id = p.id
     JOIN   users    u  ON u.id  = p.receptionist_id
     WHERE  p.visit_date = ?
     ORDER  BY p.id DESC
     LIMIT  10",
    [$today]
);

// ── 3. Online Users ───────────────────────────────────────────
$onlineUsers = Database::fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.profile_image,
            r.name AS role_name, u.last_seen
     FROM   users u
     JOIN   roles r ON r.id = u.role_id
     WHERE  u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
     AND    u.is_active  = 1
     ORDER  BY u.last_seen DESC",
    [(int) $threshold]
);

// ── 4. Recent Admissions (last 5) ────────────────────────────
$recentAdmissions = Database::fetchAll(
    "SELECT
         a.id,
         a.patient_name,
         a.status,
         a.admitted_at,
         a.discharged_at,
         a.admission_reason,
         r.room_number,
         r.room_type,
         d.name AS doctor_name
     FROM   admissions a
     JOIN   rooms   r ON r.id = a.room_id
     JOIN   doctors d ON d.id = a.doctor_id
     ORDER  BY a.id DESC
     LIMIT  5"
);

// ── 5. Revenue last 7 days ────────────────────────────────────
$revenue7Days = Database::fetchAll(
    "SELECT
         p.visit_date                   AS `date`,
         COALESCE(SUM(py.amount), 0)    AS revenue
     FROM   patients  p
     LEFT JOIN payments py
           ON py.patient_id = p.id AND py.status = 'paid'
     WHERE  p.visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP  BY p.visit_date
     ORDER  BY p.visit_date ASC"
);

// Fill missing days with 0 so the chart has all 7 points
$revenueMap = [];
foreach ($revenue7Days as $row) {
    $revenueMap[$row['date']] = (float) $row['revenue'];
}

$revenue7Filled = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $revenue7Filled[] = [
        'date'    => $day,
        'label'   => date('D d', strtotime($day)),
        'revenue' => $revenueMap[$day] ?? 0,
    ];
}

// ── 6. Doctor-wise token count today ─────────────────────────
$doctorTokens = Database::fetchAll(
    "SELECT d.name AS doctor_name, COUNT(p.id) AS patient_count,
            COALESCE(SUM(py.amount),0) AS revenue
     FROM   doctors d
     LEFT JOIN patients p
           ON p.doctor_id = d.id AND p.visit_date = ?
     LEFT JOIN payments py
           ON py.patient_id = p.id AND py.status = 'paid'
     WHERE  d.is_active = 1
     GROUP  BY d.id
     ORDER  BY patient_count DESC",
    [$today]
);

// ── Return ────────────────────────────────────────────────────
jsonResponse(true, 'Dashboard data loaded.', [
    'stats' => [
        'today_tokens'         => $todayTokens,
        'today_revenue'        => $todayRevenue,
        'today_revenue_fmt'    => formatCurrency($todayRevenue),
        'total_doctors'        => $totalDoctors,
        'active_rooms'         => $activeRooms,
        'occupied_rooms'       => $occupiedRooms,
        'admitted_patients'    => $admittedPatients,
        'total_receptionists'  => $totalReceptionists,
        'unpaid_today'         => $unpaidToday,
    ],
    'recent_patients'    => $recentPatients,
    'online_users'       => $onlineUsers,
    'recent_admissions'  => $recentAdmissions,
    'revenue_7days'      => $revenue7Filled,
    'doctor_tokens'      => $doctorTokens,
]);
