<?php
/**
 * admin/settings.php
 * System settings — tabbed layout, each section saves independently via AJAX.
 */

$pageTitle  = 'Settings';
$activePage = 'settings';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-sliders" style="color:var(--color-primary);"></i>
        System Settings
    </h2>
    <span class="text-sm" style="color:var(--color-text-muted);">
        Changes save per section immediately.
    </span>
</div>

<!-- ── Tabs ──────────────────────────────────────────────── -->
<div class="tabs" id="settingsTabs">
    <button class="tab-btn active" data-tab="hospital">
        <i class="fa-solid fa-hospital" style="margin-right:5px;"></i>Hospital
    </button>
    <button class="tab-btn" data-tab="tokens">
        <i class="fa-solid fa-ticket" style="margin-right:5px;"></i>Tokens
    </button>
    <button class="tab-btn" data-tab="financial">
        <i class="fa-solid fa-money-bill-wave" style="margin-right:5px;"></i>Financial
    </button>
    <button class="tab-btn" data-tab="receipts">
        <i class="fa-solid fa-receipt" style="margin-right:5px;"></i>Receipts
    </button>
    <button class="tab-btn" data-tab="system">
        <i class="fa-solid fa-gears" style="margin-right:5px;"></i>System
    </button>
</div>

<!-- Loading state -->
<div id="settingsLoadingState" class="card" style="text-align:center;padding:3rem;">
    <span class="spinner"></span>
    <p style="color:var(--color-text-muted);margin-top:0.75rem;">Loading settings…</p>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: HOSPITAL
     ═══════════════════════════════════════════════════════ -->
<div class="tab-content" id="tab-hospital" style="display:none;">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold" style="color:var(--color-text);">Hospital Information</h3>
                <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">
                    Displayed on receipts, slips, and the login page.
                </p>
            </div>
            <button class="btn btn-primary" id="saveHospitalBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save
            </button>
        </div>

        <form id="hospitalForm" novalidate>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">

                <!-- Logo Upload -->
                <div class="form-group md:col-span-2">
                    <label class="form-label">
                        <i class="fa-solid fa-image mr-1" style="color:var(--color-primary);"></i>
                        Hospital Logo
                    </label>
                    <div class="flex items-center gap-4 flex-wrap">
                        <!-- Current logo preview -->
                        <div id="logoPreviewWrap"
                             style="width:80px;height:80px;border-radius:12px;
                                    border:2px dashed var(--color-border-strong);
                                    display:flex;align-items:center;justify-content:center;
                                    background:var(--color-primary-lt);overflow:hidden;flex-shrink:0;">
                            <i class="fa-solid fa-hospital-user fa-2x" id="logoPlaceholderIcon"
                               style="color:var(--color-primary);"></i>
                            <img id="logoPreviewImg" src="" alt="Logo"
                                 style="width:100%;height:100%;object-fit:contain;display:none;">
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logoFileInput" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('logoFileInput').click()">
                                <i class="fa-solid fa-upload"></i> Upload Logo
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm ml-2" id="removeLogoBtn" style="display:none;">
                                <i class="fa-solid fa-trash"></i> Remove
                            </button>
                            <p class="form-hint mt-1">PNG, JPG, SVG or WebP. Max 2MB. Shown on login, receipts, slips, and headers.</p>
                        </div>
                    </div>
                </div>
                    <label class="form-label" for="s_hospital_name">
                        Hospital Name (English) <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="text" id="s_hospital_name" name="hospital_name"
                           class="form-input" maxlength="150" placeholder="City General Hospital">
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_hospital_name_urdu">
                        Hospital Name (Urdu / اردو نام)
                    </label>
                    <input type="text" id="s_hospital_name_urdu" name="hospital_name_urdu"
                           class="form-input" dir="rtl" placeholder="ہسپتال کا نام"
                           style="font-family:'Noto Nastaliq Urdu',serif;font-size:15px;line-height:2;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_hospital_address">Address (English)</label>
                    <textarea id="s_hospital_address" name="hospital_address"
                              class="form-textarea" rows="2"
                              placeholder="123 Main Street, City, Province"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_hospital_address_urdu">
                        Address (Urdu / پتہ اردو میں)
                    </label>
                    <textarea id="s_hospital_address_urdu" name="hospital_address_urdu"
                              class="form-textarea" dir="rtl" rows="2"
                              placeholder="گلی نمبر ۱، شہر"
                              style="font-family:'Noto Nastaliq Urdu',serif;line-height:2;"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_contact_email">Contact Email</label>
                    <input type="email" id="s_contact_email" name="contact_email"
                           class="form-input" placeholder="info@hospital.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_contact_phone">Contact Phone</label>
                    <input type="text" id="s_contact_phone" name="contact_phone"
                           class="form-input" placeholder="021-12345678">
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: TOKENS
     ═══════════════════════════════════════════════════════ -->
<div class="tab-content" id="tab-tokens" style="display:none;">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold" style="color:var(--color-text);">Token Configuration</h3>
                <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">
                    Controls how daily tokens are numbered and reset.
                </p>
            </div>
            <button class="btn btn-primary" id="saveTokensBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save
            </button>
        </div>

        <form id="tokensForm" novalidate>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6">

                <div class="form-group">
                    <label class="form-label" for="s_token_prefix">Token Prefix</label>
                    <input type="text" id="s_token_prefix" name="token_prefix"
                           class="form-input" maxlength="10" placeholder="TKN"
                           style="text-transform:uppercase;font-family:'JetBrains Mono',monospace;">
                    <div class="form-hint">
                        Preview: <span id="tokenPreview" class="font-mono-nums font-bold"
                                        style="color:var(--color-primary);">TKN-001</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_token_reset_time">
                        Daily Reset Time
                    </label>
                    <input type="time" id="s_token_reset_time" name="token_reset_time"
                           class="form-input" value="00:00">
                    <div class="form-hint">Token counters reset at this time each day.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="s_max_tokens_per_day">
                        Max Tokens Per Day
                    </label>
                    <input type="number" id="s_max_tokens_per_day" name="max_tokens_per_day"
                           class="form-input" min="1" max="9999" step="1" placeholder="200">
                    <div class="form-hint">Per doctor, per day.</div>
                </div>

            </div>
        </form>
    </div>

    <!-- Live token preview card -->
    <div class="card mt-4" style="background:var(--color-primary-lt);border-color:var(--color-primary-mid);">
        <div class="flex items-center gap-4">
            <div class="next-token-box" style="margin:0;flex:1;">
                <div class="next-token-label">Sample Token</div>
                <div class="token-number-display" id="liveTokenPreview">TKN-001</div>
            </div>
            <div class="text-xs" style="color:var(--color-primary);max-width:200px;">
                This is how token numbers will appear on receipts and the token screen.
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: FINANCIAL
     ═══════════════════════════════════════════════════════ -->
<div class="tab-content" id="tab-financial" style="display:none;">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold" style="color:var(--color-text);">Financial Settings</h3>
                <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">
                    Currency display used across all receipts and reports.
                </p>
            </div>
            <button class="btn btn-primary" id="saveFinancialBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save
            </button>
        </div>

        <form id="financialForm" novalidate>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6" style="max-width:520px;">

                <div class="form-group">
                    <label class="form-label" for="s_currency_symbol">Currency Symbol</label>
                    <input type="text" id="s_currency_symbol" name="currency_symbol"
                           class="form-input" maxlength="10" placeholder="Rs.">
                    <div class="form-hint">
                        Preview: <span id="currencyPreview" class="font-mono-nums font-bold"
                                        style="color:var(--color-accent);">Rs. 1,500.00</span>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: RECEIPTS
     ═══════════════════════════════════════════════════════ -->
<div class="tab-content" id="tab-receipts" style="display:none;">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold" style="color:var(--color-text);">Receipt Options</h3>
                <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">
                    Controls what appears on printed patient receipts.
                </p>
            </div>
            <button class="btn btn-primary" id="saveReceiptsBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save
            </button>
        </div>

        <form id="receiptsForm" novalidate>

            <!-- Urdu section toggle -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:1rem 1.25rem;border:1px solid var(--color-border);
                        border-radius:var(--radius-lg);margin-bottom:1rem;">
                <div>
                    <p class="font-semibold text-sm" style="color:var(--color-text);">
                        Show Urdu Section on Receipts
                    </p>
                    <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">
                        Adds an Urdu (اردو) mirror section at the bottom of every patient receipt.
                    </p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" class="toggle-input" id="s_receipt_show_urdu"
                           name="receipt_show_urdu" value="1">
                    <span class="toggle-track"></span>
                </label>
            </div>

        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: SYSTEM
     ═══════════════════════════════════════════════════════ -->
<div class="tab-content" id="tab-system" style="display:none;">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold" style="color:var(--color-text);">System Behaviour</h3>
                <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">
                    Session, notifications, and UI preferences.
                </p>
            </div>
            <button class="btn btn-primary" id="saveSystemBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save
            </button>
        </div>

        <form id="systemForm" novalidate>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">

                <!-- Sound Effects -->
                <div class="form-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;
                                padding:1rem;border:1px solid var(--color-border);border-radius:var(--radius-lg);">
                        <div>
                            <p class="font-semibold text-sm" style="color:var(--color-text);">Sound Effects</p>
                            <p class="text-xs" style="color:var(--color-text-muted);">
                                Play sounds on token generation and payment confirmation.
                            </p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" class="toggle-input" id="s_sound_effects"
                                   name="sound_effects" value="1">
                            <span class="toggle-track"></span>
                        </label>
                    </div>
                </div>

                <!-- Email Notifications -->
                <div class="form-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;
                                padding:1rem;border:1px solid var(--color-border);border-radius:var(--radius-lg);">
                        <div>
                            <p class="font-semibold text-sm" style="color:var(--color-text);">Email Notifications</p>
                            <p class="text-xs" style="color:var(--color-text-muted);">
                                Send email alerts for new admissions and discharges.
                            </p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" class="toggle-input" id="s_email_notifications"
                                   name="email_notifications" value="1">
                            <span class="toggle-track"></span>
                        </label>
                    </div>
                </div>

                <!-- Online Threshold -->
                <div class="form-group">
                    <label class="form-label" for="s_online_threshold_mins">
                        Online Status Threshold (minutes)
                    </label>
                    <input type="number" id="s_online_threshold_mins"
                           name="online_threshold_mins" class="form-input"
                           min="1" max="60" step="1" placeholder="5">
                    <div class="form-hint">
                        Users active within this many minutes appear as "Online Now".
                    </div>
                </div>

                <!-- Auto Logout -->
                <div class="form-group">
                    <label class="form-label" for="s_auto_logout_mins">
                        Auto-Logout After Inactivity (minutes)
                    </label>
                    <input type="number" id="s_auto_logout_mins"
                           name="auto_logout_mins" class="form-input"
                           min="5" max="480" step="5" placeholder="60">
                    <div class="form-hint">
                        Session expires after this many minutes of inactivity. Min: 5, Max: 480.
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- Email Test card -->
    <div class="card mt-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h4 class="font-bold text-sm" style="color:var(--color-text);">
                    <i class="fa-solid fa-envelope mr-1" style="color:var(--color-primary);"></i>
                    Test Email Configuration
                </h4>
                <p class="text-xs mt-1" style="color:var(--color-text-muted);">
                    Sends a test email to <strong><?= e($_SESSION['email'] ?? 'your account email') ?></strong> via the server's mail function.
                </p>
            </div>
            <button class="btn btn-outline" id="testEmailBtn">
                <i class="fa-solid fa-paper-plane"></i>
                Send Test Email
            </button>
        </div>
    </div>

    <!-- Audit Log preview -->
    <div class="card mt-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-bold text-sm" style="color:var(--color-text);">
                <i class="fa-solid fa-clock-rotate-left mr-1" style="color:var(--color-text-muted);"></i>
                Recent Activity Log
            </h4>
            <span class="text-xs" style="color:var(--color-text-faint);">Last 10 entries</span>
        </div>
        <div id="auditLogList">
            <div class="text-center py-4"><span class="spinner"></span></div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/admin/settings.php`;

    // ── Tab switching ─────────────────────────────────────────
    const tabBtns     = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    function showTab(tabId) {
        tabBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
        tabContents.forEach(c => {
            c.style.display = c.id === `tab-${tabId}` ? '' : 'none';
        });
        // Load audit log when system tab shown
        if (tabId === 'system') loadAuditLog();
    }

    tabBtns.forEach(btn => btn.addEventListener('click', () => showTab(btn.dataset.tab)));

    // ── Load all settings ─────────────────────────────────────
    function loadSettings() {
        fetch(`${AJAX_URL}?action=getSettings`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                applySettings(res.data.settings);
                document.getElementById('settingsLoadingState').style.display = 'none';
                showTab('hospital'); // show first tab
            })
            .catch(() => showToast('Failed to load settings.', 'error'));
    }

    function applySettings(s) {
        // Hospital
        setVal('s_hospital_name',         s.hospital_name         || '');
        setVal('s_hospital_name_urdu',    s.hospital_name_urdu    || '');
        setVal('s_hospital_address',      s.hospital_address      || '');
        setVal('s_hospital_address_urdu', s.hospital_address_urdu || '');
        setVal('s_contact_email',         s.contact_email         || '');
        setVal('s_contact_phone',         s.contact_phone         || '');

        // Logo preview
        if (s.hospital_logo) {
            const src = `${window.APP_CONFIG.baseUrl}/assets/uploads/logo/${s.hospital_logo}`;
            document.getElementById('logoPreviewImg').src         = src;
            document.getElementById('logoPreviewImg').style.display = '';
            document.getElementById('logoPlaceholderIcon').style.display = 'none';
            document.getElementById('removeLogoBtn').style.display = '';
        }

        // Tokens
        setVal('s_token_prefix',       s.token_prefix       || 'TKN');
        setVal('s_token_reset_time',   s.token_reset_time   || '00:00');
        setVal('s_max_tokens_per_day', s.max_tokens_per_day || '200');
        updateTokenPreview();

        // Financial
        setVal('s_currency_symbol', s.currency_symbol || 'Rs.');
        updateCurrencyPreview();

        // Receipts
        setChecked('s_receipt_show_urdu', s.receipt_show_urdu === '1');

        // System
        setChecked('s_sound_effects',         s.sound_effects         === '1');
        setChecked('s_email_notifications',    s.email_notifications   === '1');
        setVal('s_online_threshold_mins',      s.online_threshold_mins || '5');
        setVal('s_auto_logout_mins',           s.auto_logout_mins      || '60');
    }

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }

    function setChecked(id, checked) {
        const el = document.getElementById(id);
        if (el) el.checked = checked;
    }

    // ── Live previews ─────────────────────────────────────────
    function updateTokenPreview() {
        const prefix = (document.getElementById('s_token_prefix')?.value || 'TKN').toUpperCase();
        const preview = `${prefix}-001`;
        const el1 = document.getElementById('tokenPreview');
        const el2 = document.getElementById('liveTokenPreview');
        if (el1) el1.textContent = preview;
        if (el2) el2.textContent = preview;
    }

    function updateCurrencyPreview() {
        const sym = document.getElementById('s_currency_symbol')?.value || 'Rs.';
        const el  = document.getElementById('currencyPreview');
        if (el) el.textContent = `${sym} 1,500.00`;
    }

    document.getElementById('s_token_prefix')?.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
        updateTokenPreview();
    });

    document.getElementById('s_currency_symbol')?.addEventListener('input', updateCurrencyPreview);

    // ── Generic section saver ─────────────────────────────────
    function saveSection(formId, group) {
        const form   = document.getElementById(formId);
        if (!form) return;

        // Collect all named inputs including checkboxes
        const settings = {};
        form.querySelectorAll('[name]').forEach(el => {
            if (el.type === 'checkbox') {
                settings[el.name] = el.checked ? '1' : '0';
            } else {
                settings[el.name] = el.value;
            }
        });

        const fd = new FormData();
        fd.set('action',     'updateSettings');
        fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.set('group',      group);
        fd.set('settings',   JSON.stringify(settings));

        const btn = document.getElementById(`save${capitalize(group)}Btn`);
        ajaxRequest(AJAX_URL, 'POST', fd,
            (_, msg) => {
                showToast(msg, 'success');
                // Reload APP_CONFIG token prefix live
                if (group === 'tokens') {
                    window.APP_CONFIG.tokenPrefix = settings.token_prefix || 'TKN';
                }
                if (group === 'financial') {
                    window.APP_CONFIG.currencySymbol = settings.currency_symbol || 'Rs.';
                }
                if (group === 'system') {
                    window.APP_CONFIG.soundEnabled = settings.sound_effects === '1' ? 1 : 0;
                }
            },
            null, btn
        );
    }

    // Wire save buttons
    document.getElementById('saveHospitalBtn')?.addEventListener('click', () => saveHospital());
    document.getElementById('saveTokensBtn')?.addEventListener('click',   () => saveSection('tokensForm',     'tokens'));
    document.getElementById('saveFinancialBtn')?.addEventListener('click', () => saveSection('financialForm', 'financial'));
    document.getElementById('saveReceiptsBtn')?.addEventListener('click', () => saveSection('receiptsForm',   'receipts'));
    document.getElementById('saveSystemBtn')?.addEventListener('click',   () => saveSection('systemForm',     'system'));

    // ── Logo file picker ──────────────────────────────────────
    let pendingLogoFile = null;

    document.getElementById('logoFileInput')?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        if (!allowed.includes(file.type)) { showToast('Only JPG, PNG, SVG or WebP allowed.', 'error'); return; }
        if (file.size > 2 * 1024 * 1024)  { showToast('Logo must be under 2MB.', 'error'); return; }

        pendingLogoFile = file;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('logoPreviewImg').src           = e.target.result;
            document.getElementById('logoPreviewImg').style.display = '';
            document.getElementById('logoPlaceholderIcon').style.display = 'none';
            document.getElementById('removeLogoBtn').style.display = '';
        };
        reader.readAsDataURL(file);
        showToast('Logo selected. Click Save to upload.', 'info');
    });

    document.getElementById('removeLogoBtn')?.addEventListener('click', () => {
        pendingLogoFile = null;
        document.getElementById('logoPreviewImg').src           = '';
        document.getElementById('logoPreviewImg').style.display = 'none';
        document.getElementById('logoPlaceholderIcon').style.display = '';
        document.getElementById('removeLogoBtn').style.display = 'none';
        // Send remove instruction
        const fd = new FormData();
        fd.set('action', 'updateSettings');
        fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.set('group', 'hospital');
        fd.set('settings', JSON.stringify({ hospital_logo: '' }));
        ajaxRequest(AJAX_URL, 'POST', fd, (_, msg) => showToast(msg, 'success'));
    });

    // ── Hospital save (special — uses multipart for logo) ──────
    function saveHospital() {
        const form = document.getElementById('hospitalForm');
        if (!form) return;

        const settings = {};
        form.querySelectorAll('[name]').forEach(el => {
            if (el.type === 'checkbox') settings[el.name] = el.checked ? '1' : '0';
            else settings[el.name] = el.value;
        });

        const fd = new FormData();
        fd.set('action',     'updateSettings');
        fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.set('group',      'hospital');
        fd.set('settings',   JSON.stringify(settings));

        if (pendingLogoFile) {
            fd.set('hospital_logo', pendingLogoFile, pendingLogoFile.name);
        }

        const btn = document.getElementById('saveHospitalBtn');
        ajaxRequest(AJAX_URL, 'POST', fd,
            (data, msg) => {
                showToast(msg, 'success');
                pendingLogoFile = null;
                if (data && data.logo_filename) {
                    // Update topnav logo live
                    const topLogo = document.getElementById('topnavLogoImg');
                    if (topLogo) {
                        topLogo.src = `${window.APP_CONFIG.baseUrl}/assets/uploads/logo/${data.logo_filename}`;
                        topLogo.style.display = '';
                        document.getElementById('topnavLogoIcon')?.style && (document.getElementById('topnavLogoIcon').style.display = 'none');
                    }
                }
            },
            null, btn
        );
    }

    // ── Test Email ────────────────────────────────────────────
    document.getElementById('testEmailBtn')?.addEventListener('click', () => {
        const btn = document.getElementById('testEmailBtn');
        const fd  = new FormData();
        fd.set('action',     'testEmail');
        fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        ajaxRequest(AJAX_URL, 'POST', fd,
            (_, msg) => showToast(msg, 'success'),
            null, btn
        );
    });

    // ── Audit Log ─────────────────────────────────────────────
    function loadAuditLog() {
        fetch(`${window.APP_CONFIG.baseUrl}/ajax/admin/audit-log.php?limit=10`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                renderAuditLog(res.data.logs);
            })
            .catch(() => {});
    }

    function renderAuditLog(logs) {
        const el = document.getElementById('auditLogList');
        if (!logs.length) {
            el.innerHTML = `<p class="text-xs text-center py-4" style="color:var(--color-text-faint);">No activity recorded yet.</p>`;
            return;
        }
        el.innerHTML = logs.map(l => `
            <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid var(--color-border);">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--color-primary-lt);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;color:var(--color-primary);">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold" style="color:var(--color-text);">${escHtml(formatAction(l.action))}</p>
                    <p class="text-xs" style="color:var(--color-text-muted);">
                        ${escHtml(l.user_name || 'System')} &bull; ${escHtml(l.created_at)}
                    </p>
                </div>
            </div>
        `).join('');
    }

    function formatAction(action) {
        return action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    // ── Utilities ─────────────────────────────────────────────
    function escHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function capitalize(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ── Init ──────────────────────────────────────────────────
    loadSettings();

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>