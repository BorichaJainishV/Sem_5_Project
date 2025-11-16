<?php
date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}
session_start();
require_once __DIR__ . '/../session_helpers.php';
