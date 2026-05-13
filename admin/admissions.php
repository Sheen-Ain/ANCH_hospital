<?php
/**
 * admin/admissions.php
 * Admissions management: list, admit new patient, edit, discharge, delete, print slip.
 */

$pageTitle  = 'Admissions';
$activePage = 'admissions';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-clipboard-list" style="color:var(--color-primary);"></i>
        Admissions
    </h2>
    <button class="btn btn-primary" id="addAdmissionBtn">
        <i class="fa-solid fa-plus"></i>
        Admit Patient
    </button>
</div>

<!-- ── Summary Strip ─────────────────────────────────────── -->
<div class="stats-grid mb-4" id="admStatsGrid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));">
    <?php foreach (['Total Records','Currently Admitted','Discharged'] as $lbl): ?>
    <div class="stat-card">
        <div class="stat-card-icon slate"><span class="spinner"></span></div>
        <div><div class="stat-card-label"><?= $lbl ?></div><div class="stat-number">—</div></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filters ───────────────────────────────────────────── -->
<div class="filters-bar mb-4">
    <div class="flex-1" style="min-width:180px;max-width:300px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="Patient, doctor, room, disease…" style="padding-left:36px;">
        </div>
    </div>
    <div>
        <label class="form-label">Status</label>
        <select id="statusFilter" class="form-select" style="min-width:140px;">
            <option value="">All</option>
            <option value="admitted">Admitted</option>
            <option value="discharged">Discharged</option>
        </select>
    </div>
    <div>
        <label class="form-label">Room Type</label>
        <select id="roomTypeFilter" class="form-select" style="min-width:130px;">
            <option value="">All Types</option>
            <option value="general">General</option>
            <option value="private">Private</option>
            <option value="icu">ICU</option>
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
        <table class="data-table" id="admissionsTable">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Room</th>
                    <th>Admitted</th>
                    <th>Days</th>
                    <th>Est. Total</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="admissionsBody">
                <tr>
                    <td colspan="9" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                        Loading admissions…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ADD ADMISSION MODAL
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
                        <label class="form-label" for="add_patient_name">Patient Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_patient_name" name="patient_name" class="form-input" placeholder="Full name" required>
                        <div class="form-error-msg" id="add_patient_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_guardian_name">Father / Husband Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_guardian_name" name="guardian_name" class="form-input" placeholder="Guardian name" required>
                        <div class="form-error-msg" id="add_guardian_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="gender" id="add_adm_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="add_adm_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="add_adm_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="add_adm_gender">⚧ Other</button>
                        </div>
                        <div class="form-error-msg" id="add_adm_genderError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_adm_admitted_at">Admission Date &amp; Time</label>
                        <input type="datetime-local" id="add_adm_admitted_at" name="admitted_at" class="form-input">
                        <div class="form-hint">Leave blank to use current date/time.</div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="add_adm_address">Address <span style="color:var(--color-danger);">*</span></label>
                        <textarea id="add_adm_address" name="address" class="form-textarea"
                                  placeholder="Patient's home address" rows="2" required></textarea>
                        <div class="form-error-msg" id="add_adm_addressError"></div>
                    </div>

                    <!-- Clinical -->
                    <div class="form-group">
                        <label class="form-label" for="add_adm_disease">Disease / Diagnosis <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_adm_disease" name="disease_name" class="form-input" placeholder="e.g. Myocardial Infarction" required>
                        <div class="form-error-msg" id="add_adm_diseaseError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_adm_treatment_cost">Treatment Cost (<?= e(getSetting('currency_symbol', 'Rs.')) ?>)</label>
                        <input type="number" id="add_adm_treatment_cost" name="disease_treatment_cost"
                               class="form-input" placeholder="0.00" min="0" step="500" value="0">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Admission Reason <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="admission_reason" id="add_adm_reason" value="">
                        <div class="pill-toggle-group flex-wrap">
                            <button type="button" class="pill-toggle" data-value="operation"   data-field="add_adm_reason">🔪 Operation</button>
                            <button type="button" class="pill-toggle" data-value="observation"  data-field="add_adm_reason">👁 Observation</button>
                            <button type="button" class="pill-toggle" data-value="emergency"    data-field="add_adm_reason">🚨 Emergency</button>
                            <button type="button" class="pill-toggle" data-value="treatment"    data-field="add_adm_reason">💊 Treatment</button>
                            <button type="button" class="pill-toggle" data-value="other"        data-field="add_adm_reason">📋 Other</button>
                        </div>
                        <div class="form-error-msg" id="add_adm_reasonError"></div>
                    </div>

                    <!-- Doctor + Room -->
                    <div class="form-group">
                        <label class="form-label" for="add_adm_doctor">Attending Doctor <span style="color:var(--color-danger);">*</span></label>
                        <select id="add_adm_doctor" name="doctor_id" class="form-select" required>
                            <option value="">— Select Doctor —</option>
                        </select>
                        <div class="form-error-msg" id="add_adm_doctorError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_adm_room">Room <span style="color:var(--color-danger);">*</span></label>
                        <select id="add_adm_room" name="room_id" class="form-select" required>
                            <option value="">— Select Room —</option>
                        </select>
                        <div class="form-hint" id="add_room_fee_hint"></div>
                        <div class="form-error-msg" id="add_adm_roomError"></div>
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

<!-- ════════════════════════════════════════════════════════
     VIEW / EDIT ADMISSION MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editAdmissionModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title" id="editAdmissionTitle">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-warning);"></i>
                Edit Admission
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="editAdmissionForm" novalidate>
                <input type="hidden" name="action"     value="updateAdmission">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="edit_adm_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <div class="form-group">
                        <label class="form-label" for="edit_patient_name">Patient Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_patient_name" name="patient_name" class="form-input" required>
                        <div class="form-error-msg" id="edit_patient_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_guardian_name">Father / Husband Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_guardian_name" name="guardian_name" class="form-input" required>
                        <div class="form-error-msg" id="edit_guardian_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="gender" id="edit_adm_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="edit_adm_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="edit_adm_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="edit_adm_gender">⚧ Other</button>
                        </div>
                        <div class="form-error-msg" id="edit_adm_genderError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_adm_admitted_at">Admission Date &amp; Time</label>
                        <input type="datetime-local" id="edit_adm_admitted_at" name="admitted_at" class="form-input">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="edit_adm_address">Address <span style="color:var(--color-danger);">*</span></label>
                        <textarea id="edit_adm_address" name="address" class="form-textarea" rows="2" required></textarea>
                        <div class="form-error-msg" id="edit_adm_addressError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_adm_disease">Disease / Diagnosis <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_adm_disease" name="disease_name" class="form-input" required>
                        <div class="form-error-msg" id="edit_adm_diseaseError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_adm_treatment_cost">Treatment Cost</label>
                        <input type="number" id="edit_adm_treatment_cost" name="disease_treatment_cost"
                               class="form-input" min="0" step="500">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Admission Reason <span style="color:var(--color-danger);">*</span></label>
                        <input type="hidden" name="admission_reason" id="edit_adm_reason" value="">
                        <div class="pill-toggle-group flex-wrap">
                            <button type="button" class="pill-toggle" data-value="operation"  data-field="edit_adm_reason">🔪 Operation</button>
                            <button type="button" class="pill-toggle" data-value="observation" data-field="edit_adm_reason">👁 Observation</button>
                            <button type="button" class="pill-toggle" data-value="emergency"   data-field="edit_adm_reason">🚨 Emergency</button>
                            <button type="button" class="pill-toggle" data-value="treatment"   data-field="edit_adm_reason">💊 Treatment</button>
                            <button type="button" class="pill-toggle" data-value="other"       data-field="edit_adm_reason">📋 Other</button>
                        </div>
                        <div class="form-error-msg" id="edit_adm_reasonError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_adm_doctor">Attending Doctor <span style="color:var(--color-danger);">*</span></label>
                        <select id="edit_adm_doctor" name="doctor_id" class="form-select" required>
                            <option value="">— Select Doctor —</option>
                        </select>
                        <div class="form-error-msg" id="edit_adm_doctorError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_adm_room">Room <span style="color:var(--color-danger);">*</span></label>
                        <select id="edit_adm_room" name="room_id" class="form-select" required>
                            <option value="">— Select Room —</option>
                        </select>
                        <div class="form-hint" id="edit_room_fee_hint"></div>
                        <div class="form-error-msg" id="edit_adm_roomError"></div>
                    </div>

                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="editAdmissionSubmitBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     DISCHARGE MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="dischargeModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-door-open" style="color:var(--color-accent);"></i>
                Discharge Patient
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="dischargeSummaryBox" class="mb-4 p-3 rounded-xl text-sm"
                 style="background:var(--color-primary-lt);border:1px solid var(--color-primary-mid);">
            </div>
            <form id="dischargeForm">
                <input type="hidden" name="action"     value="dischargePatient">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="discharge_id">
                <div class="form-group">
                    <label class="form-label" for="discharged_at">Discharge Date &amp; Time</label>
                    <input type="datetime-local" id="discharged_at" name="discharged_at" class="form-input">
                    <div class="form-hint">Leave blank to use current date/time.</div>
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

<!-- ════════════════════════════════════════════════════════
     SLIP PREVIEW MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="slipModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-print" style="color:var(--color-primary);"></i>
                Admission Slip
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="receipt-preview-wrapper" id="slipContent">
                <div class="text-center py-8"><span class="spinner"></span> Generating slip…</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="printSlipBtn" data-admission-id="">
                <i class="fa-solid fa-print"></i> Print Slip
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL    = `${window.APP_CONFIG.baseUrl}/ajax/admin/admissions.php`;
    const SLIP_URL    = `${window.APP_CONFIG.baseUrl}/ajax/print-slip.php`;
    let   formDataCache = null; // doctors + rooms, loaded once and re-used

    // ── Pill toggle wiring ────────────────────────────────────
    document.querySelectorAll('.pill-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const f = btn.dataset.field;
            if (!f) return;
            document.querySelectorAll(`.pill-toggle[data-field="${f}"]`).forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById(f).value = btn.dataset.value;
        });
    });

    // ── Load form data (doctors + rooms) ──────────────────────
    function loadFormData(currentRoomId = 0, onDone = null) {
        const params = new URLSearchParams({ action: 'getFormData', current_room_id: currentRoomId });
        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                formDataCache = res.data;
                if (onDone) onDone(res.data);
            });
    }

    function populateDoctorSelect(selectId, selectedId = 0) {
        const sel = document.getElementById(selectId);
        if (!sel || !formDataCache) return;
        sel.innerHTML = '<option value="">— Select Doctor —</option>' +
            formDataCache.doctors.map(d =>
                `<option value="${d.id}" ${+d.id === +selectedId ? 'selected' : ''}>
                    Dr. ${escHtml(d.name)} — ${escHtml(d.specialization)}
                </option>`
            ).join('');
    }

    function populateRoomSelect(selectId, hintId, selectedId = 0) {
        const sel  = document.getElementById(selectId);
        const hint = document.getElementById(hintId);
        if (!sel || !formDataCache) return;

        sel.innerHTML = '<option value="">— Select Room —</option>';
        const grouped = { general: [], private: [], icu: [] };
        formDataCache.rooms.forEach(r => grouped[r.room_type]?.push(r));

        const typeLabels = { general: '🛏 General', private: '🚪 Private', icu: '🏥 ICU' };
        for (const [type, rooms] of Object.entries(grouped)) {
            if (!rooms.length) continue;
            const grp = document.createElement('optgroup');
            grp.label = typeLabels[type];
            rooms.forEach(r => {
                const opt  = document.createElement('option');
                opt.value  = r.id;
                opt.text   = `${r.room_number} — ${formatCurrency(r.daily_fee)}/day${r.is_occupied && +r.id !== +selectedId ? ' (Occupied)' : ''}`;
                opt.selected = +r.id === +selectedId;
                if (r.is_occupied && +r.id !== +selectedId) opt.disabled = true;
                grp.appendChild(opt);
            });
            sel.appendChild(grp);
        }

        // Room fee hint on change
        sel.addEventListener('change', () => {
            const room = formDataCache.rooms.find(r => +r.id === +sel.value);
            if (hint) hint.textContent = room ? `Daily rate: ${room.daily_fee_fmt}` : '';
        });
        // Trigger on load for pre-selected room
        sel.dispatchEvent(new Event('change'));
    }

    // ── Load admissions ───────────────────────────────────────
    function loadAdmissions() {
        const search   = document.getElementById('searchInput').value.trim();
        const status   = document.getElementById('statusFilter').value;
        const roomType = document.getElementById('roomTypeFilter').value;
        const params   = new URLSearchParams({ action: 'getAdmissions', search, status, room_type: roomType });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                renderStats(res.data.summary);
                renderTable(res.data.admissions);
            })
            .catch(() => showToast('Failed to load admissions.', 'error'));
    }

    // ── Stats Strip ───────────────────────────────────────────
    function renderStats(s) {
        const cfgs = [
            { label:'Total Records',       value: s.total,      icon:'fa-clipboard-list', color:'blue'   },
            { label:'Currently Admitted',  value: s.admitted,   icon:'fa-bed',            color:'violet' },
            { label:'Discharged',          value: s.discharged, icon:'fa-door-open',      color:'green'  },
        ];
        document.getElementById('admStatsGrid').innerHTML = cfgs.map(c => `
            <div class="stat-card animate-fade-in">
                <div class="stat-card-icon ${c.color}"><i class="fa-solid ${c.icon}"></i></div>
                <div><div class="stat-card-label">${c.label}</div><div class="stat-number">${c.value}</div></div>
            </div>`).join('');
    }

    // ── Table ─────────────────────────────────────────────────
    function renderTable(rows) {
        const tbody = document.getElementById('admissionsBody');
        document.getElementById('admCount').textContent = rows.length;

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="table-empty">
                <div class="table-empty-icon">📋</div>No admission records found.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(a => {
            const statusBadge = a.status === 'admitted'
                ? `<span class="badge badge-admitted">● Admitted</span>`
                : `<span class="badge badge-discharged">✓ Discharged</span>`;

            return `
            <tr class="animate-fade-in" data-id="${a.id}">
                <td><span class="font-mono-nums font-bold text-xs" style="color:var(--color-primary);">${escHtml(a.admission_ref)}</span></td>
                <td>
                    <div class="font-semibold text-xs">${escHtml(a.patient_name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${ucFirst(a.gender)} &bull; ${escHtml(a.guardian_name)}</div>
                </td>
                <td class="text-xs">${escHtml(a.doctor_name)}</td>
                <td>
                    <div class="text-xs font-medium">${escHtml(a.room_number)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${ucFirst(a.room_type)}</div>
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">${escHtml(a.admitted_fmt)}</td>
                <td class="text-xs text-center font-bold">${a.days_count}d</td>
                <td class="text-xs font-bold" style="color:var(--color-accent);">${escHtml(a.estimated_total_fmt)}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-ghost btn-icon btn-sm" title="View/Edit" onclick="openEditModal(${a.id})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        ${a.status === 'admitted' ? `
                        <button class="btn btn-icon btn-sm" title="Discharge"
                                style="color:var(--color-accent);background:var(--color-accent-lt);"
                                onclick="openDischargeModal(${a.id},'${escJs(a.patient_name)}','${escJs(a.estimated_total_fmt)}','${escJs(a.days_count)}')">
                            <i class="fa-solid fa-door-open"></i>
                        </button>` : ''}
                        <button class="btn btn-icon btn-sm" title="Print Slip"
                                style="color:var(--color-primary);background:var(--color-primary-lt);"
                                onclick="openSlipModal(${a.id})">
                            <i class="fa-solid fa-print"></i>
                        </button>
                        ${a.status === 'discharged' ? `
                        <button class="btn btn-icon btn-sm" title="Delete"
                                style="color:var(--color-danger);background:var(--color-danger-lt);"
                                onclick="confirmDelete(${a.id},'${escJs(a.patient_name)}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Add Admission ─────────────────────────────────────────
    document.getElementById('addAdmissionBtn').addEventListener('click', () => {
        resetForm('addAdmissionForm');
        clearAllPills();

        // Set datetime-local default to now
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('add_adm_admitted_at').value = now.toISOString().slice(0, 16);

        loadFormData(0, data => {
            populateDoctorSelect('add_adm_doctor');
            populateRoomSelect('add_adm_room', 'add_room_fee_hint');
        });
        openModal('addAdmissionModal');
        document.getElementById('add_patient_name').focus();
    });

    document.getElementById('addAdmissionSubmitBtn').addEventListener('click', () => {
        if (!clientValidate('add')) return;
        const btn = document.getElementById('addAdmissionSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('addAdmissionForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('addAdmissionModal');
                loadAdmissions();
                playSound('success.mp3');
            }, null, btn
        );
    });

    // ── Edit Admission ────────────────────────────────────────
    window.openEditModal = function (id) {
        const params = new URLSearchParams({ action: 'getAdmissionById', id });
        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const a = res.data.admission;

                document.getElementById('edit_adm_id').value              = a.id;
                document.getElementById('edit_patient_name').value        = a.patient_name;
                document.getElementById('edit_guardian_name').value       = a.guardian_name;
                document.getElementById('edit_adm_address').value         = a.address;
                document.getElementById('edit_adm_disease').value         = a.disease_name;
                document.getElementById('edit_adm_treatment_cost').value  = a.disease_treatment_cost;

                // admitted_at → datetime-local format
                const admDt = new Date(a.admitted_at.replace(' ', 'T'));
                admDt.setMinutes(admDt.getMinutes() - admDt.getTimezoneOffset());
                document.getElementById('edit_adm_admitted_at').value = admDt.toISOString().slice(0, 16);

                setPill('edit_adm_gender', a.gender);
                setPill('edit_adm_reason', a.admission_reason);

                const isDischarge = a.status === 'discharged';
                document.getElementById('editAdmissionTitle').innerHTML =
                    `<i class="fa-solid fa-${isDischarge ? 'eye':'pen-to-square'}" style="color:var(--color-${isDischarge?'text-muted':'warning'});"></i>
                     ${isDischarge ? 'View Admission' : 'Edit Admission'}`;
                document.getElementById('editAdmissionSubmitBtn').style.display = isDischarge ? 'none' : '';

                loadFormData(a.room_id, () => {
                    populateDoctorSelect('edit_adm_doctor', a.doctor_id);
                    populateRoomSelect('edit_adm_room', 'edit_room_fee_hint', a.room_id);
                });

                openModal('editAdmissionModal');
            })
            .catch(() => showToast('Failed to load admission data.', 'error'));
    };

    document.getElementById('editAdmissionSubmitBtn').addEventListener('click', () => {
        if (!clientValidate('edit')) return;
        const btn = document.getElementById('editAdmissionSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('editAdmissionForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('editAdmissionModal');
                loadAdmissions();
            }, null, btn
        );
    });

    // ── Discharge ─────────────────────────────────────────────
    window.openDischargeModal = function (id, name, totalFmt, days) {
        document.getElementById('discharge_id').value = id;
        document.getElementById('dischargeSummaryBox').innerHTML = `
            <p class="font-bold mb-1" style="color:var(--color-primary);">${escHtml(name)}</p>
            <p class="text-xs" style="color:var(--color-text-muted);">
                Stay: <strong>${days} day(s)</strong> &bull; Estimated total: <strong>${escHtml(totalFmt)}</strong>
            </p>`;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('discharged_at').value = now.toISOString().slice(0, 16);
        openModal('dischargeModal');
    };

    document.getElementById('dischargeSubmitBtn').addEventListener('click', () => {
        const btn = document.getElementById('dischargeSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('dischargeForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('dischargeModal');
                loadAdmissions();
                playSound('success.mp3');
            }, null, btn
        );
    });

    // ── Slip ──────────────────────────────────────────────────
    window.openSlipModal = function (id) {
        document.getElementById('slipContent').innerHTML =
            '<div class="text-center py-8"><span class="spinner"></span> Generating…</div>';
        document.getElementById('printSlipBtn').dataset.admissionId = id;
        openModal('slipModal');

        fetch(`${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=admission&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('slipContent').innerHTML = res.data.html;
                } else {
                    document.getElementById('slipContent').innerHTML =
                        `<p style="color:var(--color-danger);">${escHtml(res.message)}</p>`;
                }
            })
            .catch(() => {
                document.getElementById('slipContent').innerHTML =
                    '<p style="color:var(--color-danger);">Failed to generate slip.</p>';
            });
    };

    document.getElementById('printSlipBtn').addEventListener('click', function () {
        const id = this.dataset.admissionId;
        if (id) openPrintWindow('admission', id);
    });

    // ── Delete ────────────────────────────────────────────────
    window.confirmDelete = function (id, name) {
        showConfirmModal(
            'Delete Admission Record',
            `Delete admission record for <strong>${escHtml(name)}</strong>? This cannot be undone.`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deleteAdmission');
                fd.set('csrf_token', getCsrfToken());
                fd.set('id', id);
                ajaxRequest(AJAX_URL, 'POST', fd,
                    (_, msg) => { showToast(msg, 'success'); loadAdmissions(); }
                );
            }, 'danger'
        );
    };

    // ── Client Validation ─────────────────────────────────────
    function clientValidate(prefix) {
        let valid = true;
        const checks = [
            [`${prefix}_patient_name`,  'Patient name is required.'],
            [`${prefix}_guardian_name`, 'Guardian name is required.'],
            [`${prefix}_adm_address`,   'Address is required.'],
            [`${prefix}_adm_disease`,   'Diagnosis is required.'],
        ];
        checks.forEach(([id, msg]) => {
            const el = document.getElementById(id);
            if (!el || !el.value.trim()) { showFieldError(id, msg); valid = false; }
            else clearFieldError(id);
        });

        const genderId = `${prefix}_adm_gender`;
        if (!document.getElementById(genderId)?.value) {
            showFieldError(genderId, 'Please select gender.'); valid = false;
        } else clearFieldError(genderId);

        const reasonId = `${prefix}_adm_reason`;
        if (!document.getElementById(reasonId)?.value) {
            showFieldError(reasonId, 'Please select admission reason.'); valid = false;
        } else clearFieldError(reasonId);

        const doctorId = `${prefix}_adm_doctor`;
        if (!document.getElementById(doctorId)?.value) {
            showFieldError(doctorId, 'Please select a doctor.'); valid = false;
        } else clearFieldError(doctorId);

        const roomId = `${prefix}_adm_room`;
        if (!document.getElementById(roomId)?.value) {
            showFieldError(roomId, 'Please select a room.'); valid = false;
        } else clearFieldError(roomId);

        return valid;
    }

    // ── Pill helpers ──────────────────────────────────────────
    function setPill(fieldId, value) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b =>
            b.classList.toggle('selected', b.dataset.value === value));
        const h = document.getElementById(fieldId);
        if (h) h.value = value;
    }
    function clearAllPills() {
        document.querySelectorAll('.pill-toggle').forEach(b => b.classList.remove('selected'));
        document.querySelectorAll('input[type=hidden].pill-value').forEach(h => h.value = '');
    }

    // ── Utilities ─────────────────────────────────────────────
    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escJs(s)   { return String(s??'').replace(/'/g,"\\'"); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ── Filters ───────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', debounce(loadAdmissions, 300));
    document.getElementById('statusFilter').addEventListener('change', loadAdmissions);
    document.getElementById('roomTypeFilter').addEventListener('change', loadAdmissions);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value    = '';
        document.getElementById('statusFilter').value   = '';
        document.getElementById('roomTypeFilter').value = '';
        loadAdmissions();
    });

    // ── Init ──────────────────────────────────────────────────
    loadAdmissions();

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>