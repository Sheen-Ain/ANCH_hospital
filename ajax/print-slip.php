<?php
/**
 * ajax/print-slip.php
 * Shared endpoint — renders HTML for admission slips and patient receipts.
 *
 * GET params:
 *   type   — 'admission' | 'receipt'
 *   id     — admission ID or patient ID
 *   direct — '1' = output raw HTML directly (for print window), '0' = JSON (default)
 *
 * Returns JSON: { success, data: { html } }
 * OR raw HTML when direct=1
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireLogin();

$type   = trim($_GET['type']   ?? '');
$id     = (int) ($_GET['id']   ?? 0);
$direct = ($_GET['direct']     ?? '0') === '1';

if ($id <= 0) {
    if ($direct) { http_response_code(400); echo 'Invalid ID.'; exit; }
    jsonResponse(false, 'Invalid ID.');
}

switch ($type) {
    case 'admission':
        $html = renderAdmissionSlipHTML($id);
        break;
    case 'receipt':
        $html = renderReceiptHTML($id);
        break;
    default:
        if ($direct) { http_response_code(400); echo 'Invalid type.'; exit; }
        jsonResponse(false, 'Invalid type. Use "admission" or "receipt".');
}

if ($direct) {
    // Output raw HTML — used by openPrintWindow()
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

jsonResponse(true, 'Rendered.', ['html' => $html]);