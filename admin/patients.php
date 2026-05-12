<?php

/**
 * admin/patients.php — Enhanced All Patients Module
 *
 * Features:
 *  - Generate Token (modal, supports future appointment dates)
 *  - View patient details (modal with receipt)
 *  - Print receipt (58mm thermal via print window)
 *  - Payment update (icon + custom dropdown: cash/card/insurance/online/unpaid)
 *  - Mark as Returned (patient took token but did not consult)
 *  - Delete patient record
 *  - Full date-range filter, doctor filter, status filter
 *  - Fully responsive, aligned with TokenMed design system
 */

$pageTitle  = 'All Patients';
$activePage = 'patients';
require_once __DIR__ . '/header.php';

$doctors        = Database::fetchAll("SELECT id, name, specialization FROM doctors WHERE is_active = 1 ORDER BY name");
$currencySymbol = getSetting('currency_symbol', 'Rs.');
?>

<!-- ── Page-level CSS ─────────────────────────────────────────────── -->
<style>
    /* ── Status Badges ── */
    .badge-paid {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .badge-unpaid {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .badge-returned {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    /* ── Payment Dropdown ── */
    .pay-dropdown-wrapper {
        position: relative;
        display: inline-block;
    }

    #globalPayDropdown {
        position: absolute;
        z-index: 9999;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-modal);
        min-width: 200px;
        padding: 6px 0;
        display: none;
        animation: dropFadeIn 0.15s ease;
    }

    @keyframes dropFadeIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .pay-drop-section {
        padding: 4px 10px 2px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--color-text-faint);
    }

    .pay-drop-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 14px;
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text);
        cursor: pointer;
        transition: background .12s;
        position: relative;
    }

    .pay-drop-item:hover {
        background: var(--color-surface);
    }

    .pay-drop-item .pay-drop-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }

    .pay-drop-item .pay-tooltip {
        position: absolute;
        left: calc(100% + 8px);
        top: 50%;
        transform: translateY(-50%);
        background: #1e293b;
        color: #fff;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 6px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity .15s;
        z-index: 10000;
    }

    .pay-drop-item .pay-tooltip::before {
        content: '';
        position: absolute;
        left: -4px;
        top: 50%;
        transform: translateY(-50%);
        border: 4px solid transparent;
        border-right-color: #1e293b;
        border-left: none;
    }

    .pay-drop-item:hover .pay-tooltip {
        opacity: 1;
    }

    .pay-drop-divider {
        border: none;
        border-top: 1px solid var(--color-border);
        margin: 4px 0;
    }

    /* ── Generate Token Modal ── */
    .gt-modal-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        max-height: 72vh;
    }

    @media(max-width:700px) {
        .gt-modal-body {
            grid-template-columns: 1fr;
            max-height: none;
        }
    }

    .gt-doctor-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .625rem;
        overflow-y: auto;
        max-height: 58vh;
        padding-right: 4px;
    }

    @media(max-width:500px) {
        .gt-doctor-grid {
            grid-template-columns: 1fr;
        }
    }

    .gt-doc-card {
        background: var(--color-card);
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: .75rem;
        cursor: pointer;
        transition: all .14s;
        position: relative;
    }

    .gt-doc-card:hover {
        border-color: var(--color-primary);
        background: var(--color-primary-lt);
    }

    .gt-doc-card.selected {
        border-color: var(--color-primary);
        background: var(--color-primary-lt);
        box-shadow: 0 0 0 3px rgba(3, 105, 161, .1);
    }

    .gt-doc-card.full {
        opacity: .45;
        cursor: not-allowed;
        pointer-events: none;
    }

    .gt-doc-name {
        font-size: 12px;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 2px;
    }

    .gt-doc-spec {
        font-size: 11px;
        color: var(--color-text-muted);
    }

    .gt-doc-fee {
        font-size: 11px;
        font-weight: 600;
        color: var(--color-accent);
        margin-top: 6px;
    }

    .gt-doc-tok {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        font-weight: 800;
        color: var(--color-primary);
        margin-top: 2px;
    }

    .gt-check {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--color-primary);
        color: #fff;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 9px;
    }

    .gt-doc-card.selected .gt-check {
        display: flex;
    }

    /* Token preview banner */
    .gt-token-preview {
        background: linear-gradient(135deg, #0c4a6e, #0369a1);
        border-radius: var(--radius-lg);
        padding: 1rem 1.25rem;
        text-align: center;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }

    .gt-token-preview.inactive {
        background: linear-gradient(135deg, #475569, #64748b);
    }

    .gt-token-preview::before {
        content: '';
        position: absolute;
        width: 140px;
        height: 140px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .06);
        top: -40px;
        right: -40px;
    }

    .gt-tp-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: rgba(255, 255, 255, .65);
        margin-bottom: 4px;
    }

    .gt-tp-num {
        font-family: 'JetBrains Mono', monospace;
        font-size: 1.875rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: 3px;
        line-height: 1;
    }

    .gt-tp-doc {
        font-size: 11px;
        color: rgba(255, 255, 255, .8);
        margin-top: 6px;
    }

    .gt-tp-date {
        font-size: 10px;
        color: rgba(255, 255, 255, .55);
        margin-top: 3px;
    }

    /* Compact form inside modal */
    .gt-form-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .75rem;
    }

    @media(max-width:460px) {
        .gt-form-grid-2 {
            grid-template-columns: 1fr;
        }
    }

    .gt-section-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--color-text-faint);
        margin: 12px 0 8px;
        padding-bottom: 5px;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .gt-section-label i {
        color: var(--color-primary);
        font-size: 10px;
    }

    .gt-section-label:first-child {
        margin-top: 0;
    }

    /* Right panel scroll */
    .gt-right-panel {
        overflow-y: auto;
        max-height: 58vh;
        padding-right: 2px;
    }

    @media(max-width:700px) {
        .gt-right-panel {
            max-height: none;
        }
    }

    /* ── Responsive table ── */
    @media(max-width:900px) {
        .col-hide-md {
            display: none !important;
        }
    }

    @media(max-width:640px) {
        .col-hide-sm {
            display: none !important;
        }
    }

    /* ── Statistics Cards ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .875rem;
        margin-bottom: 1.25rem;
    }

    @media(max-width:900px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width:480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: .625rem;
        }
    }

    .stat-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1rem 1.125rem;
        display: flex;
        align-items: flex-start;
        gap: .875rem;
        transition: box-shadow .15s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }

    .stat-card.sc-blue::after {
        background: var(--color-primary);
    }

    .stat-card.sc-green::after {
        background: #059669;
    }

    .stat-card.sc-amber::after {
        background: #d97706;
    }

    .stat-card.sc-slate::after {
        background: #64748b;
    }

    .stat-card:hover {
        box-shadow: var(--shadow-md);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .sc-blue .stat-icon {
        background: var(--color-primary-lt);
        color: var(--color-primary);
    }

    .sc-green .stat-icon {
        background: #d1fae5;
        color: #059669;
    }

    .sc-amber .stat-icon {
        background: #fef3c7;
        color: #d97706;
    }

    .sc-slate .stat-icon {
        background: #f1f5f9;
        color: #64748b;
    }

    .stat-body {
        min-width: 0;
        flex: 1;
    }

    .stat-value {
        font-family: 'JetBrains Mono', monospace;
        font-size: 1.375rem;
        font-weight: 800;
        line-height: 1.1;
        color: var(--color-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .stat-label {
        font-size: 11px;
        color: var(--color-text-muted);
        margin-top: 2px;
        font-weight: 500;
    }

    .stat-sub {
        font-size: 11px;
        font-weight: 600;
        margin-top: 5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 20px;
    }

    .sc-blue .stat-sub {
        background: var(--color-primary-lt);
        color: var(--color-primary);
    }

    .sc-green .stat-sub {
        background: #d1fae5;
        color: #059669;
    }

    .sc-amber .stat-sub {
        background: #fef3c7;
        color: #d97706;
    }

    .sc-slate .stat-sub {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Skeleton loader for stats */
    .stat-skeleton {
        height: 18px;
        border-radius: 6px;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-surface) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.4s infinite;
    }

    @keyframes shimmer {
        to {
            background-position: -200% 0;
        }
    }

    /* ── Table: no forced min-width, mobile card-like rows ── */
    .patients-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #patientsTable {
        width: 100%;
        border-collapse: collapse;
    }

    /* Per-page + pagination row */
    .pagination-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        flex-wrap: wrap;
        padding: .625rem 1rem;
        border-top: 1px solid var(--color-border);
        font-size: 12px;
        color: var(--color-text-muted);
    }

    .per-page-wrap {
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .per-page-wrap select {
        font-size: 12px;
        padding: 3px 8px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-surface);
        color: var(--color-text);
        cursor: pointer;
    }

    .page-btns-wrap {
        display: flex;
        align-items: center;
        gap: .25rem;
        flex-wrap: wrap;
    }

    .page-btn {
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-surface);
        color: var(--color-text);
        font-size: 12px;
        cursor: pointer;
        transition: all .12s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .page-btn:hover:not(:disabled) {
        background: var(--color-primary-lt);
        border-color: var(--color-primary);
        color: var(--color-primary);
    }

    .page-btn.active {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: #fff;
        font-weight: 700;
    }

    .page-btn:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    .page-btn.dots {
        cursor: default;
        border-color: transparent;
        background: transparent;
    }
</style>

<!-- ── Page Header ─────────────────────────────────────────────────── -->
<div class="page-header" style="flex-wrap:wrap;gap:.75rem;">
    <div class="flex items-center gap-3 flex-wrap">
        <h2 class="page-title">
            <i class="fa-solid fa-users" style="color:var(--color-primary);"></i>
            All Patients
        </h2>
        <span class="text-xs px-2 py-0.5 rounded-full font-bold"
            style="background:var(--color-primary-lt);color:var(--color-primary);"
            id="totalCount">Loading…</span>
    </div>
    <button class="btn btn-primary btn-sm" id="openGenTokenBtn">
        <i class="fa-solid fa-ticket"></i>
        Generate Token
    </button>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────── -->
<div class="card mb-4" style="padding:.875rem 1rem;">
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">

        <div style="flex:1;min-width:160px;max-width:240px;">
            <label class="form-label">Search</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass"
                    style="position:absolute;left:11px;top:50%;transform:translateY(-50%);
                          color:var(--color-text-faint);font-size:12px;"></i>
                <input type="text" id="searchInput" class="form-input"
                    placeholder="Name, phone, doctor…" style="padding-left:33px;">
            </div>
        </div>

        <div style="min-width:130px;">
            <label class="form-label">From</label>
            <input type="date" id="dateFrom" class="form-input">
        </div>

        <div style="min-width:130px;">
            <label class="form-label">To</label>
            <input type="date" id="dateTo" class="form-input">
        </div>

        <div style="min-width:155px;">
            <label class="form-label">Doctor</label>
            <select id="doctorFilter" class="form-select">
                <option value="">All Doctors</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int)$doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width:130px;">
            <label class="form-label">Status</label>
            <select id="statusFilter" class="form-select">
                <option value="">All</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="returned">Returned</option>
            </select>
        </div>

        <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" style="height:38px;">
            <i class="fa-solid fa-xmark"></i> Clear
        </button>
    </div>
</div>

<!-- ── Statistics Cards ────────────────────────────────────────────── -->
<div class="stats-grid" id="statsGrid">
    <!-- Skeleton until first load -->
    <div class="stat-card sc-blue">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-body">
            <div class="stat-value" id="statTotal">
                <div class="stat-skeleton" style="width:60px;"></div>
            </div>
            <div class="stat-label">Total Patients</div>
        </div>
    </div>
    <div class="stat-card sc-green">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-body">
            <div class="stat-value" id="statRevenue">
                <div class="stat-skeleton" style="width:80px;"></div>
            </div>
            <div class="stat-label">Revenue Collected</div>
            <div class="stat-sub sc-green" id="statPaidCount" style="display:none;">
                <i class="fa-solid fa-ticket" style="font-size:9px;"></i>
                <span></span>
            </div>
        </div>
    </div>
    <div class="stat-card sc-amber">
        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-body">
            <div class="stat-value" id="statPending">
                <div class="stat-skeleton" style="width:80px;"></div>
            </div>
            <div class="stat-label">Pending Amount</div>
            <div class="stat-sub sc-amber" id="statUnpaidCount" style="display:none;">
                <i class="fa-solid fa-ticket" style="font-size:9px;"></i>
                <span></span>
            </div>
        </div>
    </div>
    <div class="stat-card sc-slate">
        <div class="stat-icon"><i class="fa-solid fa-person-circle-xmark"></i></div>
        <div class="stat-body">
            <div class="stat-value" id="statReturned">
                <div class="stat-skeleton" style="width:40px;"></div>
            </div>
            <div class="stat-label">Returned</div>
            <div class="stat-sub sc-slate" id="statReturnedSub" style="display:none;">
                <i class="fa-solid fa-arrow-rotate-left" style="font-size:9px;"></i>
                <span></span>
            </div>
        </div>
    </div>
</div>

<!-- ── Patients Table ──────────────────────────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;">
    <div class="patients-table-wrap">
        <table class="data-table" id="patientsTable">
            <thead>
                <tr>
                    <th style="width:88px;">Token</th>
                    <th>Patient</th>
                    <th class="col-hide-md">Doctor</th>
                    <th class="col-hide-md">Date</th>
                    <th style="width:115px;">Status</th>
                    <th style="width:90px;" class="col-hide-sm">Amount</th>
                    <th style="width:75px;" class="col-hide-md">By</th>
                    <th style="width:148px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="patientsBody">
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination row -->
    <div class="pagination-row" id="paginationBar">
        <div class="per-page-wrap">
            <span>Rows:</span>
            <select id="perPageSelect">
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
            </select>
            <span id="pageInfo" style="color:var(--color-text-muted);">—</span>
        </div>
        <div class="page-btns-wrap" id="pageBtns"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     GLOBAL PAYMENT DROPDOWN — single shared element, repositioned by JS
     ═══════════════════════════════════════════════════════════ -->
<div id="globalPayDropdown" role="menu" aria-label="Payment options">
    <div class="pay-drop-section">Mark as Paid</div>

    <div class="pay-drop-item" data-action="paid" data-method="cash">
        <span class="pay-drop-icon" style="background:#d1fae5;color:#059669;">
            <i class="fa-solid fa-money-bill-1"></i>
        </span>
        Cash
        <span class="pay-tooltip">Mark Paid · Cash</span>
    </div>
    <div class="pay-drop-item" data-action="paid" data-method="card">
        <span class="pay-drop-icon" style="background:#dbeafe;color:#2563eb;">
            <i class="fa-solid fa-credit-card"></i>
        </span>
        Card
        <span class="pay-tooltip">Mark Paid · Card</span>
    </div>
    <div class="pay-drop-item" data-action="paid" data-method="insurance">
        <span class="pay-drop-icon" style="background:#ede9fe;color:#7c3aed;">
            <i class="fa-solid fa-shield-halved"></i>
        </span>
        Insurance
        <span class="pay-tooltip">Mark Paid · Insurance</span>
    </div>
    <div class="pay-drop-item" data-action="paid" data-method="online">
        <span class="pay-drop-icon" style="background:#cffafe;color:#0891b2;">
            <i class="fa-solid fa-mobile-screen-button"></i>
        </span>
        Online
        <span class="pay-tooltip">Mark Paid · Online Transfer</span>
    </div>

    <hr class="pay-drop-divider">
    <div class="pay-drop-section">Other</div>

    <div class="pay-drop-item" data-action="unpaid" data-method="">
        <span class="pay-drop-icon" style="background:#fef3c7;color:#d97706;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </span>
        Mark Unpaid
        <span class="pay-tooltip">Revert to Unpaid</span>
    </div>

    <hr class="pay-drop-divider">

    <div class="pay-drop-item" data-action="returned" data-method="">
        <span class="pay-drop-icon" style="background:#f1f5f9;color:#64748b;">
            <i class="fa-solid fa-person-circle-xmark"></i>
        </span>
        Returned
        <span class="pay-tooltip">No Consultation — Patient Returned</span>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     PATIENT DETAIL MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="patientDetailModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-user" style="color:var(--color-primary);"></i>
                Patient Details
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:.75rem;">
            <div class="receipt-preview-wrapper" id="patientDetailContent">
                <div class="text-center py-6"><span class="spinner"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="adminPrintReceiptBtn" data-patient-id="">
                <i class="fa-solid fa-print"></i> Print Receipt
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     EDIT PATIENT MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editPatientModal">
    <div class="modal-box" style="max-width:640px;width:96vw;">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-primary);"></i>
                Edit Patient
                <span id="epTokenBadge" style="
                    margin-left:.5rem;font-family:'JetBrains Mono',monospace;
                    font-size:12px;font-weight:700;color:var(--color-primary);
                    background:var(--color-primary-lt);border:1px solid var(--color-primary);
                    border-radius:6px;padding:2px 8px;"></span>
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="modal-body" style="padding:1rem;max-height:75vh;overflow-y:auto;">
            <!-- Loading state -->
            <div id="epLoading" class="text-center py-8"><span class="spinner"></span></div>

            <!-- Error banner -->
            <div id="epError" class="mb-3" style="display:none;">
                <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:10px;
                            padding:.6rem .875rem;font-size:13px;color:#991b1b;
                            display:flex;align-items:center;gap:.5rem;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span id="epErrorText"></span>
                </div>
            </div>

            <!-- Form (hidden until data loads) -->
            <div id="epForm" style="display:none;">

                <input type="hidden" id="epPatientId">

                <!-- ── Doctor + Datetime ── -->
                <div class="gt-section-label">
                    <i class="fa-solid fa-user-doctor"></i> Doctor &amp; Appointment
                </div>
                <div class="gt-form-grid-2 mb-3">
                    <div>
                        <label class="form-label">Doctor <span style="color:var(--color-danger);">*</span></label>
                        <select id="epDoctor" class="form-select">
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?= (int)$doc['id'] ?>">Dr. <?= e($doc['name']) ?> — <?= e($doc['specialization']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Appointment Date &amp; Time <span style="color:var(--color-danger);">*</span></label>
                        <input type="datetime-local" id="epDatetime" class="form-input" style="width:100%;">
                    </div>
                </div>

                <!-- ── Patient Info ── -->
                <div class="gt-section-label">
                    <i class="fa-solid fa-user"></i> Patient Information
                </div>
                <div class="mb-2">
                    <label class="form-label">Full Name <span style="color:var(--color-danger);">*</span></label>
                    <input type="text" id="epName" class="form-input" placeholder="Patient's full name">
                </div>
                <div class="gt-form-grid-2 mb-2">
                    <div>
                        <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                        <select id="epGender" class="form-select">
                            <option value="">Select…</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Age</label>
                        <input type="number" id="epAge" class="form-input" placeholder="Years" min="0" max="150">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Phone</label>
                    <input type="tel" id="epPhone" class="form-input" placeholder="03xx-xxxxxxx">
                </div>
                <div class="mb-2">
                    <label class="form-label">Address</label>
                    <input type="text" id="epAddress" class="form-input" placeholder="City / area">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea id="epNotes" class="form-input" rows="2"
                        placeholder="Any additional notes…" style="resize:vertical;"></textarea>
                    <p class="text-xs mt-1" style="color:var(--color-text-muted);">
                        <i class="fa-solid fa-triangle-exclamation" style="color:var(--color-warning);font-size:10px;"></i>
                        If patient is marked Returned, that status is preserved in notes automatically.
                    </p>
                </div>

                <!-- ── Payment ── -->
                <div class="gt-section-label">
                    <i class="fa-solid fa-receipt"></i> Payment
                </div>
                <div class="mb-2">
                    <div style="display:flex;gap:1.25rem;">
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                            <input type="radio" name="epPayStatus" value="unpaid" id="epUnpaid"
                                style="accent-color:var(--color-warning);">
                            <span>Unpaid</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                            <input type="radio" name="epPayStatus" value="paid" id="epPaid"
                                style="accent-color:var(--color-accent);">
                            <span>Paid</span>
                        </label>
                    </div>
                </div>
                <div id="epMethodWrap" class="mb-1" style="display:none;">
                    <label class="form-label">Payment Method</label>
                    <select id="epMethod" class="form-select">
                        <option value="">Select method…</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="insurance">Insurance</option>
                        <option value="online">Online</option>
                    </select>
                </div>

            </div><!-- /#epForm -->
        </div><!-- /.modal-body -->

        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="epSaveBtn" style="display:none;">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     GENERATE TOKEN MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="genTokenModal">
    <div class="modal-box" style="max-width:800px;width:96vw;">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-ticket" style="color:var(--color-accent);"></i>
                Generate New Token
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:1rem;">

            <!-- Error banner -->
            <div id="gtError" class="mb-3" style="display:none;">
                <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:10px;
                            padding:.6rem .875rem;font-size:13px;color:#991b1b;
                            display:flex;align-items:center;gap:.5rem;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span id="gtErrorText"></span>
                </div>
            </div>

            <div class="gt-modal-body">
                <!-- ── LEFT: Doctor selection ── -->
                <div>
                    <div class="gt-section-label">
                        <i class="fa-solid fa-user-doctor"></i> Select Doctor
                    </div>
                    <div class="gt-doctor-grid" id="gtDoctorGrid">
                        <div style="grid-column:1/-1;text-align:center;padding:1.5rem;">
                            <span class="spinner"></span>
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT: Token preview + form ── -->
                <div class="gt-right-panel">
                    <!-- Token preview -->
                    <div class="gt-token-preview inactive" id="gtPreview">
                        <div class="gt-tp-label">Next Token</div>
                        <div class="gt-tp-num" id="gtPreviewNum">—</div>
                        <div class="gt-tp-doc" id="gtPreviewDoc">Select a doctor</div>
                        <div class="gt-tp-date" id="gtPreviewDate">No date selected</div>
                    </div>

                    <!-- Appointment date/time -->
                    <div class="gt-section-label">
                        <i class="fa-solid fa-calendar-days"></i> Appointment Date &amp; Time
                    </div>
                    <div class="mb-3">
                        <input type="datetime-local" id="gtDatetime" class="form-input"
                            style="width:100%;">
                        <p class="text-xs mt-1" style="color:var(--color-text-muted);">
                            <i class="fa-solid fa-circle-info" style="font-size:10px;"></i>
                            You can schedule future appointments.
                        </p>
                    </div>

                    <!-- Patient info -->
                    <div class="gt-section-label">
                        <i class="fa-solid fa-user"></i> Patient Information
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Full Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="gtName" class="form-input" placeholder="Patient's full name">
                    </div>
                    <div class="gt-form-grid-2 mb-2">
                        <div>
                            <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                            <select id="gtGender" class="form-select">
                                <option value="">Select…</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Age</label>
                            <input type="number" id="gtAge" class="form-input" placeholder="Years" min="0" max="150">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Phone</label>
                        <input type="tel" id="gtPhone" class="form-input" placeholder="03xx-xxxxxxx">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Address</label>
                        <input type="text" id="gtAddress" class="form-input" placeholder="City / area">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="gtNotes" class="form-input" rows="2"
                            placeholder="Any additional notes…" style="resize:vertical;"></textarea>
                    </div>

                    <!-- Payment -->
                    <div class="gt-section-label">
                        <i class="fa-solid fa-receipt"></i> Payment
                    </div>
                    <div class="mb-2">
                        <div style="display:flex;gap:1rem;">
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                                <input type="radio" name="gtPayStatus" value="unpaid" id="gtUnpaid"
                                    checked style="accent-color:var(--color-warning);">
                                <span>Unpaid</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                                <input type="radio" name="gtPayStatus" value="paid" id="gtPaid"
                                    style="accent-color:var(--color-accent);">
                                <span>Paid</span>
                            </label>
                        </div>
                    </div>
                    <div id="gtMethodWrap" class="mb-3" style="display:none;">
                        <label class="form-label">Payment Method</label>
                        <select id="gtMethod" class="form-select">
                            <option value="">Select method…</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="online">Online</option>
                        </select>
                    </div>

                    <!-- Fee preview -->
                    <div id="gtFeeRow" style="display:none;background:var(--color-surface);
                         border:1px solid var(--color-border);border-radius:10px;
                         padding:.5rem .875rem;display:flex;justify-content:space-between;
                         align-items:center;margin-bottom:.5rem;font-size:13px;">
                        <span style="color:var(--color-text-muted);">
                            <i class="fa-solid fa-coins" style="color:var(--color-accent);margin-right:4px;"></i>
                            Consultation Fee
                        </span>
                        <span id="gtFeeAmt" class="font-bold" style="color:var(--color-accent);font-family:'JetBrains Mono',monospace;"></span>
                    </div>
                </div><!-- /.gt-right-panel -->
            </div><!-- /.gt-modal-body -->
        </div><!-- /.modal-body -->

        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="gtSubmitBtn" disabled>
                <i class="fa-solid fa-ticket"></i>
                Generate Token
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL  = `${window.APP_CONFIG.baseUrl}/ajax/admin/patients.php`;
    let currentPage = 1;

    // ── Helpers ────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ── Default dates: last 30 days ────────────────────────────────
    const today = new Date();
    const ago30 = new Date(today); ago30.setDate(today.getDate() - 30);
    function toYMD(d) { return d.toISOString().split('T')[0]; }
    document.getElementById('dateFrom').value = toYMD(ago30);
    document.getElementById('dateTo').value   = toYMD(today);

    // ════════════════════════════════════════════════════════════
    // LOAD & RENDER PATIENTS
    // ════════════════════════════════════════════════════════════
    function loadPatients(page = 1) {
        currentPage = page;
        const perPage = parseInt(document.getElementById('perPageSelect').value, 10) || 50;

        const params = new URLSearchParams({
            action:        'getPatients',
            search:         document.getElementById('searchInput').value.trim(),
            date_from:      document.getElementById('dateFrom').value,
            date_to:        document.getElementById('dateTo').value,
            doctor_id:      document.getElementById('doctorFilter').value,
            status_filter:  document.getElementById('statusFilter').value,
            page,
            per_page: perPage,
        });

        // Show skeleton on stats
        ['statTotal','statRevenue','statPending','statReturned'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<div class="stat-skeleton" style="width:60px;margin-top:2px;"></div>';
        });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const d = res.data;
                document.getElementById('totalCount').textContent = `${d.total} patient(s)`;
                renderStats(d.stats);
                renderTable(d.patients);
                renderPagination(d.page, d.total_pages, d.total, perPage);
            })
            .catch(() => showToast('Failed to load patients.', 'error'));
    }

    // ── Payment status badge HTML ──────────────────────────────
    function payBadgeHtml(p) {
        if (p.visit_status === 'returned') {
            return `<span class="badge badge-returned" style="font-size:11px;">
                        <i class="fa-solid fa-person-circle-xmark" style="font-size:10px;margin-right:3px;"></i>Returned
                    </span>`;
        }
        if (p.payment_status === 'paid') {
            const method = p.payment_method ? ' · ' + ucFirst(p.payment_method) : '';
            return `<span class="badge badge-paid" style="font-size:11px;">
                        <i class="fa-solid fa-circle-check" style="font-size:10px;margin-right:3px;"></i>Paid${escHtml(method)}
                    </span>`;
        }
        return `<span class="badge badge-unpaid" style="font-size:11px;">
                    <i class="fa-solid fa-hourglass-half" style="font-size:10px;margin-right:3px;"></i>Unpaid
                </span>`;
    }

    // ── Payment trigger button HTML ───────────────────────────
    function payBtnHtml(p) {
        let icon, bg, color, title;
        if (p.visit_status === 'returned') {
            icon = 'fa-solid fa-person-circle-xmark'; bg = '#f1f5f9'; color = '#64748b'; title = 'Returned — Update Payment';
        } else if (p.payment_status === 'paid') {
            icon = 'fa-solid fa-circle-check'; bg = '#d1fae5'; color = '#059669'; title = 'Paid — Change Method';
        } else {
            icon = 'fa-solid fa-hourglass-half'; bg = '#fef3c7'; color = '#d97706'; title = 'Unpaid — Mark as Paid';
        }
        return `<button class="btn btn-icon btn-sm pay-trigger-btn"
                        style="background:${bg};color:${color};"
                        title="${title}"
                        onclick="openPayDropdown(${p.id}, this)">
                    <i class="${icon}" style="font-size:12px;"></i>
                </button>`;
    }

    function renderTable(patients) {
        const tbody  = document.getElementById('patientsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        if (!patients.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="table-empty">
                <div class="table-empty-icon">
                    <i class="fa-solid fa-users-slash" style="font-size:2rem;color:var(--color-text-faint);"></i>
                </div>
                No patients match your filters.</td></tr>`;
            return;
        }

        tbody.innerHTML = patients.map(p => `
            <tr class="animate-fade-in" id="row-${p.id}" data-id="${p.id}"
                data-vstatus="${escHtml(p.visit_status)}"
                data-pstatus="${escHtml(p.payment_status)}"
                data-method="${escHtml(p.payment_method ?? '')}"
                data-doctor-id="${p.doctor_id ?? ''}">
                <td>
                    <span class="font-mono-nums font-bold" style="font-size:11px;color:var(--color-primary);">
                        ${escHtml(prefix)}-${String(p.token_number).padStart(3,'0')}
                    </span>
                </td>
                <td>
                    <div class="font-medium" style="font-size:12px;">${escHtml(p.name)}</div>
                    <div style="font-size:11px;color:var(--color-text-muted);">
                        ${p.phone ? escHtml(p.phone) : '—'}
                    </div>
                </td>
                <td class="col-hide-md">
                    <div style="font-size:12px;font-weight:600;">${escHtml(p.doctor_name)}</div>
                    <div style="font-size:11px;color:var(--color-text-muted);">${escHtml(p.specialization)}</div>
                </td>
                <td class="col-hide-sm">
                    <div style="font-size:12px;font-weight:600;">${escHtml(p.visit_date_fmt)}</div>
                    <div style="font-size:11px;color:var(--color-text-muted);">${escHtml(p.visit_time_fmt)}</div>
                </td>
                <td id="badge-${p.id}">${payBadgeHtml(p)}</td>
                <td class="col-hide-sm" style="font-size:12px;font-weight:700;color:var(--color-accent);">
                    ${escHtml(p.amount_fmt)}
                </td>
                <td class="col-hide-md" style="font-size:11px;color:var(--color-text-muted);">
                    ${escHtml(p.receptionist_name)}
                </td>
                <td>
                    <div class="table-actions justify-end" style="gap:.25rem;">
                        <!-- View -->
                        <button class="btn btn-ghost btn-icon btn-sm"
                                title="View Patient Details"
                                onclick="viewPatient(${p.id})">
                            <i class="fa-solid fa-eye" style="font-size:12px;"></i>
                        </button>
                        <!-- Print -->
                        <button class="btn btn-ghost btn-icon btn-sm"
                                title="Print Receipt"
                                onclick="openPrintWindow('receipt',${p.id})">
                            <i class="fa-solid fa-print" style="font-size:12px;"></i>
                        </button>
                        <!-- Edit -->
                        <button class="btn btn-icon btn-sm"
                                style="background:var(--color-primary-lt);color:var(--color-primary);"
                                title="Edit Patient Details"
                                onclick="openEditPatient(${p.id})">
                            <i class="fa-solid fa-pen-to-square" style="font-size:12px;"></i>
                        </button>
                        <!-- Payment -->
                        ${payBtnHtml(p)}
                        <!-- Delete -->
                        <button class="btn btn-icon btn-sm"
                                style="background:var(--color-danger-lt);color:var(--color-danger);"
                                title="Delete Record"
                                onclick="confirmDelete(${p.id},'${escHtml(p.name).replace(/'/g,"\\'")}')">
                            <i class="fa-solid fa-trash" style="font-size:12px;"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    // ── Render stats cards ─────────────────────────────────────
    function renderStats(s) {
        if (!s) return;
        document.getElementById('statTotal').textContent   = s.total;
        document.getElementById('statRevenue').textContent = s.revenue;
        document.getElementById('statPending').textContent = s.pending;
        document.getElementById('statReturned').textContent= s.returned;

        const paidEl = document.getElementById('statPaidCount');
        paidEl.style.display = 'inline-flex';
        paidEl.querySelector('span').textContent = s.paid + ' paid';

        const unpaidEl = document.getElementById('statUnpaidCount');
        unpaidEl.style.display = 'inline-flex';
        unpaidEl.querySelector('span').textContent = s.unpaid + ' unpaid';

        const retEl = document.getElementById('statReturnedSub');
        retEl.style.display = s.returned > 0 ? 'inline-flex' : 'none';
        retEl.querySelector('span').textContent = 'no consultation';
    }

    function renderPagination(page, totalPages, total, perPage) {
        const start = total ? (page - 1) * perPage + 1 : 0;
        const end   = Math.min(page * perPage, total);
        document.getElementById('pageInfo').textContent =
            total ? `${start}–${end} of ${total}` : 'No results';

        const btns = document.getElementById('pageBtns');
        btns.innerHTML = '';
        if (totalPages <= 1) return;

        function makeBtn(label, pg, isActive, isDots, disabled) {
            const b = document.createElement('button');
            b.className = 'page-btn' + (isActive ? ' active' : '') + (isDots ? ' dots' : '');
            b.innerHTML = label;
            b.disabled  = disabled || isDots;
            if (!isDots && !disabled) b.onclick = () => loadPatients(pg);
            return b;
        }

        // Prev
        btns.appendChild(makeBtn('<i class="fa-solid fa-chevron-left"></i>', page - 1, false, false, page <= 1));

        // First page
        if (page > 3) {
            btns.appendChild(makeBtn('1', 1, false, false, false));
            if (page > 4) btns.appendChild(makeBtn('…', null, false, true, true));
        }

        // Window around current page
        for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p++) {
            btns.appendChild(makeBtn(p, p, p === page, false, false));
        }

        // Last page
        if (page < totalPages - 2) {
            if (page < totalPages - 3) btns.appendChild(makeBtn('…', null, false, true, true));
            btns.appendChild(makeBtn(totalPages, totalPages, false, false, false));
        }

        // Next
        btns.appendChild(makeBtn('<i class="fa-solid fa-chevron-right"></i>', page + 1, false, false, page >= totalPages));
    }

    window.loadPatients = loadPatients;

    // ════════════════════════════════════════════════════════════
    // PAYMENT DROPDOWN
    // ════════════════════════════════════════════════════════════
    const payDD     = document.getElementById('globalPayDropdown');
    let   payTarget = null; // current patient id

    window.openPayDropdown = function (patientId, triggerEl) {
        // Close if same button clicked again
        if (payDD.style.display === 'block' && payTarget === patientId) {
            closePayDropdown(); return;
        }
        payTarget = patientId;

        // Get row data to know current status
        const row = document.getElementById('row-' + patientId);
        const vStatus = row ? row.dataset.vstatus : 'active';
        const pStatus = row ? row.dataset.pstatus : 'unpaid';

        // Highlight "returned" option if currently returned
        payDD.querySelectorAll('.pay-drop-item').forEach(item => {
            item.style.fontWeight = '';
            item.style.background = '';
        });
        if (vStatus === 'returned') {
            const retItem = payDD.querySelector('[data-action="returned"]');
            if (retItem) { retItem.style.fontWeight = '700'; retItem.style.background = 'var(--color-surface)'; }
        } else if (pStatus === 'paid') {
            const pItem = payDD.querySelector(`[data-action="paid"][data-method="${row.dataset.method}"]`);
            if (pItem) { pItem.style.fontWeight = '700'; pItem.style.background = 'var(--color-surface)'; }
        } else {
            const uItem = payDD.querySelector('[data-action="unpaid"]');
            if (uItem) { uItem.style.fontWeight = '700'; uItem.style.background = 'var(--color-surface)'; }
        }

        // Position — absolute means relative to document, so add scroll offsets
        const rect = triggerEl.getBoundingClientRect();
        const sY   = window.scrollY;
        const sX   = window.scrollX;
        payDD.style.display = 'block';

        const ddW = 214;
        let left  = rect.right + sX - ddW;
        if (left < 8) left = rect.left + sX;
        if (left + ddW > document.documentElement.clientWidth - 8)
            left = document.documentElement.clientWidth - ddW - 8;
        payDD.style.left = left + 'px';
        payDD.style.top  = (rect.bottom + sY + 4) + 'px';

        requestAnimationFrame(() => {
            const ddH = payDD.offsetHeight;
            if (rect.bottom + ddH + 4 > window.innerHeight) {
                payDD.style.top = (rect.top + sY - ddH - 4) + 'px';
            }
        });
    };

    function closePayDropdown() {
        payDD.style.display = 'none';
        payTarget = null;
    }

    // Outside click closes dropdown
    document.addEventListener('click', e => {
        if (!payDD.contains(e.target) && !e.target.closest('.pay-trigger-btn')) {
            closePayDropdown();
        }
    });

    // Dropdown item click
    payDD.querySelectorAll('.pay-drop-item').forEach(item => {
        item.addEventListener('click', () => {
            if (!payTarget) return;
            const id     = payTarget;   // capture BEFORE closePayDropdown nulls it
            const action = item.dataset.action;
            const method = item.dataset.method;
            closePayDropdown();
            applyPayment(id, action, method);
        });
    });

    function applyPayment(patientId, action, method) {
        if (action === 'returned') {
            // Mark as returned
            const fd = new FormData();
            fd.set('action',      'markReturned');
            fd.set('csrf_token',  getCsrfToken());
            fd.set('patient_id',  patientId);
            fd.set('mark_action', 'return');
            postPayment(fd, patientId);
        } else {
            // Paid or unpaid
            const fd = new FormData();
            fd.set('action',          'updatePayment');
            fd.set('csrf_token',      getCsrfToken());
            fd.set('patient_id',      patientId);
            fd.set('payment_status',  action);
            if (method) fd.set('payment_method', method);
            postPayment(fd, patientId);
        }
    }

    function postPayment(fd, patientId) {
        // Show loading on trigger button
        const row    = document.getElementById('row-' + patientId);
        const payBtn = row ? row.querySelector('.pay-trigger-btn') : null;
        if (payBtn) payBtn.innerHTML = '<span class="spinner" style="width:12px;height:12px;"></span>';

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); loadPatients(currentPage); return; }
                showToast(res.message, 'success');
                if (window.APP_CONFIG.soundEnabled && res.data?.payment_status === 'paid') {
                    try { playSound('cash-register-kaching.mp3'); } catch(e) {}
                }
                // Update row data
                if (row && res.data) {
                    row.dataset.vstatus = res.data.visit_status ?? 'active';
                    row.dataset.pstatus = res.data.payment_status ?? 'unpaid';
                    row.dataset.method  = res.data.payment_method ?? '';
                    // Re-render badge + pay button
                    const p = {
                        id:             patientId,
                        visit_status:   res.data.visit_status   ?? 'active',
                        payment_status: res.data.payment_status ?? 'unpaid',
                        payment_method: res.data.payment_method ?? null,
                    };
                    const badge = document.getElementById('badge-' + patientId);
                    if (badge) badge.innerHTML = payBadgeHtml(p);
                    if (payBtn) payBtn.outerHTML = payBtnHtml(p);
                } else {
                    loadPatients(currentPage);
                }
            })
            .catch(() => { showToast('Request failed.', 'error'); loadPatients(currentPage); });
    }

    // ════════════════════════════════════════════════════════════
    // VIEW PATIENT DETAIL MODAL
    // ════════════════════════════════════════════════════════════
    window.viewPatient = function (id) {
        document.getElementById('patientDetailContent').innerHTML =
            '<div class="text-center py-6"><span class="spinner"></span></div>';
        document.getElementById('adminPrintReceiptBtn').dataset.patientId = id;
        openModal('patientDetailModal');

        fetch(`${AJAX_URL}?${new URLSearchParams({ action: 'getPatientById', id })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    document.getElementById('patientDetailContent').innerHTML =
                        `<p style="color:var(--color-danger);padding:1rem;">${escHtml(res.message)}</p>`;
                    return;
                }
                document.getElementById('patientDetailContent').innerHTML = res.data.patient.receipt_html;
            })
            .catch(() => {
                document.getElementById('patientDetailContent').innerHTML =
                    '<p style="color:var(--color-danger);padding:1rem;">Failed to load patient data.</p>';
            });
    };

    document.getElementById('adminPrintReceiptBtn')?.addEventListener('click', function () {
        const id = this.dataset.patientId;
        if (id) openPrintWindow('receipt', id);
    });

    // ════════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════════
    window.confirmDelete = function (id, name) {
        showConfirmModal(
            'Delete Patient Record',
            `Permanently delete the record for <strong>${escHtml(name)}</strong>? This cannot be undone.`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deletePatient');
                fd.set('csrf_token', getCsrfToken());
                fd.set('id', id);
                ajaxRequest(AJAX_URL, 'POST', fd,
                    (_, msg) => { showToast(msg, 'success'); loadPatients(currentPage); }
                );
            }, 'danger'
        );
    };

    // ════════════════════════════════════════════════════════════
    // EDIT PATIENT MODAL
    // ════════════════════════════════════════════════════════════
    window.openEditPatient = function (id) {
        // Reset state
        document.getElementById('epLoading').style.display = 'block';
        document.getElementById('epForm').style.display    = 'none';
        document.getElementById('epSaveBtn').style.display = 'none';
        document.getElementById('epError').style.display   = 'none';
        document.getElementById('epTokenBadge').textContent = '';
        openModal('editPatientModal');

        fetch(`${AJAX_URL}?${new URLSearchParams({ action: 'getPatientForEdit', id })}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('epLoading').style.display = 'none';
                if (!res.success) {
                    document.getElementById('epError').style.display = 'block';
                    document.getElementById('epErrorText').textContent = res.message;
                    return;
                }
                populateEditForm(res.data.patient);
            })
            .catch(() => {
                document.getElementById('epLoading').style.display = 'none';
                document.getElementById('epError').style.display   = 'block';
                document.getElementById('epErrorText').textContent = 'Failed to load patient data.';
            });
    };

    function populateEditForm(p) {
        const prefix = window.APP_CONFIG.tokenPrefix;
        document.getElementById('epTokenBadge').textContent =
            prefix + '-' + String(p.token_number).padStart(3, '0');

        document.getElementById('epPatientId').value = p.id;
        document.getElementById('epDoctor').value    = p.doctor_id || '';
        document.getElementById('epName').value      = p.name || '';
        document.getElementById('epGender').value    = p.gender || '';
        document.getElementById('epAge').value       = p.age || '';
        document.getElementById('epPhone').value     = p.phone || '';
        document.getElementById('epAddress').value   = p.address || '';

        // Notes — strip [RETURNED] prefix from display
        const rawNotes = p.notes || '';
        const isReturned = rawNotes.startsWith('[RETURNED]');
        document.getElementById('epNotes').value = rawNotes.replace('[RETURNED]', '').trim();

        // Build datetime-local value: "YYYY-MM-DDTHH:mm"
        if (p.visit_date && p.visit_time) {
            const timePart = p.visit_time.substring(0, 5); // HH:mm
            document.getElementById('epDatetime').value = `${p.visit_date}T${timePart}`;
        }

        // Payment
        const isPaid = p.payment_status === 'paid';
        document.getElementById('epUnpaid').checked = !isPaid;
        document.getElementById('epPaid').checked   = isPaid;
        document.getElementById('epMethodWrap').style.display = isPaid ? 'block' : 'none';
        document.getElementById('epMethod').value   = p.payment_method || '';

        document.getElementById('epForm').style.display    = 'block';
        document.getElementById('epSaveBtn').style.display = 'inline-flex';

        // If returned, show a note in the badge area
        if (isReturned) {
            document.getElementById('epTokenBadge').title = 'This patient is marked as Returned';
        }
    }

    // Payment radio toggle for edit modal
    document.querySelectorAll('input[name="epPayStatus"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('epMethodWrap').style.display =
                (radio.value === 'paid' && radio.checked) ? 'block' : 'none';
        });
    });

    // Save
    document.getElementById('epSaveBtn').addEventListener('click', submitEditPatient);

    function showEpError(msg) {
        document.getElementById('epErrorText').textContent = msg;
        document.getElementById('epError').style.display = 'block';
        document.getElementById('epError').scrollIntoView({ behavior:'smooth', block:'nearest' });
    }
    function hideEpError() { document.getElementById('epError').style.display = 'none'; }

    function submitEditPatient() {
        hideEpError();
        const id         = document.getElementById('epPatientId').value;
        const doctorId   = document.getElementById('epDoctor').value;
        const name       = document.getElementById('epName').value.trim();
        const gender     = document.getElementById('epGender').value;
        const dt         = document.getElementById('epDatetime').value;
        const payStatus  = document.querySelector('input[name="epPayStatus"]:checked').value;
        const method     = document.getElementById('epMethod').value;

        if (!name)     { showEpError('Patient name is required.'); return; }
        if (!gender)   { showEpError('Please select a gender.'); return; }
        if (!dt)       { showEpError('Please select an appointment date and time.'); return; }
        if (!doctorId) { showEpError('Please select a doctor.'); return; }
        if (payStatus === 'paid' && !method) { showEpError('Please select a payment method.'); return; }

        const btn  = document.getElementById('epSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;"></span> Saving…';

        const fd = new FormData();
        fd.set('action',          'updatePatient');
        fd.set('csrf_token',      getCsrfToken());
        fd.set('id',              id);
        fd.set('doctor_id',       doctorId);
        fd.set('name',            name);
        fd.set('gender',          gender);
        fd.set('visit_datetime',  dt);
        fd.set('age',             document.getElementById('epAge').value.trim());
        fd.set('phone',           document.getElementById('epPhone').value.trim());
        fd.set('address',         document.getElementById('epAddress').value.trim());
        fd.set('notes',           document.getElementById('epNotes').value.trim());
        fd.set('payment_status',  payStatus);
        if (payStatus === 'paid') fd.set('payment_method', method);

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                if (!res.success) { showEpError(res.message); return; }
                showToast(res.message, 'success');
                closeModal('editPatientModal');
                loadPatients(currentPage);
            })
            .catch(() => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                showEpError('Network error. Please try again.');
            });
    }

    // ════════════════════════════════════════════════════════════
    // GENERATE TOKEN MODAL
    // ════════════════════════════════════════════════════════════
    let gt = {
        selectedDoctorId:   null,
        selectedDoctorName: '',
        selectedFee:        0,
        selectedFeeFmt:     '',
        nextToken:          '—',
        debounceTimer:      null,
    };

    // Open modal
    document.getElementById('openGenTokenBtn').addEventListener('click', () => {
        resetGenTokenModal();
        openModal('genTokenModal');
        loadGtDoctors();
    });

    // Set default datetime to now
    function setDefaultDatetime() {
        const now = new Date();
        // Round up to next 15-min slot
        now.setMinutes(Math.ceil(now.getMinutes() / 15) * 15, 0, 0);
        const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString().slice(0, 16);
        document.getElementById('gtDatetime').value = local;
        document.getElementById('gtDatetime').min   = new Date(Date.now() - 60*60000)
            .toISOString().slice(0, 16); // allow up to 1h in past
    }

    function resetGenTokenModal() {
        gt.selectedDoctorId   = null;
        gt.selectedDoctorName = '';
        gt.selectedFee        = 0;
        gt.selectedFeeFmt     = '';
        gt.nextToken          = '—';

        document.getElementById('gtDoctorGrid').innerHTML =
            '<div style="grid-column:1/-1;text-align:center;padding:1.5rem;"><span class="spinner"></span></div>';
        document.getElementById('gtName').value    = '';
        document.getElementById('gtGender').value  = '';
        document.getElementById('gtAge').value     = '';
        document.getElementById('gtPhone').value   = '';
        document.getElementById('gtAddress').value = '';
        document.getElementById('gtNotes').value   = '';
        document.getElementById('gtUnpaid').checked = true;
        document.getElementById('gtMethodWrap').style.display = 'none';
        document.getElementById('gtMethod').value  = '';
        document.getElementById('gtFeeRow').style.display    = 'none';
        document.getElementById('gtSubmitBtn').disabled      = true;
        document.getElementById('gtError').style.display     = 'none';
        setPreview('—', 'Select a doctor', '');
        setDefaultDatetime();
    }

    function setPreview(num, docLabel, dateLabel) {
        const inactive = !gt.selectedDoctorId;
        const preview  = document.getElementById('gtPreview');
        preview.className = 'gt-token-preview' + (inactive ? ' inactive' : '');
        document.getElementById('gtPreviewNum').textContent  = num;
        document.getElementById('gtPreviewDoc').textContent  = docLabel;
        document.getElementById('gtPreviewDate').textContent = dateLabel || 'No date selected';
    }

    function loadGtDoctors() {
        const dateInput = document.getElementById('gtDatetime').value;
        const visitDate = dateInput ? dateInput.split('T')[0] : new Date().toISOString().split('T')[0];

        fetch(`${AJAX_URL}?${new URLSearchParams({ action: 'getDoctors', visit_date: visitDate })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    document.getElementById('gtDoctorGrid').innerHTML =
                        `<p style="grid-column:1/-1;color:var(--color-danger);padding:1rem;">${escHtml(res.message)}</p>`;
                    return;
                }
                renderGtDoctors(res.data.doctors);
            })
            .catch(() => {
                document.getElementById('gtDoctorGrid').innerHTML =
                    '<p style="grid-column:1/-1;color:var(--color-danger);padding:1rem;">Failed to load doctors.</p>';
            });
    }

    function renderGtDoctors(doctors) {
        const grid = document.getElementById('gtDoctorGrid');
        if (!doctors.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:1.5rem;color:var(--color-text-muted);">No active doctors found.</div>';
            return;
        }
        grid.innerHTML = doctors.map(d => `
            <div class="gt-doc-card${d.is_full ? ' full' : ''}${gt.selectedDoctorId === d.id ? ' selected' : ''}"
                 data-doc-id="${d.id}"
                 onclick="${d.is_full ? '' : 'selectGtDoctor(' + d.id + ')'}">
                <div class="gt-check"><i class="fa-solid fa-check" style="font-size:8px;"></i></div>
                <div class="gt-doc-name">Dr. ${escHtml(d.name)}</div>
                <div class="gt-doc-spec">${escHtml(d.specialization)}</div>
                <div class="gt-doc-tok">${escHtml(d.token_display)}</div>
                <div class="gt-doc-fee">${escHtml(d.fee_fmt)}</div>
                ${d.is_full ? '<div style="font-size:10px;color:var(--color-danger);margin-top:3px;"><i class="fa-solid fa-ban"></i> Full today</div>' : ''}
            </div>`).join('');
    }

    window.selectGtDoctor = function (docId) {
        gt.selectedDoctorId = docId;

        // Update card selection state
        document.querySelectorAll('.gt-doc-card').forEach(c => c.classList.remove('selected'));
        const card = document.querySelector(`.gt-doc-card[data-doc-id="${docId}"]`);
        if (card) card.classList.add('selected');

        refreshGtToken();
    };

    function refreshGtToken() {
        if (!gt.selectedDoctorId) return;
        const dateInput = document.getElementById('gtDatetime').value;
        if (!dateInput) return;
        const visitDate = dateInput.split('T')[0];

        fetch(`${AJAX_URL}?${new URLSearchParams({ action:'getNextToken', doctor_id: gt.selectedDoctorId, visit_date: visitDate })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showToast(res.message, 'warning');
                    gt.selectedDoctorId = null;
                    document.querySelectorAll('.gt-doc-card').forEach(c => c.classList.remove('selected'));
                    document.getElementById('gtSubmitBtn').disabled = true;
                    return;
                }
                gt.nextToken          = res.data.token_display;
                gt.selectedDoctorName = 'Dr. ' + res.data.doctor.name;
                gt.selectedFee        = res.data.fee;
                gt.selectedFeeFmt     = res.data.fee_fmt;

                // Format date nicely
                const dt = new Date(res.data.visit_date + 'T00:00:00');
                const dateFmt = dt.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });

                setPreview(gt.nextToken, gt.selectedDoctorName, dateFmt);
                document.getElementById('gtFeeAmt').textContent = res.data.fee_fmt;
                document.getElementById('gtFeeRow').style.display = 'flex';
                document.getElementById('gtSubmitBtn').disabled   = false;
            })
            .catch(() => showToast('Failed to load next token.', 'error'));
    }

    // Reload doctors + token preview when date changes
    document.getElementById('gtDatetime').addEventListener('change', () => {
        clearTimeout(gt.debounceTimer);
        gt.debounceTimer = setTimeout(() => {
            loadGtDoctors();
            if (gt.selectedDoctorId) refreshGtToken();
        }, 400);
    });

    // Payment radio toggle
    document.querySelectorAll('input[name="gtPayStatus"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const isPaid = radio.value === 'paid' && radio.checked;
            document.getElementById('gtMethodWrap').style.display = isPaid ? 'block' : 'none';
        });
    });

    // Submit
    document.getElementById('gtSubmitBtn').addEventListener('click', submitGenToken);

    function showGtError(msg) {
        document.getElementById('gtErrorText').textContent = msg;
        document.getElementById('gtError').style.display = 'block';
    }
    function hideGtError() {
        document.getElementById('gtError').style.display = 'none';
    }

    function submitGenToken() {
        hideGtError();

        if (!gt.selectedDoctorId) { showGtError('Please select a doctor.'); return; }
        const name     = document.getElementById('gtName').value.trim();
        const gender   = document.getElementById('gtGender').value;
        const dt       = document.getElementById('gtDatetime').value;
        const payStatus= document.querySelector('input[name="gtPayStatus"]:checked').value;
        const method   = document.getElementById('gtMethod').value;

        if (!name)    { showGtError('Patient name is required.'); return; }
        if (!gender)  { showGtError('Please select a gender.'); return; }
        if (!dt)      { showGtError('Please select an appointment date and time.'); return; }
        if (payStatus === 'paid' && !method) { showGtError('Please select a payment method.'); return; }

        const btn = document.getElementById('gtSubmitBtn');
        btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;"></span> Generating…';

        const fd = new FormData();
        fd.set('action',          'generateToken');
        fd.set('csrf_token',      getCsrfToken());
        fd.set('doctor_id',       gt.selectedDoctorId);
        fd.set('name',            name);
        fd.set('gender',          gender);
        fd.set('visit_datetime',  dt);
        fd.set('age',             document.getElementById('gtAge').value.trim());
        fd.set('phone',           document.getElementById('gtPhone').value.trim());
        fd.set('address',         document.getElementById('gtAddress').value.trim());
        fd.set('notes',           document.getElementById('gtNotes').value.trim());
        fd.set('payment_status',  payStatus);
        if (payStatus === 'paid') fd.set('payment_method', method);

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                if (!res.success) { showGtError(res.message); return; }

                // Success
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Token Generated!';
                btn.style.background = 'var(--color-accent)';
                showToast(res.message, 'success');

                if (window.APP_CONFIG.soundEnabled) {
                    try { playSound('cash-register-kaching.mp3'); } catch(e) {}
                }

                // Ask to print receipt
                if (confirm('Token generated! Print the receipt now?')) {
                    openPrintWindow('receipt', res.data.patient_id);
                }

                setTimeout(() => {
                    closeModal('genTokenModal');
                    btn.innerHTML = orig;
                    btn.style.background = '';
                    loadPatients(1);
                }, 800);
            })
            .catch(() => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                showGtError('Network error. Please try again.');
            });
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════════
    const debLoad = debounce(() => loadPatients(1), 300);
    document.getElementById('searchInput').addEventListener('input', debLoad);
    document.getElementById('dateFrom').addEventListener('change',   () => loadPatients(1));
    document.getElementById('dateTo').addEventListener('change',     () => loadPatients(1));
    document.getElementById('doctorFilter').addEventListener('change', () => loadPatients(1));
    document.getElementById('statusFilter').addEventListener('change', () => loadPatients(1));
    document.getElementById('perPageSelect').addEventListener('change', () => loadPatients(1));

    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value   = '';
        document.getElementById('dateFrom').value      = toYMD(ago30);
        document.getElementById('dateTo').value        = toYMD(today);
        document.getElementById('doctorFilter').value  = '';
        document.getElementById('statusFilter').value  = '';
        loadPatients(1);
    });

    // Initial load
    loadPatients(1);

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>