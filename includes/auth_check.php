<?php
/**
 * includes/auth_check.php
 *
 * Lightweight auth guard. Include this at the top of any protected page.
 * Provides: requireLogin(), requireAdmin(), requireReceptionist()
 *
 * These functions are defined in functions.php (already loaded via bootApp()).
 * This file exists as a clear, named entry point for the security check.
 *
 * Usage:
 *   require_once BASE_PATH . '/includes/auth_check.php';
 *   requireAdmin();        // admin-only pages
 *   requireReceptionist(); // receptionist pages (or admin in switched mode)
 *   requireLogin();        // any logged-in user
 */

// Ensure functions.php is loaded before calling guard functions
if (!function_exists('requireLogin')) {
    require_once __DIR__ . '/functions.php';
}
