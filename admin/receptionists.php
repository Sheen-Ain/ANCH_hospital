<?php
/**
 * admin/receptionists.php
 * Receptionist management: list, add, edit, toggle, delete, reset password.
 */

$pageTitle  = 'Receptionists';
$activePage = 'receptionists';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-user-nurse" style="color:var(--color-primary);"></i>
        Receptionists
    </h2>
    <button class="btn btn-primary" id="addRecBtn">
        <i class="fa-solid fa-plus"></i>
        Add Receptionist
    </button>
</div>

<!-- ── Filters Bar ───────────────────────────────────────── -->
<div class="filters-bar">
    <div class="flex-1" style="min-width:180px;max-width:320px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="Name, email, phone…" style="padding-left:36px;">
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
            Showing <strong id="recCount">—</strong> receptionist(s)
        </span>
    </div>
</div>

<!-- ── Receptionists Table ───────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="table-wrapper">
        <table class="data-table" id="recsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name &amp; Contact</th>
                    <th>Email</th>
                    <th>Patients</th>
                    <th>Online Status</th>
                    <th>Active</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="recsBody">
                <tr>
                    <td colspan="7" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                        Loading receptionists…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ADD RECEPTIONIST MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="addRecModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-user-plus" style="color:var(--color-primary);"></i>
                Add Receptionist
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="addRecForm" novalidate>
                <input type="hidden" name="action"     value="createReceptionist">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <div class="form-group">
                        <label class="form-label" for="add_first_name">First Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_first_name" name="first_name" class="form-input"
                               placeholder="Ayesha" maxlength="100" required>
                        <div class="form-error-msg" id="add_first_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_last_name">Last Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="add_last_name" name="last_name" class="form-input"
                               placeholder="Siddiqui" maxlength="100" required>
                        <div class="form-error-msg" id="add_last_nameError"></div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="add_email">Email Address <span style="color:var(--color-danger);">*</span></label>
                        <input type="email" id="add_email" name="email" class="form-input"
                               placeholder="receptionist@hospital.com" required>
                        <div class="form-error-msg" id="add_emailError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_phone">Phone Number</label>
                        <input type="text" id="add_phone" name="phone" class="form-input"
                               placeholder="0301-2345678">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <input type="hidden" name="gender" id="add_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="add_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="add_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="add_gender">⚧ Other</button>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="add_password">Password <span style="color:var(--color-danger);">*</span></label>
                        <div style="position:relative;">
                            <input type="password" id="add_password" name="password" class="form-input"
                                   placeholder="Min. 6 characters" autocomplete="new-password"
                                   style="padding-right:44px;" required>
                            <button type="button" class="login-pw-toggle" onclick="togglePw('add_password','add_pw_icon')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-faint);font-size:14px;">
                                <i class="fa-regular fa-eye" id="add_pw_icon"></i>
                            </button>
                        </div>
                        <!-- Strength bar -->
                        <div class="password-strength mt-1">
                            <div class="password-strength-bar" id="add_pwStrengthBar"></div>
                        </div>
                        <div class="form-error-msg" id="add_passwordError"></div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Account Status</label>
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
            <button class="btn btn-primary" id="addRecSubmitBtn">
                <i class="fa-solid fa-user-plus"></i> Add Receptionist
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     EDIT RECEPTIONIST MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editRecModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-warning);"></i>
                Edit Receptionist
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="editRecForm" novalidate>
                <input type="hidden" name="action"     value="updateReceptionist">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="edit_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4">

                    <div class="form-group">
                        <label class="form-label" for="edit_first_name">First Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_first_name" name="first_name" class="form-input" maxlength="100" required>
                        <div class="form-error-msg" id="edit_first_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_last_name">Last Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="edit_last_name" name="last_name" class="form-input" maxlength="100" required>
                        <div class="form-error-msg" id="edit_last_nameError"></div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="edit_email">Email Address <span style="color:var(--color-danger);">*</span></label>
                        <input type="email" id="edit_email" name="email" class="form-input" required>
                        <div class="form-error-msg" id="edit_emailError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_phone">Phone Number</label>
                        <input type="text" id="edit_phone" name="phone" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <input type="hidden" name="gender" id="edit_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="edit_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="edit_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="edit_gender">⚧ Other</button>
                        </div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Account Status</label>
                        <input type="hidden" name="is_active" id="edit_is_active" value="1">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="1" data-field="edit_is_active">✅ Active</button>
                            <button type="button" class="pill-toggle" data-value="0" data-field="edit_is_active">🚫 Inactive</button>
                        </div>
                    </div>

                </div>

                <!-- Reset password note -->
                <div style="border-top:1px solid var(--color-border);padding-top:1rem;margin-top:0.5rem;">
                    <p class="text-xs" style="color:var(--color-text-muted);">
                        <i class="fa-solid fa-circle-info mr-1" style="color:var(--color-primary);"></i>
                        To change this receptionist's password, use the
                        <strong>Reset Password</strong> button in the actions menu.
                    </p>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="editRecSubmitBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     RESET PASSWORD MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="resetPwModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-key" style="color:var(--color-warning);"></i>
                Reset Password
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p class="text-sm mb-4" style="color:var(--color-text-muted);">
                Setting a new password for: <strong id="resetPwName">—</strong>
            </p>
            <form id="resetPwForm" novalidate>
                <input type="hidden" name="action"     value="resetPassword">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="resetPw_id">

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password <span style="color:var(--color-danger);">*</span></label>
                    <div style="position:relative;">
                        <input type="password" id="new_password" name="new_password" class="form-input"
                               placeholder="Min. 6 characters" autocomplete="new-password"
                               style="padding-right:44px;" required>
                        <button type="button" onclick="togglePw('new_password','pw_icon2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-faint);font-size:14px;">
                            <i class="fa-regular fa-eye" id="pw_icon2"></i>
                        </button>
                    </div>
                    <div class="password-strength mt-1">
                        <div class="password-strength-bar" id="resetPwStrengthBar"></div>
                    </div>
                    <div class="form-error-msg" id="new_passwordError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span style="color:var(--color-danger);">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                           placeholder="Repeat password" autocomplete="new-password" required>
                    <div class="form-error-msg" id="confirm_passwordError"></div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-warning" id="resetPwSubmitBtn">
                <i class="fa-solid fa-key"></i> Reset Password
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/admin/receptionists.php`;

    // ── Pill toggle wiring ────────────────────────────────────
    document.querySelectorAll('.pill-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const fieldId = btn.dataset.field;
            if (!fieldId) return;
            document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b =>
                b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById(fieldId).value = btn.dataset.value;
        });
    });

    // ── Password show/hide ────────────────────────────────────
    window.togglePw = function (inputId, iconId) {
        const inp  = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        const show = inp.type === 'password';
        inp.type   = show ? 'text' : 'password';
        icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    };

    // Password strength wiring
    document.getElementById('add_password')?.addEventListener('input', function () {
        updatePasswordStrength(this.value, 'add_pwStrengthBar');
    });
    document.getElementById('new_password')?.addEventListener('input', function () {
        updatePasswordStrength(this.value, 'resetPwStrengthBar');
    });

    // ── Load & render receptionists ───────────────────────────
    function loadReceptionists() {
        const search = document.getElementById('searchInput').value.trim();
        const status = document.getElementById('statusFilter').value;
        const params = new URLSearchParams({ action: 'getReceptionists', search, status });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                renderTable(res.data.receptionists);
            })
            .catch(() => showToast('Failed to load receptionists.', 'error'));
    }

    function renderTable(recs) {
        const tbody = document.getElementById('recsBody');
        document.getElementById('recCount').textContent = recs.length;

        if (!recs.length) {
            tbody.innerHTML = `
                <tr><td colspan="7" class="table-empty">
                    <div class="table-empty-icon">👩‍💼</div>
                    No receptionists found.
                </td></tr>`;
            return;
        }

        tbody.innerHTML = recs.map((r, i) => {
            const initials = (r.first_name[0] || '') + (r.last_name[0] || '');
            const onlineDot = r.is_online
                ? `<span class="online-dot"></span> <span style="color:var(--color-accent);font-size:11px;font-weight:600;">Online</span>`
                : `<span style="width:8px;height:8px;border-radius:50%;display:inline-block;background:var(--color-border-strong);"></span> <span style="color:var(--color-text-faint);font-size:11px;">Offline</span>`;

            return `
            <tr class="animate-fade-in" data-id="${r.id}">
                <td class="text-xs font-mono-nums" style="color:var(--color-text-faint);">${i + 1}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <div class="avatar" style="font-size:12px;">${escHtml(initials.toUpperCase())}</div>
                        <div>
                            <div class="font-semibold text-sm">${escHtml(r.full_name)}</div>
                            <div class="text-xs" style="color:var(--color-text-muted);">${r.phone ? escHtml(r.phone) : '—'}</div>
                        </div>
                    </div>
                </td>
                <td class="text-xs">${escHtml(r.email)}</td>
                <td>
                    <span class="badge badge-active">${r.today_patients} today</span>
                    <span class="text-xs ml-1" style="color:var(--color-text-faint);">${r.total_patients} total</span>
                </td>
                <td><div class="flex items-center gap-1">${onlineDot}</div></td>
                <td>
                    <button class="toggle-switch" onclick="toggleRec(${r.id})">
                        <input type="checkbox" class="toggle-input" ${r.is_active ? 'checked' : ''} readonly>
                        <span class="toggle-track"></span>
                        <span class="text-xs font-medium" style="color:var(--color-text-muted);">
                            ${r.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </button>
                </td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-ghost btn-icon btn-sm" title="Edit" onclick="openEditModal(${r.id})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-icon btn-sm" title="Reset Password"
                                style="color:var(--color-warning);background:var(--color-warning-lt);"
                                onclick="openResetPwModal(${r.id}, '${escJs(r.full_name)}')">
                            <i class="fa-solid fa-key"></i>
                        </button>
                        <button class="btn btn-icon btn-sm" title="Delete"
                                style="color:var(--color-danger);background:var(--color-danger-lt);"
                                onclick="confirmDelete(${r.id}, '${escJs(r.full_name)}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Add ───────────────────────────────────────────────────
    document.getElementById('addRecBtn').addEventListener('click', () => {
        resetForm('addRecForm');
        setPill('add_is_active', '1');
        clearPills('add_gender');
        clearPills('add_is_active');
        setPill('add_is_active', '1');
        document.getElementById('add_pwStrengthBar').style.width = '0%';
        openModal('addRecModal');
        document.getElementById('add_first_name').focus();
    });

    document.getElementById('addRecSubmitBtn').addEventListener('click', () => {
        const form = document.getElementById('addRecForm');
        if (!clientValidate(form, 'add', true)) return;
        const btn = document.getElementById('addRecSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(form),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('addRecModal');
                resetForm('addRecForm');
                loadReceptionists();
                playSound('success.mp3');
            },
            null, btn
        );
    });

    // ── Edit ──────────────────────────────────────────────────
    window.openEditModal = function (id) {
        const params = new URLSearchParams({ action: 'getReceptionistById', id });
        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const r = res.data.receptionist;
                document.getElementById('edit_id').value         = r.id;
                document.getElementById('edit_first_name').value = r.first_name;
                document.getElementById('edit_last_name').value  = r.last_name;
                document.getElementById('edit_email').value      = r.email;
                document.getElementById('edit_phone').value      = r.phone || '';
                if (r.gender) setPill('edit_gender', r.gender);
                else clearPills('edit_gender');
                setPill('edit_is_active', String(r.is_active));
                openModal('editRecModal');
                document.getElementById('edit_first_name').focus();
            })
            .catch(() => showToast('Failed to load receptionist data.', 'error'));
    };

    document.getElementById('editRecSubmitBtn').addEventListener('click', () => {
        const form = document.getElementById('editRecForm');
        if (!clientValidate(form, 'edit', false)) return;
        const btn = document.getElementById('editRecSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(form),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('editRecModal');
                loadReceptionists();
            },
            null, btn
        );
    });

    // ── Toggle Active ─────────────────────────────────────────
    window.toggleRec = function (id) {
        const fd = new FormData();
        fd.set('action', 'toggleReceptionist');
        fd.set('csrf_token', getCsrfToken());
        fd.set('id', id);
        ajaxRequest(AJAX_URL, 'POST', fd,
            (_, msg) => { showToast(msg, 'success'); loadReceptionists(); }
        );
    };

    // ── Reset Password ────────────────────────────────────────
    window.openResetPwModal = function (id, name) {
        document.getElementById('resetPw_id').value  = id;
        document.getElementById('resetPwName').textContent = name;
        resetForm('resetPwForm');
        document.getElementById('resetPwStrengthBar').style.width = '0%';
        openModal('resetPwModal');
        document.getElementById('new_password').focus();
    };

    document.getElementById('resetPwSubmitBtn').addEventListener('click', () => {
        const newPw  = document.getElementById('new_password').value.trim();
        const conPw  = document.getElementById('confirm_password').value.trim();
        let valid    = true;

        if (newPw.length < 6) {
            showFieldError('new_password', 'Password must be at least 6 characters.'); valid = false;
        } else clearFieldError('new_password');

        if (newPw !== conPw) {
            showFieldError('confirm_password', 'Passwords do not match.'); valid = false;
        } else clearFieldError('confirm_password');

        if (!valid) return;

        const btn = document.getElementById('resetPwSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('resetPwForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('resetPwModal');
            },
            null, btn
        );
    });

    // ── Delete ────────────────────────────────────────────────
    window.confirmDelete = function (id, name) {
        showConfirmModal(
            'Delete Receptionist',
            `Are you sure you want to delete <strong>${escHtml(name)}</strong>? This action cannot be undone.`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deleteReceptionist');
                fd.set('csrf_token', getCsrfToken());
                fd.set('id', id);
                ajaxRequest(AJAX_URL, 'POST', fd,
                    (_, msg) => { showToast(msg, 'success'); loadReceptionists(); }
                );
            },
            'danger'
        );
    };

    // ── Client Validation ─────────────────────────────────────
    function clientValidate(form, prefix, requirePw) {
        let valid = true;
        const fn  = form.querySelector(`#${prefix}_first_name`);
        const ln  = form.querySelector(`#${prefix}_last_name`);
        const em  = form.querySelector(`#${prefix}_email`);

        if (!fn.value.trim()) { showFieldError(`${prefix}_first_name`, 'First name is required.'); valid = false; }
        else clearFieldError(`${prefix}_first_name`);

        if (!ln.value.trim()) { showFieldError(`${prefix}_last_name`, 'Last name is required.'); valid = false; }
        else clearFieldError(`${prefix}_last_name`);

        if (!em.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value)) {
            showFieldError(`${prefix}_email`, 'Valid email address is required.'); valid = false;
        } else clearFieldError(`${prefix}_email`);

        if (requirePw) {
            const pw = document.getElementById(`${prefix}_password`);
            if (!pw || pw.value.length < 6) {
                showFieldError(`${prefix}_password`, 'Password must be at least 6 characters.'); valid = false;
            } else clearFieldError(`${prefix}_password`);
        }

        return valid;
    }

    // ── Pill helpers ──────────────────────────────────────────
    function setPill(fieldId, value) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b => {
            b.classList.toggle('selected', b.dataset.value === value);
        });
        const h = document.getElementById(fieldId);
        if (h) h.value = value;
    }
    function clearPills(fieldId) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b =>
            b.classList.remove('selected'));
        const h = document.getElementById(fieldId);
        if (h) h.value = '';
    }

    // ── Utilities ─────────────────────────────────────────────
    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJs(str) { return String(str ?? '').replace(/'/g, "\\'"); }

    // ── Filters ───────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', debounce(loadReceptionists, 300));
    document.getElementById('statusFilter').addEventListener('change', loadReceptionists);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value  = '';
        document.getElementById('statusFilter').value = '';
        loadReceptionists();
    });

    // ── Init ──────────────────────────────────────────────────
    loadReceptionists();

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>
