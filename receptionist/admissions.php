<?php
/**
 * receptionist/admissions.php
 * Receptionist can: create new admissions, view their own records,
 * discharge patients they admitted, print slips.
 * All heavy operations are shared with ajax/admin/admissions.php.
 */

$pageTitle  = 'Admissions';
$activePage = 'admissions';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-clipboard-list" style="color:var(--color-accent);"></i>
        Admissions
    </h2>
    <button class="btn btn-primary" id="addAdmissionBtn">
        <i class="fa-solid fa-plus"></i> Admit Patient
    </button>
</div>

<!-- ── Filters ───────────────────────────────────────────── -->
<div class="filters-bar mb-4">
    <div class="flex-1" style="min-width:180px;max-width:300px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="Patient, doctor, room…" style="padding-left:36px;">
        </div>
    </div>
    <div>
        <label class="form-label">Status</label>
        <select id="statusFilter" class="form-select" style="min-width:130px;">
            <option value="">All</option>
            <option value="admitted">Admitted</option>
            <option value="discharged">Discharged</option>
        </select>
    </div>
    <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" style="align-self:flex-end;">
        <i class="fa-solid fa-xmark"></i> Clear
    </button>
    <div class="ml-auto" style="align-self:flex-end;">
        <span class="text-sm" style="color:var(--color-text-muted);">
            <strong id="admCount">—</strong> record(s)
        </span>
    </div>
</div>

<!-- ── Admissions Table ──────────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Room</th>
                    <th>Admitted</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="admissionsBody">
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ADD ADMISSION MODAL  (reuses admin AJAX endpoint)
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="addAdmissionModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-clipboard-list" style="color:var(--color-primary);"></i>
                Admit New Patient
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="addAdmissionForm" novalidate>
                <input type="hidden" name="action"     value="createAdmission">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <div class="form-group">
                        <label class="form-label" for="a_patient_name">Patient Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="a_patient_name" name="patient_name" class="form-input" required>
                        <div class="form-error-msg" id="a_patient_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="a_guardian_name">Father / Husband Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="a_guardian_name" name="guardian_name" class="form-input" required>
                        <div class="form-error-msg" id="a_guardian_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="gender" id="a_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="a_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="a_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="a_gender">⚧ Other</button>
                        </div>
                        <div class="form-error-msg" id="a_genderError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="a_admitted_at">Admission Date &amp; Time</label>
                        <input type="datetime-local" id="a_admitted_at" name="admitted_at" class="form-input">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="a_address">Address <span style="color:var(--color-danger);">*</span></label>
                        <textarea id="a_address" name="address" class="form-textarea" rows="2" required></textarea>
                        <div class="form-error-msg" id="a_addressError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="a_disease">Diagnosis <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="a_disease" name="disease_name" class="form-input" required>
                        <div class="form-error-msg" id="a_diseaseError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="a_treatment_cost">Treatment Cost (<?= e(getSetting('currency_symbol', 'Rs.')) ?>)</label>
                        <input type="number" id="a_treatment_cost" name="disease_treatment_cost"
                               class="form-input" min="0" step="500" value="0">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Admission Reason <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="admission_reason" id="a_reason" value="">
                        <div class="pill-toggle-group flex-wrap">
                            <button type="button" class="pill-toggle" data-value="operation"   data-field="a_reason">🔪 Operation</button>
                            <button type="button" class="pill-toggle" data-value="observation"  data-field="a_reason">👁 Observation</button>
                            <button type="button" class="pill-toggle" data-value="emergency"    data-field="a_reason">🚨 Emergency</button>
                            <button type="button" class="pill-toggle" data-value="treatment"    data-field="a_reason">💊 Treatment</button>
                            <button type="button" class="pill-toggle" data-value="other"        data-field="a_reason">📋 Other</button>
                        </div>
                        <div class="form-error-msg" id="a_reasonError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="a_doctor">Attending Doctor <span style="color:var(--color-danger);">*</span></label>
                        <select id="a_doctor" name="doctor_id" class="form-select" required>
                            <option value="">— Select Doctor —</option>
                        </select>
                        <div class="form-error-msg" id="a_doctorError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="a_room">Room <span style="color:var(--color-danger);">*</span></label>
                        <select id="a_room" name="room_id" class="form-select" required>
                            <option value="">— Select Room —</option>
                        </select>
                        <div class="form-hint" id="a_room_fee_hint"></div>
                        <div class="form-error-msg" id="a_roomError"></div>
                    </div>

                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="addAdmissionSubmitBtn">
                <i class="fa-solid fa-clipboard-list"></i> Admit Patient
            </button>
        </div>
    </div>
</div>

<!-- Discharge Modal -->
<div class="modal-backdrop" id="dischargeModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-door-open" style="color:var(--color-accent);"></i> Discharge Patient</div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="dischargeSummaryBox" class="mb-4 p-3 rounded-xl text-sm"
                 style="background:var(--color-primary-lt);border:1px solid var(--color-primary-mid);"></div>
            <form id="dischargeForm">
                <input type="hidden" name="action"     value="dischargePatient">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="discharge_id">
                <div class="form-group">
                    <label class="form-label" for="discharged_at">Discharge Date &amp; Time</label>
                    <input type="datetime-local" id="discharged_at" name="discharged_at" class="form-input">
                    <div class="form-hint">Leave blank for current date/time.</div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-success" id="dischargeSubmitBtn">
                <i class="fa-solid fa-door-open"></i> Confirm Discharge
            </button>
        </div>
    </div>
</div>

<!-- Slip Modal -->
<div class="modal-backdrop" id="slipModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-print" style="color:var(--color-primary);"></i> Admission Slip</div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="receipt-preview-wrapper" id="slipContent">
                <div class="text-center py-8"><span class="spinner"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="recPrintSlipBtn" data-admission-id="">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    // Reuse admin admissions AJAX for all operations
    const ADM_URL  = `${window.APP_CONFIG.baseUrl}/ajax/admin/admissions.php`;
    let formDataCache = null;

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

    // ── Load admissions ───────────────────────────────────────
    function loadAdmissions() {
        const search = document.getElementById('searchInput').value.trim();
        const status = document.getElementById('statusFilter').value;
        const params = new URLSearchParams({ action: 'getAdmissions', search, status });

        fetch(`${ADM_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                document.getElementById('admCount').textContent = res.data.admissions.length;
                renderTable(res.data.admissions);
            })
            .catch(() => showToast('Failed to load admissions.', 'error'));
    }

    function renderTable(rows) {
        const tbody = document.getElementById('admissionsBody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="table-empty">
                <div class="table-empty-icon">📋</div>No admissions found.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(a => `
            <tr class="animate-fade-in" data-id="${a.id}">
                <td><span class="font-mono-nums font-bold text-xs" style="color:var(--color-primary);">${escHtml(a.admission_ref)}</span></td>
                <td>
                    <div class="font-semibold text-xs">${escHtml(a.patient_name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${ucFirst(a.gender)}</div>
                </td>
                <td class="text-xs">${escHtml(a.doctor_name)}</td>
                <td>
                    <div class="text-xs font-medium">${escHtml(a.room_number)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${ucFirst(a.room_type)}</div>
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">${escHtml(a.admitted_fmt)}</td>
                <td class="text-xs text-center font-bold">${a.days_count}d</td>
                <td>${a.status === 'admitted'
                    ? '<span class="badge badge-admitted">● Admitted</span>'
                    : '<span class="badge badge-discharged">✓ Discharged</span>'}</td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-icon btn-sm" title="Print Slip"
                                style="color:var(--color-primary);background:var(--color-primary-lt);"
                                onclick="openSlipModal(${a.id})">
                            <i class="fa-solid fa-print"></i>
                        </button>
                        ${a.status === 'admitted' ? `
                        <button class="btn btn-icon btn-sm" title="Discharge"
                                style="color:var(--color-accent);background:var(--color-accent-lt);"
                                onclick="openDischargeModal(${a.id},'${escJs(a.patient_name)}','${escJs(a.estimated_total_fmt)}','${a.days_count}')">
                            <i class="fa-solid fa-door-open"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>`).join('');
    }

    // ── Add admission ─────────────────────────────────────────
    function loadFormData(cb) {
        if (formDataCache) { cb(formDataCache); return; }
        fetch(`${ADM_URL}?${new URLSearchParams({ action: 'getFormData' })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                formDataCache = res.data;
                cb(formDataCache);
            });
    }

    function populateDropdowns(data, selDocId = 0, selRoomId = 0) {
        const docSel  = document.getElementById('a_doctor');
        const roomSel = document.getElementById('a_room');
        const hint    = document.getElementById('a_room_fee_hint');

        docSel.innerHTML = '<option value="">— Select Doctor —</option>' +
            data.doctors.map(d =>
                `<option value="${d.id}" ${+d.id === selDocId ? 'selected' : ''}>
                    Dr. ${escHtml(d.name)} — ${escHtml(d.specialization)}
                 </option>`).join('');

        const grouped = { general:[], private:[], icu:[] };
        data.rooms.forEach(r => grouped[r.room_type]?.push(r));
        const labels  = { general:'🛏 General', private:'🚪 Private', icu:'🏥 ICU' };
        roomSel.innerHTML = '<option value="">— Select Room —</option>';
        for (const [type, rooms] of Object.entries(grouped)) {
            if (!rooms.length) continue;
            const grp = document.createElement('optgroup');
            grp.label = labels[type];
            rooms.forEach(r => {
                const opt      = document.createElement('option');
                opt.value      = r.id;
                opt.text       = `${r.room_number} — ${r.daily_fee_fmt}/day${r.is_occupied && +r.id !== selRoomId ? ' (Occupied)' : ''}`;
                opt.selected   = +r.id === selRoomId;
                if (r.is_occupied && +r.id !== selRoomId) opt.disabled = true;
                grp.appendChild(opt);
            });
            roomSel.appendChild(grp);
        }

        roomSel.addEventListener('change', () => {
            const room = data.rooms.find(r => +r.id === +roomSel.value);
            hint.textContent = room ? `Daily rate: ${room.daily_fee_fmt}` : '';
        });
        roomSel.dispatchEvent(new Event('change'));
    }

    document.getElementById('addAdmissionBtn').addEventListener('click', () => {
        resetForm('addAdmissionForm');
        document.querySelectorAll('#addAdmissionModal .pill-toggle').forEach(b =>
            b.classList.remove('selected'));
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('a_admitted_at').value = now.toISOString().slice(0, 16);
        loadFormData(data => populateDropdowns(data));
        openModal('addAdmissionModal');
        document.getElementById('a_patient_name').focus();
    });

    document.getElementById('addAdmissionSubmitBtn').addEventListener('click', () => {
        // Basic validation
        let valid = true;
        [['a_patient_name','Patient name required.'],
         ['a_guardian_name','Guardian name required.'],
         ['a_address','Address required.'],
         ['a_disease','Diagnosis required.']].forEach(([id, msg]) => {
            const el = document.getElementById(id);
            if (!el || !el.value.trim()) { showFieldError(id, msg); valid = false; }
            else clearFieldError(id);
        });
        if (!document.getElementById('a_gender').value) {
            showFieldError('a_gender', 'Select gender.'); valid = false;
        } else clearFieldError('a_gender');
        if (!document.getElementById('a_reason').value) {
            showFieldError('a_reason', 'Select admission reason.'); valid = false;
        } else clearFieldError('a_reason');
        if (!document.getElementById('a_doctor').value) {
            showFieldError('a_doctor', 'Select a doctor.'); valid = false;
        } else clearFieldError('a_doctor');
        if (!document.getElementById('a_room').value) {
            showFieldError('a_room', 'Select a room.'); valid = false;
        } else clearFieldError('a_room');

        if (!valid) return;

        const btn = document.getElementById('addAdmissionSubmitBtn');
        ajaxRequest(ADM_URL, 'POST', serializeForm(document.getElementById('addAdmissionForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('addAdmissionModal');
                formDataCache = null; // reset so next open fetches fresh room list
                loadAdmissions();
                playSound('success.mp3');
            }, null, btn
        );
    });

    // ── Discharge ─────────────────────────────────────────────
    window.openDischargeModal = function (id, name, totalFmt, days) {
        document.getElementById('discharge_id').value = id;
        document.getElementById('dischargeSummaryBox').innerHTML = `
            <p class="font-bold mb-1" style="color:var(--color-primary);">${escHtml(name)}</p>
            <p class="text-xs" style="color:var(--color-text-muted);">
                Stay: <strong>${days} day(s)</strong> &bull; Est. total: <strong>${escHtml(totalFmt)}</strong>
            </p>`;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('discharged_at').value = now.toISOString().slice(0, 16);
        openModal('dischargeModal');
    };

    document.getElementById('dischargeSubmitBtn').addEventListener('click', () => {
        const btn = document.getElementById('dischargeSubmitBtn');
        ajaxRequest(ADM_URL, 'POST', serializeForm(document.getElementById('dischargeForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('dischargeModal');
                formDataCache = null;
                loadAdmissions();
                playSound('success.mp3');
            }, null, btn
        );
    });

    // ── Slip ──────────────────────────────────────────────────
    window.openSlipModal = function (id) {
        document.getElementById('slipContent').innerHTML =
            '<div class="text-center py-8"><span class="spinner"></span></div>';
        document.getElementById('recPrintSlipBtn').dataset.admissionId = id;
        openModal('slipModal');
        fetch(`${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=admission&id=${id}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('slipContent').innerHTML =
                    res.success ? res.data.html
                                : `<p style="color:var(--color-danger);">${escHtml(res.message)}</p>`;
            });
    };

    document.getElementById('recPrintSlipBtn')?.addEventListener('click', function () {
        const id = this.dataset.admissionId;
        if (id) openPrintWindow('admission', id);
    });

    // ── Filters ───────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', debounce(loadAdmissions, 300));
    document.getElementById('statusFilter').addEventListener('change', loadAdmissions);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value  = '';
        document.getElementById('statusFilter').value = '';
        loadAdmissions();
    });

    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escJs(s)   { return String(s??'').replace(/'/g,"\\'"); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    loadAdmissions();

})();
JS;

require_once BASE_PATH . '/receptionist/footer.php';
?>