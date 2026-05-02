<?php
/**
 * admin/header.php
 * Included at the very top of every admin page.
 *
 * Expects these variables to be set BEFORE including this file:
 *   $pageTitle   (string)  — shown in <title> and page header  e.g. 'Doctors'
 *   $activePage  (string)  — sidebar link to highlight         e.g. 'doctors'
 *
 * This file:
 *   1. Boots the app (session + settings)
 *   2. Enforces admin auth
 *   3. Updates last_seen
 *   4. Prints the full HTML <head>, topnav, sidebar
 *   5. Opens <main class="main-content"> — closed by footer.php
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireAdmin();
updateLastSeen(getCurrentUserId());

$hospitalName  = getSetting('hospital_name', 'Hospital');
$hospitalLogo  = getSetting('hospital_logo', '');
$logoSrc       = $hospitalLogo ? BASE_URL . '/assets/uploads/logo/' . $hospitalLogo : '';
$csrfToken     = getCsrfToken();
$currentUserId = getCurrentUserId();

// Build display name + initials
$firstName     = $_SESSION['first_name'] ?? 'Admin';
$lastName      = $_SESSION['last_name']  ?? '';
$fullName      = trim("$firstName $lastName");
$initials      = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$profileImage  = $_SESSION['profile_image'] ?? '';
$profileSrc    = $profileImage
    ? BASE_URL . '/assets/uploads/profiles/' . e($profileImage)
    : '';

$activePage    = $activePage ?? '';
$pageTitle     = $pageTitle  ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($pageTitle) ?> — <?= e($hospitalName) ?></title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Noto Nastaliq Urdu (for receipts) — preloaded in custom.css via @import -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body>

<!-- APP CONFIG for JS -->
<script>
window.APP_CONFIG = {
    baseUrl:        '<?= BASE_URL ?>',
    currencySymbol: '<?= e(getSetting('currency_symbol', 'Rs.')) ?>',
    tokenPrefix:    '<?= e(getSetting('token_prefix', 'TKN')) ?>',
    soundEnabled:   <?= (int) getSetting('sound_effects', '1') ?>,
    userId:         <?= $currentUserId ?>,
    userRole:       'admin',
    isRoleSwitched: false,
};
</script>

<div class="app-shell">

<!-- ═══════════════════════════════════════════════════════════
     TOPNAV
     ═══════════════════════════════════════════════════════════ -->
<header class="topnav" id="mainTopnav">

    <!-- Left: Hamburger + Brand -->
    <button class="btn btn-ghost btn-icon" id="hamburgerBtn" title="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="flex items-center gap-2 select-none">
        <div id="topnavLogoBrand"
             style="width:32px;height:32px;background:var(--color-primary);border-radius:8px;
                    display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            <?php if ($logoSrc): ?>
                <img id="topnavLogoImg" src="<?= $logoSrc ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <i id="topnavLogoIcon" class="fa-solid fa-hospital-user text-white text-sm" style="display:none;"></i>
            <?php else: ?>
                <img id="topnavLogoImg" src="" alt="" style="display:none;">
                <i id="topnavLogoIcon" class="fa-solid fa-hospital-user text-white text-sm"></i>
            <?php endif; ?>
        </div>
        <span class="font-bold text-sm hidden sm:block" style="color:var(--color-text);">
            <?= e($hospitalName) ?>
        </span>
        <span class="text-xs px-2 py-0.5 rounded-full font-bold hidden md:block"
              style="background:var(--color-primary-lt);color:var(--color-primary);">
            ADMIN
        </span>
    </div>

    <!-- Right side -->
    <div class="topnav-right">

        <!-- Live clock -->
        <span class="live-clock hidden md:block" id="live-clock">00:00:00</span>

        <!-- Dark mode toggle -->
        <button class="btn btn-ghost btn-icon" id="darkModeToggle" title="Toggle dark mode">
            <i class="fa-solid fa-moon"></i>
        </button>

        <!-- Switch to receptionist view -->
        <button class="btn btn-outline btn-sm hidden md:flex items-center gap-1" id="switchRoleBtn"
                title="Switch to Receptionist view">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Switch View</span>
        </button>

        <!-- User dropdown -->
        <div class="dropdown" id="userDropdown">
            <div data-dropdown-trigger class="flex items-center gap-2 cursor-pointer">
                <?php if ($profileSrc): ?>
                    <img src="<?= $profileSrc ?>" alt="<?= e($fullName) ?>" class="avatar">
                <?php else: ?>
                    <div class="avatar" style="font-size:13px;"><?= e($initials) ?></div>
                <?php endif; ?>
                <span class="text-sm font-semibold hidden md:block" style="color:var(--color-text);">
                    <?= e($firstName) ?>
                </span>
                <i class="fa-solid fa-chevron-down text-xs hidden md:block" style="color:var(--color-text-faint);"></i>
            </div>
            <div class="dropdown-menu">
                <div style="padding:0.5rem 0.75rem 0.4rem;border-bottom:1px solid var(--color-border);margin-bottom:0.35rem;">
                    <p class="text-xs font-bold" style="color:var(--color-text);"><?= e($fullName) ?></p>
                    <p class="text-xs" style="color:var(--color-text-faint);"><?= e($_SESSION['email'] ?? '') ?></p>
                </div>
                <a href="<?= BASE_URL ?>/admin/profile.php" class="dropdown-item">
                    <i class="fa-regular fa-user w-4"></i> My Profile
                </a>
                <a href="<?= BASE_URL ?>/admin/settings.php" class="dropdown-item">
                    <i class="fa-solid fa-sliders w-4"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="dropdown-item danger">
                    <i class="fa-solid fa-right-from-bracket w-4"></i> Logout
                </a>
            </div>
        </div>

    </div>
</header>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR
     ═══════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="mainSidebar">
    <nav class="sidebar-nav">

        <!-- MAIN -->
        <div class="sidebar-section-header">Main</div>

        <a href="<?= BASE_URL ?>/admin/dashboard.php"
           class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-gauge-high"></i></span>
            Dashboard
        </a>

        <!-- MANAGEMENT -->
        <div class="sidebar-section-header mt-2">Management</div>

        <a href="<?= BASE_URL ?>/admin/doctors.php"
           class="sidebar-link <?= $activePage === 'doctors' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-user-doctor"></i></span>
            Doctors
        </a>

        <a href="<?= BASE_URL ?>/admin/receptionists.php"
           class="sidebar-link <?= $activePage === 'receptionists' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-user-nurse"></i></span>
            Receptionists
        </a>

        <a href="<?= BASE_URL ?>/admin/rooms.php"
           class="sidebar-link <?= $activePage === 'rooms' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-bed"></i></span>
            Rooms
        </a>

        <a href="<?= BASE_URL ?>/admin/admissions.php"
           class="sidebar-link <?= $activePage === 'admissions' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-clipboard-list"></i></span>
            Admissions
        </a>

        <!-- RECORDS -->
        <div class="sidebar-section-header mt-2">Records</div>

        <a href="<?= BASE_URL ?>/admin/patients.php"
           class="sidebar-link <?= $activePage === 'patients' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-users"></i></span>
            All Patients
        </a>

        <a href="<?= BASE_URL ?>/admin/payments.php"
           class="sidebar-link <?= $activePage === 'payments' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
            Payments
        </a>

        <!-- ACCOUNT -->
        <div class="sidebar-section-header mt-2">Account</div>

        <a href="<?= BASE_URL ?>/admin/profile.php"
           class="sidebar-link <?= $activePage === 'profile' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-regular fa-circle-user"></i></span>
            My Profile
        </a>

        <a href="<?= BASE_URL ?>/admin/settings.php"
           class="sidebar-link <?= $activePage === 'settings' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-sliders"></i></span>
            Settings
        </a>

        <a href="<?= BASE_URL ?>/auth/logout.php" class="sidebar-link"
           style="color:var(--color-danger);" id="logoutLink">
            <span class="sidebar-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            Logout
        </a>

    </nav>

    <!-- Sidebar footer — version -->
    <div style="padding:0.75rem 1rem;border-top:1px solid var(--color-border);font-size:11px;color:var(--color-text-faint);">
        TokenMed v2.0
    </div>
</aside>

<!-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT — opened here, closed in footer.php
     ═══════════════════════════════════════════════════════════ -->
<main class="main-content" id="mainContent">

<!-- Toast container -->
<div id="toast-container" class="toast-container"></div>

<?php
// ── Switch Role JS (inline, because it needs PHP values) ──────
?>
<script>
(function () {
    const btn = document.getElementById('switchRoleBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const fd = new FormData();
        fd.set('csrf_token', '<?= e($csrfToken) ?>');
        fd.set('action', 'switch');
        fetch('<?= BASE_URL ?>/ajax/switch-role.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) window.location.href = res.data.redirect;
                else showToast(res.message, 'error');
            })
            .catch(() => showToast('Network error.', 'error'));
    });
})();
</script>