<?php

/**
 * receptionist/patients.php — Enhanced Today's Patients Module
 *
 * Features (receptionist-scoped):
 *  - Statistics: total, paid revenue, pending, returned — scoped to today + filters
 *  - Generate Token modal (date/time picker, future appointments)
 *  - View receipt modal + print (58mm thermal)
 *  - Edit patient details (own patients only, no date restriction today)
 *  - Payment action icon + dropdown (cash/card/insurance/online/unpaid/returned)
 *  - Mark as Returned (no delete — receptionist cannot delete)
 *  - Table pagination + rows-per-page selector
 *  - Fully responsive table with horizontal scroll on small screens
 *  - Sound on payment marked paid
 *  - Karachi (PKT) time on receipts
 */

$pageTitle  = "Today's Patients";
$activePage = 'patients';
require_once __DIR__ . '/header.php';

$activeDoctors = Database::fetchAll(
    "SELECT id, name, specialization FROM doctors WHERE is_active = 1 ORDER BY name"
);
?>

<!-- ── Page-level CSS ──────────────────────────────────────────── -->
<style>
    /* ── Badges ── */
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

    /* ── Stats grid ── */
    .stats-grid-r {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .875rem;
        margin-bottom: 1.25rem;
    }

    @media(max-width:900px) {
        .stats-grid-r {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width:480px) {
        .stats-grid-r {
            grid-template-columns: 1fr 1fr;
            gap: .625rem;
        }
    }

    .stat-card-r {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: .875rem 1rem;
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        position: relative;
        overflow: hidden;
        transition: box-shadow .15s;
    }

    .stat-card-r::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }

    .stat-card-r.sc-blue::after {
        background: var(--color-primary);
    }

    .stat-card-r.sc-green::after {
        background: #059669;
    }

    .stat-card-r.sc-amber::after {
        background: #d97706;
    }

    .stat-card-r.sc-slate::after {
        background: #64748b;
    }

    .stat-card-r:hover {
        box-shadow: var(--shadow-md);
    }

    .stat-icon-r {
        width: 38px;
        height: 38px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .sc-blue .stat-icon-r {
        background: var(--color-primary-lt);
        color: var(--color-primary);
    }

    .sc-green .stat-icon-r {
        background: #d1fae5;
        color: #059669;
    }

    .sc-amber .stat-icon-r {
        background: #fef3c7;
        color: #d97706;
    }

    .sc-slate .stat-icon-r {
        background: #f1f5f9;
        color: #64748b;
    }

    .stat-value-r {
        font-family: 'JetBrains Mono', monospace;
        font-size: 1.25rem;
        font-weight: 800;
        line-height: 1.1;
        color: var(--color-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .stat-label-r {
        font-size: 11px;
        color: var(--color-text-muted);
        margin-top: 2px;
        font-weight: 500;
    }

    .stat-sub-r {
        font-size: 11px;
        font-weight: 600;
        margin-top: 5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 20px;
    }

    .sc-blue .stat-sub-r {
        background: var(--color-primary-lt);
        color: var(--color-primary);
    }

    .sc-green .stat-sub-r {
        background: #d1fae5;
        color: #059669;
    }

    .sc-amber .stat-sub-r {
        background: #fef3c7;
        color: #d97706;
    }

    .sc-slate .stat-sub-r {
        background: #f1f5f9;
        color: #64748b;
    }

    .stat-skeleton-r {
        height: 16px;
        border-radius: 6px;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-surface) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: shimmerR 1.4s infinite;
    }

    @keyframes shimmerR {
        to {
            background-position: -200% 0;
        }
    }

    /* ── Payment Dropdown ── */
    #rPayDropdown {
        position: absolute;
        z-index: 9999;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-modal);
        min-width: 204px;
        padding: 6px 0;
        display: none;
        animation: rDropIn .15s ease;
    }

    @keyframes rDropIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .r-drop-section {
        padding: 4px 10px 2px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--color-text-faint);
    }

    .r-drop-item {
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

    .r-drop-item:hover {
        background: var(--color-surface);
    }

    .r-drop-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }

    .r-drop-tooltip {
        position: absolute;
        right: calc(100% + 8px);
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

    .r-drop-tooltip::before {
        content: '';
        position: absolute;
        left: -4px;
        top: 50%;
        transform: translateY(-50%);
        border: 4px solid transparent;
        border-right-color: #1e293b;
        border-left: none;
    }

    .r-drop-item:hover .r-drop-tooltip {
        opacity: 1;
    }

    .r-drop-divider {
        border: none;
        border-top: 1px solid var(--color-border);
        margin: 4px 0;
    }

    /* ── Generate Token Modal ── */
    .gt-modal-body-r {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        max-height: 72vh;
    }

    @media(max-width:700px) {
        .gt-modal-body-r {
            grid-template-columns: 1fr;
            max-height: none;
        }
    }

    .gt-doctor-grid-r {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .625rem;
        overflow-y: auto;
        max-height: 58vh;
        padding-right: 4px;
    }

    @media(max-width:500px) {
        .gt-doctor-grid-r {
            grid-template-columns: 1fr;
        }
    }

    .gt-doc-card-r {
        background: var(--color-card);
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: .75rem;
        cursor: pointer;
        transition: all .14s;
        position: relative;
    }

    .gt-doc-card-r:hover {
        border-color: var(--color-primary);
        background: var(--color-primary-lt);
    }

    .gt-doc-card-r.selected {
        border-color: var(--color-primary);
        background: var(--color-primary-lt);
        box-shadow: 0 0 0 3px rgba(3, 105, 161, .1);
    }

    .gt-doc-card-r.full {
        opacity: .45;
        cursor: not-allowed;
        pointer-events: none;
    }

    .gt-doc-name-r {
        font-size: 12px;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 2px;
    }

    .gt-doc-spec-r {
        font-size: 11px;
        color: var(--color-text-muted);
    }

    .gt-doc-fee-r {
        font-size: 11px;
        font-weight: 600;
        color: var(--color-accent);
        margin-top: 5px;
    }

    .gt-doc-tok-r {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        font-weight: 800;
        color: var(--color-primary);
        margin-top: 2px;
    }

    .gt-check-r {
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

    .gt-doc-card-r.selected .gt-check-r {
        display: flex;
    }

    .gt-token-preview-r {
        background: linear-gradient(135deg, #0c4a6e, #0369a1);
        border-radius: var(--radius-lg);
        padding: 1rem 1.25rem;
        text-align: center;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }

    .gt-token-preview-r.inactive {
        background: linear-gradient(135deg, #475569, #64748b);
    }

    .gt-token-preview-r::before {
        content: '';
        position: absolute;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .06);
        top: -30px;
        right: -30px;
    }

    .gt-tp-label-r {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: rgba(255, 255, 255, .65);
        margin-bottom: 4px;
    }

    .gt-tp-num-r {
        font-family: 'JetBrains Mono', monospace;
        font-size: 1.875rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: 3px;
        line-height: 1;
    }

    .gt-tp-doc-r {
        font-size: 11px;
        color: rgba(255, 255, 255, .8);
        margin-top: 6px;
    }

    .gt-tp-date-r {
        font-size: 10px;
        color: rgba(255, 255, 255, .55);
        margin-top: 3px;
    }

    .gt-form-grid-2-r {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .75rem;
    }

    @media(max-width:460px) {
        .gt-form-grid-2-r {
            grid-template-columns: 1fr;
        }
    }

    .gt-section-label-r {
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

    .gt-section-label-r i {
        color: var(--color-primary);
        font-size: 10px;
    }

    .gt-section-label-r:first-child {
        margin-top: 0;
    }

    .gt-right-panel-r {
        overflow-y: auto;
        max-height: 58vh;
        padding-right: 2px;
    }

    @media(max-width:700px) {
        .gt-right-panel-r {
            max-height: none;
        }
    }

    /* ── Table responsive ── */
    .patients-table-wrap-r {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #rPatientsTable {
        width: 100%;
        border-collapse: collapse;
    }

    @media(max-width:960px) {
        .col-hide-md-r {
            display: none !important;
        }
    }

    @media(max-width:640px) {
        .col-hide-sm-r {
            display: none !important;
        }
    }

    /* ── Pagination ── */
    .pagination-row-r {
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

    .per-page-wrap-r {
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .per-page-wrap-r select {
        font-size: 12px;
        padding: 3px 8px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-surface);
        color: var(--color-text);
        cursor: pointer;
    }

    .page-btns-wrap-r {
        display: flex;
        align-items: center;
        gap: .25rem;
        flex-wrap: wrap;
    }

    .r-page-btn {
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

    .r-page-btn:hover:not(:disabled) {
        background: var(--color-primary-lt);
        border-color: var(--color-primary);
        color: var(--color-primary);
    }

    .r-page-btn.active {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: #fff;
        font-weight: 700;
    }

    .r-page-btn:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    .r-page-btn.dots {
        cursor: default;
        border-color: transparent;
        background: transparent;
    }

    /* ── Edit modal ── */
    .ep-form-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .75rem;
    }

    @media(max-width:480px) {
        .ep-form-grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ── Page Header ────────────────────────────────────────────────── -->
<div class="page-header" style="flex-wrap:wrap;gap:.75rem;">
    <div class="flex items-center gap-3 flex-wrap">
        <h2 class="page-title">
            <i class="fa-solid fa-hospital-user" style="color:var(--color-accent);"></i>
            Today's Patients
        </h2>
        <span class="text-xs px-2 py-0.5 rounded-full font-bold"
            style="background:var(--color-accent-lt);color:var(--color-accent);"
            id="rTotalCount">Loading…</span>
    </div>
    <button class="btn btn-primary btn-sm" id="rOpenGenTokenBtn">
        <i class="fa-solid fa-ticket"></i> New Token
    </button>
</div>

<!-- ── Statistics Cards ──────────────────────────────────────────── -->
<div class="stats-grid-r" id="rStatsGrid">
    <div class="stat-card-r sc-blue">
        <div class="stat-icon-r"><i class="fa-solid fa-users"></i></div>
        <div class="stat-body" style="flex:1;min-width:0;">
            <div class="stat-value-r" id="rStatTotal">
                <div class="stat-skeleton-r" style="width:40px;"></div>
            </div>
            <div class="stat-label-r">Total Today</div>
        </div>
    </div>
    <div class="stat-card-r sc-green">
        <div class="stat-icon-r"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-body" style="flex:1;min-width:0;">
            <div class="stat-value-r" id="rStatRevenue">
                <div class="stat-skeleton-r" style="width:70px;"></div>
            </div>
            <div class="stat-label-r">Revenue Collected</div>
            <div class="stat-sub-r sc-green" id="rStatPaidSub" style="display:none;">
                <i class="fa-solid fa-ticket" style="font-size:9px;"></i><span></span>
            </div>
        </div>
    </div>
    <div class="stat-card-r sc-amber">
        <div class="stat-icon-r"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-body" style="flex:1;min-width:0;">
            <div class="stat-value-r" id="rStatPending">
                <div class="stat-skeleton-r" style="width:70px;"></div>
            </div>
            <div class="stat-label-r">Pending Amount</div>
            <div class="stat-sub-r sc-amber" id="rStatUnpaidSub" style="display:none;">
                <i class="fa-solid fa-ticket" style="font-size:9px;"></i><span></span>
            </div>
        </div>
    </div>
    <div class="stat-card-r sc-slate">
        <div class="stat-icon-r"><i class="fa-solid fa-person-circle-xmark"></i></div>
        <div class="stat-body" style="flex:1;min-width:0;">
            <div class="stat-value-r" id="rStatReturned">
                <div class="stat-skeleton-r" style="width:30px;"></div>
            </div>
            <div class="stat-label-r">Returned</div>
            <div class="stat-sub-r sc-slate" id="rStatReturnedSub" style="display:none;">
                <i class="fa-solid fa-arrow-rotate-left" style="font-size:9px;"></i><span></span>
            </div>
        </div>
    </div>
</div>

<!-- ── Filters ────────────────────────────────────────────────────── -->
<div class="card mb-4" style="padding:.875rem 1rem;">
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">

        <div style="flex:1;min-width:160px;max-width:240px;">
            <label class="form-label">Search</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass"
                    style="position:absolute;left:11px;top:50%;transform:translateY(-50%);
                          color:var(--color-text-faint);font-size:12px;"></i>
                <input type="text" id="rSearchInput" class="form-input"
                    placeholder="Name, phone…" style="padding-left:33px;">
            </div>
        </div>

        <div style="min-width:155px;">
            <label class="form-label">Doctor</label>
            <select id="rDoctorFilter" class="form-select">
                <option value="">All Doctors</option>
                <?php foreach ($activeDoctors as $doc): ?>
                    <option value="<?= (int)$doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width:130px;">
            <label class="form-label">Status</label>
            <select id="rStatusFilter" class="form-select">
                <option value="">All</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="returned">Returned</option>
            </select>
        </div>

        <button class="btn btn-ghost btn-sm" id="rClearFiltersBtn" style="height:38px;">
            <i class="fa-solid fa-xmark"></i> Clear
        </button>
    </div>
</div>

<!-- ── Patients Table ─────────────────────────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;">
    <div class="patients-table-wrap-r">
        <table class="data-table" id="rPatientsTable">
            <thead>
                <tr>
                    <th style="width:86px;">Token</th>
                    <th>Patient</th>
                    <th class="col-hide-md-r">Doctor</th>
                    <th class="col-hide-sm-r" style="width:70px;">Age/Sex</th>
                    <th class="col-hide-md-r" style="width:72px;">Time</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:88px;" class="col-hide-sm-r">Amount</th>
                    <th style="width:65px;" class="col-hide-md-r">By</th>
                    <th style="width:108px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="rPatientsBody">
                <tr>
                    <td colspan="9" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-row-r" id="rPaginationBar">
        <div class="per-page-wrap-r">
            <span>Rows:</span>
            <select id="rPerPage">
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
            </select>
            <span id="rPageInfo" style="color:var(--color-text-muted);">—</span>
        </div>
        <div class="page-btns-wrap-r" id="rPageBtns"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     PAYMENT DROPDOWN
     ═══════════════════════════════════════════════════════════ -->
<div id="rPayDropdown" role="menu">
    <div class="r-drop-section">Mark as Paid</div>
    <div class="r-drop-item" data-action="paid" data-method="cash">
        <span class="r-drop-icon" style="background:#d1fae5;color:#059669;"><i class="fa-solid fa-money-bill-1"></i></span>
        Cash<span class="r-drop-tooltip">Mark Paid · Cash</span>
    </div>
    <div class="r-drop-item" data-action="paid" data-method="card">
        <span class="r-drop-icon" style="background:#dbeafe;color:#2563eb;"><i class="fa-solid fa-credit-card"></i></span>
        Card<span class="r-drop-tooltip">Mark Paid · Card</span>
    </div>
    <div class="r-drop-item" data-action="paid" data-method="insurance">
        <span class="r-drop-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fa-solid fa-shield-halved"></i></span>
        Insurance<span class="r-drop-tooltip">Mark Paid · Insurance</span>
    </div>
    <div class="r-drop-item" data-action="paid" data-method="online">
        <span class="r-drop-icon" style="background:#cffafe;color:#0891b2;"><i class="fa-solid fa-mobile-screen-button"></i></span>
        Online<span class="r-drop-tooltip">Mark Paid · Online Transfer</span>
    </div>
    <hr class="r-drop-divider">
    <div class="r-drop-section">Other</div>
    <div class="r-drop-item" data-action="unpaid" data-method="">
        <span class="r-drop-icon" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-clock-rotate-left"></i></span>
        Mark Unpaid<span class="r-drop-tooltip">Revert to Unpaid</span>
    </div>
    <hr class="r-drop-divider">
    <div class="r-drop-item" data-action="returned" data-method="">
        <span class="r-drop-icon" style="background:#f1f5f9;color:#64748b;"><i class="fa-solid fa-person-circle-xmark"></i></span>
        Returned<span class="r-drop-tooltip">No Consultation — Patient Returned</span>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     VIEW RECEIPT MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rReceiptModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-receipt" style="color:var(--color-primary);"></i> Receipt
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:.75rem;">
            <div class="receipt-preview-wrapper" id="rReceiptContent">
                <div class="text-center py-6"><span class="spinner"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="rPrintReceiptBtn" data-patient-id="">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     EDIT PATIENT MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rEditModal">
    <div class="modal-box" style="max-width:580px;width:96vw;">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-primary);"></i>
                Edit Patient
                <span id="rEditTokenBadge" style="
                    margin-left:.5rem; font-family:'JetBrains Mono',monospace;
                    font-size:12px; font-weight:700; color:var(--color-primary);
                    background:var(--color-primary-lt); border:1px solid var(--color-primary);
                    border-radius:6px; padding:2px 8px;"></span>
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:1rem;max-height:72vh;overflow-y:auto;">
            <div id="rEditLoading" class="text-center py-8"><span class="spinner"></span></div>
            <div id="rEditError" class="mb-3" style="display:none;">
                <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:10px;
                            padding:.6rem .875rem;font-size:13px;color:#991b1b;
                            display:flex;align-items:center;gap:.5rem;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span id="rEditErrorText"></span>
                </div>
            </div>
            <div id="rEditForm" style="display:none;">
                <input type="hidden" id="rEpId">

                <div class="gt-section-label-r">
                    <i class="fa-solid fa-user-doctor"></i> Doctor &amp; Appointment
                </div>
                <div class="ep-form-grid-2 mb-3">
                    <div>
                        <label class="form-label">Doctor <span style="color:var(--color-danger);">*</span></label>
                        <select id="rEpDoctor" class="form-select">
                            <?php foreach ($activeDoctors as $doc): ?>
                                <option value="<?= (int)$doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date &amp; Time <span style="color:var(--color-danger);">*</span></label>
                        <input type="datetime-local" id="rEpDatetime" class="form-input" style="width:100%;">
                    </div>
                </div>

                <div class="gt-section-label-r">
                    <i class="fa-solid fa-user"></i> Patient Information
                </div>
                <div class="mb-2">
                    <label class="form-label">Full Name <span style="color:var(--color-danger);">*</span></label>
                    <input type="text" id="rEpName" class="form-input">
                </div>
                <div class="ep-form-grid-2 mb-2">
                    <div>
                        <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                        <select id="rEpGender" class="form-select">
                            <option value="">Select…</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Age</label>
                        <input type="number" id="rEpAge" class="form-input" min="0" max="150">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Phone</label>
                    <input type="tel" id="rEpPhone" class="form-input" placeholder="03xx-xxxxxxx">
                </div>
                <div class="mb-2">
                    <label class="form-label">Address</label>
                    <input type="text" id="rEpAddress" class="form-input">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea id="rEpNotes" class="form-input" rows="2" style="resize:vertical;"></textarea>
                </div>

                <div class="gt-section-label-r">
                    <i class="fa-solid fa-receipt"></i> Payment
                </div>
                <div class="mb-2">
                    <div style="display:flex;gap:1.25rem;">
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                            <input type="radio" name="rEpPayStatus" value="unpaid" id="rEpUnpaid"
                                style="accent-color:var(--color-warning);"> Unpaid
                        </label>
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                            <input type="radio" name="rEpPayStatus" value="paid" id="rEpPaid"
                                style="accent-color:var(--color-accent);"> Paid
                        </label>
                    </div>
                </div>
                <div id="rEpMethodWrap" class="mb-1" style="display:none;">
                    <label class="form-label">Payment Method</label>
                    <select id="rEpMethod" class="form-select">
                        <option value="">Select method…</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="insurance">Insurance</option>
                        <option value="online">Online</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="rEditSaveBtn" style="display:none;">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     GENERATE TOKEN MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rGenTokenModal">
    <div class="modal-box" style="max-width:800px;width:96vw;">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-ticket" style="color:var(--color-accent);"></i>
                Generate New Token
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:1rem;">
            <div id="rGtError" class="mb-3" style="display:none;">
                <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:10px;
                            padding:.6rem .875rem;font-size:13px;color:#991b1b;
                            display:flex;align-items:center;gap:.5rem;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span id="rGtErrorText"></span>
                </div>
            </div>

            <div class="gt-modal-body-r">
                <!-- LEFT: Doctor cards -->
                <div>
                    <div class="gt-section-label-r">
                        <i class="fa-solid fa-user-doctor"></i> Select Doctor
                    </div>
                    <div class="gt-doctor-grid-r" id="rGtDoctorGrid">
                        <div style="grid-column:1/-1;text-align:center;padding:1.5rem;">
                            <span class="spinner"></span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Preview + form -->
                <div class="gt-right-panel-r">
                    <div class="gt-token-preview-r inactive" id="rGtPreview">
                        <div class="gt-tp-label-r">Next Token</div>
                        <div class="gt-tp-num-r" id="rGtPreviewNum">—</div>
                        <div class="gt-tp-doc-r" id="rGtPreviewDoc">Select a doctor</div>
                        <div class="gt-tp-date-r" id="rGtPreviewDate">No date selected</div>
                    </div>

                    <div class="gt-section-label-r">
                        <i class="fa-solid fa-calendar-days"></i> Appointment Date &amp; Time
                    </div>
                    <div class="mb-3">
                        <input type="datetime-local" id="rGtDatetime" class="form-input" style="width:100%;">
                        <p class="text-xs mt-1" style="color:var(--color-text-muted);">
                            <i class="fa-solid fa-circle-info" style="font-size:10px;"></i>
                            You can schedule future appointments.
                        </p>
                    </div>

                    <div class="gt-section-label-r">
                        <i class="fa-solid fa-user"></i> Patient Information
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Full Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="rGtName" class="form-input" placeholder="Patient's full name">
                    </div>
                    <div class="gt-form-grid-2-r mb-2">
                        <div>
                            <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                            <select id="rGtGender" class="form-select">
                                <option value="">Select…</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Age</label>
                            <input type="number" id="rGtAge" class="form-input" placeholder="Years" min="0" max="150">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Phone</label>
                        <input type="tel" id="rGtPhone" class="form-input" placeholder="03xx-xxxxxxx">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Address</label>
                        <input type="text" id="rGtAddress" class="form-input" placeholder="City / area">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="rGtNotes" class="form-input" rows="2" style="resize:vertical;"></textarea>
                    </div>

                    <div class="gt-section-label-r">
                        <i class="fa-solid fa-receipt"></i> Payment
                    </div>
                    <div class="mb-2">
                        <div style="display:flex;gap:1rem;">
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                                <input type="radio" name="rGtPayStatus" value="unpaid" id="rGtUnpaid"
                                    checked style="accent-color:var(--color-warning);"> Unpaid
                            </label>
                            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:13px;">
                                <input type="radio" name="rGtPayStatus" value="paid" id="rGtPaid"
                                    style="accent-color:var(--color-accent);"> Paid
                            </label>
                        </div>
                    </div>
                    <div id="rGtMethodWrap" class="mb-3" style="display:none;">
                        <label class="form-label">Payment Method</label>
                        <select id="rGtMethod" class="form-select">
                            <option value="">Select method…</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="online">Online</option>
                        </select>
                    </div>

                    <div id="rGtFeeRow" style="display:none;background:var(--color-surface);
                         border:1px solid var(--color-border);border-radius:10px;
                         padding:.5rem .875rem;display:flex;justify-content:space-between;
                         align-items:center;margin-bottom:.5rem;font-size:13px;">
                        <span style="color:var(--color-text-muted);">
                            <i class="fa-solid fa-coins" style="color:var(--color-accent);margin-right:4px;"></i>
                            Consultation Fee
                        </span>
                        <span id="rGtFeeAmt" class="font-bold"
                            style="color:var(--color-accent);font-family:'JetBrains Mono',monospace;"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="rGtSubmitBtn" disabled>
                <i class="fa-solid fa-ticket"></i> Generate Token
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/receptionist/tokens.php`;
    let currentPage = 1;
    let allPatients = []; // full list for client-side pagination+search

    function escHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ════════════════════════════════════════════════════════════
    // LOAD & RENDER
    // ════════════════════════════════════════════════════════════
    function loadPatients() {
        const doctorId = document.getElementById('rDoctorFilter').value;
        const status   = document.getElementById('rStatusFilter').value;
        const params   = new URLSearchParams({
            action: 'getTodayPatients',
            doctor_id: doctorId,
            status_filter: status,
        });

        ['rStatTotal','rStatRevenue','rStatPending','rStatReturned'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<div class="stat-skeleton-r" style="width:55px;margin-top:2px;"></div>';
        });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                allPatients = res.data.patients;
                renderStats(res.data.stats);
                applySearchAndRender(1);
            })
            .catch(() => showToast('Failed to load patients.', 'error'));
    }

    function renderStats(s) {
        if (!s) return;
        document.getElementById('rStatTotal').textContent   = s.total;
        document.getElementById('rStatRevenue').textContent = s.revenue;
        document.getElementById('rStatPending').textContent = s.pending;
        document.getElementById('rStatReturned').textContent= s.returned;
        document.getElementById('rTotalCount').textContent  = s.total + ' patient(s)';

        const paidSub = document.getElementById('rStatPaidSub');
        paidSub.style.display = 'inline-flex';
        paidSub.querySelector('span').textContent = s.paid + ' paid';

        const unpaidSub = document.getElementById('rStatUnpaidSub');
        unpaidSub.style.display = 'inline-flex';
        unpaidSub.querySelector('span').textContent = s.unpaid + ' unpaid';

        const retSub = document.getElementById('rStatReturnedSub');
        retSub.style.display = s.returned > 0 ? 'inline-flex' : 'none';
        retSub.querySelector('span').textContent = 'no consultation';
    }

    function applySearchAndRender(page) {
        currentPage = page;
        const search = document.getElementById('rSearchInput').value.trim().toLowerCase();
        const list   = search
            ? allPatients.filter(p =>
                p.name.toLowerCase().includes(search) ||
                (p.phone || '').includes(search) ||
                (p.doctor_name || '').toLowerCase().includes(search))
            : allPatients;

        const perPage = parseInt(document.getElementById('rPerPage').value, 10) || 50;
        const total   = list.length;
        const pages   = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;
        const slice   = list.slice((page - 1) * perPage, page * perPage);

        renderTable(slice, total, page, pages, perPage);
    }

    // ── Badge HTML ─────────────────────────────────────────────
    function payBadgeHtml(p) {
        if (p.visit_status === 'returned') {
            return `<span class="badge badge-returned" style="font-size:11px;">
                        <i class="fa-solid fa-person-circle-xmark" style="font-size:10px;margin-right:3px;"></i>Returned
                    </span>`;
        }
        if (p.payment_status === 'paid') {
            const m = p.payment_method ? ' · ' + ucFirst(p.payment_method) : '';
            return `<span class="badge badge-paid" style="font-size:11px;">
                        <i class="fa-solid fa-circle-check" style="font-size:10px;margin-right:3px;"></i>Paid${escHtml(m)}
                    </span>`;
        }
        return `<span class="badge badge-unpaid" style="font-size:11px;">
                    <i class="fa-solid fa-hourglass-half" style="font-size:10px;margin-right:3px;"></i>Unpaid
                </span>`;
    }

    function payBtnHtml(p) {
        let icon, bg, color, title;
        if (p.visit_status === 'returned') {
            icon = 'fa-solid fa-person-circle-xmark'; bg = '#f1f5f9'; color = '#64748b'; title = 'Returned — Update';
        } else if (p.payment_status === 'paid') {
            icon = 'fa-solid fa-circle-check'; bg = '#d1fae5'; color = '#059669'; title = 'Paid — Change Method';
        } else {
            icon = 'fa-solid fa-hourglass-half'; bg = '#fef3c7'; color = '#d97706'; title = 'Unpaid — Mark as Paid';
        }
        // Only own patients can update payment
        if (!p.is_mine) return ''; // locked
        return `<button class="btn btn-icon btn-sm r-pay-trigger"
                        style="background:${bg};color:${color};"
                        title="${title}"
                        onclick="rOpenPayDropdown(${p.id}, this)">
                    <i class="${icon}" style="font-size:12px;"></i>
                </button>`;
    }

    function renderTable(list, total, page, pages, perPage) {
        const tbody  = document.getElementById('rPatientsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="table-empty">
                <div class="table-empty-icon">
                    <i class="fa-solid fa-hospital-user" style="font-size:2rem;color:var(--color-text-faint);"></i>
                </div>
                No patients found for today.</td></tr>`;
        } else {
            tbody.innerHTML = list.map(p => `
                <tr class="animate-fade-in" id="rrow-${p.id}"
                    data-vstatus="${escHtml(p.visit_status)}"
                    data-pstatus="${escHtml(p.payment_status)}"
                    data-method="${escHtml(p.payment_method ?? '')}"
                    data-mine="${p.is_mine ? '1' : '0'}">
                    <td>
                        <span class="font-mono-nums font-bold"
                              style="font-size:11px;color:var(--color-primary);">
                            ${escHtml(prefix)}-${String(p.token_number).padStart(3,'0')}
                        </span>
                    </td>
                    <td>
                        <div class="font-medium" style="font-size:12px;">${escHtml(p.name)}</div>
                        <div style="font-size:11px;color:var(--color-text-muted);">
                            ${p.phone ? escHtml(p.phone) : '—'}
                        </div>
                    </td>
                    <td class="col-hide-md-r">
                        <div style="font-size:12px;font-weight:600;">${escHtml(p.doctor_name)}</div>
                        <div style="font-size:11px;color:var(--color-text-muted);">${escHtml(p.specialization)}</div>
                    </td>
                    <td class="col-hide-sm-r" style="font-size:12px;">
                        ${p.age ? p.age + 'y' : '—'} / ${ucFirst(p.gender || '').charAt(0)}
                    </td>
                    <td class="col-hide-md-r" style="font-size:12px;color:var(--color-text-muted);">
                        ${escHtml(p.visit_time_fmt)}
                    </td>
                    <td id="rbadge-${p.id}">${payBadgeHtml(p)}</td>
                    <td class="col-hide-sm-r"
                        style="font-size:12px;font-weight:700;color:var(--color-accent);">
                        ${escHtml(p.amount_fmt)}
                    </td>
                    <td class="col-hide-md-r" style="font-size:11px;color:var(--color-text-muted);">
                        ${escHtml(p.created_by_name || '—')}
                        ${p.is_mine ? '<span style="font-size:9px;background:var(--color-accent-lt);color:var(--color-accent);border-radius:4px;padding:1px 5px;margin-left:2px;">You</span>' : ''}
                    </td>
                    <td>
                        <div class="table-actions justify-end" style="gap:.25rem;">
                            <!-- View Receipt -->
                            <button class="btn btn-ghost btn-icon btn-sm"
                                    title="View Receipt"
                                    onclick="rViewReceipt(${p.id})">
                                <i class="fa-solid fa-eye" style="font-size:12px;"></i>
                            </button>
                            <!-- Print -->
                            <button class="btn btn-ghost btn-icon btn-sm"
                                    title="Print Receipt"
                                    onclick="openPrintWindow('receipt',${p.id})">
                                <i class="fa-solid fa-print" style="font-size:12px;"></i>
                            </button>
                            ${p.is_mine ? `
                            <!-- Edit -->
                            <button class="btn btn-icon btn-sm"
                                    style="background:var(--color-primary-lt);color:var(--color-primary);"
                                    title="Edit Patient"
                                    onclick="rOpenEdit(${p.id})">
                                <i class="fa-solid fa-pen-to-square" style="font-size:12px;"></i>
                            </button>
                            <!-- Payment -->
                            ${payBtnHtml(p)}
                            ` : `
                            <span style="font-size:10px;color:var(--color-text-faint);padding:0 2px;"
                                  title="Only your own patients can be edited">—</span>`}
                        </div>
                    </td>
                </tr>`).join('');
        }

        // Pagination
        const start = total ? (page - 1) * perPage + 1 : 0;
        const end   = Math.min(page * perPage, total);
        document.getElementById('rPageInfo').textContent =
            total ? `${start}–${end} of ${total}` : 'No results';

        const btns = document.getElementById('rPageBtns');
        btns.innerHTML = '';
        if (pages <= 1) return;

        function makeBtn(label, pg, isActive, isDots, disabled) {
            const b = document.createElement('button');
            b.className = 'r-page-btn' + (isActive ? ' active' : '') + (isDots ? ' dots' : '');
            b.innerHTML = label; b.disabled = disabled || isDots;
            if (!isDots && !disabled) b.onclick = () => applySearchAndRender(pg);
            return b;
        }
        btns.appendChild(makeBtn('<i class="fa-solid fa-chevron-left"></i>', page-1, false, false, page<=1));
        if (page > 3) {
            btns.appendChild(makeBtn('1', 1, false, false, false));
            if (page > 4) btns.appendChild(makeBtn('…', null, false, true, true));
        }
        for (let pg = Math.max(1, page-2); pg <= Math.min(pages, page+2); pg++) {
            btns.appendChild(makeBtn(pg, pg, pg===page, false, false));
        }
        if (page < pages - 2) {
            if (page < pages - 3) btns.appendChild(makeBtn('…', null, false, true, true));
            btns.appendChild(makeBtn(pages, pages, false, false, false));
        }
        btns.appendChild(makeBtn('<i class="fa-solid fa-chevron-right"></i>', page+1, false, false, page>=pages));
    }

    // ════════════════════════════════════════════════════════════
    // PAYMENT DROPDOWN
    // ════════════════════════════════════════════════════════════
    const rPayDD  = document.getElementById('rPayDropdown');
    let   rPayTarget = null;

    window.rOpenPayDropdown = function (patientId, triggerEl) {
        if (rPayDD.style.display === 'block' && rPayTarget === patientId) {
            rClosePayDropdown(); return;
        }
        rPayTarget = patientId;

        // Highlight current state
        const row = document.getElementById('rrow-' + patientId);
        rPayDD.querySelectorAll('.r-drop-item').forEach(i => { i.style.fontWeight=''; i.style.background=''; });
        if (row) {
            const vs = row.dataset.vstatus, ps = row.dataset.pstatus, mt = row.dataset.method;
            if (vs === 'returned') {
                const el = rPayDD.querySelector('[data-action="returned"]');
                if (el) { el.style.fontWeight='700'; el.style.background='var(--color-surface)'; }
            } else if (ps === 'paid') {
                const el = rPayDD.querySelector(`[data-action="paid"][data-method="${mt}"]`);
                if (el) { el.style.fontWeight='700'; el.style.background='var(--color-surface)'; }
            } else {
                const el = rPayDD.querySelector('[data-action="unpaid"]');
                if (el) { el.style.fontWeight='700'; el.style.background='var(--color-surface)'; }
            }
        }

        const rect = triggerEl.getBoundingClientRect();
        const sY   = window.scrollY, sX = window.scrollX;
        rPayDD.style.display = 'block';

        const ddW = 214;
        let left  = rect.right + sX - ddW;
        if (left < 8) left = rect.left + sX;
        if (left + ddW > document.documentElement.clientWidth - 8)
            left = document.documentElement.clientWidth - ddW - 8;
        rPayDD.style.left = left + 'px';
        rPayDD.style.top  = (rect.bottom + sY + 4) + 'px';

        requestAnimationFrame(() => {
            const ddH = rPayDD.offsetHeight;
            if (rect.bottom + ddH + 4 > window.innerHeight)
                rPayDD.style.top = (rect.top + sY - ddH - 4) + 'px';
        });
    };

    function rClosePayDropdown() { rPayDD.style.display = 'none'; rPayTarget = null; }

    document.addEventListener('click', e => {
        if (!rPayDD.contains(e.target) && !e.target.closest('.r-pay-trigger')) rClosePayDropdown();
    });

    rPayDD.querySelectorAll('.r-drop-item').forEach(item => {
        item.addEventListener('click', () => {
            if (!rPayTarget) return;
            const id     = rPayTarget;
            const action = item.dataset.action;
            const method = item.dataset.method;
            rClosePayDropdown();
            rApplyPayment(id, action, method);
        });
    });

    function rApplyPayment(patientId, action, method) {
        const fd = new FormData();
        fd.set('csrf_token', getCsrfToken());
        if (action === 'returned') {
            fd.set('action',      'markReturned');
            fd.set('patient_id',  patientId);
            fd.set('mark_action', 'return');
        } else {
            fd.set('action',          'updatePayment');
            fd.set('patient_id',      patientId);
            fd.set('payment_status',  action);
            if (method) fd.set('payment_method', method);
        }

        const row    = document.getElementById('rrow-' + patientId);
        const payBtn = row ? row.querySelector('.r-pay-trigger') : null;
        if (payBtn) payBtn.innerHTML = '<span class="spinner" style="width:12px;height:12px;"></span>';

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); loadPatients(); return; }
                showToast(res.message, 'success');
                if (window.APP_CONFIG.soundEnabled && res.data?.payment_status === 'paid') {
                    try { playSound('cash-register-kaching.mp3'); } catch(e) {}
                }
                loadPatients();
            })
            .catch(() => { showToast('Request failed.', 'error'); loadPatients(); });
    }

    // ════════════════════════════════════════════════════════════
    // VIEW RECEIPT
    // ════════════════════════════════════════════════════════════
    window.rViewReceipt = function (id) {
        document.getElementById('rReceiptContent').innerHTML =
            '<div class="text-center py-6"><span class="spinner"></span></div>';
        document.getElementById('rPrintReceiptBtn').dataset.patientId = id;
        openModal('rReceiptModal');
        fetch(`${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=receipt&id=${id}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('rReceiptContent').innerHTML =
                    res.success ? res.data.html
                    : `<p style="color:var(--color-danger);">${escHtml(res.message)}</p>`;
            })
            .catch(() => {
                document.getElementById('rReceiptContent').innerHTML =
                    '<p style="color:var(--color-danger);">Failed to load receipt.</p>';
            });
    };
    document.getElementById('rPrintReceiptBtn').addEventListener('click', function () {
        if (this.dataset.patientId) openPrintWindow('receipt', this.dataset.patientId);
    });

    // ════════════════════════════════════════════════════════════
    // EDIT PATIENT
    // ════════════════════════════════════════════════════════════
    window.rOpenEdit = function (id) {
        document.getElementById('rEditLoading').style.display  = 'block';
        document.getElementById('rEditForm').style.display     = 'none';
        document.getElementById('rEditSaveBtn').style.display  = 'none';
        document.getElementById('rEditError').style.display    = 'none';
        document.getElementById('rEditTokenBadge').textContent = '';
        openModal('rEditModal');

        fetch(`${AJAX_URL}?${new URLSearchParams({ action:'getPatientById', id })}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('rEditLoading').style.display = 'none';
                if (!res.success) {
                    document.getElementById('rEditError').style.display = 'block';
                    document.getElementById('rEditErrorText').textContent = res.message;
                    return;
                }
                rPopulateEdit(res.data.patient);
            })
            .catch(() => {
                document.getElementById('rEditLoading').style.display = 'none';
                document.getElementById('rEditError').style.display   = 'block';
                document.getElementById('rEditErrorText').textContent = 'Failed to load patient data.';
            });
    };

    function rPopulateEdit(p) {
        const prefix = window.APP_CONFIG.tokenPrefix;
        document.getElementById('rEditTokenBadge').textContent =
            prefix + '-' + String(p.token_number).padStart(3,'0');
        document.getElementById('rEpId').value     = p.id;
        document.getElementById('rEpDoctor').value = p.doctor_id || '';
        document.getElementById('rEpName').value   = p.name || '';
        document.getElementById('rEpGender').value = p.gender || '';
        document.getElementById('rEpAge').value    = p.age || '';
        document.getElementById('rEpPhone').value  = p.phone || '';
        document.getElementById('rEpAddress').value= p.address || '';
        const rawNotes = p.notes || '';
        document.getElementById('rEpNotes').value  = rawNotes.replace('[RETURNED]','').trim();
        if (p.visit_date && p.visit_time) {
            document.getElementById('rEpDatetime').value =
                `${p.visit_date}T${p.visit_time.substring(0,5)}`;
        }
        const isPaid = p.payment_status === 'paid';
        document.getElementById('rEpUnpaid').checked = !isPaid;
        document.getElementById('rEpPaid').checked   = isPaid;
        document.getElementById('rEpMethodWrap').style.display = isPaid ? 'block' : 'none';
        document.getElementById('rEpMethod').value = p.payment_method || '';

        document.getElementById('rEditForm').style.display    = 'block';
        document.getElementById('rEditSaveBtn').style.display = 'inline-flex';
    }

    document.querySelectorAll('input[name="rEpPayStatus"]').forEach(r => {
        r.addEventListener('change', () => {
            document.getElementById('rEpMethodWrap').style.display =
                (r.value === 'paid' && r.checked) ? 'block' : 'none';
        });
    });

    document.getElementById('rEditSaveBtn').addEventListener('click', rSaveEdit);

    function rSaveEdit() {
        document.getElementById('rEditError').style.display = 'none';
        const id        = document.getElementById('rEpId').value;
        const doctorId  = document.getElementById('rEpDoctor').value;
        const name      = document.getElementById('rEpName').value.trim();
        const gender    = document.getElementById('rEpGender').value;
        const dt        = document.getElementById('rEpDatetime').value;
        const payStatus = document.querySelector('input[name="rEpPayStatus"]:checked').value;
        const method    = document.getElementById('rEpMethod').value;

        const showErr = msg => {
            document.getElementById('rEditErrorText').textContent = msg;
            document.getElementById('rEditError').style.display = 'block';
        };

        if (!name)      { showErr('Patient name is required.'); return; }
        if (!gender)    { showErr('Please select a gender.'); return; }
        if (!dt)        { showErr('Please select appointment date and time.'); return; }
        if (!doctorId)  { showErr('Please select a doctor.'); return; }
        if (payStatus === 'paid' && !method) { showErr('Please select a payment method.'); return; }

        const btn  = document.getElementById('rEditSaveBtn');
        const orig = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;"></span> Saving…';

        const fd = new FormData();
        fd.set('action',         'updatePatient');
        fd.set('csrf_token',     getCsrfToken());
        fd.set('id',             id);
        fd.set('doctor_id',      doctorId);
        fd.set('name',           name);
        fd.set('gender',         gender);
        fd.set('visit_datetime', dt);
        fd.set('age',            document.getElementById('rEpAge').value.trim());
        fd.set('phone',          document.getElementById('rEpPhone').value.trim());
        fd.set('address',        document.getElementById('rEpAddress').value.trim());
        fd.set('notes',          document.getElementById('rEpNotes').value.trim());
        fd.set('payment_status', payStatus);
        if (payStatus === 'paid') fd.set('payment_method', method);

        fetch(AJAX_URL, { method:'POST', body:fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                if (!res.success) { showErr(res.message); return; }
                showToast(res.message, 'success');
                closeModal('rEditModal');
                loadPatients();
            })
            .catch(() => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                showErr('Network error. Please try again.');
            });
    }

    // ════════════════════════════════════════════════════════════
    // GENERATE TOKEN MODAL
    // ════════════════════════════════════════════════════════════
    let rGt = { docId:null, docName:'', fee:0, feeFmt:'', nextToken:'—', timer:null };

    document.getElementById('rOpenGenTokenBtn').addEventListener('click', () => {
        rResetGtModal();
        openModal('rGenTokenModal');
        rLoadGtDoctors();
    });

    function rSetDefaultDatetime() {
        const now = new Date();
        now.setMinutes(Math.ceil(now.getMinutes() / 15) * 15, 0, 0);
        const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString().slice(0, 16);
        document.getElementById('rGtDatetime').value = local;
    }

    function rResetGtModal() {
        rGt = { docId:null, docName:'', fee:0, feeFmt:'', nextToken:'—', timer:null };
        document.getElementById('rGtDoctorGrid').innerHTML =
            '<div style="grid-column:1/-1;text-align:center;padding:1.5rem;"><span class="spinner"></span></div>';
        ['rGtName','rGtAge','rGtPhone','rGtAddress','rGtNotes'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('rGtGender').value = '';
        document.getElementById('rGtUnpaid').checked = true;
        document.getElementById('rGtMethodWrap').style.display = 'none';
        document.getElementById('rGtMethod').value = '';
        document.getElementById('rGtFeeRow').style.display = 'none';
        document.getElementById('rGtSubmitBtn').disabled = true;
        document.getElementById('rGtError').style.display = 'none';
        rSetPreview('—', 'Select a doctor', '');
        rSetDefaultDatetime();
    }

    function rSetPreview(num, doc, date) {
        const preview = document.getElementById('rGtPreview');
        preview.className = 'gt-token-preview-r' + (rGt.docId ? '' : ' inactive');
        document.getElementById('rGtPreviewNum').textContent  = num;
        document.getElementById('rGtPreviewDoc').textContent  = doc;
        document.getElementById('rGtPreviewDate').textContent = date || 'No date selected';
    }

    function rLoadGtDoctors() {
        const dateInput = document.getElementById('rGtDatetime').value;
        const visitDate = dateInput ? dateInput.split('T')[0] : new Date().toISOString().split('T')[0];

        fetch(`${AJAX_URL}?${new URLSearchParams({ action:'getDoctors', visit_date:visitDate })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    document.getElementById('rGtDoctorGrid').innerHTML =
                        `<p style="grid-column:1/-1;color:var(--color-danger);padding:1rem;">${escHtml(res.message)}</p>`;
                    return;
                }
                rRenderGtDoctors(res.data.doctors);
            })
            .catch(() => {
                document.getElementById('rGtDoctorGrid').innerHTML =
                    '<p style="grid-column:1/-1;color:var(--color-danger);padding:1rem;">Failed to load doctors.</p>';
            });
    }

    function rRenderGtDoctors(doctors) {
        const grid = document.getElementById('rGtDoctorGrid');
        if (!doctors.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:1.5rem;color:var(--color-text-muted);">No active doctors found.</div>';
            return;
        }
        grid.innerHTML = doctors.map(d => `
            <div class="gt-doc-card-r${d.is_full ? ' full' : ''}${rGt.docId === d.id ? ' selected' : ''}"
                 data-doc-id="${d.id}"
                 onclick="${d.is_full ? '' : 'rSelectDoc(' + d.id + ')'}">
                <div class="gt-check-r"><i class="fa-solid fa-check" style="font-size:8px;"></i></div>
                <div class="gt-doc-name-r">Dr. ${escHtml(d.name)}</div>
                <div class="gt-doc-spec-r">${escHtml(d.specialization)}</div>
                <div class="gt-doc-tok-r">${escHtml(d.token_display)}</div>
                <div class="gt-doc-fee-r">${escHtml(d.fee_fmt)}</div>
                ${d.is_full ? '<div style="font-size:10px;color:var(--color-danger);margin-top:3px;"><i class="fa-solid fa-ban"></i> Full today</div>' : ''}
            </div>`).join('');
    }

    window.rSelectDoc = function (docId) {
        rGt.docId = docId;
        document.querySelectorAll('.gt-doc-card-r').forEach(c => c.classList.remove('selected'));
        const card = document.querySelector(`.gt-doc-card-r[data-doc-id="${docId}"]`);
        if (card) card.classList.add('selected');
        rRefreshToken();
    };

    function rRefreshToken() {
        if (!rGt.docId) return;
        const dateInput = document.getElementById('rGtDatetime').value;
        if (!dateInput) return;
        const visitDate = dateInput.split('T')[0];

        fetch(`${AJAX_URL}?${new URLSearchParams({ action:'getNextToken', doctor_id:rGt.docId, visit_date:visitDate })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showToast(res.message, 'warning');
                    rGt.docId = null;
                    document.querySelectorAll('.gt-doc-card-r').forEach(c => c.classList.remove('selected'));
                    document.getElementById('rGtSubmitBtn').disabled = true;
                    return;
                }
                rGt.nextToken = res.data.token_display;
                rGt.docName   = 'Dr. ' + res.data.doctor.name;
                rGt.fee       = res.data.fee;
                rGt.feeFmt    = res.data.fee_fmt;
                const dt  = new Date(res.data.visit_date + 'T00:00:00');
                const fmt = dt.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
                rSetPreview(rGt.nextToken, rGt.docName, fmt);
                document.getElementById('rGtFeeAmt').textContent   = res.data.fee_fmt;
                document.getElementById('rGtFeeRow').style.display = 'flex';
                document.getElementById('rGtSubmitBtn').disabled   = false;
            })
            .catch(() => showToast('Failed to load next token.', 'error'));
    }

    document.getElementById('rGtDatetime').addEventListener('change', () => {
        clearTimeout(rGt.timer);
        rGt.timer = setTimeout(() => {
            rLoadGtDoctors();
            if (rGt.docId) rRefreshToken();
        }, 400);
    });

    document.querySelectorAll('input[name="rGtPayStatus"]').forEach(r => {
        r.addEventListener('change', () => {
            document.getElementById('rGtMethodWrap').style.display =
                (r.value === 'paid' && r.checked) ? 'block' : 'none';
        });
    });

    document.getElementById('rGtSubmitBtn').addEventListener('click', rSubmitToken);

    function rSubmitToken() {
        document.getElementById('rGtError').style.display = 'none';
        const showErr = msg => {
            document.getElementById('rGtErrorText').textContent = msg;
            document.getElementById('rGtError').style.display = 'block';
        };

        if (!rGt.docId)   { showErr('Please select a doctor.'); return; }
        const name      = document.getElementById('rGtName').value.trim();
        const gender    = document.getElementById('rGtGender').value;
        const dt        = document.getElementById('rGtDatetime').value;
        const payStatus = document.querySelector('input[name="rGtPayStatus"]:checked').value;
        const method    = document.getElementById('rGtMethod').value;

        if (!name)   { showErr('Patient name is required.'); return; }
        if (!gender) { showErr('Please select a gender.'); return; }
        if (!dt)     { showErr('Please select an appointment date and time.'); return; }
        if (payStatus === 'paid' && !method) { showErr('Please select a payment method.'); return; }

        const btn  = document.getElementById('rGtSubmitBtn');
        const orig = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;"></span> Generating…';

        const fd = new FormData();
        fd.set('action',          'generateToken');
        fd.set('csrf_token',      getCsrfToken());
        fd.set('doctor_id',       rGt.docId);
        fd.set('name',            name);
        fd.set('gender',          gender);
        fd.set('visit_datetime',  dt);
        fd.set('age',             document.getElementById('rGtAge').value.trim());
        fd.set('phone',           document.getElementById('rGtPhone').value.trim());
        fd.set('address',         document.getElementById('rGtAddress').value.trim());
        fd.set('notes',           document.getElementById('rGtNotes').value.trim());
        fd.set('payment_status',  payStatus);
        if (payStatus === 'paid') fd.set('payment_method', method);

        fetch(AJAX_URL, { method:'POST', body:fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                if (!res.success) { showErr(res.message); return; }

                btn.innerHTML = '<i class="fa-solid fa-check"></i> Generated!';
                btn.style.background = 'var(--color-accent)';
                showToast(res.message, 'success');
                if (window.APP_CONFIG.soundEnabled && payStatus === 'paid') {
                    try { playSound('cash-register-kaching.mp3'); } catch(e) {}
                }

                if (confirm('Token generated! Print the receipt now?')) {
                    openPrintWindow('receipt', res.data.patient_id);
                }

                setTimeout(() => {
                    closeModal('rGenTokenModal');
                    btn.innerHTML = orig;
                    btn.style.background = '';
                    loadPatients();
                }, 800);
            })
            .catch(() => {
                btn.disabled  = false;
                btn.innerHTML = orig;
                showErr('Network error. Please try again.');
            });
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS + INIT
    // ════════════════════════════════════════════════════════════
    const debLoad = debounce(() => applySearchAndRender(1), 250);
    document.getElementById('rSearchInput').addEventListener('input', debLoad);
    document.getElementById('rDoctorFilter').addEventListener('change', loadPatients);
    document.getElementById('rStatusFilter').addEventListener('change', loadPatients);
    document.getElementById('rPerPage').addEventListener('change', () => applySearchAndRender(1));
    document.getElementById('rClearFiltersBtn').addEventListener('click', () => {
        document.getElementById('rSearchInput').value  = '';
        document.getElementById('rDoctorFilter').value = '';
        document.getElementById('rStatusFilter').value = '';
        loadPatients();
    });

    loadPatients();
    setInterval(loadPatients, 2 * 60 * 1000); // auto-refresh every 2 min

})();
JS;

require_once BASE_PATH . '/receptionist/footer.php';
?>