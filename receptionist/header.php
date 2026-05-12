<?php
/**
 * receptionist/header.php
 * Included at the very top of every receptionist page.
 *
 * Expects before include:
 *   $pageTitle   (string) — shown in <title> and page heading
 *   $activePage  (string) — sidebar link to highlight
 *
 * Works for BOTH genuine receptionists AND admins who switched into
 * receptionist view (isRoleSwitched() === true).
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireReceptionist();
updateLastSeen(getCurrentUserId());

$hospitalName  = getSetting('hospital_name', 'Hospital');
$hospitalLogo  = getSetting('hospital_logo', '');
$logoSrc       = $hospitalLogo ? BASE_URL . '/assets/uploads/logo/' . $hospitalLogo : '';
$csrfToken     = getCsrfToken();
$currentUserId = getCurrentUserId();
$isSwitched    = isRoleSwitched();
$isAdminActor  = isAdmin();

$firstName    = $_SESSION['first_name'] ?? 'User';
$lastName     = $_SESSION['last_name']  ?? '';
$fullName     = trim("$firstName $lastName");
$initials     = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$profileImage = $_SESSION['profile_image'] ?? '';
$profileSrc   = $profileImage
    ? BASE_URL . '/assets/uploads/profiles/' . e($profileImage)
    : '';

$activePage = $activePage ?? '';
$pageTitle  = $pageTitle  ?? 'Dashboard';

// Today's quick stats for topnav strip
$today          = date('Y-m-d');
$myTokensToday  = (int) Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM patients WHERE receptionist_id = ? AND visit_date = ?",
    [$currentUserId, $today]
)['cnt'];
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($pageTitle) ?> — <?= e($hospitalName) ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body class="<?= $isSwitched ? 'role-switched' : '' ?>">

<script>
window.APP_CONFIG = {
    baseUrl:        '<?= BASE_URL ?>',
    currencySymbol: '<?= e(getSetting('currency_symbol', 'Rs.')) ?>',
    tokenPrefix:    '<?= e(getSetting('token_prefix', 'TKN')) ?>',
    soundEnabled:   <?= (int) getSetting('sound_effects', '1') ?>,
    userId:         <?= $currentUserId ?>,
    userRole:       'receptionist',
    isRoleSwitched: <?= $isSwitched ? 'true' : 'false' ?>,
};
</script>

<!-- ═══════════════════════════════════════════════════════════
     ROLE-SWITCH BANNER — fixed outside app-shell so it never
     disrupts the flex layout.
     ═══════════════════════════════════════════════════════════ -->
<?php if ($isSwitched): ?>
<div class="role-switch-banner" id="roleSwitchBanner">
    <div class="flex items-center gap-2">
        <i class="fa-solid fa-arrows-rotate"></i>
        <span>You are viewing as <strong>Receptionist</strong>.
              Actions here are attributed to your Admin account.</span>
    </div>
    <button class="btn btn-sm" id="restoreAdminBtn"
            style="background:#92400e;color:#fff;border:none;">
        <i class="fa-solid fa-arrow-left"></i> Back to Admin View
    </button>
</div>
<?php endif; ?>

<div class="app-shell">

<!-- ═══════════════════════════════════════════════════════════
     TOPNAV
     ═══════════════════════════════════════════════════════════ -->
<header class="topnav" id="mainTopnav">

    <!-- Hamburger + Brand -->
    <button class="btn btn-ghost btn-icon" id="hamburgerBtn" title="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="flex items-center gap-2 select-none">
        <div style="width:32px;height:32px;background:var(--color-accent);border-radius:8px;
                    display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            <?php if ($logoSrc): ?>
                <img src="<?= $logoSrc ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <i class="fa-solid fa-hospital-user text-white text-sm"></i>
            <?php endif; ?>
        </div>
        <span class="font-bold text-sm hidden sm:block" style="color:var(--color-text);">
            <?= e($hospitalName) ?>
        </span>
        <span class="text-xs px-2 py-0.5 rounded-full font-bold hidden md:block"
              style="background:var(--color-accent-lt);color:var(--color-accent);">
            RECEPTION
        </span>
    </div>

    <!-- Today's token mini badge -->
    <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-xl"
         style="background:var(--color-primary-lt);border:1px solid var(--color-primary-mid);">
        <i class="fa-solid fa-ticket text-xs" style="color:var(--color-primary);"></i>
        <span class="text-xs font-bold" style="color:var(--color-primary);">
            <?= $myTokensToday ?> today
        </span>
    </div>

    <!-- Right side -->
    <div class="topnav-right">

        <span class="live-clock hidden md:block" id="live-clock">00:00:00</span>

        <button class="btn btn-ghost btn-icon" id="darkModeToggle" title="Toggle dark mode">
            <i class="fa-solid fa-moon"></i>
        </button>

        <!-- User dropdown -->
        <div class="dropdown" id="userDropdown">
            <div data-dropdown-trigger class="flex items-center gap-2 cursor-pointer">
                <?php if ($profileSrc): ?>
                    <img src="<?= $profileSrc ?>" alt="<?= e($fullName) ?>" class="avatar">
                <?php else: ?>
                    <div class="avatar" style="font-size:13px;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <span class="text-sm font-semibold hidden md:block"
                      style="color:var(--color-text);"><?= e($firstName) ?></span>
                <i class="fa-solid fa-chevron-down text-xs hidden md:block"
                   style="color:var(--color-text-faint);"></i>
            </div>
            <div class="dropdown-menu">
                <div style="padding:0.5rem 0.75rem 0.4rem;border-bottom:1px solid var(--color-border);margin-bottom:0.35rem;">
                    <p class="text-xs font-bold" style="color:var(--color-text);"><?= e($fullName) ?></p>
                    <p class="text-xs" style="color:var(--color-text-faint);"><?= e($_SESSION['email'] ?? '') ?></p>
                </div>
                <a href="<?= BASE_URL ?>/receptionist/profile.php" class="dropdown-item">
                    <i class="fa-regular fa-user w-4"></i> My Profile
                </a>
                <?php if ($isAdminActor): ?>
                <div class="dropdown-divider"></div>
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="dropdown-item"
                   style="color:var(--color-primary);">
                    <i class="fa-solid fa-shield-halved w-4"></i> Admin Dashboard
                </a>
                <?php endif; ?>
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

        <a href="<?= BASE_URL ?>/receptionist/dashboard.php"
           class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-gauge-high"></i></span>
            Dashboard
        </a>

        <!-- TOKENS -->
        <div class="sidebar-section-header mt-2">Tokens</div>

        <!-- <a href="<?= BASE_URL ?>/receptionist/generate-token.php"
           class="sidebar-link <?= $activePage === 'generate-token' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-ticket"></i></span>
            Generate Token
        </a> -->

        <a href="<?= BASE_URL ?>/receptionist/patients.php"
           class="sidebar-link <?= $activePage === 'patients' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-users"></i></span>
            Today's Patients
        </a>

        <!-- ADMISSIONS -->
        <div class="sidebar-section-header mt-2">Admissions</div>

        <a href="<?= BASE_URL ?>/receptionist/admissions.php"
           class="sidebar-link <?= $activePage === 'admissions' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-solid fa-clipboard-list"></i></span>
            Admissions
        </a>

        <!-- ACCOUNT -->
        <div class="sidebar-section-header mt-2">Account</div>

        <a href="<?= BASE_URL ?>/receptionist/profile.php"
           class="sidebar-link <?= $activePage === 'profile' ? 'active' : '' ?>">
            <span class="sidebar-icon"><i class="fa-regular fa-circle-user"></i></span>
            My Profile
        </a>

        <a href="<?= BASE_URL ?>/auth/logout.php" class="sidebar-link"
           style="color:var(--color-danger);">
            <span class="sidebar-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            Logout
        </a>

    </nav>

    <div style="padding:0.75rem 1rem;border-top:1px solid var(--color-border);
                font-size:11px;color:var(--color-text-faint);">
        TokenMed v2.0
    </div>
</aside>

<!-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT — closed by footer.php
     ═══════════════════════════════════════════════════════════ -->
<main class="main-content" id="mainContent">

<div id="toast-container" class="toast-container"></div>

<?php if ($isSwitched): ?>
<script>
(function () {
    const btn = document.getElementById('restoreAdminBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const fd = new FormData();
        fd.set('csrf_token', '<?= e($csrfToken) ?>');
        fd.set('action', 'restore');
        fetch('<?= BASE_URL ?>/ajax/switch-role.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) window.location.href = res.data.redirect;
                else showToast(res.message, 'error');
            })
            .catch(() => showToast('Network error.', 'error'));
    });
})();
</script>
<?php endif; ?>