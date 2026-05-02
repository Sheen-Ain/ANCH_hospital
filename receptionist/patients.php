<?php
/**
 * receptionist/patients.php
 * Today's registered patients — filter by doctor/payment, edit details,
 * toggle payment, view/print receipt, delete.
 */

$pageTitle  = "Today's Patients";
$activePage = 'patients';
require_once __DIR__ . '/header.php';

// Load active doctors for filter dropdown
$activeDoctors = Database::fetchAll(
    "SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name"
);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-users" style="color:var(--color-accent);"></i>
        Today's Patients
    </h2>
    <a href="<?= BASE_URL ?>/receptionist/generate-token.php" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> New Token
    </a>
</div>

<!-- ── Summary Strip ─────────────────────────────────────── -->
<div class="stats-grid mb-4" id="summaryStrip"
     style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));">
    <?php foreach (['Total', 'Paid', 'Unpaid'] as $lbl): ?>
    <div class="stat-card">
        <div class="stat-card-icon slate"><span class="spinner"></span></div>
        <div><div class="stat-card-label"><?= $lbl ?></div><div class="stat-number">—</div></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filters ───────────────────────────────────────────── -->
<div class="filters-bar mb-4">
    <div class="flex-1" style="min-width:160px;max-width:260px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="Patient name, phone…" style="padding-left:36px;">
        </div>
    </div>

    <div>
        <label class="form-label">Doctor</label>
        <select id="doctorFilter" class="form-select" style="min-width:160px;">
            <option value="">All Doctors</option>
            <?php foreach ($activeDoctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="form-label">Payment</label>
        <select id="paymentFilter" class="form-select" style="min-width:130px;">
            <option value="">All</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Unpaid</option>
        </select>
    </div>

    <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" style="align-self:flex-end;">
        <i class="fa-solid fa-xmark"></i> Clear
    </button>
</div>

<!-- ── Patients Table ────────────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="table-wrapper">
        <table class="data-table" id="patientsTable" style="min-width:820px;">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Age / Gender</th>
                    <th>Time</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Registered By</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="patientsBody">
                <tr>
                    <td colspan="9" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     EDIT PATIENT MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editPatientModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-warning);"></i>
                Edit Patient
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="editPatientForm" novalidate>
                <input type="hidden" name="action"     value="updatePatient">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="edit_pt_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="edit_pt_name">Patient Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_pt_name" name="name" class="form-input" maxlength="150" required>
                        <div class="form-error-msg" id="edit_pt_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_pt_age">Age (yrs)</label>
                        <input type="number" id="edit_pt_age" name="age" class="form-input" min="0" max="150">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="gender" id="edit_pt_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="edit_pt_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="edit_pt_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="edit_pt_gender">⚧ Other</button>
                        </div>
                        <div class="form-error-msg" id="edit_pt_genderError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_pt_phone">Phone</label>
                        <input type="text" id="edit_pt_phone" name="phone" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_pt_doctor">Doctor <span style="color:var(--color-danger);">*</span></label>
                        <select id="edit_pt_doctor" name="doctor_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($activeDoctors as $doc): ?>
                            <option value="<?= (int) $doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-error-msg" id="edit_pt_doctorError"></div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="edit_pt_address">Address</label>
                        <textarea id="edit_pt_address" name="address" class="form-textarea" rows="2"></textarea>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="edit_pt_notes">Notes</label>
                        <textarea id="edit_pt_notes" name="notes" class="form-textarea" rows="2"></textarea>
                    </div>

                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="editPatientSubmitBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     PAYMENT MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="paymentModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-money-bill-wave" style="color:var(--color-accent);"></i>
                Update Payment
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p class="text-sm mb-3" style="color:var(--color-text-muted);">
                Patient: <strong id="paymentPatientName">—</strong>
            </p>
            <form id="paymentForm" novalidate>
                <input type="hidden" name="action"      value="updatePayment">
                <input type="hidden" name="csrf_token"  value="<?= e($csrfToken) ?>">
                <input type="hidden" name="patient_id"  id="pay_patient_id">

                <div class="form-group">
                    <label class="form-label">Payment Status</label>
                    <input type="hidden" name="payment_status" id="pay_status" value="unpaid">
                    <div class="payment-toggle">
                        <button type="button" class="payment-toggle-btn unpaid" id="payBtnUnpaid" data-value="unpaid">⏳ Unpaid</button>
                        <button type="button" class="payment-toggle-btn paid"   id="payBtnPaid"   data-value="paid">✅ Paid</button>
                    </div>
                </div>

                <div class="form-group" id="payMethodGroup" style="display:none;">
                    <label class="form-label">Payment Method <span style="color:var(--color-danger);">*</span></label>
                    <input type="hidden" name="payment_method" id="pay_method" value="">
                    <div class="pill-toggle-group">
                        <button type="button" class="pill-toggle" data-value="cash"      data-field="pay_method">💵 Cash</button>
                        <button type="button" class="pill-toggle" data-value="card"      data-field="pay_method">💳 Card</button>
                        <button type="button" class="pill-toggle" data-value="insurance" data-field="pay_method">🏥 Insurance</button>
                        <button type="button" class="pill-toggle" data-value="online"    data-field="pay_method">📱 Online</button>
                    </div>
                    <div class="form-error-msg" id="pay_methodError"></div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-success" id="paymentSubmitBtn">
                <i class="fa-solid fa-check"></i> Save Payment
            </button>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal-backdrop" id="receiptModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-receipt" style="color:var(--color-primary);"></i> Receipt</div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:0.75rem;">
            <div class="receipt-preview-wrapper" id="receiptContent">
                <div class="text-center py-6"><span class="spinner"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="printReceiptBtn" data-patient-id="">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/receptionist/tokens.php`;

    // ── Pill toggles ──────────────────────────────────────────
    document.querySelectorAll('.pill-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const f = btn.dataset.field;
            if (!f) return;
            document.querySelectorAll(`.pill-toggle[data-field="${f}"]`).forEach(b =>
                b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById(f).value = btn.dataset.value;
        });
    });

    // ── Load patients ─────────────────────────────────────────
    function loadPatients() {
        const search   = document.getElementById('searchInput').value.trim();
        const doctorId = document.getElementById('doctorFilter').value;
        const payment  = document.getElementById('paymentFilter').value;
        const params   = new URLSearchParams({
            action: 'getTodayPatients',
            doctor_id: doctorId, payment_status: payment
        });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                renderSummary(res.data.summary);
                renderTable(res.data.patients, search);
            })
            .catch(() => showToast('Failed to load patients.', 'error'));
    }

    function renderSummary(s) {
        const cfgs = [
            { label:'Total Today', value: s.total,  icon:'fa-users',              color:'blue'  },
            { label:'Paid',        value: s.paid,   icon:'fa-circle-check',       color:'green' },
            { label:'Unpaid',      value: s.unpaid, icon:'fa-clock',              color:'amber' },
        ];
        document.getElementById('summaryStrip').innerHTML = cfgs.map(c => `
            <div class="stat-card animate-fade-in">
                <div class="stat-card-icon ${c.color}"><i class="fa-solid ${c.icon}"></i></div>
                <div><div class="stat-card-label">${c.label}</div><div class="stat-number">${c.value}</div></div>
            </div>`).join('');
    }

    function renderTable(patients, search) {
        const tbody  = document.getElementById('patientsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        // Client-side search filter
        const list = search
            ? patients.filter(p =>
                p.name.toLowerCase().includes(search.toLowerCase()) ||
                (p.phone || '').includes(search) ||
                (p.created_by_name || '').toLowerCase().includes(search.toLowerCase()))
            : patients;

        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="table-empty">
                <div class="table-empty-icon">🏥</div>
                No patients found for today.
                <br><a href="${window.APP_CONFIG.baseUrl}/receptionist/generate-token.php"
                   class="btn btn-primary btn-sm mt-2">
                    <i class="fa-solid fa-plus"></i> Generate Token
                </a>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = list.map(p => {
            // Only the receptionist who registered this patient can edit/update payment
            const isMine = p.is_mine;

            return `
            <tr class="animate-fade-in" data-id="${p.id}">
                <td>
                    <span class="font-mono-nums font-bold text-xs" style="color:var(--color-primary);">
                        ${prefix}-${p.token_number}
                    </span>
                </td>
                <td>
                    <div class="font-medium text-xs">${escHtml(p.name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">
                        ${p.phone ? escHtml(p.phone) : '—'}
                    </div>
                </td>
                <td>
                    <div class="text-xs font-medium">${escHtml(p.doctor_name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${escHtml(p.specialization)}</div>
                </td>
                <td class="text-xs">
                    ${p.age ? p.age + ' yrs' : '—'} / ${ucFirst(p.gender)}
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">${escHtml(p.visit_time_fmt)}</td>
                <td class="text-xs font-bold" style="color:var(--color-accent);">
                    ${escHtml(p.amount_fmt)}
                </td>
                <td>
                    <span class="badge ${p.payment_status === 'paid' ? 'badge-paid' : 'badge-unpaid'}">
                        ${p.payment_status === 'paid' ? '✓ Paid' : '⏳ Unpaid'}
                    </span>
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">
                    ${escHtml(p.created_by_name || '—')}
                    ${isMine ? '<span class="badge badge-active" style="font-size:9px;margin-left:3px;">You</span>' : ''}
                </td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-ghost btn-icon btn-sm" title="View Receipt"
                                onclick="viewReceipt(${p.id})">
                            <i class="fa-solid fa-receipt"></i>
                        </button>
                        ${isMine ? `
                        <button class="btn btn-icon btn-sm" title="Update Payment"
                                style="color:var(--color-accent);background:var(--color-accent-lt);"
                                onclick="openPaymentModal(${p.id}, '${escJs(p.name)}', '${p.payment_status}', '${p.payment_method || ''}')">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </button>
                        <button class="btn btn-ghost btn-icon btn-sm" title="Edit"
                                onclick="openEditModal(${p.id})">
                            <i class="fa-solid fa-pen"></i>
                        </button>` : `
                        <span class="text-xs" style="color:var(--color-text-faint);padding:0 4px;"
                              title="You can only edit patients you registered">—</span>`}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Edit Patient ──────────────────────────────────────────
    window.openEditModal = function (id) {
        fetch(`${AJAX_URL}?${new URLSearchParams({ action: 'getPatientById', id })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const p = res.data.patient;
                document.getElementById('edit_pt_id').value      = p.id;
                document.getElementById('edit_pt_name').value    = p.name;
                document.getElementById('edit_pt_age').value     = p.age || '';
                document.getElementById('edit_pt_phone').value   = p.phone || '';
                document.getElementById('edit_pt_address').value = p.address || '';
                document.getElementById('edit_pt_notes').value   = p.notes || '';
                document.getElementById('edit_pt_doctor').value  = p.doctor_id;
                setPill('edit_pt_gender', p.gender);
                openModal('editPatientModal');
                document.getElementById('edit_pt_name').focus();
            })
            .catch(() => showToast('Failed to load patient.', 'error'));
    };

    document.getElementById('editPatientSubmitBtn').addEventListener('click', () => {
        const name   = document.getElementById('edit_pt_name').value.trim();
        const gender = document.getElementById('edit_pt_gender').value;
        const doc    = document.getElementById('edit_pt_doctor').value;
        let valid    = true;

        if (!name)   { showFieldError('edit_pt_name',   'Name is required.');         valid = false; } else clearFieldError('edit_pt_name');
        if (!gender) { showFieldError('edit_pt_gender', 'Please select gender.');      valid = false; } else clearFieldError('edit_pt_gender');
        if (!doc)    { showFieldError('edit_pt_doctor', 'Please select a doctor.');    valid = false; } else clearFieldError('edit_pt_doctor');

        if (!valid) return;

        const btn = document.getElementById('editPatientSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('editPatientForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('editPatientModal');
                loadPatients();
            }, null, btn
        );
    });

    // ── Payment Modal ─────────────────────────────────────────
    window.openPaymentModal = function (id, name, status, method) {
        document.getElementById('pay_patient_id').value     = id;
        document.getElementById('paymentPatientName').textContent = name;
        document.getElementById('pay_status').value         = status;
        document.getElementById('pay_method').value         = method;

        // Set buttons
        document.getElementById('payBtnPaid').classList.toggle('selected', status === 'paid');
        document.getElementById('payBtnUnpaid').classList.toggle('selected', status !== 'paid');
        document.getElementById('payMethodGroup').style.display = status === 'paid' ? '' : 'none';

        // Set method pill
        if (method) {
            document.querySelectorAll('.pill-toggle[data-field="pay_method"]').forEach(b =>
                b.classList.toggle('selected', b.dataset.value === method));
        } else {
            document.querySelectorAll('.pill-toggle[data-field="pay_method"]').forEach(b =>
                b.classList.remove('selected'));
        }

        openModal('paymentModal');
    };

    document.getElementById('payBtnPaid').addEventListener('click', () => {
        document.getElementById('pay_status').value = 'paid';
        document.getElementById('payBtnPaid').classList.add('selected');
        document.getElementById('payBtnUnpaid').classList.remove('selected');
        document.getElementById('payMethodGroup').style.display = '';
    });

    document.getElementById('payBtnUnpaid').addEventListener('click', () => {
        document.getElementById('pay_status').value = 'unpaid';
        document.getElementById('payBtnUnpaid').classList.add('selected');
        document.getElementById('payBtnPaid').classList.remove('selected');
        document.getElementById('payMethodGroup').style.display = 'none';
        document.getElementById('pay_method').value = '';
        document.querySelectorAll('.pill-toggle[data-field="pay_method"]').forEach(b =>
            b.classList.remove('selected'));
    });

    document.getElementById('paymentSubmitBtn').addEventListener('click', () => {
        const status = document.getElementById('pay_status').value;
        const method = document.getElementById('pay_method').value;
        if (status === 'paid' && !method) {
            showFieldError('pay_method', 'Please select a payment method.'); return;
        }
        clearFieldError('pay_method');
        const btn = document.getElementById('paymentSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('paymentForm')),
            (_, msg) => {
                showToast(msg, 'success');
                playSound('cash-register.mp3');
                closeModal('paymentModal');
                loadPatients();
            }, null, btn
        );
    });

    // ── Receipt ───────────────────────────────────────────────
    window.viewReceipt = function (id) {
        document.getElementById('receiptContent').innerHTML =
            '<div class="text-center py-6"><span class="spinner"></span></div>';
        document.getElementById('printReceiptBtn').dataset.patientId = id;
        openModal('receiptModal');

        fetch(`${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=receipt&id=${id}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('receiptContent').innerHTML =
                    res.success ? res.data.html : `<p style="color:var(--color-danger);">${escHtml(res.message)}</p>`;
            })
            .catch(() => {
                document.getElementById('receiptContent').innerHTML =
                    '<p style="color:var(--color-danger);">Failed to load receipt.</p>';
            });
    };

    // Print receipt button
    document.getElementById('printReceiptBtn')?.addEventListener('click', function () {
        const id = this.dataset.patientId;
        if (id) openPrintWindow('receipt', id);
    });

    // ── Delete ────────────────────────────────────────────────
    window.confirmDelete = function (id, name) {
        showConfirmModal(
            'Delete Patient Record',
            `Delete record for <strong>${escHtml(name)}</strong>? This also removes their token and payment.`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deletePatient');
                fd.set('csrf_token', getCsrfToken());
                fd.set('id', id);
                ajaxRequest(AJAX_URL, 'POST', fd,
                    (_, msg) => { showToast(msg, 'success'); loadPatients(); }
                );
            }, 'danger'
        );
    };

    // ── Pill helpers ──────────────────────────────────────────
    function setPill(fieldId, value) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b =>
            b.classList.toggle('selected', b.dataset.value === value));
        const h = document.getElementById(fieldId);
        if (h) h.value = value;
    }

    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escJs(s)   { return String(s??'').replace(/'/g,"\\'"); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ── Filters ───────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', debounce(loadPatients, 250));
    document.getElementById('doctorFilter').addEventListener('change', loadPatients);
    document.getElementById('paymentFilter').addEventListener('change', loadPatients);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value  = '';
        document.getElementById('doctorFilter').value = '';
        document.getElementById('paymentFilter').value = '';
        loadPatients();
    });

    // ── Init ──────────────────────────────────────────────────
    loadPatients();
    setInterval(loadPatients, 2 * 60 * 1000);

})();
JS;

require_once BASE_PATH . '/receptionist/footer.php';
?>