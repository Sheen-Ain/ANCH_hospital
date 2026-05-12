<?php

/**
 * includes/functions.php
 * ALL shared PHP helper functions for TokenMed.
 *
 * This is the single source of truth for shared logic.
 * No function defined here may be re-defined in any other file.
 *
 * Load order (every page):
 *   1. config.php
 *   2. includes/db.php
 *   3. includes/functions.php   ← this file
 *   4. includes/settings.php    ← called inside bootApp()
 *   5. includes/auth_check.php  ← role guards
 */

// ────────────────────────────────────────────────
// BOOTSTRAP
// ────────────────────────────────────────────────

/**
 * Boot the application: start session, load settings.
 * Call once at the very top of every page.
 */
function bootApp(): void
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  // Set timezone to Karachi (PKT, UTC+5)
  date_default_timezone_set('Asia/Karachi');

  // Load settings into global $SETTINGS
  require_once BASE_PATH . '/includes/settings.php';

  // Auto-logout based on session age
  autoLogoutCheck();
}

// ────────────────────────────────────────────────
// AUTH HELPERS
// ────────────────────────────────────────────────

/**
 * Redirect to login if user is not authenticated.
 */
function requireLogin(): void
{
  if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
  }
}

/**
 * Require admin role (or admin acting as receptionist still counts).
 * Redirects to receptionist dashboard if a plain receptionist tries to access.
 */
function requireAdmin(): void
{
  requireLogin();
  if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/receptionist/dashboard.php');
    exit;
  }
}

/**
 * Require receptionist access: either a real receptionist OR admin in switched mode.
 */
function requireReceptionist(): void
{
  requireLogin();
  if (!isAdmin() && !isReceptionist()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
  }
}

/**
 * Returns true if the logged-in user's base role is admin.
 */
function isAdmin(): bool
{
  return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin';
}

/**
 * Returns true if the logged-in user's base role is receptionist.
 */
function isReceptionist(): bool
{
  return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'receptionist';
}

/**
 * Returns true if an admin has switched into the receptionist view.
 */
function isRoleSwitched(): bool
{
  return isAdmin() && isset($_SESSION['role_override']) && $_SESSION['role_override'] === 'receptionist';
}

/**
 * Returns the effective role the user is currently acting as.
 * If admin is switched, returns 'receptionist'.
 *
 * @return string 'admin' | 'receptionist'
 */
function getEffectiveRole(): string
{
  if (isRoleSwitched()) {
    return 'receptionist';
  }
  return $_SESSION['role_name'] ?? 'receptionist';
}

/**
 * Returns the currently logged-in user's ID.
 */
function getCurrentUserId(): int
{
  return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * Returns the base role name of the current user.
 */
function getCurrentUserRole(): string
{
  return $_SESSION['role_name'] ?? '';
}

/**
 * Check session age; if past auto_logout_mins, destroy session and redirect.
 */
function autoLogoutCheck(): void
{
  if (empty($_SESSION['user_id'])) {
    return;
  }

  $limit = (int) getSetting('auto_logout_mins', '60');
  if ($limit <= 0) {
    return;
  }

  if (isset($_SESSION['last_activity'])) {
    $idle = time() - (int) $_SESSION['last_activity'];
    if ($idle > $limit * 60) {
      session_unset();
      session_destroy();
      header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
      exit;
    }
  }

  $_SESSION['last_activity'] = time();
}

// ────────────────────────────────────────────────
// SETTINGS HELPERS
// ────────────────────────────────────────────────

/**
 * Read a setting value from the global $SETTINGS array.
 *
 * @param  string $key
 * @param  string $default  Returned if key not found or value is null/empty
 * @return string
 */
function getSetting(string $key, string $default = ''): string
{
  global $SETTINGS;
  return isset($SETTINGS[$key]) && $SETTINGS[$key] !== null && $SETTINGS[$key] !== ''
    ? (string) $SETTINGS[$key]
    : $default;
}

// ────────────────────────────────────────────────
// FORMATTING HELPERS
// ────────────────────────────────────────────────

/**
 * Format an amount as currency using the configured symbol.
 * e.g. "Rs. 1,200.00"
 */
function formatCurrency(float $amount): string
{
  $symbol = getSetting('currency_symbol', 'Rs.');
  return $symbol . ' ' . number_format($amount, 2);
}

/**
 * Format a date string (YYYY-MM-DD or datetime) to DD/MM/YYYY.
 * Pakistani date convention.
 */
function formatDate(string $date): string
{
  if (empty($date) || $date === '0000-00-00') {
    return '—';
  }
  try {
    $dt = new DateTime($date);
    return $dt->format('d/m/Y');
  } catch (Exception $e) {
    return $date;
  }
}

/**
 * Format a datetime string to DD/MM/YYYY HH:MM AM/PM.
 */
function formatDateTime(string $datetime): string
{
  if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
    return '—';
  }
  try {
    $dt = new DateTime($datetime);
    return $dt->format('d/m/Y h:i A');
  } catch (Exception $e) {
    return $datetime;
  }
}

/**
 * Format a time string (HH:MM:SS) to 12-hour AM/PM format.
 */
function formatTime(string $time): string
{
  if (empty($time)) {
    return '—';
  }
  try {
    $dt = new DateTime('1970-01-01 ' . $time);
    return $dt->format('h:i A');
  } catch (Exception $e) {
    return $time;
  }
}

/**
 * Sanitize user input: trim whitespace + escape HTML special chars.
 * Use before storing to DB (in addition to prepared statements).
 * Also use when outputting dynamic text in HTML outside of contexts
 * where htmlspecialchars is already applied by the template.
 */
function sanitize(string $input): string
{
  return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Output a value safely for HTML contexts.
 * Shorthand for echo htmlspecialchars(...)
 */
function e(mixed $value): string
{
  return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ────────────────────────────────────────────────
// TOKEN HELPERS
// ────────────────────────────────────────────────

/**
 * Get the next available token number for a doctor on a given date.
 * Token numbers are per-doctor, per-day, resetting to 1 each new day.
 *
 * @param  int    $doctorId
 * @param  string $date      YYYY-MM-DD format
 * @return int    Next token number (1-indexed)
 */
function getNextTokenNumber(int $doctorId, string $date): int
{
  $row = Database::fetchOne(
    "SELECT MAX(token_number) AS max_token
         FROM tokens
         WHERE doctor_id = ? AND token_date = ?",
    [$doctorId, $date]
  );

  return ($row && $row['max_token'] !== null) ? (int) $row['max_token'] + 1 : 1;
}

// ────────────────────────────────────────────────
// RECEIPT & SLIP GENERATION
// ────────────────────────────────────────────────

/**
 * Generate a unique receipt reference number.
 * Format: R-XXXXX (5-digit zero-padded random)
 */
function generateReceiptNumber(): string
{
  return 'R-' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Render the full receipt HTML for a patient.
 * Used by both print preview and PDF download — single source of truth.
 *
 * @param  int    $patientId
 * @return string HTML string
 */
function renderReceiptHTML(int $patientId): string
{
  $row = Database::fetchOne(
    "SELECT
             p.id, p.name, p.age, p.phone, p.gender, p.address, p.notes,
             p.visit_date, p.visit_time, p.serial_number,
             t.token_number, t.token_date,
             d.name      AS doctor_name,
             d.specialization,
             py.amount, py.status AS payment_status, py.payment_method,
             u.first_name AS rec_first, u.last_name AS rec_last
         FROM patients p
         JOIN tokens   t  ON t.id  = p.token_id
         JOIN doctors  d  ON d.id  = p.doctor_id
         LEFT JOIN payments py ON py.patient_id = p.id
         JOIN users    u  ON u.id  = p.receptionist_id
         WHERE p.id = ?",
    [$patientId]
  );

  if (!$row) {
    return '<p>Patient record not found.</p>';
  }

  $prefix       = getSetting('token_prefix', 'TKN');
  $hospitalName = getSetting('hospital_name', 'Hospital');
  $hospitalUrdu = getSetting('hospital_name_urdu', '');
  $address      = getSetting('hospital_address', '');
  $addressUrdu  = getSetting('hospital_address_urdu', '');
  $phone        = getSetting('contact_phone', '');
  $showUrdu     = getSetting('receipt_show_urdu', '1') === '1';
  $logoFile     = getSetting('hospital_logo', '');
  $logoSrc      = $logoFile ? BASE_URL . '/assets/uploads/logo/' . e($logoFile) : '';
  $recName      = e($row['rec_first'] . ' ' . $row['rec_last']);
  $tokenDisplay = $prefix . '-' . str_pad($row['token_number'], 3, '0', STR_PAD_LEFT);
  $isPaid       = $row['payment_status'] === 'paid';
  $printedAt    = date('d/m/Y h:i A');
  $payStatus    = strtoupper($row['payment_status'] ?? 'UNPAID');
  $feeAmount    = formatCurrency((float)($row['amount'] ?? 0));
  $genderAge    = ucfirst(e($row['gender']))
    . ($row['age'] ? ', Age: ' . (int)$row['age'] . ' yrs' : '');

  $methodUrduMap = [
    'cash'      => 'نقد',
    'card'      => 'کارڈ',
    'insurance' => 'انشورنس',
    'online'    => 'آن لائن',
  ];
  $methodUrdu = $methodUrduMap[$row['payment_method'] ?? ''] ?? '';

  ob_start();
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=58mm">
    <title>Receipt</title>
    <style>
      /* ── Wrapper — centered on screen ── */
      #rct-root {
        display: block;
        width: 75mm;
        margin: 0 auto;
        padding: 0;
        background: #fff;
      }

      #rct-root #rct {
        width: 75mm;
        padding: 3mm 2.5mm;
        background: #fff;
        color: #000;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        font-weight: 700;
        /* 🔥 global bold */
        box-sizing: border-box;
      }

      /* ── Reset children ── */
      #rct-root #rct * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-weight: 700;
        /* 🔥 force bold everywhere */
        color: #000;
      }

      /* ── LOGO FIX (center properly) ── */
      #rct-root .logo-wrap {
        width: 100%;
        text-align: center;
        /* 🔥 THIS is the real fix */
        margin-bottom: 6px;
      }

      #rct-root .logo-img {
        display: inline-block;
        /* 🔥 important */
        max-height: 44px;
        max-width: 130px;
      }

      /* ── Separators ── */
      #rct-root .sep-solid {
        border-top: 1.5px solid #000;
        margin: 5px 0;
      }

      #rct-root .sep-dash {
        border-top: 1.5px dashed #000;
        margin: 5px 0;
      }

      #rct-root .sep-double {
        border-top: 3px double #000;
        margin: 6px 0;
      }

      /* ── Section label ── */
      #rct-root .sec-lbl {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 4px;
        text-align: left;
      }

      /* ── Header ── */
      #rct-root .hosp-name {
        text-align: center;
        font-size: 15px;
        margin-bottom: 2px;
      }

      #rct-root .hosp-sub {
        text-align: center;
        font-size: 11px;
        margin: 1px 0;
      }

      /* ── Token ── */
      #rct-root .token-box {
        border: 2px solid #000;
        text-align: center;
        padding: 6px 3px;
        margin: 6px 0;
      }

      #rct-root .token-box .t-label {
        font-size: 10px;
        letter-spacing: 1px;
      }

      #rct-root .token-box .t-num {
        font-size: 30px;
        letter-spacing: 4px;
        line-height: 1.2;
      }

      #rct-root .token-box .t-serial {
        font-size: 10px;
        margin-top: 2px;
      }

      /* ── TABLE FIX (LEFT / RIGHT ALIGN CLEAN) ── */
      #rct-root .info-tbl {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
      }

      #rct-root .info-tbl td {
        padding: 3px 0;
        vertical-align: top;
      }

      /* LEFT LABEL */
      #rct-root .info-tbl .lbl {
        width: 45%;
        text-align: left;
        font-size: 11px;
      }

      /* RIGHT VALUE */
      #rct-root .info-tbl .val {
        width: 55%;
        text-align: right;
        /* 🔥 IMPORTANT */
        font-size: 11px;
        word-break: break-word;
      }

      /* ── Notes ── */
      #rct-root .notes-box {
        border: 1px dashed #000;
        padding: 5px;
        font-size: 11px;
        margin: 4px 0;
      }

      /* ── Footer ── */
      #rct-root .rct-footer {
        font-size: 11px;
        text-align: center;
      }

      #rct-root .rct-footer p {
        margin: 2px 0;
      }

      /* ── Urdu ── */
      #rct-root .urdu-wrap {
        direction: rtl;
        text-align: right;
        font-family: 'Noto Nastaliq Urdu', serif;
        font-size: 13px;
        line-height: 2;
        margin-top: 5px;
      }

      #rct-root .urdu-wrap .u-hosp,
      #rct-root .urdu-wrap .u-addr,
      #rct-root .urdu-wrap .u-title {
        text-align: center;
      }

      #rct-root .u-token-box {
        border: 1.5px solid #000;
        text-align: center;
        padding: 4px;
        margin: 4px 0;
        direction: ltr;
      }

      #rct-root .u-token-box .ut-num {
        font-size: 20px;
        letter-spacing: 3px;
      }

      #rct-root .urdu-tbl {
        width: 100%;
        border-collapse: collapse;
      }

      #rct-root .urdu-tbl td {
        padding: 2px 0;
      }

      /* Urdu labels left, values right */
      #rct-root .urdu-tbl .u-lbl {
        width: 40%;
        text-align: left;
        padding-left: 6px;
      }

      #rct-root .urdu-tbl .u-val {
        width: 60%;
        text-align: right;
      }

      /* ── PRINT ── */
      @media print {

        @page {
          size: 75mm auto;
          margin: 0;
        }

        body>*:not(#rct-root) {
          display: none !important;
        }

        #rct-root {
          position: fixed;
          top: 0;
          left: 0;
          width: 73mm;
          margin: 0;
          padding: 0;
        }

        #rct-root #rct {
          width: 73mm;
          padding: 2mm 1mm;
        }
      }
    </style>
  </head>

  <body>

    <div id="rct-root">
      <div id="rct">

        <!-- HEADER -->
        <?php if ($logoSrc): ?>
          <div class="logo-wrap">
            <img class="logo-img" src="<?= $logoSrc ?>" alt="<?= e($hospitalName) ?>">
          </div>
        <?php endif; ?>

        <div class="hosp-name"><?= e($hospitalName) ?></div>

        <?php if ($address): ?>
          <div class="hosp-sub"><?= e($address) ?></div>
        <?php endif; ?>

        <?php if ($phone): ?>
          <div class="hosp-sub">Tel: <?= e($phone) ?></div>
        <?php endif; ?>

        <hr class="sep-double">

        <!-- TOKEN -->
        <div class="token-box">
          <div class="t-label">Token Number</div>
          <div class="t-num"><?= e($tokenDisplay) ?></div>
          <div class="t-serial">Serial No: <?= (int)$row['serial_number'] ?></div>
        </div>

        <!-- PATIENT INFO -->
        <hr class="sep-dash">
        <div class="sec-lbl">Patient Info</div>

        <table class="info-tbl">
          <tr>
            <td class="lbl">Name</td>
            <td class="val"><?= e($row['name']) ?></td>
          </tr>
          <tr>
            <td class="lbl">Gender</td>
            <td class="val"><?= $genderAge ?></td>
          </tr>
        </table>

        <!-- APPOINTMENT -->
        <hr class="sep-dash">
        <div class="sec-lbl">Appointment</div>

        <table class="info-tbl">
          <tr>
            <td class="lbl">Doctor</td>
            <td class="val">
              Dr. <?= e($row['doctor_name']) ?><br>
              (<?= e($row['specialization']) ?>)
            </td>
          </tr>
          <tr>
            <td class="lbl">Date/Time</td>
            <td class="val"><?= formatDate($row['visit_date']) ?> <?= formatTime($row['visit_time']) ?></td>
          </tr>
        </table>

        <!-- PAYMENT -->
        <hr class="sep-dash">
        <div class="sec-lbl">Payment</div>

        <table class="info-tbl">
          <tr>
            <td class="lbl">Fee</td>
            <td class="val"><?= $feeAmount ?> [<?= $payStatus ?>]</td>
          </tr>
          <?php if ($isPaid && !empty($row['payment_method'])): ?>
            <tr>
              <td class="lbl">Method</td>
              <td class="val"><?= ucfirst(e($row['payment_method'])) ?></td>
            </tr>
          <?php endif; ?>
        </table>

        <!-- NOTES -->
        <?php if (!empty($row['notes'])): ?>
          <hr class="sep-dash">
          <div class="sec-lbl">Notes</div>
          <div class="notes-box"><?= e($row['notes']) ?></div>
        <?php endif; ?>

        <!-- FOOTER -->
        <hr class="sep-solid">

        <div class="rct-footer">
          <p>By: <?= $recName ?></p>
          <p><?= $printedAt ?></p>
        </div>

        <!-- URDU SECTION -->
        <?php if ($showUrdu): ?>

          <hr class="sep-double">

          <div class="urdu-wrap">

            <?php if ($hospitalUrdu): ?>
              <div class="u-hosp"><?= e($hospitalUrdu) ?></div>
            <?php endif; ?>

            <?php if ($addressUrdu): ?>
              <div class="u-addr"><?= e($addressUrdu) ?></div>
            <?php endif; ?>

            <div class="u-title">پرچی / رسید</div>

            <div class="u-token-box">
              <div class="ut-num"><?= e($tokenDisplay) ?></div>
              <div class="ut-serial">سیریل نمبر <?= (int)$row['serial_number'] ?></div>
            </div>

            <table class="urdu-tbl">
              <tr>
                <td class="u-val" style="direction:ltr;text-align:right;">
                  Dr. <?= e($row['doctor_name']) ?>
                </td>
                <td class="u-lbl">ڈاکٹر</td>
              </tr>
              <tr>
                <td class="u-val" style="direction:ltr;text-align:right;">
                  <?= formatDate($row['visit_date']) ?> <?= formatTime($row['visit_time']) ?>
                </td>
                <td class="u-lbl">تاریخ / وقت</td>
              </tr>
              <tr>
                <td class="u-val" style="direction:ltr;text-align:right;">
                  <?= $feeAmount ?>
                </td>
                <td class="u-lbl">فیس</td>
              </tr>
              <tr>
                <td class="u-val">
                  <?= $isPaid ? 'ادا شدہ' : 'ادا نہیں ہوا' ?>
                </td>
                <td class="u-lbl">ادائیگی</td>
              </tr>
              <?php if ($isPaid && $methodUrdu): ?>
                <tr>
                  <td class="u-val"><?= e($methodUrdu) ?></td>
                  <td class="u-lbl">طریقہ</td>
                </tr>
              <?php endif; ?>
            </table>

          </div>

        <?php endif; ?>

      </div><!-- /#rct -->
    </div><!-- /#rct-root -->

  </body>

  </html>
<?php
  return ob_get_clean();
}

/**
 * Render the full admission slip HTML for an admission record.
 * Used by both print preview and PDF download.
 *
 * @param  int    $admissionId
 * @return string HTML string
 */
function renderAdmissionSlipHTML(int $admissionId): string
{
  $row = Database::fetchOne(
    "SELECT
             a.*,
             d.name        AS doctor_name,
             d.specialization,
             r.room_number, r.room_type,
             u.first_name  AS created_by_first,
             u.last_name   AS created_by_last
         FROM admissions a
         JOIN doctors  d ON d.id = a.doctor_id
         JOIN rooms    r ON r.id = a.room_id
         JOIN users    u ON u.id = a.created_by
         WHERE a.id = ?",
    [$admissionId]
  );

  if (!$row) {
    return '<p class="text-red-500">Admission record not found.</p>';
  }

  $hospitalName = getSetting('hospital_name', 'Hospital');
  $address      = getSetting('hospital_address', '');
  $phone        = getSetting('contact_phone', '');
  $logoFile     = getSetting('hospital_logo', '');
  $logoSrc      = $logoFile ? BASE_URL . '/assets/uploads/logo/' . e($logoFile) : '';
  $admittedBy   = e($row['created_by_first'] . ' ' . $row['created_by_last']);
  $printedAt    = date('d/m/Y h:i A');
  $admRef       = 'ADM-' . str_pad((string)$row['id'], 4, '0', STR_PAD_LEFT);

  // Calculate days
  $daysAdmitted = 1;
  if ($row['status'] === 'discharged' && $row['discharged_at']) {
    $admitted    = new DateTime($row['admitted_at']);
    $discharged  = new DateTime($row['discharged_at']);
    $daysAdmitted = max(1, (int) $admitted->diff($discharged)->days);
  } elseif ($row['status'] === 'admitted') {
    $admitted    = new DateTime($row['admitted_at']);
    $now         = new DateTime();
    $daysAdmitted = max(1, (int) $admitted->diff($now)->days + 1);
  }

  $roomTotal      = $daysAdmitted * (float) $row['room_fee_per_day'];
  $estimatedTotal = $roomTotal + (float) $row['disease_treatment_cost'];

  ob_start(); ?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
      * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
      }

      body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 13px;
        color: #1e293b;
        background: #fff;
        padding: 24px;
      }

      .slip-wrapper {
        max-width: 640px;
        margin: 0 auto;
        background: #fff;
      }

      /* Header */
      .slip-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 3px solid #0369a1;
        margin-bottom: 16px;
      }

      .slip-logo {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        background: #e0f2fe;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        border: 1px solid #bae6fd;
      }

      .slip-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
      }

      .slip-logo i {
        font-size: 28px;
        color: #0369a1;
      }

      .slip-header-text {
        flex: 1;
      }

      .slip-header-text h1 {
        font-size: 18px;
        font-weight: 800;
        color: #0369a1;
        margin-bottom: 2px;
      }

      .slip-header-text h2 {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #64748b;
        margin-bottom: 4px;
      }

      .slip-header-text p {
        font-size: 11px;
        color: #94a3b8;
        margin: 1px 0;
      }

      .slip-ref-box {
        text-align: right;
        flex-shrink: 0;
      }

      .slip-ref-num {
        font-size: 20px;
        font-weight: 800;
        color: #0369a1;
        font-family: 'Courier New', monospace;
      }

      .slip-ref-date {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
      }

      /* Status badge */
      .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 700;
      }

      .status-admitted {
        background: #ede9fe;
        color: #5b21b6;
      }

      .status-discharged {
        background: #f0fdf4;
        color: #166534;
      }

      /* Section */
      .slip-section {
        margin-bottom: 14px;
      }

      .slip-section-title {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #e2e8f0;
      }

      .slip-section-title i {
        font-size: 11px;
        color: #0369a1;
      }

      /* Info grid */
      .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 16px;
      }

      .info-row {
        display: flex;
        flex-direction: column;
      }

      .info-label {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        margin-bottom: 1px;
      }

      .info-value {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
      }

      .info-value.mono {
        font-family: 'Courier New', monospace;
      }

      /* Financial table */
      .fin-table {
        width: 100%;
        border-collapse: collapse;
      }

      .fin-table td {
        padding: 5px 0;
        font-size: 13px;
        vertical-align: middle;
      }

      .fin-table td:last-child {
        text-align: right;
        font-weight: 600;
      }

      .fin-table .fin-lbl {
        color: #64748b;
      }

      .fin-table .fin-lbl i {
        font-size: 11px;
        margin-right: 4px;
        color: #94a3b8;
      }

      .fin-total td {
        border-top: 2px solid #0369a1;
        padding-top: 8px;
        font-weight: 800;
        color: #0369a1;
        font-size: 14px;
      }

      /* Footer */
      .slip-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        border-top: 2px solid #e2e8f0;
        margin-top: 16px;
        padding-top: 12px;
        font-size: 11px;
        color: #94a3b8;
      }

      .sig-line {
        border-top: 1px solid #94a3b8;
        width: 160px;
        text-align: center;
        padding-top: 4px;
        font-size: 11px;
        color: #64748b;
      }

      .disclaimer {
        font-size: 10px;
        color: #94a3b8;
        text-align: center;
        margin-top: 10px;
        font-style: italic;
      }

      /* ── A4 Print ── */
      @media print {
        @page {
          size: A4;
          margin: 15mm 12mm;
        }

        html,
        body {
          margin: 0 !important;
          padding: 0 !important;
          background: #fff !important;
        }

        body>*:not(#slip-print-root) {
          display: none !important;
        }

        #slip-print-root {
          display: block !important;
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          width: 100% !important;
          margin: 0 !important;
          padding: 0 !important;
        }

        body {
          padding: 0 !important;
        }

        .slip-wrapper {
          max-width: 100% !important;
        }

        * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
      }
    </style>
  </head>

  <body>
    <div id="slip-print-root">
      <div class="slip-wrapper">

        <!-- Header -->
        <div class="slip-header">
          <div class="slip-logo">
            <?php if ($logoSrc): ?>
              <img src="<?= $logoSrc ?>" alt="<?= e($hospitalName) ?>">
            <?php else: ?>
              <i class="fa-solid fa-hospital-user"></i>
            <?php endif; ?>
          </div>
          <div class="slip-header-text">
            <h1><?= e($hospitalName) ?></h1>
            <h2>Admission Slip</h2>
            <?php if ($address): ?><p><i class="fa-solid fa-location-dot"></i> <?= e($address) ?></p><?php endif; ?>
            <?php if ($phone):   ?><p><i class="fa-solid fa-phone"></i> <?= e($phone) ?></p><?php endif; ?>
          </div>
          <div class="slip-ref-box">
            <div class="slip-ref-num"><?= $admRef ?></div>
            <div class="slip-ref-date"><?= $printedAt ?></div>
            <div style="margin-top:6px;">
              <?php if ($row['status'] === 'admitted'): ?>
                <span class="status-badge status-admitted">
                  <i class="fa-solid fa-circle-dot"></i> Admitted
                </span>
              <?php else: ?>
                <span class="status-badge status-discharged">
                  <i class="fa-solid fa-circle-check"></i> Discharged
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Patient Info -->
        <div class="slip-section">
          <div class="slip-section-title">
            <i class="fa-solid fa-user"></i> Patient Information
          </div>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-label">Patient Name</span>
              <span class="info-value"><?= e($row['patient_name']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Father / Husband</span>
              <span class="info-value"><?= e($row['guardian_name']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Gender</span>
              <span class="info-value"><?= ucfirst(e($row['gender'])) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Address</span>
              <span class="info-value" style="font-weight:400;font-size:12px;"><?= e($row['address']) ?></span>
            </div>
          </div>
        </div>

        <!-- Clinical Info -->
        <div class="slip-section">
          <div class="slip-section-title">
            <i class="fa-solid fa-stethoscope"></i> Clinical Details
          </div>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-label">Attending Doctor</span>
              <span class="info-value">Dr. <?= e($row['doctor_name']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Specialization</span>
              <span class="info-value"><?= e($row['specialization']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Diagnosis</span>
              <span class="info-value"><?= e($row['disease_name']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Admission Reason</span>
              <span class="info-value"><?= ucfirst(e($row['admission_reason'])) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Room Assigned</span>
              <span class="info-value mono"><?= e($row['room_number']) ?> — <?= ucfirst(e($row['room_type'])) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Admitted On</span>
              <span class="info-value"><?= formatDateTime($row['admitted_at']) ?></span>
            </div>
            <?php if ($row['discharged_at']): ?>
              <div class="info-row">
                <span class="info-label">Discharged On</span>
                <span class="info-value"><?= formatDateTime($row['discharged_at']) ?></span>
              </div>
            <?php endif; ?>
            <div class="info-row">
              <span class="info-label">Duration</span>
              <span class="info-value"><?= $daysAdmitted ?> day<?= $daysAdmitted !== 1 ? 's' : '' ?></span>
            </div>
          </div>
        </div>

        <!-- Financial Summary -->
        <div class="slip-section">
          <div class="slip-section-title">
            <i class="fa-solid fa-money-bill-wave"></i> Financial Summary
          </div>
          <table class="fin-table">
            <tr>
              <td class="fin-lbl"><i class="fa-solid fa-bed"></i> Room Fee / Day</td>
              <td><?= formatCurrency((float) $row['room_fee_per_day']) ?></td>
            </tr>
            <tr>
              <td class="fin-lbl"><i class="fa-solid fa-calendar-days"></i> Room Total (<?= $daysAdmitted ?> day<?= $daysAdmitted !== 1 ? 's' : '' ?>)</td>
              <td><?= formatCurrency($roomTotal) ?></td>
            </tr>
            <tr>
              <td class="fin-lbl"><i class="fa-solid fa-pills"></i> Treatment Cost</td>
              <td><?= formatCurrency((float) $row['disease_treatment_cost']) ?></td>
            </tr>
            <tr class="fin-total">
              <td class="fin-lbl"><i class="fa-solid fa-calculator"></i> Estimated Total *</td>
              <td><?= formatCurrency($estimatedTotal) ?></td>
            </tr>
          </table>
          <p style="font-size:10px;color:#94a3b8;margin-top:5px;">
            * Estimated amount, subject to change upon final discharge.
          </p>
        </div>

        <!-- Footer -->
        <div class="slip-footer">
          <div>
            <p style="margin-bottom:2px;"><i class="fa-solid fa-user-nurse" style="margin-right:4px;"></i>Admitted by: <?= $admittedBy ?></p>
            <p>Department: Reception</p>
          </div>
          <div style="text-align:center;">
            <div class="sig-line">Authorised Signature</div>
          </div>
          <div style="text-align:right;">
            <p>System: TokenMed</p>
            <p>Printed: <?= $printedAt ?></p>
          </div>
        </div>

        <div class="disclaimer">
          This is a computer-generated document. No signature required for admission confirmation.
        </div>

      </div><!-- /.slip-wrapper -->
    </div><!-- /#slip-print-root -->
  </body>

  </html>
<?php
  return ob_get_clean();
}

// ────────────────────────────────────────────────
// LOGGING
// ────────────────────────────────────────────────

/**
 * Write an entry to the audit_logs table.
 *
 * @param int         $userId  The user performing the action
 * @param string      $action  Short description e.g. 'doctor_created'
 * @param mixed       $details Optional details (string or array; array is JSON-encoded)
 */
function logActivity(int $userId, string $action, mixed $details = null): void
{
  $detailStr = null;
  if ($details !== null) {
    $detailStr = is_array($details) ? json_encode($details) : (string) $details;
  }

  $ip = $_SERVER['REMOTE_ADDR'] ?? null;

  try {
    Database::execute(
      "INSERT INTO audit_logs (user_id, action, details, ip_address)
             VALUES (?, ?, ?, ?)",
      [$userId, $action, $detailStr, $ip]
    );
  } catch (Exception $e) {
    error_log('TokenMed Audit Log Error: ' . $e->getMessage());
  }
}

/**
 * Update the last_seen timestamp for a user (for online status).
 *
 * @param int $userId
 */
function updateLastSeen(int $userId): void
{
  try {
    Database::execute(
      "UPDATE users SET last_seen = NOW() WHERE id = ?",
      [$userId]
    );
  } catch (Exception $e) {
    error_log('TokenMed LastSeen Update Error: ' . $e->getMessage());
  }
}

// ────────────────────────────────────────────────
// AJAX / RESPONSE HELPERS
// ────────────────────────────────────────────────

/**
 * Output a standardized JSON response and terminate.
 * Every AJAX endpoint must use this — no ad-hoc echo/die.
 *
 * @param bool   $success
 * @param string $message  Human-readable status message
 * @param array  $data     Optional payload
 */
function jsonResponse(bool $success, string $message, array $data = []): void
{
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode([
    'success' => $success,
    'message' => $message,
    'data'    => $data,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ────────────────────────────────────────────────
// CSRF
// ────────────────────────────────────────────────

/**
 * Generate (or return existing) CSRF token stored in session.
 */
function getCsrfToken(): string
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token submitted with a form/AJAX request.
 * Call this at the top of every POST handler.
 * On failure: returns a JSON error and exits (for AJAX), or redirects (for forms).
 *
 * @param bool $isAjax  If true, returns JSON on failure instead of redirecting
 */
function validateCsrf(bool $isAjax = true): void
{
  $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $stored    = $_SESSION['csrf_token'] ?? '';

  if (!$stored || !hash_equals($stored, $submitted)) {
    if ($isAjax) {
      jsonResponse(false, 'Invalid or expired CSRF token. Please refresh the page.');
    } else {
      header('Location: ' . BASE_URL . '/auth/login.php?csrf=1');
      exit;
    }
  }
}

// ────────────────────────────────────────────────
// FILE UPLOAD HELPERS
// ────────────────────────────────────────────────

/**
 * Handle a profile image upload for a user.
 * Validates type, size, saves to /assets/uploads/profiles/ and returns filename.
 *
 * @param  array  $file   $_FILES['profile_image'] element
 * @param  int    $userId Used to name the file uniquely
 * @return string         Filename (relative), or empty string on failure
 */
function uploadProfileImage(array $file, int $userId): string
{
  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  $maxSize      = 2 * 1024 * 1024; // 2MB

  if ($file['error'] !== UPLOAD_ERR_OK) {
    return '';
  }
  if (!in_array($file['type'], $allowedTypes, true)) {
    return '';
  }
  if ($file['size'] > $maxSize) {
    return '';
  }

  $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
  $destDir  = BASE_PATH . '/assets/uploads/profiles/';
  $dest     = $destDir . $filename;

  if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
  }

  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    return '';
  }

  return $filename;
}

// ────────────────────────────────────────────────
// MISC UTILITIES
// ────────────────────────────────────────────────

/**
 * Pad an ID for display (e.g. ADM-0023, TKN-007).
 */
function padId(int $id, int $length = 4): string
{
  return str_pad((string) $id, $length, '0', STR_PAD_LEFT);
}

/**
 * Calculate days between two datetime strings.
 * Returns at least 1.
 */
function daysBetween(string $from, string $to): int
{
  try {
    $d1 = new DateTime($from);
    $d2 = new DateTime($to);
    return max(1, (int) $d1->diff($d2)->days);
  } catch (Exception $e) {
    return 1;
  }
}

/**
 * Return the gender label with an icon for display.
 */
function genderLabel(string $gender): string
{
  return match ($gender) {
    'male'   => '♂ Male',
    'female' => '♀ Female',
    'other'  => '⚧ Other',
    default  => '—',
  };
}
