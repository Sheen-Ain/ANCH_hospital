<?php
/**
 * receptionist/generate-token.php
 * Redesigned token generation module:
 * - Doctor card selection
 * - datetime-local appointment picker (date + time in one field)
 * - Token numbering resets per doctor + appointment date
 * - Receipt prints in-page modal (no new tab)
 */

$pageTitle  = 'Generate Token';
$activePage = 'generate-token';
require_once __DIR__ . '/header.php';

$preSelectedDoctorId = (int) ($_GET['doctor_id'] ?? 0);
$currencySymbol = getSetting('currency_symbol', 'Rs.');
?>

<!-- Page-level print CSS — only the receipt modal prints -->
<style>
@media print {
  @page { size: 58mm auto; margin: 2mm 3mm; }
  body > *                         { display: none !important; }
  #receiptPrintArea                { display: block !important; position: fixed !important;
                                     top: 0; left: 0; width: 100%; background: #fff; z-index: 99999; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
#receiptPrintArea { display: none; }

/* ── Module-level styles ── */
.gt-shell {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 1.25rem;
    align-items: start;
}

@media (max-width: 1100px) {
    .gt-shell { grid-template-columns: 1fr; }
}

/* Doctor card */
.doc-card {
    background: var(--color-card);
    border: 2px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 0.875rem 1rem;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
}
.doc-card:hover { border-color: var(--color-primary); background: var(--color-primary-lt); }
.doc-card.selected {
    border-color: var(--color-primary);
    background: var(--color-primary-lt);
    box-shadow: 0 0 0 3px rgba(3,105,161,0.12);
}
.doc-card.full { opacity: 0.45; cursor: not-allowed; }
.doc-card .doc-name { font-size: 13px; font-weight: 700; color: var(--color-text); margin-bottom: 2px; }
.doc-card .doc-spec { font-size: 11px; color: var(--color-text-muted); margin-bottom: 8px; }
.doc-card .doc-token {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.1rem; font-weight: 800;
    color: var(--color-primary);
    letter-spacing: 1px;
}
.doc-card .doc-meta {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 6px; font-size: 11px;
}
.doc-card .selected-check {
    position: absolute; top: 8px; right: 8px;
    width: 20px; height: 20px; border-radius: 50%;
    background: var(--color-primary); color: #fff;
    display: none; align-items: center; justify-content: center;
    font-size: 10px;
}
.doc-card.selected .selected-check { display: flex; }

/* Token preview box */
.token-preview-card {
    background: linear-gradient(135deg, #0c4a6e, #0369a1);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1rem;
    position: relative;
    overflow: hidden;
}
.token-preview-card::before {
    content: '';
    position: absolute;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    top: -60px; right: -60px;
}
.token-preview-card .tpc-label {
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em;
    color: rgba(255,255,255,0.7); margin-bottom: 8px;
}
.token-preview-card .tpc-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 2.25rem; font-weight: 800;
    color: #fff; letter-spacing: 3px;
    line-height: 1;
}
.token-preview-card .tpc-doctor {
    font-size: 12px; color: rgba(255,255,255,0.8);
    margin-top: 8px;
}
.token-preview-card .tpc-date {
    font-size: 11px; color: rgba(255,255,255,0.6);
    margin-top: 4px;
}
.token-preview-card .tpc-meta {
    display: flex; justify-content: center; gap: 1.5rem;
    margin-top: 12px; padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,0.15);
}
.token-preview-card .tpc-meta-item {
    text-align: center;
}
.token-preview-card .tpc-meta-val {
    font-size: 14px; font-weight: 700; color: #7dd3fc;
}
.token-preview-card .tpc-meta-lbl {
    font-size: 10px; color: rgba(255,255,255,0.6);
    text-transform: uppercase; letter-spacing: 0.05em;
}
.token-preview-card.inactive {
    background: linear-gradient(135deg, #475569, #64748b);
}

/* Form section label */
.form-section-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.1em; color: var(--color-text-faint);
    margin-bottom: 8px; margin-top: 16px;
    display: flex; align-items: center; gap: 6px;
    padding-bottom: 6px; border-bottom: 1px solid var(--color-border);
}
.form-section-label i { color: var(--color-primary); font-size: 11px; }
.form-section-label:first-child { margin-top: 0; }

/* Receipt modal inner */
.receipt-modal-inner {
    max-height: 70vh;
    overflow-y: auto;
    padding: 0;
}

/* Thermal receipt preview (in-modal) */
.thermal-receipt {
    width: 100%;
    max-width: 300px;
    margin: 0 auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #1e293b;
    background: #fff;
    padding: 16px 12px;
}
.thermal-receipt .tr-center { text-align: center; }
.thermal-receipt .tr-divider { border-top: 1px dashed #94a3b8; margin: 8px 0; }
.thermal-receipt .tr-divider-solid { border-top: 2px solid #1e293b; margin: 8px 0; }
.thermal-receipt .tr-logo { font-size: 20px; margin-bottom: 4px; }
.thermal-receipt .tr-hospital { font-size: 13px; font-weight: bold; color: #0369a1; }
.thermal-receipt .tr-subtitle { font-size: 10px; color: #64748b; }
.thermal-receipt .tr-token-num {
    font-size: 2rem; font-weight: 900;
    color: #0369a1; letter-spacing: 3px;
    margin: 8px 0 4px;
}
.thermal-receipt .tr-row {
    display: flex; justify-content: space-between;
    padding: 2px 0; font-size: 11.5px;
}
.thermal-receipt .tr-row .tr-lbl { color: #64748b; }
.thermal-receipt .tr-row .tr-val { font-weight: 600; color: #1e293b; text-align: right; max-width: 60%; }
.thermal-receipt .tr-paid   { color: #059669; font-weight: bold; }
.thermal-receipt .tr-unpaid { color: #d97706; font-weight: bold; }
.thermal-receipt .tr-footer { font-size: 10px; color: #94a3b8; text-align: center; margin-top: 4px; }
</style>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h2 class="page-title">
            <i class="fa-solid fa-ticket" style="color:var(--color-accent);"></i>
            Generate Token
        </h2>
        <p class="text-xs mt-0.5" style="color:var(--color-text-muted);" id="todayLabel"></p>
    </div>
    <a href="<?= BASE_URL ?>/receptionist/patients.php" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-list-ul"></i>
        View Patients
    </a>
</div>

<div class="gt-shell">

    <!-- ══════════════════════════════════════════════
         LEFT — Doctor Selection + Doctor cards
         ══════════════════════════════════════════════ -->
    <div class="flex flex-col gap-3">

        <!-- Doctor search + cards -->
        <div class="card" style="padding:1rem 1.25rem;">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <h3 class="font-bold text-sm" style="color:var(--color-text);">
                    <i class="fa-solid fa-user-doctor mr-1" style="color:var(--color-primary);"></i>
                    Select Attending Doctor
                </h3>
                <div style="position:relative;max-width:210px;width:100%;">
                    <i class="fa-solid fa-magnifying-glass"
                       style="position:absolute;left:10px;top:50%;transform:translateY(-50%);
                              color:var(--color-text-faint);font-size:12px;pointer-events:none;"></i>
                    <input type="text" id="doctorSearch" class="form-input"
                           placeholder="Search by name or specialty…"
                           style="padding-left:30px;font-size:12px;min-height:36px;">
                </div>
            </div>

            <div id="doctorCardsGrid"
                 style="display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));
                        gap:0.6rem;max-height:420px;overflow-y:auto;padding-right:2px;">
                <div class="text-center py-8 col-span-full" style="color:var(--color-text-muted);">
                    <span class="spinner"></span>
                    <p class="mt-2 text-xs">Loading doctors…</p>
                </div>
            </div>
        </div>

        <!-- Appointment date/time picker -->
        <div class="card" style="padding:1rem 1.25rem;">
            <div class="flex items-center gap-2 mb-3">
                <i class="fa-solid fa-calendar-plus" style="color:var(--color-primary);font-size:14px;"></i>
                <h3 class="font-bold text-sm" style="color:var(--color-text);">Appointment Date &amp; Time</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="visit_datetime">
                        <i class="fa-regular fa-clock mr-1"></i>
                        Date &amp; Time <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="datetime-local" id="visit_datetime" name="visit_datetime"
                           class="form-input" required style="min-height:44px;">
                    <div class="form-hint">
                        Defaults to now. Change for future appointments.
                    </div>
                    <div class="form-error-msg" id="visit_datetimeError"></div>
                </div>

                <div style="padding-top:22px;">
                    <div id="appointmentInfo"
                         style="background:var(--color-primary-lt);border:1px solid var(--color-primary-mid);
                                border-radius:var(--radius-md);padding:0.75rem;font-size:12px;">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-circle-info" style="color:var(--color-primary);"></i>
                            <span class="font-semibold" style="color:var(--color-primary);">Token Preview</span>
                        </div>
                        <div id="appointmentDateLabel" style="color:var(--color-text-muted);">—</div>
                        <div id="appointmentTokenPreview" class="font-mono-nums font-bold mt-1"
                             style="color:var(--color-primary);font-size:1rem;">Select a doctor first</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════
         RIGHT — Token Preview + Patient Form
         ══════════════════════════════════════════════ -->
    <div class="flex flex-col gap-3">

        <!-- Token number preview card -->
        <div class="token-preview-card inactive" id="tokenPreviewCard">
            <div class="tpc-label">
                <i class="fa-solid fa-ticket mr-1"></i>
                Next Token Number
            </div>
            <div class="tpc-num" id="tpcNum">—</div>
            <div class="tpc-doctor" id="tpcDoctor">Select a doctor to begin</div>
            <div class="tpc-date" id="tpcDate"></div>
            <div class="tpc-meta" id="tpcMeta" style="display:none;">
                <div class="tpc-meta-item">
                    <div class="tpc-meta-val" id="tpcFee">—</div>
                    <div class="tpc-meta-lbl">
                        <i class="fa-solid fa-tag"></i> Fee
                    </div>
                </div>
                <div class="tpc-meta-item">
                    <div class="tpc-meta-val" id="tpcLeft">—</div>
                    <div class="tpc-meta-lbl">
                        <i class="fa-solid fa-hourglass-half"></i> Slots Left
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient details form -->
        <div class="card" style="padding:1rem 1.25rem;">
            <form id="tokenForm" novalidate>
                <input type="hidden" name="action"        value="generateToken">
                <input type="hidden" name="csrf_token"    value="<?= e($csrfToken) ?>">
                <input type="hidden" name="doctor_id"     id="selectedDoctorId" value="">
                <input type="hidden" name="visit_datetime" id="visitDatetimeHidden" value="">

                <!-- Patient details section -->
                <div class="form-section-label">
                    <i class="fa-solid fa-user"></i> Patient Details
                </div>

                <!-- Name -->
                <div class="form-group">
                    <label class="form-label" for="pt_name">
                        Full Name <span style="color:var(--color-danger);">*</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-user" style="position:absolute;left:12px;top:50%;
                           transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;pointer-events:none;"></i>
                        <input type="text" id="pt_name" name="name" class="form-input"
                               placeholder="Patient's full name" maxlength="150"
                               style="padding-left:36px;" required>
                    </div>
                    <div class="form-error-msg" id="pt_nameError"></div>
                </div>

                <!-- Age + Gender -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label" for="pt_age">
                            <i class="fa-solid fa-cake-candles mr-1" style="color:var(--color-text-faint);font-size:11px;"></i>
                            Age (yrs)
                        </label>
                        <input type="number" id="pt_age" name="age" class="form-input"
                               placeholder="0–150" min="0" max="150" step="1">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-venus-mars mr-1" style="color:var(--color-text-faint);font-size:11px;"></i>
                            Gender <span style="color:var(--color-danger);">*</span>
                        </label>
                        <input type="hidden" name="gender" id="pt_gender" value="">
                        <div class="flex gap-1.5">
                            <button type="button" class="pill-toggle flex-1" data-value="male"   data-field="pt_gender" style="font-size:12px;padding:0.4rem 0.5rem;">
                                <i class="fa-solid fa-mars mr-1"></i>Male
                            </button>
                            <button type="button" class="pill-toggle flex-1" data-value="female" data-field="pt_gender" style="font-size:12px;padding:0.4rem 0.5rem;">
                                <i class="fa-solid fa-venus mr-1"></i>Female
                            </button>
                            <button type="button" class="pill-toggle flex-1" data-value="other"  data-field="pt_gender" style="font-size:12px;padding:0.4rem 0.5rem;">
                                Other
                            </button>
                        </div>
                        <div class="form-error-msg" id="pt_genderError"></div>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label class="form-label" for="pt_phone">
                        <i class="fa-solid fa-phone mr-1" style="color:var(--color-text-faint);font-size:11px;"></i>
                        Phone Number
                    </label>
                    <input type="text" id="pt_phone" name="phone" class="form-input"
                           placeholder="0300-1234567">
                </div>

                <!-- Address -->
                <div class="form-group">
                    <label class="form-label" for="pt_address">
                        <i class="fa-solid fa-location-dot mr-1" style="color:var(--color-text-faint);font-size:11px;"></i>
                        Address
                    </label>
                    <textarea id="pt_address" name="address" class="form-textarea" rows="2"
                              placeholder="Patient's home address (optional)"></textarea>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label class="form-label" for="pt_notes">
                        <i class="fa-solid fa-note-sticky mr-1" style="color:var(--color-text-faint);font-size:11px;"></i>
                        Notes / Symptoms
                    </label>
                    <textarea id="pt_notes" name="notes" class="form-textarea" rows="2"
                              placeholder="Symptoms, referral info… (optional)"></textarea>
                </div>

                <!-- Payment section -->
                <div class="form-section-label">
                    <i class="fa-solid fa-money-bill-wave"></i> Payment
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Status</label>
                    <input type="hidden" name="payment_status" id="pt_payment_status" value="unpaid">
                    <div class="payment-toggle">
                        <button type="button" class="payment-toggle-btn unpaid selected"
                                id="btnUnpaid">
                            <i class="fa-solid fa-clock mr-1"></i>Unpaid
                        </button>
                        <button type="button" class="payment-toggle-btn paid"
                                id="btnPaid">
                            <i class="fa-solid fa-circle-check mr-1"></i>Paid
                        </button>
                    </div>
                </div>

                <div class="form-group" id="paymentMethodGroup" style="display:none;">
                    <label class="form-label">
                        Payment Method <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="hidden" name="payment_method" id="pt_payment_method" value="">
                    <div class="pill-toggle-group">
                        <button type="button" class="pill-toggle" data-value="cash"
                                data-field="pt_payment_method" style="font-size:12px;">
                            <i class="fa-solid fa-money-bill mr-1"></i>Cash
                        </button>
                        <button type="button" class="pill-toggle" data-value="card"
                                data-field="pt_payment_method" style="font-size:12px;">
                            <i class="fa-solid fa-credit-card mr-1"></i>Card
                        </button>
                        <button type="button" class="pill-toggle" data-value="insurance"
                                data-field="pt_payment_method" style="font-size:12px;">
                            <i class="fa-solid fa-shield-halved mr-1"></i>Insur.
                        </button>
                        <button type="button" class="pill-toggle" data-value="online"
                                data-field="pt_payment_method" style="font-size:12px;">
                            <i class="fa-solid fa-mobile-screen mr-1"></i>Online
                        </button>
                    </div>
                    <div class="form-error-msg" id="pt_payment_methodError"></div>
                </div>

                <!-- Generate button -->
                <button type="submit" class="btn btn-primary w-full btn-lg mt-2"
                        id="generateBtn" disabled>
                    <i class="fa-solid fa-ticket"></i>
                    Generate Token
                </button>
                <p class="text-center text-xs mt-2" id="generateHint"
                   style="color:var(--color-text-faint);">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    Select a doctor above to enable
                </p>

            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     RECEIPT MODAL (in-page, no new tab)
     ══════════════════════════════════════════════ -->
<div class="modal-backdrop" id="receiptModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-circle-check" style="color:var(--color-accent);"></i>
                Token Generated!
            </div>
            <button class="modal-close" data-modal-close>
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Receipt content (also used for window.print()) -->
        <div class="modal-body" style="padding:0.75rem;">
            <div class="receipt-modal-inner" id="receiptModalInner">
                <div class="text-center py-6"><span class="spinner"></span></div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-ghost" id="newTokenBtn">
                <i class="fa-solid fa-plus"></i> New Token
            </button>
            <button class="btn btn-primary" id="printReceiptBtn">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<!-- Hidden print area — cloned here before window.print() -->
<div id="receiptPrintArea"></div>

<?php
$preSelectedId  = $preSelectedDoctorId;
$currSymbolJs   = e($currencySymbol);
$extraJs = <<<JS
(function () {
    'use strict';

    const AJAX_URL   = `\${window.APP_CONFIG.baseUrl}/ajax/receptionist/tokens.php`;
    const prefix     = window.APP_CONFIG.tokenPrefix;
    let   doctors    = [];
    let   selectedDoc= null;
    let   lastPatientId = null;

    // ── Today label ───────────────────────────────────────────
    document.getElementById('todayLabel').textContent =
        'Today: ' + new Date().toLocaleDateString('en-PK', {
            weekday:'long', year:'numeric', month:'long', day:'numeric'
        });

    // ── Set datetime-local default to NOW ─────────────────────
    function nowLocalISO() {
        const now = new Date();
        now.setSeconds(0, 0);
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 16);
    }

    const dtPicker = document.getElementById('visit_datetime');
    dtPicker.value = nowLocalISO();
    updateAppointmentInfo();

    // ── Pill toggle wiring ────────────────────────────────────
    document.querySelectorAll('.pill-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const f = btn.dataset.field;
            if (!f) return;
            document.querySelectorAll(`.pill-toggle[data-field="\${f}"]`).forEach(b =>
                b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById(f).value = btn.dataset.value;
            if (f === 'pt_gender') clearFieldError('pt_gender');
        });
    });

    // ── Payment toggle ────────────────────────────────────────
    document.getElementById('btnPaid').addEventListener('click', () => {
        document.getElementById('pt_payment_status').value = 'paid';
        document.getElementById('btnPaid').classList.add('selected');
        document.getElementById('btnUnpaid').classList.remove('selected');
        document.getElementById('paymentMethodGroup').style.display = '';
    });
    document.getElementById('btnUnpaid').addEventListener('click', () => {
        document.getElementById('pt_payment_status').value = 'unpaid';
        document.getElementById('btnUnpaid').classList.add('selected');
        document.getElementById('btnPaid').classList.remove('selected');
        document.getElementById('paymentMethodGroup').style.display = 'none';
        document.getElementById('pt_payment_method').value = '';
        document.querySelectorAll('.pill-toggle[data-field="pt_payment_method"]').forEach(b =>
            b.classList.remove('selected'));
    });

    // ── DateTime change → refresh token preview ───────────────
    dtPicker.addEventListener('change', () => {
        updateAppointmentInfo();
        if (selectedDoc) refreshTokenPreview(selectedDoc, getVisitDate());
    });

    function getVisitDate() {
        const val = dtPicker.value;
        return val ? val.split('T')[0] : new Date().toISOString().split('T')[0];
    }

    function updateAppointmentInfo() {
        const val = dtPicker.value;
        if (!val) return;
        const dt  = new Date(val);
        const lbl = dt.toLocaleDateString('en-PK', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
        const tm  = dt.toLocaleTimeString('en-PK', { hour:'2-digit', minute:'2-digit' });
        document.getElementById('appointmentDateLabel').textContent = lbl + ' at ' + tm;
        if (selectedDoc) {
            refreshTokenPreview(selectedDoc, getVisitDate());
        } else {
            document.getElementById('appointmentTokenPreview').textContent = 'Select a doctor first';
        }
    }

    // ── Load doctors ──────────────────────────────────────────
    function loadDoctors() {
        fetch(`\${AJAX_URL}?action=getDoctors`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                doctors = res.data.doctors;
                renderDoctorCards(doctors);
                if ({$preSelectedId} > 0) {
                    const doc = doctors.find(d => +d.id === {$preSelectedId});
                    if (doc) selectDoctor(doc);
                }
            })
            .catch(() => showToast('Failed to load doctors.', 'error'));
    }

    function renderDoctorCards(list) {
        const grid = document.getElementById('doctorCardsGrid');
        if (!list.length) {
            grid.innerHTML = `<div class="text-center py-6 col-span-full" style="color:var(--color-text-muted);">
                <i class="fa-solid fa-user-doctor fa-2x mb-2" style="opacity:0.3;"></i>
                <p class="text-xs">No active doctors found.</p></div>`;
            return;
        }

        grid.innerHTML = list.map(d => {
            const isSelected = selectedDoc && +selectedDoc.id === +d.id;
            const isFull     = d.is_full;
            return `
            <div class="doc-card \${isSelected ? 'selected' : ''} \${isFull ? 'full' : ''}"
                 data-doc-id="\${d.id}"
                 onclick="\${isFull ? '' : 'selectDoctorById(' + d.id + ')'}">
                <div class="selected-check"><i class="fa-solid fa-check"></i></div>
                <div class="doc-name">
                    <i class="fa-solid fa-stethoscope mr-1" style="color:var(--color-primary);font-size:10px;"></i>
                    Dr. \${escHtml(d.name)}
                </div>
                <div class="doc-spec">\${escHtml(d.specialization)}</div>
                <div class="doc-token">\${prefix}-\${String(d.next_token).padStart(3,'0')}</div>
                <div class="doc-meta">
                    <span style="color:var(--color-accent);font-weight:600;font-size:11px;">
                        \${escHtml(d.fee_fmt)}
                    </span>
                    <span class="badge \${isFull ? 'badge-danger' : 'badge-active'}" style="font-size:9px;">
                        \${isFull ? 'Full' : d.tokens_left + ' left'}
                    </span>
                </div>
            </div>`;
        }).join('');
    }

    window.selectDoctorById = function (id) {
        const doc = doctors.find(d => +d.id === +id);
        if (doc) selectDoctor(doc);
    };

    function selectDoctor(doc) {
        selectedDoc = doc;
        document.getElementById('selectedDoctorId').value = doc.id;
        document.getElementById('generateBtn').disabled   = false;
        document.getElementById('generateHint').style.display = 'none';
        renderDoctorCards(doctors);
        refreshTokenPreview(doc, getVisitDate());
        document.getElementById('pt_name').focus();
    }

    // ── Fetch real next token for selected doc + visit date ───
    function refreshTokenPreview(doc, visitDate) {
        const params = new URLSearchParams({ action:'getNextToken', doctor_id: doc.id, visit_date: visitDate });
        fetch(`\${AJAX_URL}?\${params}`)
            .then(r => r.json())
            .then(res => {
                const card = document.getElementById('tokenPreviewCard');
                card.classList.remove('inactive');

                if (!res.success) {
                    document.getElementById('tpcNum').textContent    = 'FULL';
                    document.getElementById('tpcDoctor').textContent = res.message;
                    document.getElementById('generateBtn').disabled  = true;
                    return;
                }

                const d = res.data;
                document.getElementById('tpcNum').textContent    = d.token_display;
                document.getElementById('tpcDoctor').textContent = 'Dr. ' + doc.name + ' — ' + doc.specialization;
                document.getElementById('tpcDate').textContent   = formatDate(visitDate) + ' appointment';
                document.getElementById('tpcFee').textContent    = d.fee_fmt;
                document.getElementById('tpcLeft').textContent   = d.tokens_left;
                document.getElementById('tpcMeta').style.display = 'flex';
                document.getElementById('generateBtn').disabled  = false;
                document.getElementById('generateHint').style.display = 'none';

                // Update appointment info preview
                document.getElementById('appointmentTokenPreview').textContent = d.token_display;

                // Update the selected doctor's next_token for card display
                const idx = doctors.findIndex(d2 => +d2.id === +doc.id);
                if (idx !== -1) doctors[idx].next_token = d.next_token;
            })
            .catch(() => {});
    }

    // ── Doctor search ─────────────────────────────────────────
    document.getElementById('doctorSearch').addEventListener('input', debounce(function () {
        const q = this.value.toLowerCase().trim();
        renderDoctorCards(q ? doctors.filter(d =>
            d.name.toLowerCase().includes(q) || d.specialization.toLowerCase().includes(q)
        ) : doctors);
    }, 200));

    // ── Form submit ───────────────────────────────────────────
    document.getElementById('tokenForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!clientValidate()) return;

        // Sync datetime field to hidden input
        document.getElementById('visitDatetimeHidden').value = dtPicker.value;

        // Manually add visit_datetime to FormData (the hidden input is already named)
        const fd  = serializeForm(this);
        // Ensure visit_datetime is correctly named as expected by AJAX
        fd.set('visit_datetime', dtPicker.value);

        const btn = document.getElementById('generateBtn');
        ajaxRequest(AJAX_URL, 'POST', fd,
            (data, msg) => {
                showToast(msg, 'success');
                playSound('cash-register.mp3');
                lastPatientId = data.patient_id;
                openReceiptModal(data);

                // Update next token on selected doctor card
                if (selectedDoc) {
                    const idx = doctors.findIndex(d => +d.id === +selectedDoc.id);
                    if (idx !== -1) {
                        doctors[idx].next_token = data.next_token;
                        doctors[idx].tokens_left = Math.max(0, (doctors[idx].tokens_left || 1) - 1);
                    }
                    // Refresh preview for same doctor + date
                    refreshTokenPreview(selectedDoc, getVisitDate());
                    renderDoctorCards(doctors);
                }

                // Reset form (keep doctor + datetime selected)
                const savedDocId = document.getElementById('selectedDoctorId').value;
                const savedDt    = dtPicker.value;
                resetForm('tokenForm');
                document.getElementById('selectedDoctorId').value = savedDocId;
                dtPicker.value = savedDt;
                document.getElementById('btnUnpaid').classList.add('selected');
                document.getElementById('btnPaid').classList.remove('selected');
                document.getElementById('pt_payment_status').value = 'unpaid';
                document.getElementById('paymentMethodGroup').style.display = 'none';
                document.getElementById('generateBtn').disabled = false;
                document.getElementById('generateHint').style.display = 'none';
            },
            null, btn
        );
    });

    // ── Receipt modal (in-page) ───────────────────────────────
    function openReceiptModal(data) {
        document.getElementById('receiptModalInner').innerHTML =
            '<div class="text-center py-4"><span class="spinner"></span></div>';
        openModal('receiptModal');

        // Build inline thermal receipt HTML
        fetch(`\${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=receipt&id=\${data.patient_id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    // Extract just the body content from the full HTML doc
                    const parser = new DOMParser();
                    const doc    = parser.parseFromString(res.data.html, 'text/html');
                    const root   = doc.getElementById('receipt-print-root');
                    document.getElementById('receiptModalInner').innerHTML =
                        root ? root.innerHTML : res.data.html;
                } else {
                    document.getElementById('receiptModalInner').innerHTML =
                        `<p style="color:var(--color-danger);text-align:center;">\${escHtml(res.message)}</p>`;
                }
            })
            .catch(() => {
                document.getElementById('receiptModalInner').innerHTML =
                    '<p style="color:var(--color-danger);text-align:center;">Failed to load receipt.</p>';
            });
    }

    // ── Print button — prints only modal content ──────────────
    document.getElementById('printReceiptBtn').addEventListener('click', () => {
        const printArea  = document.getElementById('receiptPrintArea');
        const modalInner = document.getElementById('receiptModalInner');

        // Clone modal content into the fixed print area
        printArea.innerHTML = modalInner.innerHTML;
        printArea.style.display = 'block';

        window.print();

        // Restore after print dialog closes
        setTimeout(() => {
            printArea.innerHTML = '';
            printArea.style.display = 'none';
        }, 1500);
    });

    // ── New token button ──────────────────────────────────────
    document.getElementById('newTokenBtn').addEventListener('click', () => {
        closeModal('receiptModal');
        document.getElementById('pt_name').focus();
    });

    // ── Client validation ─────────────────────────────────────
    function clientValidate() {
        let valid = true;

        if (!document.getElementById('selectedDoctorId').value) {
            showToast('Please select a doctor first.', 'warning'); valid = false;
        }

        const name = document.getElementById('pt_name').value.trim();
        if (!name) { showFieldError('pt_name', 'Patient name is required.'); valid = false; }
        else clearFieldError('pt_name');

        if (!document.getElementById('pt_gender').value) {
            showFieldError('pt_gender', 'Please select gender.'); valid = false;
        } else clearFieldError('pt_gender');

        if (!dtPicker.value) {
            showFieldError('visit_datetime', 'Please select appointment date and time.'); valid = false;
        } else clearFieldError('visit_datetime');

        const status = document.getElementById('pt_payment_status').value;
        if (status === 'paid' && !document.getElementById('pt_payment_method').value) {
            showFieldError('pt_payment_method', 'Please select a payment method.'); valid = false;
        } else clearFieldError('pt_payment_method');

        return valid;
    }

    // ── Utilities ─────────────────────────────────────────────
    function escHtml(s) {
        return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init ──────────────────────────────────────────────────
    loadDoctors();

})();
JS;

require_once BASE_PATH . '/receptionist/footer.php';
?>