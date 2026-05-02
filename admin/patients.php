<?php
/**
 * admin/patients.php
 * Admin view of ALL patient records across all dates.
 * Read + delete only — editing is done by receptionists on the day.
 */

$pageTitle  = 'All Patients';
$activePage = 'patients';
require_once __DIR__ . '/header.php';

$doctors = Database::fetchAll(
    "SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name"
);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-users" style="color:var(--color-primary);"></i>
        All Patients
    </h2>
    <span class="text-sm" id="totalCount" style="color:var(--color-text-muted);">Loading…</span>
</div>

<!-- ── Filters ───────────────────────────────────────────── -->
<div class="filters-bar mb-4">

    <div class="flex-1" style="min-width:160px;max-width:260px;">
        <label class="form-label">Search</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="Name, phone, doctor…" style="padding-left:36px;">
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
        <select id="doctorFilter" class="form-select" style="min-width:160px;">
            <option value="">All Doctors</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>">Dr. <?= e($doc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="form-label">Payment</label>
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

<!-- ── Patients Table ────────────────────────────────────── -->
<div class="card" style="padding:0;">
    <div class="table-wrapper">
        <table class="data-table" id="patientsTable">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Gender / Age</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>By</th>
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

    <!-- Pagination -->
    <div id="paginationBar"
         style="padding:0.75rem 1.25rem;border-top:1px solid var(--color-border);
                display:flex;align-items:center;justify-content:space-between;gap:1rem;
                font-size:13px;color:var(--color-text-muted);">
        <span id="pageInfo">—</span>
        <div class="flex gap-2" id="pageBtns"></div>
    </div>
</div>

<!-- Patient Detail Modal -->
<div class="modal-backdrop" id="patientDetailModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-user" style="color:var(--color-primary);"></i>
                Patient Details
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding:0.75rem;">
            <div class="receipt-preview-wrapper" id="patientDetailContent">
                <div class="text-center py-6"><span class="spinner"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Close</button>
            <button class="btn btn-primary" id="adminPrintReceiptBtn" data-patient-id="">
                <i class="fa-solid fa-print"></i> Print Receipt
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/admin/patients.php`;
    let currentPage = 1;

    // Default date range: last 30 days
    const today    = new Date();
    const thirtyAgo= new Date(today);
    thirtyAgo.setDate(today.getDate() - 30);

    function toLocalDate(d) {
        return d.toISOString().split('T')[0];
    }

    document.getElementById('dateFrom').value = toLocalDate(thirtyAgo);
    document.getElementById('dateTo').value   = toLocalDate(today);

    // ── Load patients ─────────────────────────────────────────
    function loadPatients(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({
            action:         'getPatients',
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
                document.getElementById('totalCount').textContent =
                    `${d.total} patient(s) found`;
                renderTable(d.patients);
                renderPagination(d.page, d.total_pages, d.total, d.per_page);
            })
            .catch(() => showToast('Failed to load patients.', 'error'));
    }

    function renderTable(patients) {
        const tbody  = document.getElementById('patientsBody');
        const prefix = window.APP_CONFIG.tokenPrefix;

        if (!patients.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="table-empty">
                <div class="table-empty-icon">🏥</div>No patients match your filters.</td></tr>`;
            return;
        }

        tbody.innerHTML = patients.map(p => `
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
                <td>
                    <div class="text-xs font-medium">${escHtml(p.visit_date_fmt)}</div>
                    <div class="text-xs" style="color:var(--color-text-muted);">${escHtml(p.visit_time_fmt)}</div>
                </td>
                <td class="text-xs">${ucFirst(p.gender)}${p.age ? ' / ' + p.age + 'y' : ''}</td>
                <td class="text-xs font-bold" style="color:var(--color-accent);">
                    ${escHtml(p.amount_fmt)}
                </td>
                <td>
                    <span class="badge ${p.payment_status === 'paid' ? 'badge-paid' : 'badge-unpaid'}">
                        ${p.payment_status === 'paid' ? '✓ Paid' : '⏳ Unpaid'}
                    </span>
                </td>
                <td class="text-xs" style="color:var(--color-text-muted);">
                    ${escHtml(p.receptionist_name)}
                </td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-ghost btn-icon btn-sm" title="View & Print Receipt"
                                onclick="viewPatient(${p.id})">
                            <i class="fa-solid fa-receipt"></i>
                        </button>
                        <button class="btn btn-icon btn-sm" title="Delete"
                                style="color:var(--color-danger);background:var(--color-danger-lt);"
                                onclick="confirmDelete(${p.id}, '${escJs(p.name)}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function renderPagination(page, totalPages, total, perPage) {
        const start   = (page - 1) * perPage + 1;
        const end     = Math.min(page * perPage, total);
        document.getElementById('pageInfo').textContent =
            total ? `Showing ${start}–${end} of ${total}` : 'No results';

        const btns = document.getElementById('pageBtns');
        if (totalPages <= 1) { btns.innerHTML = ''; return; }

        let html = `<button class="btn btn-ghost btn-sm" ${page <= 1 ? 'disabled' : ''}
                            onclick="loadPatients(${page - 1})">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>`;
        for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p++) {
            html += `<button class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-ghost'}"
                             onclick="loadPatients(${p})">${p}</button>`;
        }
        html += `<button class="btn btn-ghost btn-sm" ${page >= totalPages ? 'disabled' : ''}
                         onclick="loadPatients(${page + 1})">
                     <i class="fa-solid fa-chevron-right"></i>
                 </button>`;
        btns.innerHTML = html;
    }

    // View patient detail / receipt
    window.viewPatient = function (id) {
        document.getElementById('patientDetailContent').innerHTML =
            '<div class="text-center py-6"><span class="spinner"></span></div>';
        document.getElementById('adminPrintReceiptBtn').dataset.patientId = id;
        openModal('patientDetailModal');

        fetch(`${AJAX_URL}?${new URLSearchParams({ action: 'getPatientById', id })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    document.getElementById('patientDetailContent').innerHTML =
                        `<p style="color:var(--color-danger);">${escHtml(res.message)}</p>`;
                    return;
                }
                document.getElementById('patientDetailContent').innerHTML =
                    res.data.patient.receipt_html;
            })
            .catch(() => {
                document.getElementById('patientDetailContent').innerHTML =
                    '<p style="color:var(--color-danger);">Failed to load patient data.</p>';
            });
    };

    document.getElementById('adminPrintReceiptBtn')?.addEventListener('click', function () {
        const id = this.dataset.patientId;
        if (id) openPrintWindow('receipt', id);
    });

    // Delete
    window.confirmDelete = function (id, name) {
        showConfirmModal(
            'Delete Patient Record',
            `Permanently delete the record for <strong>${escHtml(name)}</strong>?`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deletePatient');
                fd.set('csrf_token', getCsrfToken());
                fd.set('id', id);
                ajaxRequest(AJAX_URL, 'POST', fd,
                    (_, msg) => { showToast(msg, 'success'); loadPatients(currentPage); }
                );
            }, 'danger'
        );
    };

    // Expose for pagination buttons
    window.loadPatients = loadPatients;

    // Filters
    const debouncedLoad = debounce(() => loadPatients(1), 300);
    document.getElementById('searchInput').addEventListener('input', debouncedLoad);
    document.getElementById('dateFrom').addEventListener('change', () => loadPatients(1));
    document.getElementById('dateTo').addEventListener('change', () => loadPatients(1));
    document.getElementById('doctorFilter').addEventListener('change', () => loadPatients(1));
    document.getElementById('paymentFilter').addEventListener('change', () => loadPatients(1));
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value  = '';
        document.getElementById('dateFrom').value     = '';
        document.getElementById('dateTo').value       = '';
        document.getElementById('doctorFilter').value = '';
        document.getElementById('paymentFilter').value= '';
        loadPatients(1);
    });

    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escJs(s)   { return String(s??'').replace(/'/g,"\\'"); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    loadPatients(1);
})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>