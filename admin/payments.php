<?php
/**
 * admin/payments.php
 * Admin payments overview: revenue stats, full payments table, update payment status.
 */

$pageTitle  = 'Payments';
$activePage = 'payments';
require_once __DIR__ . '/header.php';

$doctors = Database::fetchAll(
    "SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name"
);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-money-bill-wave" style="color:var(--color-primary);"></i>
        Payments
    </h2>
    <button class="btn btn-ghost btn-sm" id="refreshBtn">
        <i class="fa-solid fa-rotate-right"></i> Refresh
    </button>
</div>

<!-- ── Revenue Stats ─────────────────────────────────────── -->
<div class="stats-grid mb-4" id="revenueStatsGrid"
     style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">
    <?php foreach (['Today','This Week','This Month','All Time'] as $l): ?>
    <div class="stat-card">
        <div class="stat-card-icon green"><span class="spinner"></span></div>
        <div><div class="stat-card-label"><?= $l ?></div><div class="stat-number">—</div></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filter row ────────────────────────────────────────── -->
<!-- Filter summary strip -->
<div class="stats-grid mb-4" id="filterSummaryStrip"
     style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));display:none;">
    <div class="stat-card">
        <div class="stat-card-icon green"><i class="fa-solid fa-circle-check"></i></div>
        <div><div class="stat-card-label">Paid (filtered)</div>
             <div class="stat-number" id="filteredPaid">—</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon amber"><i class="fa-solid fa-clock"></i></div>
        <div><div class="stat-card-label">Unpaid (filtered)</div>
             <div class="stat-number" id="filteredUnpaid">—</div></div>
    </div>
</div>

<div class="filters-bar mb-4">
    <div class="flex-1" style="min-width:160px;max-width:240px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="Patient, doctor…" style="padding-left:36px;">
        </div>
    </div>
    <div>
        <label class="form-label">From</label>
        <input type="date" id="dateFrom" class="form-input" style="min-width:140px;">
    </div>
    <div>
        <label class="form-label">To</label>
        <input type="date" id="dateTo" class="form-input" style="min-width:140px;">
    </div>
    <div>
        <label class="form-label">Doctor</label>
        <select id="doctorFilter" class="form-select" style="min-width:155px;">
            <option value="">All Doctors</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label">Status</label>
        <select id="paymentFilter" class="form-select" style="min-width:120px;">
            <option value="">All</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Unpaid</option>
        </select>
    </div>
    <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" style="align-self:flex-end;">
        <i class="fa-solid fa-xmark"></i> Clear
    </button>
</div>

<!-- ── Payments Table ────────────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>By</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="paymentsBody">
                <tr>
                    <td colspan="9" class="table-empty">
                        <div class="table-empty-icon"><span class="spinner"></span></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div id="paginationBar"
         style="padding:0.75rem 1.25rem;border-top:1px solid var(--color-border);
                display:flex;align-items:center;justify-content:space-between;gap:1rem;
                font-size:13px;color:var(--color-text-muted);">
        <span id="pageInfo">—</span>
        <div class="flex gap-2" id="pageBtns"></div>
    </div>
</div>

<!-- Update Payment Modal -->
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
                Patient: <strong id="payModalName">—</strong>
            </p>
            <form id="paymentForm" novalidate>
                <input type="hidden" name="action"       value="updatePayment">
                <input type="hidden" name="csrf_token"   value="<?= e($csrfToken) ?>">
                <input type="hidden" name="patient_id"   id="pay_patient_id">

                <div class="form-group">
                    <label class="form-label">Payment Status</label>
                    <input type="hidden" name="payment_status" id="pay_status" value="unpaid">
                    <div class="payment-toggle">
                        <button type="button" class="payment-toggle-btn unpaid"
                                id="payBtnUnpaid">⏳ Unpaid</button>
                        <button type="button" class="payment-toggle-btn paid"
                                id="payBtnPaid">✅ Paid</button>
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
            <button class="btn btn-success" id="paySubmitBtn">
                <i class="fa-solid fa-check"></i> Save Payment
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/admin/payments.php`;
    let currentPage = 1;

    // Default date range: this month
    const today   = new Date();
    const firstDOM= new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('dateFrom').value = firstDOM.toISOString().split('T')[0];
    document.getElementById('dateTo').value   = today.toISOString().split('T')[0];

    // ── Load revenue stats ────────────────────────────────────
    function loadRevenueStats() {
        fetch(`${AJAX_URL}?action=getRevenueStats`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                const d  = res.data;
                const cfg = [
                    { label:"Today's Revenue",     value: d.today,    icon:'fa-sun',          color:'green'  },
                    { label:"This Week",            value: d.week,     icon:'fa-calendar-week', color:'blue'   },
                    { label:"This Month",           value: d.month,    icon:'fa-calendar',      color:'violet' },
                    { label:"All-Time Revenue",     value: d.all_time, icon:'fa-chart-line',    color:'green'  },
                ];
                document.getElementById('revenueStatsGrid').innerHTML = cfg.map(c => `
                    <div class="stat-card animate-fade-in">
                        <div class="stat-card-icon ${c.color}">
                            <i class="fa-solid ${c.icon}"></i>
                        </div>
                        <div>
                            <div class="stat-card-label">${c.label}</div>
                            <div class="stat-number" style="font-size:1.15rem;">${c.value}</div>
                        </div>
                    </div>`).join('');
            })
            .catch(() => {});
    }

    // ── Load payments ─────────────────────────────────────────
    function loadPayments(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({
            action:         'getPayments',
            search:         document.getElementById('searchInput').value.trim(),
            date_from:      document.getElementById('dateFrom').value,
            date_to:        document.getElementById('dateTo').value,
            doctor_id:      document.getElementById('doctorFilter').value,
            payment_status: document.getElementById('paymentFilter').value,
            page,
            per_page:       50,
        });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const d = res.data;

                // Show filter summary if any filter active
                const hasFilter = params.get('search') || params.get('doctor_id') ||
                                  params.get('payment_status');
                const strip = document.getElementById('filterSummaryStrip');
                strip.style.display = hasFilter ? '' : 'none';
                document.getElementById('filteredPaid').textContent   = d.paid_fmt;
                document.getElementById('filteredUnpaid').textContent = d.unpaid_fmt;

                renderTable(d.payments);
                renderPagination(d.page, d.total_pages, d.total, d.per_page);
            })
            .catch(() => showToast('Failed to load payments.', 'error'));
    }

    function renderTable(rows) {
        const tbody  = document.getElementById('paymentsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="table-empty">
                <div class="table-empty-icon">💰</div>No payment records found.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr class="animate-fade-in" data-id="${r.patient_id}">
                <td>
                    <span class="font-mono-nums font-bold text-xs" style="color:var(--color-primary);">
                        ${prefix}-${r.token_number}
                    </span>
                </td>
                <td>
                    <div class="font-medium text-xs">${escHtml(r.name)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${ucFirst(r.gender)}</div>
                </td>
                <td class="text-xs">${escHtml(r.doctor_name)}</td>
                <td>
                    <div class="text-xs font-medium">${escHtml(r.visit_date_fmt)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${escHtml(r.visit_time_fmt)}</div>
                </td>
                <td class="font-bold text-xs" style="color:var(--color-accent);">
                    ${escHtml(r.amount_fmt)}
                </td>
                <td class="text-xs">
                    ${r.payment_method
                        ? `<span class="badge badge-active">${ucFirst(r.payment_method)}</span>`
                        : '<span style="color:var(--color-text-faint);">—</span>'}
                </td>
                <td>
                    <span class="badge ${r.payment_status === 'paid' ? 'badge-paid' : 'badge-unpaid'}">
                        ${r.payment_status === 'paid' ? '✓ Paid' : '⏳ Unpaid'}
                    </span>
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">
                    ${escHtml(r.receptionist_name)}
                </td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-icon btn-sm" title="Update Payment"
                                style="color:var(--color-accent);background:var(--color-accent-lt);"
                                onclick="openPayModal(${r.patient_id},'${escJs(r.name)}','${r.payment_status}','${r.payment_method||''}')">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function renderPagination(page, totalPages, total, perPage) {
        const start = (page - 1) * perPage + 1;
        const end   = Math.min(page * perPage, total);
        document.getElementById('pageInfo').textContent =
            total ? `Showing ${start}–${end} of ${total}` : 'No results';

        const btns = document.getElementById('pageBtns');
        if (totalPages <= 1) { btns.innerHTML = ''; return; }

        let html = `<button class="btn btn-ghost btn-sm" ${page<=1?'disabled':''}
                            onclick="loadPayments(${page-1})">
                        <i class="fa-solid fa-chevron-left"></i></button>`;
        for (let p = Math.max(1, page-2); p <= Math.min(totalPages, page+2); p++) {
            html += `<button class="btn btn-sm ${p===page?'btn-primary':'btn-ghost'}"
                             onclick="loadPayments(${p})">${p}</button>`;
        }
        html += `<button class="btn btn-ghost btn-sm" ${page>=totalPages?'disabled':''}
                         onclick="loadPayments(${page+1})">
                     <i class="fa-solid fa-chevron-right"></i></button>`;
        btns.innerHTML = html;
    }

    // ── Payment modal ─────────────────────────────────────────
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

    window.openPayModal = function (id, name, status, method) {
        document.getElementById('pay_patient_id').value         = id;
        document.getElementById('payModalName').textContent     = name;
        document.getElementById('pay_status').value             = status;
        document.getElementById('pay_method').value             = method;
        document.getElementById('payBtnPaid').classList.toggle('selected',   status === 'paid');
        document.getElementById('payBtnUnpaid').classList.toggle('selected', status !== 'paid');
        document.getElementById('payMethodGroup').style.display = status === 'paid' ? '' : 'none';
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

    document.getElementById('paySubmitBtn').addEventListener('click', () => {
        const status = document.getElementById('pay_status').value;
        const method = document.getElementById('pay_method').value;
        if (status === 'paid' && !method) {
            showFieldError('pay_method', 'Please select a payment method.'); return;
        }
        clearFieldError('pay_method');
        const btn = document.getElementById('paySubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('paymentForm')),
            (_, msg) => {
                showToast(msg, 'success');
                playSound('cash-register.mp3');
                closeModal('paymentModal');
                loadPayments(currentPage);
                loadRevenueStats();
            }, null, btn
        );
    });

    // Expose for pagination
    window.loadPayments = loadPayments;

    // Filters
    const debouncedLoad = debounce(() => loadPayments(1), 300);
    document.getElementById('searchInput').addEventListener('input', debouncedLoad);
    document.getElementById('dateFrom').addEventListener('change', () => loadPayments(1));
    document.getElementById('dateTo').addEventListener('change', () => loadPayments(1));
    document.getElementById('doctorFilter').addEventListener('change', () => loadPayments(1));
    document.getElementById('paymentFilter').addEventListener('change', () => loadPayments(1));
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value  = '';
        document.getElementById('dateFrom').value     = '';
        document.getElementById('dateTo').value       = '';
        document.getElementById('doctorFilter').value = '';
        document.getElementById('paymentFilter').value= '';
        loadPayments(1);
    });
    document.getElementById('refreshBtn').addEventListener('click', () => {
        loadRevenueStats();
        loadPayments(currentPage);
    });

    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escJs(s)   { return String(s??'').replace(/'/g,"\\'"); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // Init
    loadRevenueStats();
    loadPayments(1);
})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>
