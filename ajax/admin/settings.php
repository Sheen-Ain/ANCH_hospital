<?php
/**
 * ajax/admin/settings.php
 * Single AJAX file for ALL settings operations.
 *
 * Dispatch via `action`:
 *   getSettings      — return all settings grouped by category
 *   updateSettings   — bulk update a group of settings
 *   testEmail        — send a test email to the admin's address
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
        case 'getSettings': getSettings(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'updateSettings': updateSettings(); break;
        case 'testEmail':      testEmail();      break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getSettings
 * Returns all settings grouped by logical category.
 */
function getSettings(): void
{
    $rows = Database::fetchAll(
        "SELECT setting_key, setting_value FROM settings ORDER BY id"
    );

    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }

    // Group for the UI
    $grouped = [
        'hospital' => [
            'hospital_name'         => $settings['hospital_name']         ?? '',
            'hospital_name_urdu'    => $settings['hospital_name_urdu']    ?? '',
            'hospital_address'      => $settings['hospital_address']      ?? '',
            'hospital_address_urdu' => $settings['hospital_address_urdu'] ?? '',
            'contact_email'         => $settings['contact_email']         ?? '',
            'contact_phone'         => $settings['contact_phone']         ?? '',
        ],
        'tokens' => [
            'token_prefix'        => $settings['token_prefix']        ?? 'TKN',
            'token_reset_time'    => $settings['token_reset_time']    ?? '00:00',
            'max_tokens_per_day'  => $settings['max_tokens_per_day']  ?? '200',
        ],
        'financial' => [
            'currency_symbol'     => $settings['currency_symbol']     ?? 'Rs.',
        ],
        'receipts' => [
            'receipt_show_urdu'   => $settings['receipt_show_urdu']   ?? '1',
        ],
        'system' => [
            'sound_effects'          => $settings['sound_effects']          ?? '1',
            'online_threshold_mins'  => $settings['online_threshold_mins']  ?? '5',
            'auto_logout_mins'       => $settings['auto_logout_mins']       ?? '60',
            'email_notifications'    => $settings['email_notifications']    ?? '1',
            'sms_notifications'      => $settings['sms_notifications']      ?? '0',
        ],
    ];

    jsonResponse(true, 'Settings loaded.', [
        'settings' => $settings,
        'grouped'  => $grouped,
    ]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * updateSettings
 * Receives a JSON-encoded array of { key: value } pairs and bulk-upserts them.
 *
 * POST fields:
 *   group    — which logical group is being saved (for logging)
 *   settings — JSON string: { "hospital_name": "...", ... }
 */
function updateSettings(): void
{
    $group        = trim($_POST['group']    ?? 'unknown');
    $settingsJson = trim($_POST['settings'] ?? '{}');

    $incoming = json_decode($settingsJson, true);
    if (!is_array($incoming)) {
        jsonResponse(false, 'Invalid settings payload.');
    }

    // Whitelist of allowed keys — never let arbitrary keys into settings table
    $allowedKeys = [
        'hospital_name', 'hospital_name_urdu', 'hospital_address', 'hospital_address_urdu',
        'contact_email', 'contact_phone', 'hospital_logo',
        'token_prefix', 'token_reset_time', 'max_tokens_per_day',
        'currency_symbol',
        'receipt_show_urdu',
        'sound_effects', 'online_threshold_mins', 'auto_logout_mins',
        'email_notifications', 'sms_notifications',
    ];

    // Per-key validation
    $errors = [];

    foreach ($incoming as $key => $value) {
        if (!in_array($key, $allowedKeys, true)) {
            $errors[] = "Unknown setting key: {$key}";
            continue;
        }

        $value = trim((string) $value);

        // Specific rules
        switch ($key) {
            case 'contact_email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Contact email must be a valid email address.';
                }
                break;
            case 'max_tokens_per_day':
                if (!is_numeric($value) || (int)$value < 1 || (int)$value > 9999) {
                    $errors[] = 'Max tokens per day must be between 1 and 9999.';
                }
                break;
            case 'online_threshold_mins':
                if (!is_numeric($value) || (int)$value < 1 || (int)$value > 60) {
                    $errors[] = 'Online threshold must be between 1 and 60 minutes.';
                }
                break;
            case 'auto_logout_mins':
                if (!is_numeric($value) || (int)$value < 5 || (int)$value > 480) {
                    $errors[] = 'Auto-logout must be between 5 and 480 minutes.';
                }
                break;
            case 'token_reset_time':
                if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
                    $errors[] = 'Token reset time must be in HH:MM format.';
                }
                break;
        }
    }

    if ($errors) {
        jsonResponse(false, implode(' ', $errors));
    }

    // ── Handle logo file upload ───────────────────────────────
    $logoFilename = null;
    if (!empty($_FILES['hospital_logo']['name'])) {
        $file         = $_FILES['hospital_logo'];
        $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        $maxSize      = 2 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(false, 'Logo upload failed (error code ' . $file['error'] . ').');
        }
        if (!in_array($file['type'], $allowedTypes, true)) {
            jsonResponse(false, 'Logo must be JPG, PNG, SVG or WebP.');
        }
        if ($file['size'] > $maxSize) {
            jsonResponse(false, 'Logo must be under 2MB.');
        }

        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $logoFilename = 'hospital_logo_' . time() . '.' . $ext;
        $destDir      = BASE_PATH . '/assets/uploads/logo/';
        $dest         = $destDir . $logoFilename;

        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        // Delete old logo if exists
        $oldLogo = Database::fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'hospital_logo'"
        )['setting_value'] ?? '';
        if ($oldLogo && file_exists($destDir . $oldLogo)) {
            @unlink($destDir . $oldLogo);
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(false, 'Failed to save logo file.');
        }

        // Add to incoming so it gets upserted
        $incoming['hospital_logo'] = $logoFilename;
    }
    Database::beginTransaction();
    try {
        foreach ($incoming as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) continue;
            Database::execute(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [trim($key), trim((string) $value)]
            );
        }
        Database::commit();
    } catch (Exception $e) {
        Database::rollback();
        error_log('Settings update error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to save settings. Please try again.');
    }

    logActivity(getCurrentUserId(), 'settings_updated', ['group' => $group]);
    jsonResponse(true, ucfirst($group) . ' settings saved successfully.', [
        'logo_filename' => $logoFilename,
    ]);
}

/**
 * testEmail
 * Sends a test email to the admin's registered address.
 * POST fields: (none beyond csrf_token)
 */
function testEmail(): void
{
    $adminEmail = $_SESSION['email'] ?? '';
    if (empty($adminEmail)) {
        jsonResponse(false, 'No email address found for your account.');
    }

    $hospitalName = getSetting('hospital_name', 'Hospital');
    $subject      = "Test Email from {$hospitalName}";
    $message      = "This is a test email from the TokenMed system.\n\n"
                  . "If you received this, your email configuration is working correctly.\n\n"
                  . "— {$hospitalName} / TokenMed";
    $headers      = "From: {$hospitalName} <noreply@tokenmed.local>\r\n"
                  . "X-Mailer: TokenMed/PHP";

    $sent = mail($adminEmail, $subject, $message, $headers);

    if ($sent) {
        logActivity(getCurrentUserId(), 'test_email_sent', ['to' => $adminEmail]);
        jsonResponse(true, "Test email sent to {$adminEmail}. Check your inbox.");
    } else {
        jsonResponse(false, 'Failed to send test email. Check your server mail configuration (sendmail/SMTP).');
    }
}