/**
 * common.js — TokenMed Shared JavaScript
 *
 * Loaded on EVERY page (both admin and receptionist layouts).
 * Contains all reusable utilities. No module-specific code lives here.
 *
 * Sections:
 *   1.  APP_CONFIG safety guard
 *   2.  Toast notifications
 *   3.  Modal management
 *   4.  Confirm modal
 *   5.  AJAX helper (Fetch wrapper)
 *   6.  Sound effects
 *   7.  Currency + Date formatting (client-side mirrors of PHP helpers)
 *   8.  Dark mode
 *   9.  Live clock
 *  10.  Ping (last_seen update every 60s)
 *  11.  Search / filter
 *  12.  Sidebar toggle (mobile)
 *  13.  Dropdown toggle
 *  14.  Debounce utility
 *  15.  CSRF token helper
 *  16.  Form utilities
 *  17.  Bulk-select helper
 *  18.  Auto-init on DOMContentLoaded
 */

'use strict';

/* ============================================================
   1. APP_CONFIG SAFETY GUARD
   ============================================================ */

// Ensure APP_CONFIG always exists, even if header.php failed to print it.
window.APP_CONFIG = window.APP_CONFIG || {
    baseUrl:        '',
    currencySymbol: 'Rs.',
    tokenPrefix:    'TKN',
    soundEnabled:   1,
    userId:         0,
    userRole:       'receptionist',
    isRoleSwitched: false,
};

/* ============================================================
   2. TOAST NOTIFICATIONS
   ============================================================ */

/**
 * Show a toast notification.
 *
 * @param {string} message   Text to display
 * @param {string} type      'success' | 'error' | 'warning' | 'info'
 * @param {number} duration  Auto-dismiss delay in ms (default 4500)
 */
function showToast(message, type = 'info', duration = 4500) {
    // Create container if it doesn't exist yet
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = {
        success: '<i class="fa-solid fa-circle-check"></i>',
        error:   '<i class="fa-solid fa-circle-xmark"></i>',
        warning: '<i class="fa-solid fa-triangle-exclamation"></i>',
        info:    '<i class="fa-solid fa-circle-info"></i>',
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" aria-label="Close notification">&#x2715;</button>
        <div class="toast-progress"></div>
    `;

    container.appendChild(toast);

    // Animate the progress bar shrinking over `duration` ms
    const progressBar = toast.querySelector('.toast-progress');
    if (progressBar) {
        progressBar.style.transition = `transform ${duration}ms linear`;
        // Force reflow so the transition starts from width:100%
        progressBar.getBoundingClientRect();
        progressBar.style.transform = 'scaleX(0)';
    }

    // ── Dismiss logic ─────────────────────────────────────────
    let dismissed = false;

    function dismiss() {
        if (dismissed) return;
        dismissed = true;

        toast.classList.add('dismissing');

        // Use setTimeout to remove DOM — reliable on all browsers/OS
        // (animationend can silently fail on Windows Chrome/Firefox)
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300); // matches toast-out animation duration
    }

    // Auto-dismiss after duration
    const autoTimer = setTimeout(dismiss, duration);

    // X button click
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(autoTimer);
        dismiss();
    });

    // Also allow clicking anywhere on the toast body to dismiss (except the close btn)
    toast.addEventListener('click', (e) => {
        if (e.target.classList.contains('toast-close')) return;
        clearTimeout(autoTimer);
        dismiss();
    });
}

/* ============================================================
   2b. PRINT WINDOW HELPER
   ============================================================ */

/**
 * Open a receipt or slip in a dedicated print window.
 * Fetches the full standalone HTML from print-slip.php?direct=1
 * and opens window.print() automatically inside that window.
 *
 * @param {string} type  'receipt' | 'admission'
 * @param {number} id    Patient ID or Admission ID
 */
function openPrintWindow(type, id) {
    const url = `${window.APP_CONFIG.baseUrl}/ajax/print-slip.php?type=${type}&id=${id}&direct=1`;
    const win = window.open(url, '_blank', 'width=500,height=750,scrollbars=yes');
    if (!win) {
        showToast('Popup blocked. Please allow popups for this site and try again.', 'warning', 6000);
        return;
    }
    // Auto-trigger print once the page is fully loaded
    win.addEventListener('load', () => {
        win.focus();
        win.print();
    });
}


/**
 * Open a modal by its backdrop element ID.
 * @param {string} modalId  ID of the .modal-backdrop element
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn(`openModal: modal "${modalId}" not found.`);
        return;
    }
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Close on backdrop click
    modal.addEventListener('click', _modalBackdropClickHandler);

    // Close on Escape
    document.addEventListener('keydown', _modalEscHandler);
}

/**
 * Close a modal by its backdrop element ID.
 * @param {string} modalId
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('open');
    modal.removeEventListener('click', _modalBackdropClickHandler);
    document.removeEventListener('keydown', _modalEscHandler);
    // Restore scroll only if no other modals are open
    if (!document.querySelector('.modal-backdrop.open')) {
        document.body.style.overflow = '';
    }
}

/** Close all currently open modals */
function closeAllModals() {
    document.querySelectorAll('.modal-backdrop.open').forEach(m => {
        m.classList.remove('open');
        m.removeEventListener('click', _modalBackdropClickHandler);
    });
    document.removeEventListener('keydown', _modalEscHandler);
    document.body.style.overflow = '';
}

function _modalBackdropClickHandler(e) {
    // Only close if clicking the dark backdrop itself, not the .modal-box
    if (e.target === e.currentTarget) {
        closeModal(e.currentTarget.id);
    }
}

function _modalEscHandler(e) {
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-backdrop.open');
        if (openModal) closeModal(openModal.id);
    }
}

/* ============================================================
   4. CONFIRM MODAL
   ============================================================ */

/**
 * Show a confirmation dialog modal.
 * Creates a temporary modal DOM element, shows it, calls onConfirm on confirmation.
 *
 * @param {string}   title        Modal heading
 * @param {string}   message      Body text (HTML allowed)
 * @param {string}   confirmText  Confirm button label (default: 'Confirm')
 * @param {Function} onConfirm    Called when user confirms
 * @param {string}   type         'danger' | 'warning' | 'primary' (affects button color)
 */
function showConfirmModal(title, message, confirmText = 'Confirm', onConfirm = null, type = 'danger') {
    // Remove any existing confirm modal
    const existing = document.getElementById('_confirmModal');
    if (existing) existing.remove();

    const btnClass = {
        danger:  'btn btn-danger',
        warning: 'btn btn-warning',
        primary: 'btn btn-primary',
    }[type] || 'btn btn-danger';

    const icons = {
        danger:  '<i class="fa-solid fa-triangle-exclamation text-red-500"></i>',
        warning: '<i class="fa-solid fa-circle-exclamation text-amber-500"></i>',
        primary: '<i class="fa-solid fa-circle-question text-sky-500"></i>',
    };

    const modal = document.createElement('div');
    modal.id = '_confirmModal';
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <div class="modal-box modal-sm">
            <div class="modal-header">
                <div class="modal-title">
                    ${icons[type] || icons.danger}
                    ${title}
                </div>
                <button class="modal-close" onclick="closeModal('_confirmModal')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:14px;color:var(--color-text-muted);line-height:1.6;">${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeModal('_confirmModal')">Cancel</button>
                <button class="${btnClass}" id="_confirmBtn">${confirmText}</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    openModal('_confirmModal');

    document.getElementById('_confirmBtn').addEventListener('click', () => {
        closeModal('_confirmModal');
        if (typeof onConfirm === 'function') onConfirm();
    });
}

/* ============================================================
   5. AJAX HELPER
   ============================================================ */

/**
 * Wrapper around the Fetch API.
 * Automatically adds CSRF token header on POST/PUT/DELETE.
 * Expects the server to return { success, message, data } JSON.
 *
 * @param {string}   url       Endpoint URL
 * @param {string}   method    'GET' | 'POST' | 'PUT' | 'DELETE'
 * @param {FormData|Object|null} data  Request body
 * @param {Function} onSuccess Called with response.data on success
 * @param {Function} onError   Called with error message string
 * @param {HTMLElement|null} loadingBtn  Button to show spinner on during request
 */
function ajaxRequest(url, method = 'GET', data = null, onSuccess = null, onError = null, loadingBtn = null) {
    const headers = {};

    // CSRF header for mutating requests
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase())) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (token) headers['X-CSRF-Token'] = token;
    }

    // Spinner state on button
    let originalHtml = '';
    if (loadingBtn) {
        originalHtml = loadingBtn.innerHTML;
        loadingBtn.disabled = true;
        loadingBtn.innerHTML = `<span class="spinner"></span> Loading…`;
    }

    const options = { method, headers };

    if (data) {
        if (data instanceof FormData) {
            options.body = data;
        } else {
            options.body = JSON.stringify(data);
            headers['Content-Type'] = 'application/json';
        }
    }

    fetch(url, options)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            return res.json();
        })
        .then(response => {
            if (loadingBtn) {
                loadingBtn.disabled = false;
                loadingBtn.innerHTML = originalHtml;
            }

            if (response.success) {
                if (typeof onSuccess === 'function') onSuccess(response.data, response.message);
            } else {
                const msg = response.message || 'An error occurred.';
                showToast(msg, 'error');
                if (typeof onError === 'function') onError(msg);
            }
        })
        .catch(err => {
            if (loadingBtn) {
                loadingBtn.disabled = false;
                loadingBtn.innerHTML = originalHtml;
            }
            const msg = err.message || 'Network error. Please try again.';
            showToast(msg, 'error');
            if (typeof onError === 'function') onError(msg);
            console.error('ajaxRequest error:', err);
        });
}

/* ============================================================
   6. SOUND EFFECTS
   ============================================================ */

// Cache loaded Audio objects to avoid repeated creation
const _soundCache = {};

/**
 * Play an audio file from /assets/sounds/.
 * Respects APP_CONFIG.soundEnabled — plays nothing if sounds are off.
 *
 * @param {string} filename  e.g. 'cash-register-kaching.mp3'
 */
function playSound(filename) {
    if (!window.APP_CONFIG.soundEnabled) return;

    try {
        if (!_soundCache[filename]) {
            _soundCache[filename] = new Audio(`${window.APP_CONFIG.baseUrl}/assets/sounds/${filename}`);
        }
        const audio = _soundCache[filename];
        audio.currentTime = 0;
        audio.play().catch(() => {
            // Autoplay policy may block — silently ignore
        });
    } catch (e) {
        // Audio not supported or file missing — silently ignore
    }
}

/* ============================================================
   7. CURRENCY + DATE FORMATTING (client-side)
   ============================================================ */

/**
 * Format a number as currency string using APP_CONFIG.currencySymbol.
 * @param {number|string} amount
 * @returns {string}  e.g. "Rs. 1,200.00"
 */
function formatCurrency(amount) {
    const num = parseFloat(amount) || 0;
    const symbol = window.APP_CONFIG.currencySymbol || 'Rs.';
    return `${symbol} ${num.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/**
 * Format an ISO date string (YYYY-MM-DD) to DD/MM/YYYY.
 * Pakistani date convention.
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const [year, month, day] = dateStr.split('T')[0].split('-');
        return `${day}/${month}/${year}`;
    } catch (e) {
        return dateStr;
    }
}

/**
 * Format a 24h time string (HH:MM:SS or HH:MM) to 12-hour AM/PM.
 * @param {string} timeStr
 * @returns {string}
 */
function formatTime(timeStr) {
    if (!timeStr) return '—';
    try {
        const [hourStr, minStr] = timeStr.split(':');
        let hour = parseInt(hourStr, 10);
        const min  = minStr || '00';
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;
        return `${hour}:${min} ${ampm}`;
    } catch (e) {
        return timeStr;
    }
}

/* ============================================================
   8. DARK MODE
   ============================================================ */

/**
 * Initialize dark mode from localStorage on page load.
 * Called automatically on DOMContentLoaded.
 */
function initDarkMode() {
    const isDark = localStorage.getItem('tokenmed_dark') === '1';
    document.documentElement.classList.toggle('dark', isDark);
    _updateDarkModeIcon(isDark);
}

/**
 * Toggle dark mode and persist preference.
 */
function toggleDarkMode() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('tokenmed_dark', isDark ? '1' : '0');
    _updateDarkModeIcon(isDark);
}

function _updateDarkModeIcon(isDark) {
    const btn = document.getElementById('darkModeToggle');
    if (!btn) return;
    btn.innerHTML = isDark
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
    btn.title = isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
}

/* ============================================================
   9. LIVE CLOCK
   ============================================================ */

/**
 * Start a live HH:MM:SS clock in the topnav.
 * Looks for an element with id="live-clock".
 */
function startLiveClock() {
    const el = document.getElementById('live-clock');
    if (!el) return;

    function tick() {
        const now  = new Date();
        const hh   = String(now.getHours()).padStart(2, '0');
        const mm   = String(now.getMinutes()).padStart(2, '0');
        const ss   = String(now.getSeconds()).padStart(2, '0');
        el.textContent = `${hh}:${mm}:${ss}`;
    }

    tick();
    setInterval(tick, 1000);
}

/* ============================================================
   10. PING — update last_seen every 60 seconds
   ============================================================ */

/**
 * Ping the server every 60 seconds to update the user's last_seen timestamp.
 * This powers the "Online Now" admin widget.
 */
function startPing() {
    if (!window.APP_CONFIG.userId) return;

    const ping = () => {
        fetch(`${window.APP_CONFIG.baseUrl}/ajax/ping.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${encodeURIComponent(getCsrfToken())}`,
        }).catch(() => { /* silently ignore ping failures */ });
    };

    ping(); // immediate first ping
    setInterval(ping, 60_000);
}

/* ============================================================
   11. SEARCH / FILTER (live table search)
   ============================================================ */

/**
 * Wire up a live search input to filter rows in a table.
 * Hides rows where no td contains the search text (case-insensitive).
 *
 * @param {string} inputId  ID of the <input> element
 * @param {string} tableId  ID of the <table> element
 */
function initSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', debounce(() => {
        const query = input.value.trim().toLowerCase();
        const rows  = table.querySelectorAll('tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            if (row.dataset.noSearch) return; // skip empty-state rows
            const text = row.textContent.toLowerCase();
            const show = !query || text.includes(query);
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Show/hide empty state row
        const emptyRow = table.querySelector('tr[data-empty]');
        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }, 220));
}

/* ============================================================
   12. SIDEBAR TOGGLE (mobile)
   ============================================================ */

/**
 * Toggle the sidebar open/closed on mobile.
 * Wires the hamburger button, overlay click, and sidebar.
 */
function initSidebar() {
    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar   = document.getElementById('mainSidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    if (!hamburger || !sidebar) return;

    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open');
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // Close sidebar on nav link click (mobile UX)
    sidebar.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', () => {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        });
    });
}

/* ============================================================
   13. DROPDOWN TOGGLE (user menu)
   ============================================================ */

/**
 * Initialize all .dropdown elements to toggle on trigger click.
 * Closes when clicking outside.
 */
function initDropdowns() {
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        if (!trigger) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close all other dropdowns
            document.querySelectorAll('.dropdown.open').forEach(d => {
                if (d !== dropdown) d.classList.remove('open');
            });
            dropdown.classList.toggle('open');
        });
    });

    // Click outside to close
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
    });
}

/* ============================================================
   14. DEBOUNCE UTILITY
   ============================================================ */

/**
 * Returns a debounced version of fn that delays invocation by `delay` ms.
 * @param {Function} fn
 * @param {number}   delay  Milliseconds
 * @returns {Function}
 */
function debounce(fn, delay = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

/* ============================================================
   15. CSRF TOKEN HELPER
   ============================================================ */

/**
 * Read the CSRF token from the meta tag printed by header.php.
 * Used by ajaxRequest and startPing automatically.
 * @returns {string}
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/* ============================================================
   16. FORM UTILITIES
   ============================================================ */

/**
 * Serialize a <form> element into a FormData object,
 * automatically appending the CSRF token.
 * @param {HTMLFormElement} form
 * @returns {FormData}
 */
function serializeForm(form) {
    const fd = new FormData(form);
    const token = getCsrfToken();
    if (token) fd.set('csrf_token', token);
    return fd;
}

/**
 * Reset a form and clear any visible validation error states.
 * @param {HTMLFormElement|string} formOrId
 */
function resetForm(formOrId) {
    const form = typeof formOrId === 'string'
        ? document.getElementById(formOrId)
        : formOrId;
    if (!form) return;
    form.reset();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.form-error-msg').forEach(el => el.textContent = '');
}

/**
 * Show an inline validation error on a field.
 * @param {string} fieldId
 * @param {string} message
 */
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.add('is-invalid');
    const errEl = document.getElementById(`${fieldId}Error`);
    if (errEl) errEl.textContent = message;
}

/**
 * Clear a field's error state.
 * @param {string} fieldId
 */
function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.remove('is-invalid');
    const errEl = document.getElementById(`${fieldId}Error`);
    if (errEl) errEl.textContent = '';
}

/**
 * Password strength indicator.
 * Fills a .password-strength-bar element based on password quality.
 * @param {string} password
 * @param {string} barId  ID of the .password-strength-bar element
 */
function updatePasswordStrength(password, barId) {
    const bar = document.getElementById(barId);
    if (!bar) return;

    let score = 0;
    if (password.length >= 8)  score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    const widths = ['0%', '20%', '40%', '65%', '85%', '100%'];
    const colors = ['transparent', '#ef4444', '#f97316', '#eab308', '#22c55e', '#059669'];

    bar.style.width  = widths[score];
    bar.style.background = colors[score];
}

/* ============================================================
   17. BULK-SELECT HELPER
   ============================================================ */

/**
 * Wire up a "select all" checkbox to individual row checkboxes.
 * Shows/hides the bulk actions bar when any row is selected.
 *
 * @param {string} selectAllId   ID of the header checkbox
 * @param {string} rowCheckClass CSS class of row checkboxes
 * @param {string} bulkBarId     ID of the bulk actions bar element
 * @param {string} countId       ID of element showing selected count
 */
function initBulkSelect(selectAllId, rowCheckClass, bulkBarId, countId) {
    const selectAll = document.getElementById(selectAllId);
    const bulkBar   = document.getElementById(bulkBarId);
    const countEl   = document.getElementById(countId);

    if (!selectAll || !bulkBar) return;

    const updateBar = () => {
        const checked = document.querySelectorAll(`.${rowCheckClass}:checked`);
        const count   = checked.length;
        bulkBar.classList.toggle('visible', count > 0);
        if (countEl) countEl.textContent = count;
    };

    selectAll.addEventListener('change', () => {
        document.querySelectorAll(`.${rowCheckClass}`).forEach(cb => {
            cb.checked = selectAll.checked;
        });
        updateBar();
    });

    document.querySelectorAll(`.${rowCheckClass}`).forEach(cb => {
        cb.addEventListener('change', () => {
            const all   = document.querySelectorAll(`.${rowCheckClass}`);
            const allChecked = [...all].every(c => c.checked);
            selectAll.checked = allChecked;
            updateBar();
        });
    });

    // Expose getSelectedIds for bulk action buttons
    window.getSelectedIds = () =>
        [...document.querySelectorAll(`.${rowCheckClass}:checked`)].map(c => c.value);
}

/* ============================================================
   18. AUTO-INIT ON DOMContentLoaded
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
    initDarkMode();
    startLiveClock();
    startPing();
    initSidebar();
    initDropdowns();

    // Wire dark mode toggle button
    const dmToggle = document.getElementById('darkModeToggle');
    if (dmToggle) dmToggle.addEventListener('click', toggleDarkMode);

    // Wire all [data-modal-close] buttons globally
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.closest('.modal-backdrop')?.id;
            if (modalId) closeModal(modalId);
        });
    });

    // Wire all [data-modal-open] triggers
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
    });

    // Auto-init search fields
    document.querySelectorAll('[data-search-input]').forEach(input => {
        initSearch(input.id, input.dataset.searchInput);
    });

    // Password strength on any #newPassword or #password field
    const pwField = document.getElementById('password') || document.getElementById('newPassword');
    if (pwField) {
        pwField.addEventListener('input', () => {
            updatePasswordStrength(pwField.value, 'passwordStrengthBar');
        });
    }
});