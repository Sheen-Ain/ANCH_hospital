<?php
/**
 * receptionist/dashboard.php
 * Receptionist home — personal stats, active doctor cards with next token numbers,
 * today's patient list, recent admissions.
 */

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-gauge-high" style="color:var(--color-accent);"></i>
        Dashboard
    </h2>
    <div class="flex items-center gap-2">
        <span class="text-sm" style="color:var(--color-text-muted);" id="todayDate"></span>
        <button class="btn btn-ghost btn-sm" id="refreshBtn">
            <i class="fa-solid fa-rotate-right"></i> Refresh
        </button>
    </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="stats-grid" id="statsGrid">
    <?php for ($i = 0; $i < 4; $i++): ?>
    <div class="stat-card">
        <div class="stat-card-icon slate"><span class="spinner"></span></div>
        <div><div class="stat-card-label">Loading…</div><div class="stat-number">—</div></div>
    </div>
    <?php endfor; ?>
</div>

<!-- ── Middle row: Doctors + Quick Action ────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    <!-- Active doctors — token status card grid -->
    <div class="card lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-sm" style="color:var(--color-text);">
                <i class="fa-solid fa-user-doctor mr-1" style="color:var(--color-primary);"></i>
                Doctors — Token Status Today
            </h3>
            <a href="<?= BASE_URL ?>/receptionist/generate-token.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> New Token
            </a>
        </div>
        <div id="doctorCardsGrid"
             style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;">
            <div class="text-center py-6 col-span-full">
                <span class="spinner"></span>
            </div>
        </div>
    </div>

    <!-- Quick action panel -->
    <div class="card flex flex-col gap-3">
        <h3 class="font-bold text-sm" style="color:var(--color-text);">
            <i class="fa-solid fa-bolt mr-1" style="color:var(--color-warning);"></i>
            Quick Actions
        </h3>

        <a href="<?= BASE_URL ?>/receptionist/generate-token.php"
           class="btn btn-primary w-full" style="justify-content:flex-start;gap:0.75rem;">
            <i class="fa-solid fa-ticket"></i>
            Generate Token
        </a>

        <a href="<?= BASE_URL ?>/receptionist/patients.php"
           class="btn btn-ghost w-full" style="justify-content:flex-start;gap:0.75rem;">
            <i class="fa-solid fa-users"></i>
            View Today's Patients
        </a>

        <a href="<?= BASE_URL ?>/receptionist/admissions.php"
           class="btn btn-ghost w-full" style="justify-content:flex-start;gap:0.75rem;">
            <i class="fa-solid fa-clipboard-list"></i>
            Manage Admissions
        </a>

        <div class="section-sep"></div>

        <!-- Recent admissions mini-list -->
        <div>
            <p class="text-xs font-bold mb-2" style="color:var(--color-text-muted);
               text-transform:uppercase;letter-spacing:0.06em;">My Recent Admissions</p>
            <div id="recentAdmissionsMini">
                <div class="text-center py-3"><span class="spinner"></span></div>
            </div>
        </div>
    </div>

</div>

<!-- ── Today's Patients Table ────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="flex items-center justify-between px-5 py-4"
         style="border-bottom:1px solid var(--color-border);">
        <h3 class="font-bold text-sm" style="color:var(--color-text);">
            <i class="fa-solid fa-users mr-1" style="color:var(--color-primary);"></i>
            My Patients Today
        </h3>
        <a href="<?= BASE_URL ?>/receptionist/patients.php" class="btn btn-ghost btn-sm">
            All Patients <i class="fa-solid fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Time</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="myPatientsBody">
                <tr>
                    <td colspan="6" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Receipt preview modal (shared) -->
<div class="modal-backdrop" id="receiptModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-receipt" style="color:var(--color-primary);"></i>
                Patient Receipt
            </div>
            <button class="modal-close" data-modal-close>
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="padding:0.75rem;">
            <div class="receipt-preview-wrapper" id="receiptContent">
                <div class="text-center py-6"><span class="spinner"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="dashPrintReceiptBtn" data-patient-id="">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/receptionist/dashboard.php`;

    // Today date display
    const todayEl = document.getElementById('todayDate');
    if (todayEl) {
        todayEl.textContent = new Date().toLocaleDateString('en-PK', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    // ── Load dashboard ────────────────────────────────────────
    function loadDashboard() {
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) { refreshBtn.disabled = true; refreshBtn.innerHTML = '<span class="spinner"></span>'; }

        fetch(AJAX_URL)
            .then(r => r.json())
            .then(res => {
                if (refreshBtn) { refreshBtn.disabled = false; refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Refresh'; }
                if (!res.success) { showToast(res.message, 'error'); return; }
                const d = res.data;
                renderStats(d.stats);
                renderDoctorCards(d.active_doctors);
                renderMyPatients(d.my_patients);
                renderRecentAdmissions(d.recent_admissions);
            })
            .catch(() => {
                if (refreshBtn) { refreshBtn.disabled = false; refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Refresh'; }
                showToast('Failed to load dashboard.', 'error');
            });
    }

    // ── Stat cards ────────────────────────────────────────────
    function renderStats(s) {
        const cards = [
            { icon:'fa-ticket',          color:'blue',   label:"My Tokens Today",   value: s.my_tokens_today,  sub: `${s.unpaid_today} unpaid` },
            { icon:'fa-money-bill-wave', color:'green',  label:"My Revenue Today",  value: s.my_revenue_fmt,   sub: 'Collected payments' },
            { icon:'fa-clock',           color:'amber',  label:"Pending Payment",   value: s.unpaid_today,     sub: 'Patients unpaid' },
            { icon:'fa-user-doctor',     color:'blue',   label:"Active Doctors",    value: s.active_doctors,   sub: 'Available today' },
        ];
        document.getElementById('statsGrid').innerHTML = cards.map(c => `
            <div class="stat-card animate-fade-in">
                <div class="stat-card-icon ${c.color}"><i class="fa-solid ${c.icon}"></i></div>
                <div>
                    <div class="stat-card-label">${c.label}</div>
                    <div class="stat-number">${c.value}</div>
                    <div class="stat-card-sub">${c.sub}</div>
                </div>
            </div>`).join('');
    }

    // ── Doctor cards ──────────────────────────────────────────
    function renderDoctorCards(doctors) {
        const grid = document.getElementById('doctorCardsGrid');
        if (!doctors.length) {
            grid.innerHTML = `<p class="text-xs text-center col-span-full py-4"
                               style="color:var(--color-text-faint);">No active doctors.</p>`;
            return;
        }
        const prefix = window.APP_CONFIG.tokenPrefix;
        grid.innerHTML = doctors.map(d => `
            <div class="room-card animate-fade-in"
                 style="border-top:3px solid var(--color-primary);cursor:pointer;"
                 onclick="window.location.href='${window.APP_CONFIG.baseUrl}/receptionist/generate-token.php?doctor_id=${d.id}'">
                <div class="flex items-start justify-between mb-1">
                    <div class="font-bold text-xs" style="color:var(--color-text);">
                        Dr. ${escHtml(d.name)}
                    </div>
                    <span class="badge badge-active">${d.today_total} total</span>
                </div>
                <div class="text-xs mb-3" style="color:var(--color-text-muted);">${escHtml(d.specialization)}</div>

                <div class="next-token-box" style="padding:0.75rem;margin-bottom:0.75rem;">
                    <div class="next-token-label" style="font-size:10px;">Next Token</div>
                    <div style="font-family:'JetBrains Mono',monospace;font-size:1.3rem;
                                font-weight:800;color:var(--color-primary);">
                        ${prefix}-${String(d.next_token).padStart(3,'0')}
                    </div>
                </div>

                <div class="text-xs font-bold" style="color:var(--color-accent);">${escHtml(d.fee_fmt)}</div>
                <div class="text-xs mt-0.5" style="color:var(--color-text-faint);">
                    My tokens: ${d.my_tokens}
                </div>
            </div>`).join('');
    }

    // ── My patients today ─────────────────────────────────────
    function renderMyPatients(patients) {
        const tbody  = document.getElementById('myPatientsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        if (!patients.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="table-empty">
                <div class="table-empty-icon">🏥</div>
                No patients registered today yet.<br>
                <a href="${window.APP_CONFIG.baseUrl}/receptionist/generate-token.php"
                   class="btn btn-primary btn-sm mt-2">
                    <i class="fa-solid fa-plus"></i> Generate First Token
                </a>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = patients.map(p => `
            <tr class="animate-fade-in">
                <td>
                    <span class="font-mono-nums font-bold text-xs" style="color:var(--color-primary);">
                        ${prefix}-${p.token_number}
                    </span>
                </td>
                <td>
                    <div class="font-medium text-xs">${escHtml(p.name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${ucFirst(p.gender)}</div>
                </td>
                <td>
                    <div class="text-xs font-medium">${escHtml(p.doctor_name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${escHtml(p.specialization)}</div>
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">${formatTime(p.visit_time)}</td>
                <td>
                    <span class="badge ${p.payment_status === 'paid' ? 'badge-paid' : 'badge-unpaid'}">
                        ${p.payment_status === 'paid' ? '✓ Paid' : '⏳ Unpaid'}
                    </span>
                </td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-ghost btn-icon btn-sm" title="View Receipt"
                                onclick="viewReceipt(${p.id})">
                            <i class="fa-solid fa-receipt"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    // ── Recent admissions mini ────────────────────────────────
    function renderRecentAdmissions(adms) {
        const el = document.getElementById('recentAdmissionsMini');
        if (!adms.length) {
            el.innerHTML = `<p class="text-xs" style="color:var(--color-text-faint);">None yet.</p>`;
            return;
        }
        el.innerHTML = adms.map(a => `
            <div class="mb-2 pb-2" style="border-bottom:1px solid var(--color-border);">
                <div class="flex justify-between items-start">
                    <p class="text-xs font-semibold" style="color:var(--color-text);">${escHtml(a.patient_name)}</p>
                    <span class="badge ${a.status === 'admitted' ? 'badge-admitted' : 'badge-discharged'}" style="font-size:9px;">
                        ${a.status === 'admitted' ? '● In' : '✓ Out'}
                    </span>
                </div>
                <p class="text-xs" style="color:var(--color-text-muted);">
                    Room ${escHtml(a.room_number)} &bull; ${escHtml(a.admitted_fmt)}
                </p>
            </div>`).join('');
    }

    // ── View receipt ──────────────────────────────────────────
    window.viewReceipt = function (patientId) {
        document.getElementById('receiptContent').innerHTML =
            '<div class="text-center py-6"><span class="spinner"></span></div>';
        document.getElementById('dashPrintReceiptBtn').dataset.patientId = patientId;
        openModal('receiptModal');

        fetch(`${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=receipt&id=${patientId}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('receiptContent').innerHTML =
                    res.success ? res.data.html
                                : `<p style="color:var(--color-danger);">${escHtml(res.message)}</p>`;
            })
            .catch(() => {
                document.getElementById('receiptContent').innerHTML =
                    '<p style="color:var(--color-danger);">Failed to load receipt.</p>';
            });
    };

    // Print button
    document.getElementById('dashPrintReceiptBtn')?.addEventListener('click', function () {
        const id = this.dataset.patientId;
        if (id) openPrintWindow('receipt', id);
    });

    // ── Utilities ─────────────────────────────────────────────
    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ── Init ──────────────────────────────────────────────────
    loadDashboard();
    document.getElementById('refreshBtn')?.addEventListener('click', loadDashboard);
    setInterval(loadDashboard, 3 * 60 * 1000); // auto-refresh every 3 min

})();
JS;

require_once BASE_PATH . '/receptionist/footer.php';
?>