<?php

/**
 * auth/login.php — Beautiful split-layout login page.
 * No scroll, full viewport, logo support, dark mode safe.
 */
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

bootApp();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . (isAdmin() && !isRoleSwitched() ? '/admin/dashboard.php' : '/receptionist/dashboard.php'));
    exit;
}

$csrfToken    = getCsrfToken();
$hospitalName = getSetting('hospital_name', 'Hospital Management System');
$hospitalAddr = getSetting('hospital_address', '');
$hospitalPhone = getSetting('contact_phone', '');
$logoFile     = getSetting('hospital_logo', '');
$logoSrc      = $logoFile ? BASE_URL . '/assets/uploads/logo/' . $logoFile : '';
$timeoutMsg   = isset($_GET['timeout']) ? 'Your session has expired. Please log in again.' : '';
$csrfMsg      = isset($_GET['csrf'])    ? 'Security token expired. Please try again.'      : '';
?>
<!DOCTYPE html>
<html lang="en" class="">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= e($hospitalName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
            width: 100%;
            overflow: hidden;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        }

        /* ── Full-viewport shell ── */
        .login-shell {
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* ── Left panel — branding ── */
        .login-brand {
            flex: 1.1;
            background: linear-gradient(145deg, #0c4a6e 0%, #075985 40%, #0369a1 70%, #0284c7 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circles */
        .login-brand::before {
            content: '';
            position: absolute;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            border: 60px solid rgba(255, 255, 255, 0.05);
            top: -100px;
            right: -100px;
        }

        .login-brand::after {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            border: 50px solid rgba(255, 255, 255, 0.05);
            bottom: -80px;
            left: -60px;
        }

        .brand-logo-wrap {
            width: 100px;
            height: 100px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        /* .brand-logo-wrap img  { width:100%; height:100%; object-fit:cover; } */
        .brand-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;

            /* keep it clean + centered glow on logo only */
            filter:
                drop-shadow(0 0 6px rgba(125, 211, 252, 0.55)) drop-shadow(0 0 14px rgba(3, 105, 161, 0.35)) brightness(1.05) contrast(1.5);

            transform: translateZ(0);
            /* smoother rendering */
        }

        .brand-logo-wrap i {
            font-size: 42px;
            color: #fff;
        }

        .brand-title {
            font-size: clamp(1.2rem, 2.5vw, 1.75rem);
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            line-height: 1.2;
        }

        .brand-sub {
            font-size: clamp(0.75rem, 1.2vw, 0.875rem);
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 1;
        }

        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
            max-width: 280px;
            position: relative;
            z-index: 1;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 0.6rem 0.875rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            font-weight: 500;
        }

        .brand-feature i {
            font-size: 14px;
            color: #7dd3fc;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
        }

        .brand-footer {
            position: absolute;
            bottom: 1.25rem;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            z-index: 1;
            letter-spacing: 0.05em;
        }

        /* ── Right panel — form ── */
        .login-form-panel {
            flex: 0.9;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 2.5rem;
            overflow: hidden;
        }

        .dark .login-form-panel {
            background: #1e293b;
        }

        .login-form-inner {
            width: 100%;
            max-width: 360px;
        }

        .login-form-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .dark .login-form-title {
            color: #e2e8f0;
        }

        .login-form-sub {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 1.75rem;
        }

        .dark .login-form-sub {
            color: #94a3b8;
        }

        /* Input group */
        .lg-input-group {
            margin-bottom: 1rem;
        }

        .lg-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .dark .lg-label {
            color: #cbd5e1;
        }

        .lg-input-wrap {
            position: relative;
        }

        .lg-input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
            pointer-events: none;
            z-index: 1;
        }

        .lg-input {
            width: 100%;
            padding: 0.65rem 0.875rem 0.65rem 2.5rem;
            font-family: inherit;
            font-size: 14px;
            color: #1e293b;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            min-height: 44px;
            -webkit-appearance: none;
            appearance: none;
        }

        .lg-input:focus {
            border-color: #0369a1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(3, 105, 161, 0.12);
        }

        .dark .lg-input {
            color: #e2e8f0;
            background: #263148;
            border-color: #475569;
        }

        .dark .lg-input:focus {
            border-color: #0284c7;
            background: #1e293b;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .dark .lg-input::placeholder {
            color: #475569;
        }

        .lg-pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            line-height: 1;
            transition: color 0.15s;
        }

        .lg-pw-toggle:hover {
            color: #475569;
        }

        .lg-error {
            font-size: 11.5px;
            color: #dc2626;
            margin-top: 4px;
            display: none;
        }

        .lg-error.visible {
            display: block;
        }

        .lg-input.invalid {
            border-color: #dc2626;
        }

        .dark .lg-input.invalid {
            border-color: #ef4444;
        }

        /* Alert banners */
        .lg-alert {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.875rem;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .lg-alert.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .lg-alert.danger {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fecaca;
        }

        .dark .lg-alert.warning {
            background: rgba(217, 119, 6, 0.15);
            border-color: rgba(217, 119, 6, 0.3);
            color: #fbbf24;
        }

        .dark .lg-alert.danger {
            background: rgba(220, 38, 38, 0.15);
            border-color: rgba(220, 38, 38, 0.3);
            color: #f87171;
        }

        /* Remember me */
        .lg-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            cursor: pointer;
            user-select: none;
        }

        .lg-remember input {
            accent-color: #0369a1;
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .lg-remember span {
            font-size: 13px;
            color: #64748b;
        }

        .dark .lg-remember span {
            color: #94a3b8;
        }

        /* Submit button */
        .lg-submit {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #0369a1, #0284c7);
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 46px;
            transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
            box-shadow: 0 4px 14px rgba(3, 105, 161, 0.35);
            letter-spacing: 0.01em;
        }

        .lg-submit:hover {
            opacity: 0.92;
            box-shadow: 0 6px 20px rgba(3, 105, 161, 0.4);
        }

        .lg-submit:active {
            transform: scale(0.98);
        }

        .lg-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Divider */
        .lg-divider {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 11px;
            color: #94a3b8;
            letter-spacing: 0.04em;
        }

        /* ── Responsive: stack on mobile ── */
        @media (max-width: 768px) {
            .login-brand {
                display: none;
            }

            .login-form-panel {
                flex: 1;
                padding: 1.5rem;
            }
        }

        /* ── Spinner ── */
        .spin {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: lgSpin 0.6s linear infinite;
        }

        @keyframes lgSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Fade in ── */
        .lg-fadein {
            animation: lgFadeIn 0.4s ease forwards;
        }

        @keyframes lgFadeIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <script>
        // Apply saved dark mode before render to avoid flash
        (function() {
            if (localStorage.getItem('tokenmed_dark') === '1') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <div class="login-shell">

        <!-- ── LEFT: Branding ── -->
        <div class="login-brand">

            <div class="brand-logo-wrap">
                <?php if ($logoSrc): ?>
                    <img src="<?= $logoSrc ?>" alt="<?= e($hospitalName) ?>">
                <?php else: ?>
                    <i class="fa-solid fa-hospital-user"></i>
                <?php endif; ?>
            </div>

            <div class="brand-title"><?= e($hospitalName) ?></div>
            <div class="brand-sub">
                <?= $hospitalAddr ? e($hospitalAddr) : 'Hospital Reception Management System' ?>
            </div>

            <div class="brand-features">
                <div class="brand-feature">
                    <i class="fa-solid fa-ticket"></i>
                    Smart Token Generation
                </div>
                <div class="brand-feature">
                    <i class="fa-solid fa-users"></i>
                    Patient Management
                </div>
                <div class="brand-feature">
                    <i class="fa-solid fa-bed"></i>
                    Admission &amp; Discharge
                </div>
                <div class="brand-feature">
                    <i class="fa-solid fa-chart-line"></i>
                    Revenue Reports
                </div>
            </div>

            <div class="brand-footer">TokenMed v2.0 &mdash; Secure &amp; Reliable</div>
        </div>

        <!-- ── RIGHT: Form ── -->
        <div class="login-form-panel">
            <div class="login-form-inner lg-fadein">

                <h2 class="login-form-title">Welcome Back</h2>
                <p class="login-form-sub">Sign in to continue to your dashboard</p>

                <!-- Alerts -->
                <?php if ($timeoutMsg): ?>
                    <div class="lg-alert warning">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <?= e($timeoutMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($csrfMsg): ?>
                    <div class="lg-alert danger">
                        <i class="fa-solid fa-shield-exclamation"></i>
                        <?= e($csrfMsg) ?>
                    </div>
                <?php endif; ?>

                <div class="lg-alert danger" id="loginError" style="display:none;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span id="loginErrorText"></span>
                </div>

                <!-- Form -->
                <form id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <!-- Email -->
                    <div class="lg-input-group">
                        <label class="lg-label" for="email">Email Address</label>
                        <div class="lg-input-wrap">
                            <i class="fa-regular fa-envelope lg-input-icon"></i>
                            <input type="email" id="email" name="email" class="lg-input"
                                placeholder="you@hospital.com" autocomplete="email" required>
                        </div>
                        <div class="lg-error" id="emailError"></div>
                    </div>

                    <!-- Password -->
                    <div class="lg-input-group">
                        <label class="lg-label" for="password">Password</label>
                        <div class="lg-input-wrap">
                            <i class="fa-solid fa-lock lg-input-icon"></i>
                            <input type="password" id="password" name="password" class="lg-input"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                style="padding-right:44px;" required>
                            <button type="button" class="lg-pw-toggle" id="pwToggle">
                                <i class="fa-regular fa-eye" id="pwIcon"></i>
                            </button>
                        </div>
                        <div class="lg-error" id="passwordError"></div>
                    </div>

                    <!-- Remember me -->
                    <label class="lg-remember">
                        <input type="checkbox" name="remember_me" id="rememberMe">
                        <span>Remember me on this device</span>
                    </label>

                    <!-- Submit -->
                    <button type="submit" class="lg-submit" id="loginBtn">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Sign In
                    </button>
                </form>

                <div class="lg-divider">
                    <i class="fa-solid fa-shield-halved" style="margin-right:4px;"></i>
                    Secured with CSRF protection &amp; bcrypt encryption
                </div>

            </div>
        </div>

    </div>

    <script>
        window.APP_CONFIG = {
            baseUrl: '<?= BASE_URL ?>',
            soundEnabled: 0,
            userId: 0,
            userRole: '',
            isRoleSwitched: false,
            currencySymbol: 'Rs.',
            tokenPrefix: 'TKN',
        };
    </script>
    <script src="<?= BASE_URL ?>/assets/js/common.js"></script>
    <script>
        (function() {
            'use strict';

            // Dark mode toggle (hidden button for power users — Ctrl+D)
            document.addEventListener('keydown', e => {
                if (e.ctrlKey && e.key === 'd') {
                    e.preventDefault();
                    toggleDarkMode();
                }
            });

            // Password show/hide
            const pwField = document.getElementById('password');
            const pwIcon = document.getElementById('pwIcon');
            document.getElementById('pwToggle').addEventListener('click', () => {
                const show = pwField.type === 'password';
                pwField.type = show ? 'text' : 'password';
                pwIcon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            });

            // Field error helpers
            function fieldErr(id, msg) {
                const el = document.getElementById(id + 'Error');
                const inp = document.getElementById(id);
                if (el) {
                    el.textContent = msg;
                    el.classList.add('visible');
                }
                if (inp) inp.classList.add('invalid');
            }

            function clearErr(id) {
                const el = document.getElementById(id + 'Error');
                const inp = document.getElementById(id);
                if (el) {
                    el.textContent = '';
                    el.classList.remove('visible');
                }
                if (inp) inp.classList.remove('invalid');
            }

            // Alert box
            const errBox = document.getElementById('loginError');
            const errText = document.getElementById('loginErrorText');

            function showAlert(msg) {
                errText.textContent = msg;
                errBox.style.display = 'flex';
            }

            function hideAlert() {
                errBox.style.display = 'none';
            }

            // Form submit
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');

            form.addEventListener('submit', e => {
                e.preventDefault();
                hideAlert();

                const email = document.getElementById('email').value.trim();
                const pass = pwField.value;
                let ok = true;

                if (!email) {
                    fieldErr('email', 'Email is required.');
                    ok = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    fieldErr('email', 'Enter a valid email.');
                    ok = false;
                } else clearErr('email');

                if (!pass) {
                    fieldErr('password', 'Password is required.');
                    ok = false;
                } else clearErr('password');

                if (!ok) return;

                const orig = loginBtn.innerHTML;
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="spin"></span> Signing in…';

                fetch(`${window.APP_CONFIG.baseUrl}/ajax/login.php`, {
                        method: 'POST',
                        body: new FormData(form)
                    })
                    .then(r => r.json())
                    .then(res => {
                        loginBtn.disabled = false;
                        if (res.success) {
                            loginBtn.innerHTML = '<i class="fa-solid fa-check"></i> Success!';
                            loginBtn.style.background = 'linear-gradient(135deg,#059669,#047857)';
                            loginBtn.style.boxShadow = '0 4px 14px rgba(5,150,105,0.4)';
                            window.location.href = res.data.redirect || `${window.APP_CONFIG.baseUrl}/`;
                        } else {
                            loginBtn.innerHTML = orig;
                            showAlert(res.message || 'Login failed. Please try again.');
                        }
                    })
                    .catch(() => {
                        loginBtn.disabled = false;
                        loginBtn.innerHTML = orig;
                        showAlert('Network error. Please check your connection.');
                    });
            });

            initDarkMode();
        })();
    </script>
</body>

</html>