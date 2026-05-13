<?php
/**
 * admin/rooms.php
 * Room management: visual card grid, stat strip, add, edit, toggle maintenance, delete.
 */

$pageTitle  = 'Rooms';
$activePage = 'rooms';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fa-solid fa-bed" style="color:var(--color-primary);"></i>
        Rooms
    </h2>
    <button class="btn btn-primary" id="addRoomBtn">
        <i class="fa-solid fa-plus"></i>
        Add Room
    </button>
</div>

<!-- ── Summary Strip ─────────────────────────────────────── -->
<div class="stats-grid mb-4" id="roomStatsGrid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));">
    <?php foreach (['Total Rooms','Occupied','Available','Inactive'] as $label): ?>
    <div class="stat-card">
        <div class="stat-card-icon slate"><span class="spinner"></span></div>
        <div>
            <div class="stat-card-label"><?= $label ?></div>
            <div class="stat-number">—</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Filters Bar ───────────────────────────────────────── -->
<div class="filters-bar mb-4">
    <div class="flex-1" style="min-width:160px;max-width:260px;">
        <label class="form-label">Search Room #</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-text-faint);font-size:13px;"></i>
            <input type="text" id="searchInput" class="form-input"
                   placeholder="e.g. G-101" style="padding-left:36px;">
        </div>
    </div>

    <div>
        <label class="form-label">Type</label>
        <select id="typeFilter" class="form-select" style="min-width:130px;">
            <option value="">All Types</option>
            <option value="general">General</option>
            <option value="private">Private</option>
            <option value="icu">ICU</option>
        </select>
    </div>

    <div>
        <label class="form-label">Status</label>
        <select id="statusFilter" class="form-select" style="min-width:140px;">
            <option value="">All Statuses</option>
            <option value="available">Available</option>
            <option value="occupied">Occupied</option>
            <option value="inactive">Maintenance</option>
        </select>
    </div>

    <div>
        <label class="form-label">View</label>
        <div class="flex gap-1">
            <button class="btn btn-primary btn-sm" id="viewGridBtn" title="Card view">
                <i class="fa-solid fa-grid-2"></i>
            </button>
            <button class="btn btn-ghost btn-sm" id="viewTableBtn" title="Table view">
                <i class="fa-solid fa-list"></i>
            </button>
        </div>
    </div>

    <button class="btn btn-ghost btn-sm" id="clearFiltersBtn" style="align-self:flex-end;">
        <i class="fa-solid fa-xmark"></i> Clear
    </button>
</div>

<!-- ── Card Grid View ────────────────────────────────────── -->
<div id="roomsGridView">
    <div id="roomsGrid" class="module-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="room-card" style="opacity:0.4;">
            <div class="room-number-display">…</div>
            <div class="text-xs mt-1" style="color:var(--color-text-faint);">Loading…</div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- ── Table View (hidden by default) ───────────────────── -->
<div id="roomsTableView" style="display:none;">
    <div class="card" style="padding:0;">
        <div class="table-wrapper">
            <table class="data-table" id="roomsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Room Number</th>
                        <th>Type</th>
                        <th>Daily Fee</th>
                        <th>Status</th>
                        <th>Occupancy</th>
                        <th>Total Admissions</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="roomsTableBody">
                    <tr>
                        <td colspan="8" class="table-empty">
                            <div class="table-empty-icon"><span class="spinner"></span></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ADD ROOM MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="addRoomModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-bed" style="color:var(--color-primary);"></i>
                Add New Room
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="addRoomForm" novalidate>
                <input type="hidden" name="action"     value="createRoom">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <!-- Room Number -->
                <div class="form-group">
                    <label class="form-label" for="add_room_number">
                        Room Number <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="text" id="add_room_number" name="room_number" class="form-input"
                           placeholder="e.g. G-101, P-202, ICU-3"
                           maxlength="20" style="text-transform:uppercase;" required>
                    <div class="form-hint">Auto-uppercased. Must be unique.</div>
                    <div class="form-error-msg" id="add_room_numberError"></div>
                </div>

                <!-- Room Type -->
                <div class="form-group">
                    <label class="form-label">Room Type <span style="color:var(--color-danger);">*</span></label>
                    <input type="hidden" name="room_type" id="add_room_type" value="">
                    <div class="pill-toggle-group">
                        <button type="button" class="pill-toggle" data-value="general" data-field="add_room_type">
                            🛏 General
                        </button>
                        <button type="button" class="pill-toggle" data-value="private" data-field="add_room_type">
                            🚪 Private
                        </button>
                        <button type="button" class="pill-toggle" data-value="icu" data-field="add_room_type">
                            🏥 ICU
                        </button>
                    </div>
                    <div class="form-error-msg" id="add_room_typeError"></div>
                </div>

                <!-- Daily Fee -->
                <div class="form-group">
                    <label class="form-label" for="add_daily_fee">
                        Daily Fee (<?= e(getSetting('currency_symbol', 'Rs.')) ?>) <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="number" id="add_daily_fee" name="daily_fee" class="form-input"
                           placeholder="0.00" min="0" step="100" required>
                    <div class="form-error-msg" id="add_daily_feeError"></div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <input type="hidden" name="is_active" id="add_room_status" value="1">
                    <div class="pill-toggle-group">
                        <button type="button" class="pill-toggle selected" data-value="1" data-field="add_room_status">✅ Active</button>
                        <button type="button" class="pill-toggle" data-value="0" data-field="add_room_status">🔧 Maintenance</button>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="addRoomSubmitBtn">
                <i class="fa-solid fa-plus"></i> Add Room
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     EDIT ROOM MODAL
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editRoomModal">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-pen-to-square" style="color:var(--color-warning);"></i>
                Edit Room
            </div>
            <button class="modal-close" data-modal-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="editRoomForm" novalidate>
                <input type="hidden" name="action"     value="updateRoom">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id"         id="edit_room_id">

                <div class="form-group">
                    <label class="form-label" for="edit_room_number">
                        Room Number <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="text" id="edit_room_number" name="room_number" class="form-input"
                           maxlength="20" style="text-transform:uppercase;" required>
                    <div class="form-error-msg" id="edit_room_numberError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Room Type <span style="color:var(--color-danger);">*</span></label>
                    <input type="hidden" name="room_type" id="edit_room_type" value="">
                    <div class="pill-toggle-group">
                        <button type="button" class="pill-toggle" data-value="general" data-field="edit_room_type">🛏 General</button>
                        <button type="button" class="pill-toggle" data-value="private" data-field="edit_room_type">🚪 Private</button>
                        <button type="button" class="pill-toggle" data-value="icu"     data-field="edit_room_type">🏥 ICU</button>
                    </div>
                    <div class="form-error-msg" id="edit_room_typeError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_daily_fee">
                        Daily Fee (<?= e(getSetting('currency_symbol', 'Rs.')) ?>) <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input type="number" id="edit_daily_fee" name="daily_fee" class="form-input"
                           min="0" step="100" required>
                    <div class="form-error-msg" id="edit_daily_feeError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <input type="hidden" name="is_active" id="edit_room_status" value="1">
                    <div class="pill-toggle-group">
                        <button type="button" class="pill-toggle" data-value="1" data-field="edit_room_status">✅ Active</button>
                        <button type="button" class="pill-toggle" data-value="0" data-field="edit_room_status">🔧 Maintenance</button>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="editRoomSubmitBtn">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
(function () {
    'use strict';

    const AJAX_URL = `${window.APP_CONFIG.baseUrl}/ajax/admin/rooms.php`;
    let currentView = 'grid'; // 'grid' | 'table'
    let allRooms    = [];

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

    // Auto-uppercase room number inputs
    ['add_room_number', 'edit_room_number'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', function () {
            const pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });

    // ── View Toggle ───────────────────────────────────────────
    document.getElementById('viewGridBtn').addEventListener('click', () => {
        currentView = 'grid';
        document.getElementById('roomsGridView').style.display  = '';
        document.getElementById('roomsTableView').style.display = 'none';
        document.getElementById('viewGridBtn').className  = 'btn btn-primary btn-sm';
        document.getElementById('viewTableBtn').className = 'btn btn-ghost btn-sm';
    });

    document.getElementById('viewTableBtn').addEventListener('click', () => {
        currentView = 'table';
        document.getElementById('roomsGridView').style.display  = 'none';
        document.getElementById('roomsTableView').style.display = '';
        document.getElementById('viewGridBtn').className  = 'btn btn-ghost btn-sm';
        document.getElementById('viewTableBtn').className = 'btn btn-primary btn-sm';
        renderTable(allRooms);
    });

    // ── Load rooms ────────────────────────────────────────────
    function loadRooms() {
        const search = document.getElementById('searchInput').value.trim();
        const type   = document.getElementById('typeFilter').value;
        const status = document.getElementById('statusFilter').value;
        const params = new URLSearchParams({ action: 'getRooms', search, type, status });

        fetch(`${AJAX_URL}?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                allRooms = res.data.rooms;
                renderStats(res.data.summary);
                renderGrid(allRooms);
                if (currentView === 'table') renderTable(allRooms);
            })
            .catch(() => showToast('Failed to load rooms.', 'error'));
    }

    // ── Stats Strip ───────────────────────────────────────────
    function renderStats(s) {
        const configs = [
            { label: 'Total Rooms',  value: s.total,    icon: 'fa-bed',             color: 'blue'  },
            { label: 'Occupied',     value: s.occupied,  icon: 'fa-user',            color: 'violet'},
            { label: 'Available',    value: s.available, icon: 'fa-circle-check',    color: 'green' },
            { label: 'Maintenance',  value: s.inactive,  icon: 'fa-wrench',          color: 'amber' },
        ];
        document.getElementById('roomStatsGrid').innerHTML = configs.map(c => `
            <div class="stat-card animate-fade-in">
                <div class="stat-card-icon ${c.color}"><i class="fa-solid ${c.icon}"></i></div>
                <div>
                    <div class="stat-card-label">${c.label}</div>
                    <div class="stat-number">${c.value}</div>
                </div>
            </div>`).join('');
    }

    // ── Card Grid ─────────────────────────────────────────────
    function renderGrid(rooms) {
        const grid = document.getElementById('roomsGrid');
        if (!rooms.length) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--color-text-muted);">
                    <div style="font-size:2.5rem;">🛏</div>
                    <p style="margin-top:0.5rem;">No rooms found.</p>
                </div>`;
            return;
        }

        const typeColors = {
            general: { bg: 'var(--color-primary-lt)',  border: 'var(--color-primary)',  badge: 'badge-general' },
            private: { bg: 'var(--color-violet-lt)',   border: 'var(--color-violet)',   badge: 'badge-private' },
            icu:     { bg: 'var(--color-danger-lt)',   border: 'var(--color-danger)',   badge: 'badge-icu'     },
        };

        grid.innerHTML = rooms.map(r => {
            const tc      = typeColors[r.room_type] || typeColors.general;
            const occupied = r.is_occupied > 0;
            const inactive = !r.is_active;

            let statusBadge;
            if (inactive)     statusBadge = `<span class="badge badge-inactive">🔧 Maint.</span>`;
            else if (occupied) statusBadge = `<span class="badge badge-admitted">● Occupied</span>`;
            else               statusBadge = `<span class="badge badge-active">✓ Available</span>`;

            return `
            <div class="room-card animate-fade-in" style="border-top:3px solid ${tc.border};opacity:${inactive ? '0.65' : '1'};">
                <div class="flex items-start justify-between mb-2">
                    <div class="room-number-display">${escHtml(r.room_number)}</div>
                    ${statusBadge}
                </div>
                <div class="flex items-center gap-1 mb-3">
                    <span class="badge ${tc.badge}">${ucFirst(r.room_type)}</span>
                </div>
                ${occupied ? `
                <div class="text-xs mb-2 p-2 rounded-lg" style="background:var(--color-violet-lt);color:var(--color-violet);">
                    <i class="fa-solid fa-user mr-1"></i> ${escHtml(r.current_patient || 'Patient')}
                </div>` : ''}
                <div class="text-sm font-bold mb-3" style="color:var(--color-accent);">
                    ${escHtml(r.daily_fee_fmt)} <span style="font-size:10px;font-weight:400;color:var(--color-text-muted);">/ day</span>
                </div>
                <div class="table-actions" style="justify-content:flex-start;gap:6px;">
                    <button class="btn btn-ghost btn-sm" onclick="openEditModal(${r.id})" title="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn btn-sm" title="${r.is_active ? 'Mark as Maintenance' : 'Mark as Active'}"
                            style="color:var(--color-warning);background:var(--color-warning-lt);"
                            onclick="toggleRoom(${r.id})">
                        <i class="fa-solid ${r.is_active ? 'fa-wrench' : 'fa-circle-check'}"></i>
                    </button>
                    <button class="btn btn-sm" title="Delete"
                            style="color:var(--color-danger);background:var(--color-danger-lt);"
                            onclick="confirmDelete(${r.id}, '${escJs(r.room_number)}')">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    // ── Table View ────────────────────────────────────────────
    function renderTable(rooms) {
        const tbody = document.getElementById('roomsTableBody');
        if (!rooms.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="table-empty"><div class="table-empty-icon">🛏</div>No rooms found.</td></tr>`;
            return;
        }
        tbody.innerHTML = rooms.map((r, i) => {
            const occupied = r.is_occupied > 0;
            let statusCell;
            if (!r.is_active)    statusCell = `<span class="badge badge-inactive">🔧 Maint.</span>`;
            else if (occupied)   statusCell = `<span class="badge badge-admitted">● Occupied</span>`;
            else                 statusCell = `<span class="badge badge-active">✓ Available</span>`;

            return `
            <tr class="animate-fade-in" data-id="${r.id}">
                <td class="text-xs font-mono-nums" style="color:var(--color-text-faint);">${i+1}</td>
                <td><span class="font-mono-nums font-bold">${escHtml(r.room_number)}</span></td>
                <td><span class="badge badge-${r.room_type === 'icu' ? 'icu' : r.room_type === 'private' ? 'private' : 'general'}">${ucFirst(r.room_type)}</span></td>
                <td class="font-bold" style="color:var(--color-accent);">${escHtml(r.daily_fee_fmt)}</td>
                <td>${statusCell}</td>
                <td class="text-xs">${occupied ? escHtml(r.current_patient || '—') : '<span style="color:var(--color-text-faint);">—</span>'}</td>
                <td class="text-xs text-center">${r.total_admissions}</td>
                <td>
                    <div class="table-actions justify-end">
                        <button class="btn btn-ghost btn-icon btn-sm" onclick="openEditModal(${r.id})" title="Edit">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-icon btn-sm" title="${r.is_active ? 'Maintenance' : 'Activate'}"
                                style="color:var(--color-warning);background:var(--color-warning-lt);"
                                onclick="toggleRoom(${r.id})">
                            <i class="fa-solid fa-wrench"></i>
                        </button>
                        <button class="btn btn-icon btn-sm" title="Delete"
                                style="color:var(--color-danger);background:var(--color-danger-lt);"
                                onclick="confirmDelete(${r.id},'${escJs(r.room_number)}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Add Room ──────────────────────────────────────────────
    document.getElementById('addRoomBtn').addEventListener('click', () => {
        resetForm('addRoomForm');
        clearAllPills('addRoomForm');
        setPill('add_room_status', '1');
        openModal('addRoomModal');
        document.getElementById('add_room_number').focus();
    });

    document.getElementById('addRoomSubmitBtn').addEventListener('click', () => {
        if (!clientValidate('add')) return;
        const btn = document.getElementById('addRoomSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('addRoomForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('addRoomModal');
                loadRooms();
                playSound('success.mp3');
            }, null, btn
        );
    });

    // ── Edit Room ─────────────────────────────────────────────
    window.openEditModal = function (id) {
        fetch(`${AJAX_URL}?${new URLSearchParams({ action: 'getRoomById', id })}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { showToast(res.message, 'error'); return; }
                const room = res.data.room;
                document.getElementById('edit_room_id').value     = room.id;
                document.getElementById('edit_room_number').value = room.room_number;
                document.getElementById('edit_daily_fee').value   = room.daily_fee;
                setPill('edit_room_type',   room.room_type);
                setPill('edit_room_status', String(room.is_active));
                openModal('editRoomModal');
                document.getElementById('edit_room_number').focus();
            })
            .catch(() => showToast('Failed to load room data.', 'error'));
    };

    document.getElementById('editRoomSubmitBtn').addEventListener('click', () => {
        if (!clientValidate('edit')) return;
        const btn = document.getElementById('editRoomSubmitBtn');
        ajaxRequest(AJAX_URL, 'POST', serializeForm(document.getElementById('editRoomForm')),
            (_, msg) => {
                showToast(msg, 'success');
                closeModal('editRoomModal');
                loadRooms();
            }, null, btn
        );
    });

    // ── Toggle ────────────────────────────────────────────────
    window.toggleRoom = function (id) {
        const fd = new FormData();
        fd.set('action', 'toggleRoom');
        fd.set('csrf_token', getCsrfToken());
        fd.set('id', id);
        ajaxRequest(AJAX_URL, 'POST', fd,
            (_, msg) => { showToast(msg, 'success'); loadRooms(); }
        );
    };

    // ── Delete ────────────────────────────────────────────────
    window.confirmDelete = function (id, number) {
        showConfirmModal(
            'Delete Room',
            `Are you sure you want to delete <strong>Room ${escHtml(number)}</strong>? This cannot be undone.`,
            'Yes, Delete',
            () => {
                const fd = new FormData();
                fd.set('action', 'deleteRoom');
                fd.set('csrf_token', getCsrfToken());
                fd.set('id', id);
                ajaxRequest(AJAX_URL, 'POST', fd,
                    (_, msg) => { showToast(msg, 'success'); loadRooms(); }
                );
            }, 'danger'
        );
    };

    // ── Validation ────────────────────────────────────────────
    function clientValidate(prefix) {
        let valid = true;
        const numId  = `${prefix}_room_number`;
        const typeId = `${prefix}_room_type`;
        const feeId  = `${prefix}_daily_fee`;

        const numEl  = document.getElementById(numId);
        const typeEl = document.getElementById(typeId);
        const feeEl  = document.getElementById(feeId);

        if (!numEl.value.trim()) {
            showFieldError(numId, 'Room number is required.'); valid = false;
        } else clearFieldError(numId);

        if (!typeEl.value) {
            showFieldError(typeId, 'Please select a room type.'); valid = false;
        } else clearFieldError(typeId);

        if (feeEl.value === '' || isNaN(feeEl.value) || parseFloat(feeEl.value) < 0) {
            showFieldError(feeId, 'Please enter a valid daily fee.'); valid = false;
        } else clearFieldError(feeId);

        return valid;
    }

    // ── Pill helpers ──────────────────────────────────────────
    function setPill(fieldId, value) {
        document.querySelectorAll(`.pill-toggle[data-field="${fieldId}"]`).forEach(b =>
            b.classList.toggle('selected', b.dataset.value === value));
        const h = document.getElementById(fieldId);
        if (h) h.value = value;
    }
    function clearAllPills(formId) {
        document.getElementById(formId)?.querySelectorAll('.pill-toggle').forEach(b =>
            b.classList.remove('selected'));
    }

    // ── Utilities ─────────────────────────────────────────────
    function escHtml(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escJs(s)   { return String(s??'').replace(/'/g,"\\'"); }
    function ucFirst(s) { return s ? s[0].toUpperCase() + s.slice(1) : ''; }

    // ── Filters ───────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', debounce(loadRooms, 300));
    document.getElementById('typeFilter').addEventListener('change', loadRooms);
    document.getElementById('statusFilter').addEventListener('change', loadRooms);
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value  = '';
        document.getElementById('typeFilter').value   = '';
        document.getElementById('statusFilter').value = '';
        loadRooms();
    });

    // ── Init ──────────────────────────────────────────────────
    loadRooms();

})();
JS;

require_once BASE_PATH . '/admin/footer.php';
?>
