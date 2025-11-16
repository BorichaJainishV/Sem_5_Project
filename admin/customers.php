<?php
// ---------------------------------------------------------------------
// /admin/customers.php - View and Manage Customers
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) { header('Location: index.php'); exit(); }
include '../db_connection.php';

$searchQuery = trim($_GET['q'] ?? '');

if ($searchQuery !== '') {
    $like = '%' . $searchQuery . '%';
    $customersStmt = $conn->prepare("SELECT customer_id, name, email, created_at FROM customer WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC");
    if ($customersStmt === false) {
        throw new RuntimeException('Failed to prepare customer query: ' . $conn->error);
    }
    $customersStmt->bind_param('ss', $like, $like);
    $customersStmt->execute();
    $customers = $customersStmt->get_result();
} else {
    $customers = $conn->query("SELECT customer_id, name, email, created_at FROM customer ORDER BY created_at DESC");
    $customersStmt = null;
}

$totalCustomers = $conn->query("SELECT COUNT(*) AS c FROM customer");
$totalCustomerCount = $totalCustomers ? (int)$totalCustomers->fetch_assoc()['c'] : 0;
if ($totalCustomers) { $totalCustomers->free(); }

if ($customersStmt instanceof mysqli_stmt) {
    $customersStmt->close();
}

?>
<?php $ADMIN_TITLE = 'Manage Customers'; require_once __DIR__ . '/_header.php'; ?>
<?php $ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50'; ?>
<?php $ADMIN_TITLE = 'Manage Customers'; require_once __DIR__ . '/_header.php'; ?>
<div class="flex-1 p-10 space-y-8">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-3xl font-bold">Customer Accounts</h1>
                <p class="text-sm text-slate-500 mt-1">Track signups, reach out quickly, and understand who’s shopping your collections.</p>
            </div>
            <a href="orders.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow hover:bg-indigo-700 transition">
                <span>View Orders</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" /></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white/10 rounded-xl shadow p-6 border border-white/10 backdrop-blur">
                <p class="text-xs uppercase tracking-wide text-indigo-200">Total customers</p>
                <p class="mt-2 text-3xl font-semibold text-white"><?php echo number_format($totalCustomerCount); ?></p>
            </div>
            <?php if ($searchQuery !== ''): ?>
            <div class="bg-white/10 rounded-xl border border-white/10 p-6 backdrop-blur">
                <p class="text-xs uppercase tracking-wide text-indigo-200">Filtered</p>
                <p class="mt-2 text-indigo-100 font-semibold">Showing results for “<?php echo htmlspecialchars($searchQuery); ?>”.</p>
            </div>
            <?php endif; ?>
        </div>

        <form method="GET" action="customers.php" class="bg-white/10 border border-white/10 rounded-xl shadow p-4 flex flex-col sm:flex-row gap-3 sm:items-end backdrop-blur">
            <div class="flex-1">
                <label for="q" class="block text-xs font-semibold text-indigo-100 uppercase tracking-wide mb-2">Search customers</label>
                <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Name or email address" class="w-full px-4 py-2.5 border border-white/20 bg-white/10 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 placeholder:text-indigo-200">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-500 text-white text-sm font-semibold rounded-lg hover:bg-indigo-600 transition">Search</button>
                <a href="customers.php" class="px-5 py-2.5 border border-white/20 text-sm font-semibold rounded-lg hover:bg-white/10 transition">Clear</a>
            </div>
        </form>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-md overflow-x-auto backdrop-blur">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Customer ID</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Name</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Email</th>
                        <th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-indigo-100/80">No customers match your filters yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $customers->fetch_assoc()): ?>
                        <tr>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-white">#<?php echo $row['customer_id']; ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 font-semibold text-white"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-sm text-indigo-200"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-sm text-indigo-100/80"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
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