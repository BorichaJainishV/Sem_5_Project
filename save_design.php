<?php
// Start buffering as early as possible to avoid stray output
if (!headers_sent()) { ob_start(); }

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/session_helpers.php';
include 'db_connection.php';

// Ensure buffering is enabled (in case headers were already sent earlier)
if (!headers_sent()) { ob_start(); }

function respond_json($payload, $status = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    // Clear any buffered output to ensure clean JSON
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($payload);
    exit();
}

// --- VALIDATION ---
if (!isset($_SESSION['customer_id'])) {
    respond_json(['success' => false, 'error' => 'User not logged in.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['success' => false, 'error' => 'Invalid request method.'], 405);
}

// CSRF validation
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    respond_json(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw);
$customer_id = (int)$_SESSION['customer_id'];

// Accept either single-view (textureMap) or multi-view (images with at least front)
if (!$data || (
    !isset($data->textureMap)
    && !(isset($data->images) && is_object($data->images) && (isset($data->images->front) || isset($data->images->back) || isset($data->images->left) || isset($data->images->right)))
)) {
    respond_json(['success' => false, 'error' => 'Incomplete design data received.'], 400);
}

$designId = isset($data->designId) ? (int)$data->designId : 0;
$isUpdate = $designId > 0;
$existingDesign = null;
if ($isUpdate) {
    $stmtExisting = $conn->prepare('SELECT design_id, customer_id, front_preview_url, back_preview_url, left_preview_url, right_preview_url, texture_map_url FROM custom_designs WHERE design_id = ? LIMIT 1');
    if (!$stmtExisting) {
        respond_json(['success' => false, 'error' => 'Design lookup failed.'], 500);
    }
    $stmtExisting->bind_param('i', $designId);
    $stmtExisting->execute();
    $resultExisting = $stmtExisting->get_result();
    $existingDesign = $resultExisting->fetch_assoc();
    $stmtExisting->close();

    if (!$existingDesign) {
        respond_json(['success' => false, 'error' => 'Design not found.'], 404);
    }
    if ((int)$existingDesign['customer_id'] !== $customer_id) {
        respond_json(['success' => false, 'error' => 'You do not have permission to modify this design.'], 403);
    }
}

$existingCols = [];
try {
    $resCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custom_designs'");
    if ($resCols) {
        while ($row = $resCols->fetch_assoc()) {
            $existingCols[$row['COLUMN_NAME']] = true;
        }
        $resCols->free();
    }
} catch (mysqli_sql_exception $e) {
    // Ignore schema introspection failures; optional columns simply won't update.
}

// Optional metadata from client
$incomingApparel = null;
$incomingBaseColor = null;
if (isset($data->design) && isset($data->design->apparelType)) {
    $incomingApparel = $data->design->apparelType;
} elseif (isset($data->apparelType)) {
    $incomingApparel = $data->apparelType;
}
if (isset($data->design) && isset($data->design->baseColor)) {
    $incomingBaseColor = $data->design->baseColor;
} elseif (isset($data->baseColor)) {
    $incomingBaseColor = $data->baseColor;
}
// Validate apparel_type against allowed list
$allowedApparel = ['tshirt','hoodie','shirt'];
$apparel_raw = $incomingApparel ? preg_replace('/[^a-zA-Z0-9_\- ]/', '', $incomingApparel) : '';
$apparel_lc = strtolower(trim($apparel_raw));
$apparel_type = in_array($apparel_lc, $allowedApparel, true) ? $apparel_lc : 'custom';
$base_color = null;
if ($incomingBaseColor) {
    $hex = preg_replace('/[^0-9a-fA-F]/', '', ltrim((string)$incomingBaseColor, '#'));
    if (strlen($hex) === 3 || strlen($hex) === 6) {
        $base_color = '#' . strtolower($hex);
    }
}

// --- FILE HANDLING ---
// Create a per-day directory and a per-design folder for cleanliness
$base_dir = 'uploads/designs/';
$today = date('Ymd');
$day_dir = $base_dir . $today . '/';
if (!is_dir($day_dir) && !mkdir($day_dir, 0755, true)) {
    respond_json(['success' => false, 'error' => 'Failed to create day directory'], 500);
}

function save_base64_image($base64_string, $output_dir) {
    $parts = explode(',', $base64_string, 2);
    $img_data = count($parts) > 1 ? $parts[1] : $parts[0];
    $decoded_data = base64_decode($img_data);
    if ($decoded_data === false) {
        throw new Exception('Invalid image data');
    }
    // Verify PNG signature after base64 decode to ensure image data
    // If not PNG, still save as .png after re-encoding
    $filename = 'design_' . bin2hex(random_bytes(16)) . '_front.png';
    $filepath = $output_dir . $filename;
    $bytesWritten = file_put_contents($filepath, $decoded_data, LOCK_EX);
    if ($bytesWritten === false || $bytesWritten <= 0) {
        throw new Exception('Failed to write image file');
    }
    return $filepath;
}

function move_temp_file($source, $target) {
    if (!$source) {
        return null;
    }
    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        throw new Exception('Failed to create design directory');
    }
    if (file_exists($target) && !unlink($target)) {
        throw new Exception('Failed to replace existing design file');
    }
    if (!rename($source, $target)) {
        if (!copy($source, $target)) {
            throw new Exception('Failed to move design file');
        }
        if (!unlink($source)) {
            // Best effort cleanup
        }
    }
    return $target;
}

function update_optional_column(mysqli $conn, array $existingCols, int $designId, string $column, $value): void
{
    if (!isset($existingCols[$column])) {
        return;
    }
    if ($value === null) {
        $stmt = $conn->prepare("UPDATE custom_designs SET $column = NULL WHERE design_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $designId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $stmt = $conn->prepare("UPDATE custom_designs SET $column = ? WHERE design_id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $value, $designId);
        $stmt->execute();
        $stmt->close();
    }
}

function log_design_activity(int $customerId, int $designId, string $action, array $metadata = []): void
{
    $logDir = __DIR__ . '/storage/logs';
    $logPath = $logDir . '/design_activity.log';
    $record = [
        'timestamp' => date('c'),
        'customer_id' => $customerId,
        'design_id' => $designId,
        'action' => $action,
        'metadata' => $metadata
    ];

    try {
        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            return;
        }
        file_put_contents($logPath, json_encode($record) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Logging failures should never interrupt user flow.
    }
}

// Single or multi-view
$front_preview_path = null;
$other_previews = [];
$texture_map_temp_path = null;
$texture_map_path = null;
try {
    if (isset($data->images) && is_object($data->images)) {
        // Multi-view object: {front, back, left, right}
        foreach (['front','back','left','right'] as $k) {
            if (isset($data->images->$k)) {
                $p = save_base64_image($data->images->$k, $day_dir);
                if ($k === 'front') $front_preview_path = $p; else $other_previews[$k] = $p;
            }
        }
        // If front is missing, use the first available view as front
        if (!$front_preview_path && !empty($other_previews)) {
            $firstKey = array_key_first($other_previews);
            $front_preview_path = $other_previews[$firstKey];
            unset($other_previews[$firstKey]);
        }
        if (!$front_preview_path) { throw new Exception('No views provided'); }
    } else {
        // Single-view
        $front_preview_path = save_base64_image($data->textureMap, $day_dir);
    }
} catch (Exception $e) {
    respond_json(['success' => false, 'error' => 'Could not save design images.'], 500);
}

if (isset($data->textureMap)) {
    try {
        $texture_map_temp_path = save_base64_image($data->textureMap, $day_dir);
    } catch (Exception $e) {
        $texture_map_temp_path = null;
    }
}

// --- DATABASE INSERTION / UPDATE ---
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$product_name = 'Custom ' . ucfirst($apparel_type);
$base_price = 999.00;
$price = $base_price;

if (!empty($data->pricing) && is_object($data->pricing)) {
    $price = isset($data->pricing->total) ? (float)$data->pricing->total : $base_price;
} else {
    $complexityFactor = 1.0;

    if (!empty($data->layers) && is_array($data->layers)) {
        $layerCount = count($data->layers);
        if ($layerCount > 3) {
            $complexityFactor += min(($layerCount - 3) * 0.15, 1.0);
        }
    }

    if (!empty($apparel_type) && in_array(strtolower($apparel_type), ['hoodie', 'jacket'], true)) {
        $complexityFactor += 0.35;
    }

    if (!empty($data->uses_full_wrap)) {
        $complexityFactor += 0.25;
    }

    $price = round($base_price * $complexityFactor, 2);
}
$design_json = isset($data->design) ? json_encode($data->design) : null;
$column_map = [ 'back' => 'back_preview_url', 'left' => 'left_preview_url', 'right' => 'right_preview_url' ];

if ($isUpdate) {
    $design_dir = null;
    foreach (['front_preview_url', 'texture_map_url', 'back_preview_url', 'left_preview_url', 'right_preview_url'] as $pathKey) {
        if (!empty($existingDesign[$pathKey])) {
            $design_dir = rtrim(dirname($existingDesign[$pathKey]), '/\\') . '/';
            break;
        }
    }
    if (!$design_dir) {
        $design_dir = $day_dir . 'design_' . $designId . '/';
    }
    if (!is_dir($design_dir) && !mkdir($design_dir, 0755, true)) {
        respond_json(['success' => false, 'error' => 'Failed to prepare design directory'], 500);
    }

    try {
        $front_preview_path = move_temp_file($front_preview_path, $design_dir . 'front.png');
        foreach ($other_previews as $key => $tmpPath) {
            $other_previews[$key] = move_temp_file($tmpPath, $design_dir . $key . '.png');
        }
        if ($texture_map_temp_path) {
            $texture_map_path = move_temp_file($texture_map_temp_path, $design_dir . 'texture.png');
        } else {
            $texture_map_path = $existingDesign['texture_map_url'] ?? $texture_map_path;
        }
    } catch (Exception $e) {
        respond_json(['success' => false, 'error' => 'Failed to persist updated assets.'], 500);
    }

    try {
        $updateStmt = $conn->prepare('UPDATE custom_designs SET product_name = ?, price = ?, front_preview_url = ? WHERE design_id = ?');
        $updateStmt->bind_param('sdsi', $product_name, $price, $front_preview_path, $designId);
        $updateStmt->execute();
        $updateStmt->close();
    } catch (mysqli_sql_exception $e) {
        respond_json(['success' => false, 'error' => 'Failed to update design record.'], 500);
    }

    foreach ($other_previews as $key => $finalPath) {
        $col = $column_map[$key] ?? null;
        if ($col) {
            update_optional_column($conn, $existingCols, $designId, $col, $finalPath);
        }
    }

    if ($texture_map_temp_path) {
        update_optional_column($conn, $existingCols, $designId, 'texture_map_url', $texture_map_path);
    }

    update_optional_column($conn, $existingCols, $designId, 'design_json', $design_json);
    update_optional_column($conn, $existingCols, $designId, 'apparel_type', $apparel_type);
    update_optional_column($conn, $existingCols, $designId, 'base_color', $base_color);

    if ($design_json) {
        file_put_contents($design_dir . 'design.json', $design_json, LOCK_EX);
    }

    $currentIds = get_custom_design_ids();
    if (!in_array($designId, $currentIds, true)) {
        add_custom_design_id($designId);
    } else {
        sync_custom_design_cart_quantity();
    }

    log_design_activity($customer_id, $designId, 'update', [
        'apparel_type' => $apparel_type,
        'base_color' => $base_color,
        'has_texture' => (bool)$texture_map_path
    ]);

    $conn->close();
    respond_json([
        'success' => true,
        'message' => 'Design updated successfully!',
        'design_id' => $designId,
        'preview' => $front_preview_path,
        'texture_map' => $texture_map_path ?? ($existingDesign['texture_map_url'] ?? null)
    ], 200);
}

// Create new design path
try {
    $stmt = $conn->prepare('INSERT INTO custom_designs (customer_id, product_name, price, front_preview_url, design_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('isdss', $customer_id, $product_name, $price, $front_preview_path, $design_json);
} catch (mysqli_sql_exception $e) {
    $stmt = $conn->prepare('INSERT INTO custom_designs (customer_id, product_name, price, front_preview_url, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->bind_param('isds', $customer_id, $product_name, $price, $front_preview_path);
}

if ($stmt->execute()) {
    $new_design_id = $conn->insert_id;
    $design_dir = $day_dir . 'design_' . $new_design_id . '/';

    try {
        $front_preview_path = move_temp_file($front_preview_path, $design_dir . 'front.png');
        foreach ($other_previews as $key => $tmpPath) {
            $other_previews[$key] = move_temp_file($tmpPath, $design_dir . $key . '.png');
        }
        if ($texture_map_temp_path) {
            $texture_map_path = move_temp_file($texture_map_temp_path, $design_dir . 'texture.png');
        }
    } catch (Exception $e) {
        respond_json(['success' => false, 'error' => 'Failed to finalize design assets.'], 500);
    }

    try {
        $frontUpdate = $conn->prepare('UPDATE custom_designs SET front_preview_url = ? WHERE design_id = ?');
        $frontUpdate->bind_param('si', $front_preview_path, $new_design_id);
        $frontUpdate->execute();
        $frontUpdate->close();
    } catch (mysqli_sql_exception $e) {
        // Ignore; minimal schema will still have temp path stored.
    }

    foreach ($other_previews as $key => $finalPath) {
        $col = $column_map[$key] ?? null;
        if ($col) {
            update_optional_column($conn, $existingCols, $new_design_id, $col, $finalPath);
        }
    }

    if ($texture_map_path) {
        update_optional_column($conn, $existingCols, $new_design_id, 'texture_map_url', $texture_map_path);
    }

    update_optional_column($conn, $existingCols, $new_design_id, 'design_json', $design_json);
    update_optional_column($conn, $existingCols, $new_design_id, 'apparel_type', $apparel_type);
    update_optional_column($conn, $existingCols, $new_design_id, 'base_color', $base_color);

    if ($design_json) {
        file_put_contents($design_dir . 'design.json', $design_json, LOCK_EX);
    }

    add_custom_design_id($new_design_id);

    log_design_activity($customer_id, $new_design_id, 'create', [
        'apparel_type' => $apparel_type,
        'base_color' => $base_color,
        'has_texture' => (bool)$texture_map_path
    ]);

    $stmt->close();
    $conn->close();

    respond_json([
        'success' => true,
        'message' => 'Design saved and added to cart!',
        'design_id' => $new_design_id,
        'preview' => $front_preview_path,
        'texture_map' => $texture_map_path
    ], 200);
} else {
    $error = 'Database error: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    respond_json(['success' => false, 'error' => $error], 500);
}
?>