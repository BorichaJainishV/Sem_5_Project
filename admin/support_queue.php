<?php
// ---------------------------------------------------------------------
// /admin/support_queue.php - Queue viewer for escalated support tickets
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../core/support_ticket_queue.php';
require_once 'activity_logger.php';

$flash = $_SESSION['support_queue_flash'] ?? null;
unset($_SESSION['support_queue_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['support_queue_flash'] = ['type' => 'error', 'message' => 'Invalid session token.'];
        header('Location: support_queue.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    $ticketId = trim((string)($_POST['ticket_id'] ?? ''));

    if ($action === 'resolve' && $ticketId !== '') {
        if (resolve_support_ticket($ticketId, (int)$_SESSION['admin_id'])) {
            log_admin_activity((int)$_SESSION['admin_id'], 'support_ticket_resolved', ['ticket_id' => $ticketId]);
            $_SESSION['support_queue_flash'] = ['type' => 'success', 'message' => 'Ticket marked as resolved.'];
        } else {
            $_SESSION['support_queue_flash'] = ['type' => 'error', 'message' => 'Unable to update ticket status.'];
        }
    }

    header('Location: support_queue.php');
    exit();
}

$openTickets = get_support_tickets('open', 100);
$resolvedTickets = get_support_tickets('resolved', 12);
?>
<?php $ADMIN_TITLE = 'Support Queue'; require_once __DIR__ . '/_header.php'; ?>
<?php $ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50'; ?>
<?php $ADMIN_TITLE = 'Support Queue'; require_once __DIR__ . '/_header.php'; ?>
<div class="flex-1 p-10 space-y-8">
        <div>
            <h1 class="text-3xl font-bold mb-2 text-white">Support Queue</h1>
            <p class="text-sm text-indigo-100/80">Escalated chat transcripts with cart context so the team can reply quickly.</p>
        </div>

        <?php if ($flash): ?>
            <div class="rounded-xl px-4 py-3 <?php echo $flash['type'] === 'success' ? 'bg-emerald-500/15 text-emerald-100 border border-emerald-500/40' : 'bg-rose-500/15 text-rose-100 border border-rose-500/40'; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="space-y-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <h2 class="text-2xl font-semibold text-white">Open Tickets (<?php echo count($openTickets); ?>)</h2>
                <span class="text-sm text-indigo-100/70">Showing latest <?php echo count($openTickets); ?> conversations</span>
            </div>

            <?php if (empty($openTickets)): ?>
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6 text-indigo-100/70">
                    No open tickets waiting—check back after the next escalation.
                </div>
            <?php else: ?>
                <?php foreach ($openTickets as $ticket):
                    $createdAt = !empty($ticket['created_at']) ? date('M d, Y · H:i', (int)$ticket['created_at']) : 'Just now';
                    $customer = $ticket['customer'] ?? [];
                    $orderContext = $ticket['order_context'] ?? [];
                    $cartItems = $orderContext['cart_items'] ?? [];
                    $shipping = $orderContext['shipping'] ?? [];
                    $channels = array_filter($ticket['channels'] ?? []);
                    ?>
                    <article class="bg-white/5 border border-white/10 rounded-2xl p-6 shadow-lg space-y-5">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Ticket <?php echo htmlspecialchars($ticket['id'] ?? ''); ?></h3>
                                <p class="text-xs text-indigo-100/70">Raised <?php echo htmlspecialchars($createdAt); ?></p>
                            </div>
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['id'] ?? ''); ?>">
                                <input type="hidden" name="action" value="resolve">
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm font-semibold hover:bg-emerald-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7" /></svg>
                                    Mark resolved
                                </button>
                            </form>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                            <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-2">
                                <p class="text-xs uppercase tracking-wide text-indigo-100/60">Customer</p>
                                <p class="text-white font-semibold"><?php echo htmlspecialchars($customer['name'] ?? 'Mystic Customer'); ?></p>
                                <?php if (!empty($customer['email'])): ?>
                                    <p class="text-indigo-100/70">Email: <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="underline hover:text-white"><?php echo htmlspecialchars($customer['email']); ?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($ticket['preferred_contact'])): ?>
                                    <p class="text-indigo-100/70">Prefers: <?php echo htmlspecialchars($ticket['preferred_contact']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($channels)): ?>
                                    <p class="text-indigo-100/70">Channels: <?php echo htmlspecialchars(implode(', ', array_keys($channels))); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-2">
                                <p class="text-xs uppercase tracking-wide text-indigo-100/60">Order Snapshot</p>
                                <?php if (!empty($cartItems)): ?>
                                    <ul class="space-y-1 text-indigo-100/80">
                                        <?php foreach ($cartItems as $item): ?>
                                            <li>
                                                <?php echo (int)($item['quantity'] ?? 0); ?>x <?php echo htmlspecialchars($item['name'] ?? 'Item'); ?>
                                                <?php if (isset($item['unit_price'])): ?>
                                                    <span class="text-indigo-100/60">(₹<?php echo number_format((float)$item['unit_price'], 2); ?> each)</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-indigo-100/70">Cart details unavailable.</p>
                                <?php endif; ?>
                                <?php if (!empty($orderContext['estimated_total'])): ?>
                                    <p class="text-indigo-100/80 font-semibold">Estimated total: ₹<?php echo number_format((float)$orderContext['estimated_total'], 2); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($shipping)): ?>
                                    <p class="text-indigo-100/70">Ship to: <?php echo htmlspecialchars(trim(($shipping['name'] ?? '') . ' ' . ($shipping['city'] ?? ''))); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <details class="bg-slate-900/40 border border-white/10 rounded-xl">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-indigo-100">Conversation transcript</summary>
                            <div class="px-4 py-4 space-y-3 text-sm text-indigo-100/80">
                                <?php if (!empty($ticket['conversation'])): ?>
                                    <?php foreach ($ticket['conversation'] as $entry):
                                        $role = $entry['role'] ?? 'customer';
                                        $label = $role === 'agent' ? 'Support' : 'Customer';
                                        $timestamp = !empty($entry['timestamp']) ? date('M d, H:i', (int)$entry['timestamp']) : '';
                                        $message = trim((string)($entry['message'] ?? ''));
                                        ?>
                                        <div class="bg-white/5 border border-white/10 rounded-lg px-3 py-2">
                                            <p class="text-xs text-indigo-200/80 uppercase tracking-wide flex items-center justify-between">
                                                <span><?php echo htmlspecialchars($label); ?></span>
                                                <?php if ($timestamp): ?><span><?php echo htmlspecialchars($timestamp); ?></span><?php endif; ?>
                                            </p>
                                            <p class="mt-1 text-indigo-100/90 leading-relaxed"><?php echo nl2br(htmlspecialchars($message)); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-indigo-100/70">No chat messages were captured.</p>
                                <?php endif; ?>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <h2 class="text-xl font-semibold text-white">Recently Resolved</h2>
                <span class="text-xs text-indigo-100/60">Latest <?php echo count($resolvedTickets); ?> tickets closed</span>
            </div>
            <?php if (empty($resolvedTickets)): ?>
                <p class="text-sm text-indigo-100/70">No recent resolutions yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto bg-white/5 border border-white/10 rounded-2xl">
                    <table class="min-w-full text-sm text-indigo-100/80">
                        <thead class="text-xs uppercase tracking-wide text-indigo-200/80">
                            <tr>
                                <th class="px-4 py-3 text-left">Ticket</th>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Summary</th>
                                <th class="px-4 py-3 text-left">Resolved At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resolvedTickets as $ticket): ?>
                                <tr class="border-t border-white/10">
                                    <td class="px-4 py-3 font-semibold text-indigo-100"><?php echo htmlspecialchars($ticket['id'] ?? ''); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($ticket['customer']['name'] ?? 'Mystic Customer'); ?></td>
                                    <td class="px-4 py-3 truncate max-w-xs" title="<?php echo htmlspecialchars($ticket['issue_summary'] ?? ''); ?>"><?php echo htmlspecialchars($ticket['issue_summary'] ?? ''); ?></td>
                                    <td class="px-4 py-3">
                                        <?php echo !empty($ticket['resolved_at']) ? htmlspecialchars(date('M d, Y · H:i', (int)$ticket['resolved_at'])) : '—'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
</body>
</html>
