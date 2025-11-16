<?php
// ---------------------------------------------------------------------
// drop_waitlist_enroll.php - API endpoint to capture drop waitlist sign-ups
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'status' => 'method_not_allowed',
        'message' => 'Use POST to join the waitlist.',
    ]);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$slug = $payload['slug'] ?? $payload['drop_slug'] ?? '';
$slug = strtolower(trim((string) $slug));
if ($slug === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'status' => 'invalid',
        'message' => 'Missing drop identifier.',
    ]);
    exit();
}

$csrfToken = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'status' => 'csrf_failed',
        'message' => 'Security check failed. Please refresh and try again.',
    ]);
    exit();
}

require_once __DIR__ . '/core/drop_waitlist.php';

$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';
$source = $payload['source'] ?? 'banner';
$context = $payload['context'] ?? [];

$result = record_waitlist_signup($slug, [
    'email' => $email,
    'name' => $name,
    'source' => $source,
    'context' => $context,
], [
    'rate_limit_window' => DROP_WAITLIST_RATE_LIMIT_WINDOW,
    'rate_limit_max' => DROP_WAITLIST_RATE_LIMIT_MAX,
]);

$status = $result['status'] ?? 'error';

switch ($status) {
    case 'stored':
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'status' => 'stored',
            'message' => 'You are on the list. We will reach out when the drop goes live.',
        ]);
        break;
    case 'exists':
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status' => 'exists',
            'message' => 'You are already on the waitlist for this drop.',
        ]);
        break;
    case 'rate_limited':
        http_response_code(429);
        $retryAfter = isset($result['retry_after']) ? (int) $result['retry_after'] : DROP_WAITLIST_RATE_LIMIT_WINDOW;
        header('Retry-After: ' . $retryAfter);
        echo json_encode([
            'success' => false,
            'status' => 'rate_limited',
            'retryAfter' => $retryAfter,
            'message' => $result['message'] ?? 'Please wait a moment before trying again.',
        ]);
        break;
    case 'invalid':
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'status' => 'invalid',
            'message' => $result['message'] ?? 'Please check your details and try again.',
        ]);
        break;
    default:
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $result['message'] ?? 'We could not save your request. Please try later.',
        ]);
        break;
}
