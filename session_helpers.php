<?php
// Utility helpers for working with custom design session data.

if (!function_exists('ensure_session_started')) {
    function ensure_session_started(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('ensure_custom_design_array')) {
    function ensure_custom_design_array(): array
    {
        ensure_session_started();
        if (!isset($_SESSION['custom_design_ids']) || !is_array($_SESSION['custom_design_ids'])) {
            $_SESSION['custom_design_ids'] = [];
        }
        return $_SESSION['custom_design_ids'];
    }
}

if (!function_exists('get_custom_design_ids')) {
    function get_custom_design_ids(): array
    {
        ensure_session_started();
        if (empty($_SESSION['custom_design_ids']) || !is_array($_SESSION['custom_design_ids'])) {
            return [];
        }
        return array_values(array_filter($_SESSION['custom_design_ids'], static fn($id) => (int)$id > 0));
    }
}

if (!function_exists('custom_design_count')) {
    function custom_design_count(): int
    {
        return count(get_custom_design_ids());
    }
}

if (!function_exists('add_custom_design_id')) {
    function add_custom_design_id(int $designId): void
    {
        ensure_session_started();
        $designId = (int)$designId;
        if ($designId <= 0) {
            return;
        }
        $ids = ensure_custom_design_array();
        if (!in_array($designId, $ids, true)) {
            $_SESSION['custom_design_ids'][] = $designId;
        }
        sync_custom_design_cart_quantity();
    }
}

if (!function_exists('remove_custom_design_id')) {
    function remove_custom_design_id(int $designId): void
    {
        ensure_session_started();
        if (empty($_SESSION['custom_design_ids']) || !is_array($_SESSION['custom_design_ids'])) {
            return;
        }
        $_SESSION['custom_design_ids'] = array_values(array_filter(
            $_SESSION['custom_design_ids'],
            static fn($storedId) => (int)$storedId !== (int)$designId
        ));
        sync_custom_design_cart_quantity();
    }
}

if (!function_exists('clear_custom_design_ids')) {
    function clear_custom_design_ids(): void
    {
        ensure_session_started();
        unset($_SESSION['custom_design_ids']);
        sync_custom_design_cart_quantity();
    }
}

if (!function_exists('sync_custom_design_cart_quantity')) {
    function sync_custom_design_cart_quantity(): void
    {
        ensure_session_started();
        $count = custom_design_count();
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if ($count > 0) {
            $_SESSION['cart'][4] = $count;
        } else {
            unset($_SESSION['cart'][4]);
        }
    }
}
