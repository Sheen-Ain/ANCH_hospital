<?php
/**
 * admin/doctors.php
 * Doctor management: list, add, edit, toggle active/inactive, delete.
 * All mutations go through ajax/admin/doctors.php — zero page reloads.
 */

$pageTitle  = 'Doctors';
$activePage = 'doctors';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-user-doctor" style="color:var(--color-primary);"></i>
        Doctors
    </h2>
    <button class="btn btn-primary" id="addDoctorBtn">
        <i class="fa-solid fa-plus"></i>
        Add Doctor
    </button>
</div>

<!-- ── Filters Bar ───────────────────────────────────────── -->
<div class="filters-bar">
    <div class="flex-1" style="min-width:180px;max-width:320px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input" placeholder="Name, specialization, email…"
                   style="padding-left:36px;">
        </div>
    </div>

    <div>
        <label class="form-label">Status</label>
        <select id="statusFilter" class="form-select" style="min-width:130px;">
            <option value="">All Statuses</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
        </select>
    </div>

    <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" style="align-self:flex-end;">
        <i class="fa-solid fa-xmark"></i> Clear
    </button>

    <div class="ml-auto" style="align-self:flex-end;">
        <span class="text-sm" style="color:var(--color-text-muted);">
            Showing <strong id="doctorCount">—</strong> doctor(s)
        </span>
    </div>
</div>

<!-- ── Doctors Table ─────────────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="table-wrapper">
        <table class="data-table" id="doctorsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name &amp; Specialization</th>
                    <th>Contact</th>
                    <th>Fee</th>
                    <th>Today's Patients</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="doctorsBody">
                <tr>
                    <td colspan="7" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                        Loading doctors…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ADD DOCTOR MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="addDoctorModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-user-doctor" style="color:var(--color-primary);"></i>
                Add New Doctor
            </div>
            <button class="modal-close" data-modal-close>
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="addDoctorForm" novalidate>
                <input type="hidden" name="action" value="createDoctor">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <!-- Name -->
                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="add_name">Full Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_name" name="name" class="form-input"
                               placeholder="e.g. Dr. Hassan Raza" maxlength="150" required>
                        <div class="form-error-msg" id="add_nameError"></div>
                    </div>

                    <!-- Specialization -->
                    <div class="form-group">
                        <label class="form-label" for="add_specialization">Specialization <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_specialization" name="specialization" class="form-input"
                               placeholder="e.g. Cardiology" required>
                        <div class="form-error-msg" id="add_specializationError"></div>
                    </div>

                    <!-- Designation -->
                    <div class="form-group">
                        <label class="form-label" for="add_designation">Designation / Qualifications</label>
                        <input type="text" id="add_designation" name="designation" class="form-input"
                               placeholder="e.g. MBBS, FCPS">
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label" for="add_email">Email Address</label>
                        <input type="email" id="add_email" name="email" class="form-input"
                               placeholder="doctor@hospital.com">
                        <div class="form-error-msg" id="add_emailError"></div>
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label" for="add_phone">Phone Number</label>
                        <input type="text" id="add_phone" name="phone" class="form-input"
                               placeholder="0311-1234567">
                    </div>

                    <!-- Consultation Fee -->
                    <div class="form-group">
                        <label class="form-label" for="add_fee">Consultation Fee (<?= e(getSetting('currency_symbol', 'Rs.')) ?>) <span style="color:var(--color-danger);">*</span></label>
                        <input type="number" id="add_fee" name="fee" class="form-input"
                               placeholder="0.00" min="0" step="50" required>
                        <div class="form-error-msg" id="add_feeError"></div>
                    </div>

                    <!-- Gender -->
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <input type="hidden" name="gender" id="add_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="add_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="add_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="add_gender">⚧ Other</button>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-group md:col-span-2">
                        <label class="form-label">Status</label>
                        <input type="hidden" name="is_active" id="add_is_active" value="1">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle selected" data-value="1" data-field="add_is_active">✅ Active</button>
                            <button type="button" class="pill-toggle" data-value="0" data-field="add_is_active">🚫 Inactive</button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="addDoctorSubmitBtn">
                <i class="fa-solid fa-plus"></i> Add Doctor
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     EDIT DOCTOR MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editDoctorModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-warning);"></i>
                Edit Doctor
            </div>
            <button class="modal-close" data-modal-close>
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="editDoctorForm" novalidate>
                <input type="hidden" name="action" value="updateDoctor">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="edit_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="edit_name">Full Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_name" name="name" class="form-input" maxlength="150" required>
                        <div class="form-error-msg" id="edit_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_specialization">Specialization <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_specialization" name="specialization" class="form-input" required>
                        <div class="form-error-msg" id="edit_specializationError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_designation">Designation / Qualifications</label>
                        <input type="text" id="edit_designation" name="designation" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_email">Email Address</label>
                        <input type="email" id="edit_email" name="email" class="form-input">
                        <div class="form-error-msg" id="edit_emailError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_phone">Phone Number</label>
                        <input type="text" id="edit_phone" name="phone" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_fee">Consultation Fee (<?= e(getSetting('currency_symbol', 'Rs.')) ?>) <span style="color:var(--color-danger);">*</span></label>
                        <input type="number" id="edit_fee" name="fee" class="form-input" min="0" step="50" required>
                        <div class="form-error-msg" id="edit_feeError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <input type="hidden" name="gender" id="edit_gender" value="">
                        <div class="pill-toggle-group" id="edit_genderGroup">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="edit_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="edit_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="edit_gender">⚧ Other</button>
                        </div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Status</label>
                        <input type="hidden" name="is_active" id="edit_is_active" value="1">
                        <div class="pill-toggle-group" id="edit_statusGroup">
                            <button type="button" class="pill-toggle" data-value="1" data-field="edit_is_active">✅ Active</button>
                            <button type="button" class="pill-toggle" data-value="0" data-field="edit_is_active">🚫 Inactive</button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="editDoctorSubmitBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/admin/doctors.php`;

    // ── Pill toggle helper ────────────────────────────────────
    // Handles all .pill-toggle buttons in any form.
    document.querySelectorAll('.pill-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const fieldId = btn.dataset.field;
            const value   = btn.dataset.value;
            if (!fieldId) return;

            // Deselect siblings in same group
            document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b => {
                b.classList.remove('selected', 'selected-green', 'selected-amber');
            });

            btn.classList.add('selected');
            document.getElementById(fieldId).value = value;
        });
    });

    // ── Load & render doctors table ───────────────────────────
    function loadDoctors() {
        const search = document.getElementById('searchInput').value.trim();
        const status = document.getElementById('statusFilter').value;
        const params = new URLSearchParams({ action: 'getDoctors', search, status });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                renderTable(res.data.doctors);
            })
            .catch(() => showToast('Failed to load doctors.', 'error'));
    }

    function renderTable(doctors) {
        const tbody = document.getElementById('doctorsBody');
        document.getElementById('doctorCount').textContent = doctors.length;

        if (!doctors.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="table-empty">
                        <div class="table-empty-icon">👨‍⚕️</div>
                        No doctors found. Add the first one!
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = doctors.map((d, i) => `
            <tr class="animate-fade-in" data-id="${d.id}">
                <td class="text-xs font-mono-nums" style="color:var(--color-text-faint);">${i + 1}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-lt);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--color-primary);flex-shrink:0;">
                            ${d.gender === 'female' ? '👩‍⚕️' : '👨‍⚕️'}
                        </div>
                        <div>
                            <div class="font-semibold text-sm">${escHtml(d.name)}</div>
                            <div class="text-xs" style="color:var(--color-text-muted);">
                                ${escHtml(d.specialization)}
                                ${d.designation ? ' &bull; ' + escHtml(d.designation) : ''}
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-xs">${d.email ? escHtml(d.email) : '<span style="color:var(--color-text-faint);">—</span>'}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${d.phone ? escHtml(d.phone) : ''}</div>
                </td>
                <td>
                    <span class="font-mono-nums font-bold text-sm" style="color:var(--color-accent);">
                        ${escHtml(d.fee_fmt)}
                    </span>
                </td>
                <td>
                    <span class="badge badge-active">${d.today_patients} today</span>
                    <span class="text-xs ml-1" style="color:var(--color-text-faint);">${d.total_patients} total</span>
                </td>
                <td>
                    <button class="toggle-switch" onclick="toggleDoctor(${d.id}, this)" title="${d.is_active ? 'Deactivate' : 'Activate'}">
                        <input type="checkbox" class="toggle-input" ${d.is_active ? 'checked' : ''} readonly>
                        <span class="toggle-track"></span>
                        <span class="text-xs font-medium" style="color:var(--color-text-muted);">
                            ${d.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </button>
                </td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-ghost btn-icon btn-sm" title="Edit Doctor"
                                onclick="openEditModal(${d.id})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-icon btn-sm" title="Delete Doctor"
                                style="color:var(--color-danger);background:var(--color-danger-lt);"
                                onclick="confirmDelete(${d.id}, '${escJs(d.name)}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // ── Add Doctor ────────────────────────────────────────────
    document.getElementById('addDoctorBtn').addEventListener('click', () => {
        resetForm('addDoctorForm');
        // Reset pills to defaults
        setActivePill('add_is_active', '1');
        openModal('addDoctorModal');
        document.getElementById('add_name').focus();
    });

    document.getElementById('addDoctorSubmitBtn').addEventListener('click', () => {
        const form = document.getElementById('addDoctorForm');
        if (!clientValidate(form, 'add')) return;
        const btn = document.getElementById('addDoctorSubmitBtn');
        const fd  = serializeForm(form);

        ajaxRequest(AJAX_URL, 'POST', fd,
            (data, msg) => {
                showToast(msg, 'success');
                closeModal('addDoctorModal');
                resetForm('addDoctorForm');
                loadDoctors();
                playSound('success.mp3');
            },
            null, btn
        );
    });

    // ── Edit Doctor ───────────────────────────────────────────
    window.openEditModal = function (id) {
        const params = new URLSearchParams({ action: 'getDoctorById', id });
        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const d = res.data.doctor;

                document.getElementById('edit_id').value             = d.id;
                document.getElementById('edit_name').value           = d.name;
                document.getElementById('edit_specialization').value = d.specialization;
                document.getElementById('edit_designation').value    = d.designation || '';
                document.getElementById('edit_email').value          = d.email  || '';
                document.getElementById('edit_phone').value          = d.phone  || '';
                document.getElementById('edit_fee').value            = d.fee;

                // Set pill toggles
                if (d.gender) setActivePill('edit_gender', d.gender);
                else clearPills('edit_gender');
                setActivePill('edit_is_active', String(d.is_active));

                openModal('editDoctorModal');
                document.getElementById('edit_name').focus();
            })
            .catch(() => showToast('Failed to load doctor data.', 'error'));
    };

    document.getElementById('editDoctorSubmitBtn').addEventListener('click', () => {
        const form = document.getElementById('editDoctorForm');
        if (!clientValidate(form, 'edit')) return;
        const btn = document.getElementById('editDoctorSubmitBtn');
        const fd  = serializeForm(form);

        ajaxRequest(AJAX_URL, 'POST', fd,
            (data, msg) => {
                showToast(msg, 'success');
                closeModal('editDoctorModal');
                loadDoctors();
            },
            null, btn
        );
    });

    // ── Toggle Active ─────────────────────────────────────────
    window.toggleDoctor = function (id, triggerEl) {
        const fd = new FormData();
        fd.set('action', 'toggleDoctor');
        fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.set('id', id);

        ajaxRequest(AJAX_URL, 'POST', fd,
            (data, msg) => {
                showToast(msg, 'success');
                loadDoctors();
            }
        );
    };

    // ── Delete ────────────────────────────────────────────────
    window.confirmDelete = function (id, name) {
        showConfirmModal(
            'Delete Doctor',
            `Are you sure you want to delete <strong>Dr. ${escHtml(name)}</strong>? This cannot be undone.`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deleteDoctor');
                fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                fd.set('id', id);

                ajaxRequest(AJAX_URL, 'POST', fd,
                    (data, msg) => { showToast(msg, 'success'); loadDoctors(); },
                    null, null
                );
            },
            'danger'
        );
    };

    // ── Client-side validation ────────────────────────────────
    function clientValidate(form, prefix) {
        let valid = true;
        const name = form.querySelector(`#${prefix}_name`);
        const spec = form.querySelector(`#${prefix}_specialization`);
        const fee  = form.querySelector(`#${prefix}_fee`);

        if (!name.value.trim()) { showFieldError(`${prefix}_name`, 'Doctor name is required.'); valid = false; }
        else clearFieldError(`${prefix}_name`);

        if (!spec.value.trim()) { showFieldError(`${prefix}_specialization`, 'Specialization is required.'); valid = false; }
        else clearFieldError(`${prefix}_specialization`);

        if (fee.value === '' || isNaN(fee.value) || parseFloat(fee.value) < 0) {
            showFieldError(`${prefix}_fee`, 'Please enter a valid consultation fee.');
            valid = false;
        } else clearFieldError(`${prefix}_fee`);

        return valid;
    }

    // ── Pill helpers ──────────────────────────────────────────
    function setActivePill(fieldId, value) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b => {
            b.classList.toggle('selected', b.dataset.value === value);
        });
        const hidden = document.getElementById(fieldId);
        if (hidden) hidden.value = value;
    }

    function clearPills(fieldId) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b => {
            b.classList.remove('selected', 'selected-green', 'selected-amber');
        });
        const hidden = document.getElementById(fieldId);
        if (hidden) hidden.value = '';
    }

    // ── Utility ───────────────────────────────────────────────
    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJs(str) {
        return String(str ?? '').replace(/'/g, "\\'");
    }

    // ── Filters ───────────────────────────────────────────────
    const debouncedLoad = debounce(loadDoctors, 300);
    document.getElementById('searchInput').addEventListener('input', debouncedLoad);
    document.getElementById('statusFilter').addEventListener('change', loadDoctors);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        loadDoctors();
    });

    // ── Init ──────────────────────────────────────────────────
    loadDoctors();

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>
