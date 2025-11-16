<?php
// ---------------------------------------------------------------------
// /admin/orders.php - View and Manage Customer Orders
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) { header('Location: index.php'); exit(); }
include '../db_connection.php';
require_once '../email_handler.php';
require_once 'activity_logger.php';

// Ensure CSRF token is present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$statusOptions = [
    'all' => 'All statuses',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$recentWindowDays = 90;

// --- Handle order status updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';
    $newStatus = null;

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        header('Location: orders.php');
        exit();
    }

    if ($orderId > 0 && isset($_POST['mark_shipped'])) {
        $currentStmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
        if ($currentStmt) {
            $currentStmt->bind_param("i", $orderId);
            $currentStmt->execute();
            $currentStmt->bind_result($currentStatus);
            if ($currentStmt->fetch() && strtolower($currentStatus) === 'processing') {
                $newStatus = 'shipped';
            }
            $currentStmt->close();
        }
    } elseif (isset($_POST['update_status'])) {
        $requestedStatus = strtolower(trim($_POST['status'] ?? ''));
        if (isset($statusOptions[$requestedStatus]) && $requestedStatus !== 'all') {
            $newStatus = $requestedStatus;
        }
    }

    if ($orderId > 0 && $newStatus !== null) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();

            $statusSent = in_array($newStatus, ['pending', 'processing', 'shipped', 'completed', 'cancelled'], true)
                ? $newStatus
                : 'updated';

            $notifyStmt = $conn->prepare("SELECT c.email, c.name, COALESCE(b.amount, 0) AS amount FROM orders o JOIN customer c ON o.customer_id = c.customer_id LEFT JOIN billing b ON o.order_id = b.order_id WHERE o.order_id = ? LIMIT 1");
            if ($notifyStmt) {
                $notifyStmt->bind_param("i", $orderId);
                if ($notifyStmt->execute()) {
                    $notifyResult = $notifyStmt->get_result();
                    $notifyRow = $notifyResult ? $notifyResult->fetch_assoc() : null;
                    if ($notifyResult) {
                        $notifyResult->free();
                    }
                    if ($notifyRow && !empty($notifyRow['email'])) {
                        $customerEmail = $notifyRow['email'];
                        $customerName = $notifyRow['name'] ?? 'Mystic customer';
                        $orderAmount = isset($notifyRow['amount']) ? (float) $notifyRow['amount'] : null;
                        sendOrderStatusUpdateEmail(
                            $customerEmail,
                            $customerName,
                            $orderId,
                            $statusSent,
                            [
                                'amount' => $orderAmount,
                            ]
                        );

                    }
                }
                $notifyStmt->close();
            }

            log_admin_activity(
                (int) ($_SESSION['admin_id'] ?? 0),
                'order_status_update',
                [
                    'order_id' => $orderId,
                    'new_status' => $statusSent,
                ]
            );
        }
    }

    header('Location: orders.php');
    exit();
}
// --- END: Handle order status updates ---

$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
if (!isset($statusOptions[$statusFilter])) {
    $statusFilter = 'all';
}

$searchQuery = trim($_GET['q'] ?? '');
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';
$exportParams = $_GET;
$exportParams['export'] = 'csv';
$exportLink = 'orders.php?' . http_build_query($exportParams);

$whereParts = [];
$paramTypes = '';
$paramValues = [];

if ($statusFilter !== 'all') {
    $whereParts[] = 'o.status = ?';
    $paramTypes .= 's';
    $paramValues[] = $statusFilter;
}

if ($searchQuery !== '') {
    $whereParts[] = '(c.name LIKE ? OR c.email LIKE ? OR o.order_id = ?)';
    $likeTerm = '%' . $searchQuery . '%';
    $paramTypes .= 'ssi';
    $paramValues[] = $likeTerm;
    $paramValues[] = $likeTerm;
    $paramValues[] = (int)$searchQuery;
}

$orderSql = "SELECT o.order_id, o.order_date, o.status, c.name AS customer_name, c.email AS customer_email, b.amount, (SELECT COUNT(*) FROM orders o_recent WHERE o_recent.customer_id = o.customer_id AND o_recent.order_date >= DATE_SUB(NOW(), INTERVAL {$recentWindowDays} DAY)) AS recent_orders FROM orders o JOIN customer c ON o.customer_id = c.customer_id LEFT JOIN billing b ON o.order_id = b.order_id";
if (!empty($whereParts)) {
    $orderSql .= ' WHERE ' . implode(' AND ', $whereParts);
}
$orderSql .= ' ORDER BY o.order_date DESC, o.order_id DESC';

$orderStmt = $conn->prepare($orderSql);
if ($orderStmt === false) {
    throw new RuntimeException('Failed to prepare order query: ' . $conn->error);
}

if (!empty($paramValues)) {
    $orderStmt->bind_param($paramTypes, ...$paramValues);
}

$orderStmt->execute();
$orderResults = $orderStmt->get_result();

if ($exportCsv) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open output stream for CSV export.');
    }

    fputcsv($output, ['Order ID', 'Customer', 'Email', 'Recent Orders (last ' . $recentWindowDays . ' days)', 'Status', 'Order Date', 'Amount']);

    while ($exportRow = $orderResults->fetch_assoc()) {
        fputcsv($output, [
            $exportRow['order_id'],
            $exportRow['customer_name'],
            $exportRow['customer_email'],
            $exportRow['recent_orders'],
            ucfirst($exportRow['status']),
            $exportRow['order_date'],
            $exportRow['amount'],
        ]);
    }

    fclose($output);
    $orderStmt->close();
    exit();
}

$orders = $orderResults;

$statusCounts = [];
$countResult = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");
if ($countResult) {
    while ($countRow = $countResult->fetch_assoc()) {
        $key = strtolower($countRow['status']);
        $statusCounts[$key] = (int)$countRow['total'];
    }
    $countResult->free();
}

$orderStmt->close();
?>
<?php $ADMIN_TITLE = 'Manage Orders'; require_once __DIR__ . '/_header.php'; ?>
<?php $ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50'; ?>
<?php $ADMIN_TITLE = 'Manage Orders'; require_once __DIR__ . '/_header.php'; ?>
<div class="flex-1 p-10 space-y-8">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-3xl font-bold">Customer Orders</h1>
                <p class="text-sm text-slate-500 mt-1">Monitor activity in real time, filter by fulfillment status, and keep shoppers in the loop.</p>
            </div>
            <a href="../shop.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow hover:bg-indigo-700 transition">
                <span>Open Storefront</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" /></svg>
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
            <?php foreach ($statusOptions as $key => $label): 
                if ($key === 'all') { continue; }
                $count = $statusCounts[$key] ?? 0;
            ?>
            <div class="bg-white/10 rounded-xl shadow p-4 border border-white/10 backdrop-blur <?php echo $statusFilter === $key ? 'ring-2 ring-indigo-400' : ''; ?>">
                <p class="text-xs uppercase tracking-wide text-indigo-200"><?php echo htmlspecialchars($label); ?></p>
                <p class="mt-2 text-2xl font-semibold text-white"><?php echo number_format($count); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="orders.php" class="bg-white/10 border border-white/10 rounded-xl shadow p-4 flex flex-col md:flex-row gap-3 md:items-end backdrop-blur">
            <div class="flex-1">
                <label for="q" class="block text-xs font-semibold text-indigo-100 uppercase tracking-wide mb-2">Search</label>
                <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Order #, customer name, or email" class="w-full px-4 py-2.5 border border-white/20 bg-white/10 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 placeholder:text-indigo-200">
            </div>
            <div class="w-full md:w-56">
               <label 
  for="status" 
  class="block text-xs font-semibold text-gray-700 dark:text-indigo-100 uppercase tracking-wide mb-2"
>
  Status
</label>

<select 
  id="status" 
  name="status" 
  class="w-full px-4 py-2.5 
         border border-gray-300 dark:border-white/20 
         bg-white dark:bg-white/10 
         text-gray-900 dark:text-white 
         rounded-lg 
         focus:outline-none focus:ring-2 
         focus:ring-indigo-500 dark:focus:ring-indigo-300"
>
  
   <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php if ($statusFilter === $value) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="submit" class="px-5 py-2.5 bg-indigo-500 text-white text-sm font-semibold rounded-lg hover:bg-indigo-600 transition">Apply</button>
                <a href="orders.php" class="px-5 py-2.5 border border-white/20 text-sm font-semibold rounded-lg hover:bg-white/10 transition">Reset</a>
                <a href="<?php echo htmlspecialchars($exportLink); ?>" class="px-5 py-2.5 bg-emerald-500 text-white text-sm font-semibold rounded-lg hover:bg-emerald-600 transition">Export CSV</a>
            </div>
        </form>

        <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-sm text-indigo-100/90 backdrop-blur">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M12 20.5c4.694 0 8.5-3.806 8.5-8.5s-3.806-8.5-8.5-8.5-8.5 3.806-8.5 8.5 3.806 8.5 8.5 8.5z"/></svg>
                <span class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Status Legend</span>
            </div>
            <p class="mt-2 text-xs text-indigo-100/70">In the status column you can move between actions (pending, processing, shipped, completed, cancelled) using the arrow keys before pressing update.</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-5 text-indigo-100/80">
                <span title="Awaiting confirmation or payment">Pending: awaiting action</span>
                <span title="Being prepared or produced">Processing: in progress</span>
                <span title="Handed to carrier with tracking">Shipped: with courier</span>
                <span title="Customer confirmed delivery">Completed: delivered</span>
                <span title="Cancelled by customer or staff">Cancelled: closed</span>
            </div>
        </div>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-md overflow-x-auto backdrop-blur">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Order ID</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Customer</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Contact</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Recent Orders (<?php echo $recentWindowDays; ?>d)</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Date</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Amount</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Status</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-indigo-100/80">No orders found for the current filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $orders->fetch_assoc()): ?>
                        <tr>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-white">#<?php echo $row['order_id']; ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10">
                                <p class="font-semibold text-white"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                            </td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-sm text-indigo-200"><?php echo htmlspecialchars($row['customer_email']); ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-sm">
                                <?php $recentCount = (int)$row['recent_orders']; ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md <?php echo $recentCount > 1 ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-indigo-100/80'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7" /></svg>
                                    <span><?php echo $recentCount; ?> order<?php echo $recentCount === 1 ? '' : 's'; ?></span>
                                </span>
                            </td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-sm text-indigo-100/80"><?php echo date("M d, Y", strtotime($row['order_date'])); ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 font-semibold text-white">₹<?php echo htmlspecialchars(number_format((float)$row['amount'], 2)); ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
    <?php 
        switch(strtolower($row['status'])) {
            case 'completed': 
                echo 'bg-green-100 text-green-800 dark:bg-green-800/30 dark:text-green-300'; 
                break;
            case 'shipped': 
                echo 'bg-blue-100 text-blue-800 dark:bg-blue-800/30 dark:text-blue-300'; 
                break;
            case 'pending': 
                echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700/30 dark:text-yellow-300'; 
                break;
            case 'processing': 
                echo 'bg-purple-100 text-purple-800 dark:bg-purple-800/30 dark:text-purple-300'; 
                break;
            case 'cancelled': 
                echo 'bg-red-100 text-red-800 dark:bg-red-800/30 dark:text-red-300'; 
                break;
            default: 
                echo 'bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-300';
        }
    ?>">
    <?= htmlspecialchars(ucfirst($row['status'])) ?>
</span>

                            </td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10">
                                <form method="POST" action="orders.php" class="flex items-center gap-2 flex-wrap">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$row['order_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <select name="status" class="w-full text-sm border-white/20 bg-black/10 text-white rounded-md">
                                        <?php foreach ($statusOptions as $value => $label): 
                                            if ($value === 'all') { continue; }
                                        ?>
                                            <option value="<?php echo $value; ?>" <?php if ($row['status'] === $value) echo 'selected'; ?>><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_status" class="px-3 py-1 bg-indigo-500 text-white text-sm rounded-md hover:bg-indigo-600">Update</button>
                                    <?php if (strtolower($row['status']) === 'processing'): ?>
                                        <button type="submit" name="mark_shipped" value="1" class="px-3 py-1 bg-emerald-500 text-white text-sm rounded-md hover:bg-emerald-600">
                                            Mark as shipped
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>