<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/plain');
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/core/style_quiz_helpers.php';

seedInventoryQuizMetadata($conn);
$inventoryMetadata = loadInventoryQuizMetadata($conn);
ensureStyleQuizResultsTable($conn);

$defaultStyle = 'minimal';
$defaultPalette = 'monochrome';
$defaultGoal = 'everyday';

$existingStmt = $conn->prepare('SELECT customer_id FROM style_quiz_results WHERE customer_id = ? LIMIT 1');
$upsertStmt = $conn->prepare('INSERT INTO style_quiz_results (customer_id, style_choice, palette_choice, goal_choice, persona_label, persona_summary, recommendations_json) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE style_choice = VALUES(style_choice), palette_choice = VALUES(palette_choice), goal_choice = VALUES(goal_choice), persona_label = VALUES(persona_label), persona_summary = VALUES(persona_summary), recommendations_json = VALUES(recommendations_json), updated_at = CURRENT_TIMESTAMP');

if (!$existingStmt || !$upsertStmt) {
    echo "Failed to prepare statements.\n";
    exit();
}

$customers = $conn->query('SELECT customer_id, name FROM customer');
if (!$customers) {
    echo "Unable to query customers.\n";
    exit();
}

$processed = 0;
$skipped = 0;

while ($row = $customers->fetch_assoc()) {
    $customerId = (int) ($row['customer_id'] ?? 0);
    if ($customerId <= 0) {
        continue;
    }

    $existingStmt->bind_param('i', $customerId);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    $exists = $existingResult && $existingResult->num_rows > 0;
    if ($existingResult) {
        $existingResult->free();
    }

    if ($exists) {
        $skipped++;
        continue;
    }

    [$personaLabel, $personaSummary] = derivePersonaLabel($defaultStyle, $defaultPalette, $defaultGoal);
    $recommendations = buildRecommendations($conn, $defaultStyle, $defaultPalette, $defaultGoal, $inventoryMetadata);
    $recommendationsJson = json_encode($recommendations, JSON_UNESCAPED_SLASHES);

    $upsertStmt->bind_param(
        'issssss',
        $customerId,
        $defaultStyle,
        $defaultPalette,
        $defaultGoal,
        $personaLabel,
        $personaSummary,
        $recommendationsJson
    );
    $upsertStmt->execute();
    $processed++;
}

$customers->free();
$existingStmt->close();
$upsertStmt->close();

echo "Style quiz migration complete.\n";
echo "Records inserted: {$processed}\n";
echo "Records skipped (already had quiz data): {$skipped}\n";
