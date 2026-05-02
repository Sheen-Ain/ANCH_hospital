<?php
/**
 * admin/dashboard.php
 * Admin home — stat cards, revenue chart, recent patients, online users, recent admissions.
 * All data loaded via a single AJAX call to ajax/admin/dashboard.php on page load.
 */

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-gauge-high" style="color:var(--color-primary);"></i>
        Dashboard
    </h2>
    <div class="flex items-center gap-2">
        <span class="text-sm" style="color:var(--color-text-muted);" id="todayDate"></span>
        <button class="btn btn-outline btn-sm" id="refreshDashBtn" title="Refresh data">
            <i class="fa-solid fa-rotate-right"></i>
            Refresh
        </button>
    </div>
</div>

<!-- ── Stat Cards ────────────────────────────────────────── -->
<div class="stats-grid" id="statsGrid">
    <!-- Skeleton placeholders while loading -->
    <?php for ($i = 0; $i < 6; $i++): ?>
    <div class="stat-card">
        <div class="stat-card-icon slate" style="animation:pulse 1.5s ease infinite;">
            <i class="fa-solid fa-spinner fa-spin"></i>
        </div>
        <div>
            <div class="stat-card-label">Loading…</div>
            <div class="stat-number">—</div>
        </div>
    </div>
    <?php endfor; ?>
</div>

<!-- ── Middle Row: Revenue Chart + Doctor Tokens ─────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    <!-- Revenue 7-day bar chart -->
    <div class="card lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-sm" style="color:var(--color-text);">Revenue — Last 7 Days</h3>
                <p class="text-xs mt-0.5" style="color:var(--color-text-muted);">Paid consultations only</p>
            </div>
            <div class="badge badge-active" id="weekTotalBadge">Loading…</div>
        </div>
        <div id="revenueChartWrap" style="height:200px;position:relative;">
            <canvas id="revenueChart" style="width:100%;height:100%;"></canvas>
        </div>
    </div>

    <!-- Doctor token counts today -->
    <div class="card">
        <h3 class="font-bold text-sm mb-3" style="color:var(--color-text);">
            <i class="fa-solid fa-user-doctor mr-1" style="color:var(--color-primary);"></i>
            Doctors — Today
        </h3>
        <div id="doctorTokensList">
            <div class="text-center py-6" style="color:var(--color-text-faint);">
                <span class="spinner"></span>
            </div>
        </div>
    </div>

</div>

<!-- ── Bottom Row: Recent Patients + Online Users + Admissions ── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Recent Patients Today -->
    <div class="card lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-sm" style="color:var(--color-text);">
                <i class="fa-solid fa-users mr-1" style="color:var(--color-primary);"></i>
                Today's Patients
            </h3>
            <a href="<?= BASE_URL ?>/admin/patients.php" class="btn btn-ghost btn-sm">
                View All <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="table-wrapper" id="recentPatientsWrap">
            <table class="data-table" id="recentPatientsTable">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Time</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody id="recentPatientsBody">
                    <tr>
                        <td colspan="5" class="table-empty">
                            <div class="table-empty-icon"><span class="spinner"></span></div>
                            Loading patients…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right column: Online Users + Recent Admissions -->
    <div class="flex flex-col gap-4">

        <!-- Online Users -->
        <div class="card">
            <h3 class="font-bold text-sm mb-3" style="color:var(--color-text);">
                <span class="online-dot mr-1"></span>
                Online Now
            </h3>
            <div id="onlineUsersList">
                <div class="text-center py-4" style="color:var(--color-text-faint);">
                    <span class="spinner"></span>
                </div>
            </div>
        </div>

        <!-- Recent Admissions -->
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-sm" style="color:var(--color-text);">
                    <i class="fa-solid fa-clipboard-list mr-1" style="color:var(--color-violet);"></i>
                    Recent Admissions
                </h3>
                <a href="<?= BASE_URL ?>/admin/admissions.php" class="btn btn-ghost btn-sm">All</a>
            </div>
            <div id="recentAdmissionsList">
                <div class="text-center py-4" style="color:var(--color-text-faint);">
                    <span class="spinner"></span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    // ── Today's date display ─────────────────────────────────
    const todayEl = document.getElementById('todayDate');
    if (todayEl) {
        todayEl.textContent = new Date().toLocaleDateString('en-PK', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    // ── Revenue chart instance ────────────────────────────────
    let revenueChart = null;

    // ── Load dashboard data ───────────────────────────────────
    function loadDashboard() {
        const refreshBtn = document.getElementById('refreshDashBtn');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<span class="spinner"></span> Loading…';
        }

        fetch(`${window.APP_CONFIG.baseUrl}/ajax/admin/dashboard.php`)
            .then(r => r.json())
            .then(res => {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Refresh';
                }
                if (!res.success) { showToast(res.message, 'error'); return; }
                const d = res.data;
                renderStats(d.stats);
                renderRevenueChart(d.revenue_7days);
                renderDoctorTokens(d.doctor_tokens);
                renderRecentPatients(d.recent_patients);
                renderOnlineUsers(d.online_users);
                renderRecentAdmissions(d.recent_admissions);
            })
            .catch(() => {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Refresh';
                }
                showToast('Failed to load dashboard data.', 'error');
            });
    }

    // ── Stat Cards ────────────────────────────────────────────
    function renderStats(stats) {
        const cards = [
            {
                icon:  'fa-ticket',
                color: 'blue',
                label: "Today's Tokens",
                value: stats.today_tokens,
                sub:   `${stats.unpaid_today} unpaid`,
            },
            {
                icon:  'fa-money-bill-wave',
                color: 'green',
                label: "Today's Revenue",
                value: stats.today_revenue_fmt,
                sub:   'Paid consultations',
            },
            {
                icon:  'fa-user-doctor',
                color: 'blue',
                label: 'Active Doctors',
                value: stats.total_doctors,
                sub:   'Available today',
            },
            {
                icon:  'fa-bed',
                color: 'violet',
                label: 'Room Occupancy',
                value: `${stats.occupied_rooms} / ${stats.active_rooms}`,
                sub:   'Rooms occupied / active',
            },
            {
                icon:  'fa-clipboard-list',
                color: 'amber',
                label: 'Admitted Patients',
                value: stats.admitted_patients,
                sub:   'Currently in-patient',
            },
            {
                icon:  'fa-user-nurse',
                color: 'slate',
                label: 'Receptionists',
                value: stats.total_receptionists,
                sub:   'Registered staff',
            },
        ];

        const grid = document.getElementById('statsGrid');
        grid.innerHTML = cards.map(c => `
            <div class="stat-card animate-fade-in">
                <div class="stat-card-icon ${c.color}">
                    <i class="fa-solid ${c.icon}"></i>
                </div>
                <div>
                    <div class="stat-card-label">${c.label}</div>
                    <div class="stat-number">${c.value}</div>
                    <div class="stat-card-sub">${c.sub}</div>
                </div>
            </div>
        `).join('');
    }

    // ── Revenue Chart ─────────────────────────────────────────
    function renderRevenueChart(data) {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const isDark = document.documentElement.classList.contains('dark');
        const gridColor  = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
        const labelColor = isDark ? '#94a3b8' : '#64748b';

        const labels   = data.map(d => d.label);
        const revenues = data.map(d => d.revenue);
        const weekTotal = revenues.reduce((a, b) => a + b, 0);

        // Update badge
        const badge = document.getElementById('weekTotalBadge');
        if (badge) badge.textContent = formatCurrency(weekTotal);

        if (revenueChart) revenueChart.destroy();

        revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label:           'Revenue',
                    data:            revenues,
                    backgroundColor: 'rgba(3,105,161,0.15)',
                    borderColor:     'rgba(3,105,161,0.9)',
                    borderWidth:     2,
                    borderRadius:    8,
                    borderSkipped:   false,
                    hoverBackgroundColor: 'rgba(3,105,161,0.3)',
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${formatCurrency(ctx.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid:  { display: false },
                        ticks: { color: labelColor, font: { size: 11 } },
                    },
                    y: {
                        grid:  { color: gridColor },
                        ticks: {
                            color: labelColor,
                            font:  { size: 11 },
                            callback: v => formatCurrency(v),
                        },
                        beginAtZero: true,
                    },
                },
            },
        });
    }

    // ── Doctor Tokens ─────────────────────────────────────────
    function renderDoctorTokens(rows) {
        const el = document.getElementById('doctorTokensList');
        if (!rows.length) {
            el.innerHTML = `<p class="text-xs text-center py-4" style="color:var(--color-text-faint);">No doctors found.</p>`;
            return;
        }

        const max = Math.max(...rows.map(r => r.patient_count), 1);
        el.innerHTML = rows.map(r => `
            <div class="mb-3">
                <div class="flex justify-between text-xs mb-1">
                    <span class="font-medium" style="color:var(--color-text);">Dr. ${escHtml(r.doctor_name)}</span>
                    <span class="font-bold" style="color:var(--color-primary);">${r.patient_count}</span>
                </div>
                <div style="height:6px;background:var(--color-border);border-radius:99px;overflow:hidden;">
                    <div style="height:100%;width:${Math.round((r.patient_count/max)*100)}%;background:var(--color-primary);border-radius:99px;transition:width 0.6s ease;"></div>
                </div>
            </div>
        `).join('');
    }

    // ── Recent Patients ───────────────────────────────────────
    function renderRecentPatients(rows) {
        const tbody = document.getElementById('recentPatientsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        if (!rows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="table-empty">
                        <div class="table-empty-icon">🏥</div>
                        No patients registered yet today.
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = rows.map(p => `
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
            </tr>
        `).join('');
    }

    // ── Online Users ──────────────────────────────────────────
    function renderOnlineUsers(users) {
        const el = document.getElementById('onlineUsersList');

        if (!users.length) {
            el.innerHTML = `<p class="text-xs text-center py-3" style="color:var(--color-text-faint);">No users online.</p>`;
            return;
        }

        el.innerHTML = users.map(u => {
            const initials = (u.first_name[0] || '') + (u.last_name[0] || '');
            return `
            <div class="online-user-item">
                <div class="avatar" style="width:28px;height:28px;font-size:10px;flex-shrink:0;">
                    ${escHtml(initials.toUpperCase())}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-medium truncate" style="color:var(--color-text);">
                        ${escHtml(u.first_name + ' ' + u.last_name)}
                    </div>
                    <div class="text-xs capitalize" style="color:var(--color-text-faint);">${escHtml(u.role_name)}</div>
                </div>
                <span class="online-dot flex-shrink-0"></span>
            </div>`;
        }).join('');
    }

    // ── Recent Admissions ─────────────────────────────────────
    function renderRecentAdmissions(rows) {
        const el = document.getElementById('recentAdmissionsList');

        if (!rows.length) {
            el.innerHTML = `<p class="text-xs text-center py-3" style="color:var(--color-text-faint);">No admissions yet.</p>`;
            return;
        }

        const typeColors = { general:'badge-general', private:'badge-private', icu:'badge-icu' };

        el.innerHTML = rows.map(a => `
            <div class="mb-3 pb-3" style="border-bottom:1px solid var(--color-border);">
                <div class="flex justify-between items-start gap-1">
                    <div>
                        <p class="text-xs font-bold" style="color:var(--color-text);">
                            ${escHtml(a.patient_name)}
                        </p>
                        <p class="text-xs" style="color:var(--color-text-muted);">
                            Room ${escHtml(a.room_number)} &bull; Dr. ${escHtml(a.doctor_name)}
                        </p>
                    </div>
                    <span class="badge ${a.status === 'admitted' ? 'badge-admitted' : 'badge-discharged'} flex-shrink-0">
                        ${a.status === 'admitted' ? '● In' : '✓ Out'}
                    </span>
                </div>
            </div>
        `).join('');
    }

    // ── Utility: safe HTML escape ─────────────────────────────
    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

    // ── Init ──────────────────────────────────────────────────
    loadDashboard();

    // Refresh button
    document.getElementById('refreshDashBtn')?.addEventListener('click', loadDashboard);

    // Auto-refresh every 5 minutes
    setInterval(loadDashboard, 5 * 60 * 1000);

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>
