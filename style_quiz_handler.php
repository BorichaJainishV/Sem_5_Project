<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Unsupported request method.'
    ]);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    echo json_encode([
        'success' => false,
        'message' => 'We could not read your quiz answers. Please try again.'
    ]);
    exit();
}

$style = $payload['style'] ?? null;
$palette = $payload['palette'] ?? null;
$goal = $payload['goal'] ?? null;
$source = $payload['source'] ?? 'shop_quiz';

$validSources = ['shop_quiz', 'inbox_flow', 'account_prompt', 'spotlight'];
if (!in_array($source, $validSources, true)) {
    $source = 'shop_quiz';
}

$validStyles = ['street', 'minimal', 'bold'];
$validPalettes = ['monochrome', 'earth', 'vivid'];
$validGoals = ['everyday', 'launch', 'gift'];

if (!in_array($style, $validStyles, true) ||
    !in_array($palette, $validPalettes, true) ||
    !in_array($goal, $validGoals, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please answer each question so we can tailor your bundle.'
    ]);
    exit();
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    echo json_encode([
        'success' => false,
        'message' => 'For security reasons we need you to refresh and try again.'
    ]);
    exit();
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/core/style_quiz_helpers.php';

seedInventoryQuizMetadata($conn);

[$personaLabel, $personaSummary] = derivePersonaLabel($style, $palette, $goal);
$inventoryMetadata = loadInventoryQuizMetadata($conn);
$recommendations = buildRecommendations($conn, $style, $palette, $goal, $inventoryMetadata);
$submittedAt = date('Y-m-d H:i:s');

$customerId = $_SESSION['customer_id'] ?? null;
$accountSynced = false;
$recommendationsJson = json_encode($recommendations, JSON_UNESCAPED_SLASHES);

$_SESSION['style_quiz_last_result'] = [
    'persona_label' => $personaLabel,
    'persona_summary' => $personaSummary,
    'style' => $style,
    'palette' => $palette,
    'goal' => $goal,
    'recommendations' => $recommendations,
    'source' => $source,
    'captured_at' => $submittedAt,
];

if ($customerId) {
    ensureStyleQuizResultsTable($conn);
    $stmt = $conn->prepare('INSERT INTO style_quiz_results (customer_id, style_choice, palette_choice, goal_choice, persona_label, persona_summary, recommendations_json, source_label, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE style_choice = VALUES(style_choice), palette_choice = VALUES(palette_choice), goal_choice = VALUES(goal_choice), persona_label = VALUES(persona_label), persona_summary = VALUES(persona_summary), recommendations_json = VALUES(recommendations_json), source_label = VALUES(source_label), submitted_at = VALUES(submitted_at), updated_at = CURRENT_TIMESTAMP');

    if ($stmt) {
        $stmt->bind_param('issssssss', $customerId, $style, $palette, $goal, $personaLabel, $personaSummary, $recommendationsJson, $source, $submittedAt);
        if ($stmt->execute()) {
            $accountSynced = true;
        }
        $stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'personaLabel' => $personaLabel,
    'personaSummary' => $personaSummary,
    'recommendations' => $recommendations,
    'accountSynced' => $accountSynced,
    'source' => $source,
    'submittedAt' => $submittedAt,
]);
