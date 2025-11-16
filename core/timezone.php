<?php
// ---------------------------------------------------------------------
// core/timezone.php - Centralized timezone helpers for the storefront
// ---------------------------------------------------------------------

if (!defined('MYSTIC_APP_TIMEZONE')) {
    define('MYSTIC_APP_TIMEZONE', 'Asia/Kolkata');
}

if (!function_exists('mystic_apply_timezone')) {
    function mystic_apply_timezone(): void
    {
        static $applied = false;
        if ($applied) {
            return;
        }

        $target = MYSTIC_APP_TIMEZONE;
        try {
            if (!@date_default_timezone_set($target)) {
                date_default_timezone_set('UTC');
            }
        } catch (Throwable $e) {
            date_default_timezone_set('UTC');
        }

        $applied = true;
    }
}

if (!function_exists('mystic_app_timezone')) {
    function mystic_app_timezone(): DateTimeZone
    {
        $name = MYSTIC_APP_TIMEZONE;
        try {
            return new DateTimeZone($name);
        } catch (Throwable $e) {
            return new DateTimeZone('UTC');
        }
    }
}

mystic_apply_timezone();
