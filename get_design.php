<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/session_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$designId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($designId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid design id']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT design_id, customer_id, product_name, front_preview_url, back_preview_url, left_preview_url, right_preview_url, texture_map_url, design_json, apparel_type, base_color, created_at FROM custom_designs WHERE design_id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare statement: ' . $conn->error);
    }
    $stmt->bind_param('i', $designId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        if ((int)$row['customer_id'] !== (int)$_SESSION['customer_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You do not have access to this design']);
            exit;
        }

        $images = [];
        foreach (['front', 'back', 'left', 'right'] as $side) {
            $key = $side . '_preview_url';
            if (!empty($row[$key])) {
                $images[$side] = $row[$key];
            }
        }

        $textureMapUrl = $row['texture_map_url'] ?? null;
        if (!$textureMapUrl && !empty($row['front_preview_url'])) {
            $candidate = rtrim(dirname($row['front_preview_url']), '/\\') . '/texture.png';
            $filesystemPath = __DIR__ . '/' . ltrim(str_replace(['\\', '//'], '/', $candidate), '/');
            if (file_exists($filesystemPath)) {
                $textureMapUrl = $candidate;
            }
        }

        $payload = [
            'success' => true,
            'design_id' => (int)$row['design_id'],
            'product_name' => $row['product_name'],
            'images' => $images,
            'design_json' => $row['design_json'] ? json_decode($row['design_json'], true) : null,
            'texture_map_url' => $textureMapUrl,
            'apparel_type' => $row['apparel_type'] ?? null,
            'base_color' => $row['base_color'] ?? null,
            'created_at' => $row['created_at'],
        ];

        echo json_encode($payload);
        exit;
    }

    // Fallback for legacy orders stored in the generic designs table
    $legacyStmt = $conn->prepare('SELECT design_id, customer_id, design_file, design_file_back, design_type, created_at FROM designs WHERE design_id = ? LIMIT 1');
    if (!$legacyStmt) {
        throw new RuntimeException('Failed to prepare fallback statement: ' . $conn->error);
    }
    $legacyStmt->bind_param('i', $designId);
    $legacyStmt->execute();
    $legacyResult = $legacyStmt->get_result();
    $legacyRow = $legacyResult->fetch_assoc();
    $legacyStmt->close();

    if (!$legacyRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Design not found']);
        exit;
    }

    if ((int)$legacyRow['customer_id'] !== (int)$_SESSION['customer_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have access to this design']);
        exit;
    }

    $images = [];
    if (!empty($legacyRow['design_file']) && $legacyRow['design_file'] !== 'N/A') {
        $images['front'] = $legacyRow['design_file'];
    }
    if (!empty($legacyRow['design_file_back']) && $legacyRow['design_file_back'] !== 'N/A') {
        $images['back'] = $legacyRow['design_file_back'];
    }

    $fallbackTexture = $images['front'] ?? null;

    $payload = [
        'success' => true,
        'design_id' => (int)$legacyRow['design_id'],
        'product_name' => null,
        'images' => $images,
        'design_json' => null,
        'texture_map_url' => $fallbackTexture,
        'apparel_type' => $legacyRow['design_type'] ?? null,
        'base_color' => null,
        'created_at' => $legacyRow['created_at'] ?? null,
    ];

    echo json_encode($payload);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    error_log('get_design.php failed: ' . $e->getMessage());
    exit;
}
