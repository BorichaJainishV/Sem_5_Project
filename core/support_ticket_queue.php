<?php
// ---------------------------------------------------------------------
// core/support_ticket_queue.php - Simple JSON-backed support ticket queue
// ---------------------------------------------------------------------

if (!defined('SUPPORT_TICKET_STORAGE_PATH')) {
    define('SUPPORT_TICKET_STORAGE_PATH', __DIR__ . '/../storage/support_tickets.json');
}

if (!function_exists('load_support_tickets')) {
    function load_support_tickets(): array
    {
        $path = SUPPORT_TICKET_STORAGE_PATH;
        if (!is_readable($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('save_support_tickets')) {
    function save_support_tickets(array $tickets): bool
    {
        $dir = dirname(SUPPORT_TICKET_STORAGE_PATH);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
        }

        $encoded = json_encode($tickets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return false;
        }

        return @file_put_contents(SUPPORT_TICKET_STORAGE_PATH, $encoded, LOCK_EX) !== false;
    }
}

if (!function_exists('generate_support_ticket_id')) {
    function generate_support_ticket_id(): string
    {
        try {
            return 'ticket_' . bin2hex(random_bytes(4));
        } catch (Exception $exception) {
            return 'ticket_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        }
    }
}

if (!function_exists('enqueue_support_ticket')) {
    function enqueue_support_ticket(array $ticket): bool
    {
        $tickets = load_support_tickets();

        $record = [
            'id' => $ticket['id'] ?? generate_support_ticket_id(),
            'created_at' => $ticket['created_at'] ?? time(),
            'updated_at' => time(),
            'status' => $ticket['status'] ?? 'open',
            'issue_summary' => trim((string)($ticket['issue_summary'] ?? '')),
            'preferred_contact' => trim((string)($ticket['preferred_contact'] ?? '')),
            'customer' => [
                'id' => $ticket['customer']['id'] ?? null,
                'name' => trim((string)($ticket['customer']['name'] ?? '')),
                'email' => trim((string)($ticket['customer']['email'] ?? '')),
            ],
            'conversation' => is_array($ticket['conversation'] ?? null) ? array_values($ticket['conversation']) : [],
            'order_context' => is_array($ticket['order_context'] ?? null) ? $ticket['order_context'] : [],
            'chat_log_id' => $ticket['chat_log_id'] ?? '',
            'channels' => is_array($ticket['channels'] ?? null) ? $ticket['channels'] : [],
            'source' => $ticket['source'] ?? 'checkout_support',
        ];

        $tickets[] = $record;
        return save_support_tickets($tickets);
    }
}

if (!function_exists('get_support_tickets')) {
    function get_support_tickets(?string $statusFilter = null, int $limit = 50): array
    {
        $tickets = load_support_tickets();

        usort($tickets, function (array $a, array $b): int {
            return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
        });

        if ($statusFilter !== null) {
            $statusFilter = strtolower($statusFilter);
            $tickets = array_filter($tickets, function (array $ticket) use ($statusFilter): bool {
                return strtolower($ticket['status'] ?? 'open') === $statusFilter;
            });
        }

        if ($limit > 0) {
            $tickets = array_slice($tickets, 0, $limit);
        }

        return array_values($tickets);
    }
}

if (!function_exists('resolve_support_ticket')) {
    function resolve_support_ticket(string $ticketId, int $adminId = 0): bool
    {
        $tickets = load_support_tickets();
        $updated = false;

        foreach ($tickets as &$ticket) {
            if (($ticket['id'] ?? '') === $ticketId) {
                if (strtolower($ticket['status'] ?? 'open') === 'resolved') {
                    return true;
                }
                $ticket['status'] = 'resolved';
                $ticket['resolved_at'] = time();
                $ticket['resolved_by'] = $adminId;
                $ticket['updated_at'] = time();
                $updated = true;
                break;
            }
        }
        unset($ticket);

        return $updated ? save_support_tickets($tickets) : false;
    }
}

if (!function_exists('count_open_support_tickets')) {
    function count_open_support_tickets(): int
    {
        $tickets = load_support_tickets();
        $count = 0;
        foreach ($tickets as $ticket) {
            if (strtolower($ticket['status'] ?? 'open') === 'open') {
                $count++;
            }
        }
        return $count;
    }
}
