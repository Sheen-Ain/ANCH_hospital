<?php
/**
 * includes/settings.php
 * Loads ALL rows from the `settings` table into the global $SETTINGS array.
 *
 * Include this file (via functions.php) on every page that needs a setting.
 * Access values via getSetting() — never access $SETTINGS directly in views.
 *
 * This file requires db.php to already be included.
 */

/**
 * Fetch all settings from the database and return as associative array.
 *
 * @return array  ['setting_key' => 'setting_value', ...]
 */
function loadSettings(): array
{
    try {
        $rows = Database::fetchAll("SELECT `setting_key`, `setting_value` FROM `settings`");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        error_log('TokenMed Settings Load Error: ' . $e->getMessage());
        return [];
    }
}

// Populate global once when this file is included
global $SETTINGS;
$SETTINGS = loadSettings();
