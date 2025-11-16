<?php
// ---------------------------------------------------------------------
// admin/_sidebar.php - Centralized admin sidebar with active link highlight
// ---------------------------------------------------------------------
// Expect $ADMIN_TITLE to be set by the including page.
if (!isset($ADMIN_TITLE)) {
    $ADMIN_TITLE = '';
}

function _admin_is_active(string $title, string $adminTitle): bool
{
    $t = strtolower($title);
    $a = strtolower($adminTitle);
    return strpos($a, $t) !== false || strpos($t, $a) !== false;
}

$links = [
    ['href' => 'dashboard.php', 'label' => 'Dashboard'],
    ['href' => 'products.php', 'label' => 'Products'],
    ['href' => 'orders.php', 'label' => 'Orders'],
    ['href' => 'marketing.php', 'label' => 'Marketing'],
    ['href' => 'support_queue.php', 'label' => 'Support Queue'],
    ['href' => 'spotlight.php', 'label' => 'Spotlight'],
    ['href' => 'customers.php', 'label' => 'Customers'],
];
?>
<div class="w-64 bg-slate-900/80 text-white p-5 border-r border-white/10 backdrop-blur">
    <h1 class="text-2xl font-bold mb-10">Admin Panel</h1>
    <nav>
        <?php foreach ($links as $link):
            $active = _admin_is_active($link['label'], $ADMIN_TITLE);
            $classes = 'block py-2.5 px-4 rounded hover:bg-gray-700';
            if ($active) { $classes .= ' bg-gray-700'; }
        ?>
            <a href="<?php echo $link['href']; ?>" class="<?php echo $classes; ?>"><?php echo htmlspecialchars($link['label']); ?></a>
        <?php endforeach; ?>

        <a href="../index.php" class="block py-2.5 px-4 rounded hover:bg-gray-700 mt-10">Back to Site</a>
        <a href="logout.php" class="block py-2.5 px-4 rounded hover:bg-gray-700">Logout</a>
    </nav>
</div>
