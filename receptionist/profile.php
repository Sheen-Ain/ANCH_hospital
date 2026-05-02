<?php
/**
 * receptionist/profile.php
 * Receptionist's own profile: view stats, edit details, change password, upload avatar.
 */

$pageTitle  = 'My Profile';
$activePage = 'profile';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-regular fa-circle-user" style="color:var(--color-accent);"></i>
        My Profile
    </h2>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- ── Left: Avatar + Stats ──────────────────────────────── -->
    <div class="flex flex-col gap-4">

        <div class="card text-center">
            <!-- Avatar -->
            <div class="avatar-upload mx-auto mb-3" id="avatarUploadWrap" title="Click to change photo">
                <div id="avatarDisplay"
                     style="width:100px;height:100px;border-radius:50%;
                            background:var(--color-accent-lt);
                            display:flex;align-items:center;justify-content:center;
                            font-size:2rem;font-weight:700;color:var(--color-accent);
                            border:3px solid var(--color-border);overflow:hidden;cursor:pointer;">
                    <span id="avatarInitials">…</span>
                </div>
                <div class="avatar-upload-overlay" id="avatarOverlay">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <input type="file" id="avatarFileInput" accept="image/*" style="display:none;">
            </div>

            <h3 class="font-bold text-base" id="profileFullName" style="color:var(--color-text);">—</h3>
            <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">Receptionist</p>
            <p class="text-xs mt-1" id="profileEmail" style="color:var(--color-text-faint);">—</p>

            <div class="section-sep"></div>

            <!-- Stats grid -->
            <div class="grid grid-cols-3 gap-2 text-center">
                <div style="padding:0.5rem;background:var(--color-primary-lt);border-radius:var(--radius-md);">
                    <div class="stat-number" style="font-size:1.2rem;" id="statToday">—</div>
                    <div class="stat-card-label" style="font-size:9px;">Today</div>
                </div>
                <div style="padding:0.5rem;background:var(--color-accent-lt);border-radius:var(--radius-md);">
                    <div class="stat-number" style="font-size:1.2rem;" id="statTotal">—</div>
                    <div class="stat-card-label" style="font-size:9px;">Total</div>
                </div>
                <div style="padding:0.5rem;background:var(--color-violet-lt);border-radius:var(--radius-md);">
                    <div class="stat-number" style="font-size:1.2rem;" id="statAdmissions">—</div>
                    <div class="stat-card-label" style="font-size:9px;">Admitted</div>
                </div>
            </div>
        </div>

        <!-- Quick info -->
        <div class="card">
            <h4 class="font-bold text-sm mb-3" style="color:var(--color-text);">Account Details</h4>
            <div id="quickInfoList">
                <div class="text-center py-4"><span class="spinner"></span></div>
            </div>
        </div>

    </div>

    <!-- ── Right: Edit form + Change password ────────────────── -->
    <div class="lg:col-span-2 flex flex-col gap-4">

        <!-- Edit Profile -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold" style="color:var(--color-text);">Edit Profile</h3>
                <button class="btn btn-primary" id="saveProfileBtn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
            <form id="profileForm" novalidate enctype="multipart/form-data">
                <input type="hidden" name="action"     value="updateProfile">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">

                    <div class="form-group">
                        <label class="form-label" for="p_first_name">First Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="p_first_name" name="first_name" class="form-input" maxlength="100" required>
                        <div class="form-error-msg" id="p_first_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="p_last_name">Last Name <span style="color:var(--color-danger);">*</span></label>
                        <input type="text" id="p_last_name" name="last_name" class="form-input" maxlength="100" required>
                        <div class="form-error-msg" id="p_last_nameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="p_email">Email Address</label>
                        <input type="email" id="p_email" class="form-input"
                               style="opacity:0.6;cursor:not-allowed;" disabled>
                        <div class="form-hint">Email cannot be changed. Contact admin.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="p_phone">Phone Number</label>
                        <input type="text" id="p_phone" name="phone" class="form-input">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Gender</label>
                        <input type="hidden" name="gender" id="p_gender" value="">
                        <div class="pill-toggle-group">
                            <button type="button" class="pill-toggle" data-value="male"   data-field="p_gender">♂ Male</button>
                            <button type="button" class="pill-toggle" data-value="female" data-field="p_gender">♀ Female</button>
                            <button type="button" class="pill-toggle" data-value="other"  data-field="p_gender">⚧ Other</button>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold" style="color:var(--color-text);">Change Password</h3>
                    <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">Min. 6 characters.</p>
                </div>
                <button class="btn btn-warning" id="changePasswordBtn">
                    <i class="fa-solid fa-key"></i> Update Password
                </button>
            </div>
            <form id="passwordForm" novalidate>
                <input type="hidden" name="action"     value="changePassword">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5">

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password <span style="color:var(--color-danger);">*</span></label>
                        <div style="position:relative;">
                            <input type="password" id="current_password" name="current_password"
                                   class="form-input" style="padding-right:44px;">
                            <button type="button" onclick="togglePw('current_password','cur_icon')"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-faint);font-size:14px;">
                                <i class="fa-regular fa-eye" id="cur_icon"></i>
                            </button>
                        </div>
                        <div class="form-error-msg" id="current_passwordError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password <span style="color:var(--color-danger);">*</span></label>
                        <div style="position:relative;">
                            <input type="password" id="new_password" name="new_password"
                                   class="form-input" style="padding-right:44px;">
                            <button type="button" onclick="togglePw('new_password','new_icon')"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-faint);font-size:14px;">
                                <i class="fa-regular fa-eye" id="new_icon"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-1">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="form-error-msg" id="new_passwordError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password <span style="color:var(--color-danger);">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input">
                        <div class="form-error-msg" id="confirm_passwordError"></div>
                    </div>

                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="danger-zone">
            <div class="danger-zone-title">
                <i class="fa-solid fa-triangle-exclamation"></i> Danger Zone
            </div>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold" style="color:var(--color-text);">Sign Out</p>
                    <p class="text-xs" style="color:var(--color-text-muted);">End your current session.</p>
                </div>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/receptionist/profile.php`;
    let pendingImageFile = null;

    // Pill toggles
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

    window.togglePw = function (inputId, iconId) {
        const inp  = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (!inp || !icon) return;
        const show = inp.type === 'password';
        inp.type   = show ? 'text' : 'password';
        icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    };

    document.getElementById('new_password')?.addEventListener('input', function () {
        updatePasswordStrength(this.value, 'passwordStrengthBar');
    });

    // Load profile
    function loadProfile() {
        fetch(`${AJAX_URL}?action=getProfile`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                apply(res.data.profile);
            })
            .catch(() => showToast('Failed to load profile.', 'error'));
    }

    function apply(p) {
        const fullName = `${p.first_name} ${p.last_name}`.trim();
        const initials = ((p.first_name[0]||'') + (p.last_name[0]||'')).toUpperCase();

        document.getElementById('profileFullName').textContent = fullName;
        document.getElementById('profileEmail').textContent    = p.email;
        document.getElementById('statToday').textContent      = p.today_patients;
        document.getElementById('statTotal').textContent      = p.total_patients;
        document.getElementById('statAdmissions').textContent = p.total_admissions;

        // Avatar
        const display = document.getElementById('avatarDisplay');
        if (p.profile_image) {
            display.innerHTML = `<img src="${window.APP_CONFIG.baseUrl}/assets/uploads/profiles/${escHtml(p.profile_image)}"
                                      style="width:100%;height:100%;object-fit:cover;">`;
        } else {
            document.getElementById('avatarInitials').textContent = initials;
        }

        // Quick info
        document.getElementById('quickInfoList').innerHTML = `
            <div class="online-user-item">
                <i class="fa-solid fa-envelope w-4" style="color:var(--color-accent);"></i>
                <span class="text-xs">${escHtml(p.email)}</span>
            </div>
            <div class="online-user-item">
                <i class="fa-solid fa-phone w-4" style="color:var(--color-primary);"></i>
                <span class="text-xs">${p.phone ? escHtml(p.phone) : '<em style="color:var(--color-text-faint);">Not set</em>'}</span>
            </div>
            <div class="online-user-item">
                <i class="fa-solid fa-venus-mars w-4" style="color:var(--color-violet);"></i>
                <span class="text-xs">${p.gender ? ucFirst(p.gender) : '<em style="color:var(--color-text-faint);">Not set</em>'}</span>
            </div>
            <div class="online-user-item">
                <i class="fa-solid fa-calendar w-4" style="color:var(--color-warning);"></i>
                <span class="text-xs">Joined ${escHtml(p.created_fmt)}</span>
            </div>`;

        // Form
        document.getElementById('p_first_name').value = p.first_name || '';
        document.getElementById('p_last_name').value  = p.last_name  || '';
        document.getElementById('p_email').value      = p.email      || '';
        document.getElementById('p_phone').value      = p.phone      || '';
        if (p.gender) setPill('p_gender', p.gender);
    }

    // Avatar upload
    const avatarWrap = document.getElementById('avatarUploadWrap');
    const fileInput  = document.getElementById('avatarFileInput');
    const overlay    = document.getElementById('avatarOverlay');

    avatarWrap.addEventListener('mouseenter', () => overlay.style.opacity = '1');
    avatarWrap.addEventListener('mouseleave', () => overlay.style.opacity = '0');
    avatarWrap.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (!['image/jpeg','image/png','image/gif','image/webp'].includes(file.type)) {
            showToast('Only JPG, PNG, GIF or WebP allowed.', 'error'); return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showToast('Image must be under 2MB.', 'error'); return;
        }
        pendingImageFile = file;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatarDisplay').innerHTML =
                `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
        };
        reader.readAsDataURL(file);
        showToast('Image selected. Save changes to upload.', 'info');
    });

    // Save Profile
    document.getElementById('saveProfileBtn').addEventListener('click', () => {
        let valid = true;
        const fn = document.getElementById('p_first_name').value.trim();
        const ln = document.getElementById('p_last_name').value.trim();
        if (!fn) { showFieldError('p_first_name', 'First name is required.'); valid = false; }
        else clearFieldError('p_first_name');
        if (!ln) { showFieldError('p_last_name', 'Last name is required.');  valid = false; }
        else clearFieldError('p_last_name');
        if (!valid) return;

        const fd = serializeForm(document.getElementById('profileForm'));
        if (pendingImageFile) fd.set('profile_image', pendingImageFile, pendingImageFile.name);

        const btn = document.getElementById('saveProfileBtn');
        ajaxRequest(AJAX_URL, 'POST', fd,
            (_, msg) => {
                showToast(msg, 'success');
                pendingImageFile = null;
                setTimeout(() => loadProfile(), 400);
            }, null, btn
        );
    });

    // Change Password
    document.getElementById('changePasswordBtn').addEventListener('click', () => {
        const curPw = document.getElementById('current_password').value.trim();
        const newPw = document.getElementById('new_password').value.trim();
        const conPw = document.getElementById('confirm_password').value.trim();
        let valid   = true;

        if (!curPw)        { showFieldError('current_password', 'Current password is required.'); valid = false; }
        else clearFieldError('current_password');
        if (newPw.length < 6) { showFieldError('new_password', 'Min 6 characters.'); valid = false; }
        else clearFieldError('new_password');
        if (newPw !== conPw) { showFieldError('confirm_password', 'Passwords do not match.'); valid = false; }
        else clearFieldError('confirm_password');
        if (!valid) return;

        const btn = document.getElementById('changePasswordBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('passwordForm')),
            (_, msg) => {
                showToast(msg, 'success');
                resetForm('passwordForm');
                document.getElementById('passwordStrengthBar').style.width = '0%';
            }, null, btn
        );
    });

    function setPill(fieldId, value) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b =>
            b.classList.toggle('selected', b.dataset.value === value));
        const h = document.getElementById(fieldId);
        if (h) h.value = value;
    }

    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    loadProfile();
})();
JS;

require_once BASE_PATH . '/receptionist/footer.php';
?>
