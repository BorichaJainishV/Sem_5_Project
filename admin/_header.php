<?php
// ---------------------------------------------------------------------
// admin/_header.php - Shared admin HTML head
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo isset($ADMIN_TITLE) ? htmlspecialchars($ADMIN_TITLE) : 'Admin'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<?php
// Emit body and include the centralized sidebar. Pages may set $ADMIN_BODY_CLASS before including this file.
$bodyClass = isset($ADMIN_BODY_CLASS) ? $ADMIN_BODY_CLASS : 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50';
?>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
<div class="min-h-screen flex">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>

    <!-- Content column starts in each page with: <div class="flex-1 p-10"> -->
<?php
// End header
?>
