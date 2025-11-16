<?php
// ---------------------------------------------------------------------
// /admin/products.php - Admin Product Management (CRUD)
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
if (!isset($_SESSION['admin_id'])) { header('Location: index.php'); exit(); }
include '../db_connection.php';
require_once 'activity_logger.php';
require_once '../core/style_quiz_helpers.php';

ensureInventoryQuizMetadataTable($conn);

$styleVocabulary = [
    'street' => 'Urban Creator (street)',
    'minimal' => 'Clean Classic (minimal)',
    'bold' => 'Statement Maker (bold)',
];

$paletteVocabulary = [
    'monochrome' => 'Monochrome Sleek',
    'earth' => 'Earthy Balance',
    'vivid' => 'Vivid Gradient',
];

$goalVocabulary = [
    'everyday' => 'Dialed Everyday Rotation',
    'launch' => 'Launch-ready Merch Drop',
    'gift' => 'Premium Gift Kit',
];

$defaultStyleKey = 'street';
if (!isset($styleVocabulary[$defaultStyleKey])) {
    $styleKeys = array_keys($styleVocabulary);
    $defaultStyleKey = $styleKeys ? (string)$styleKeys[0] : '';
}

$defaultPaletteKey = 'monochrome';
if (!isset($paletteVocabulary[$defaultPaletteKey])) {
    $paletteKeys = array_keys($paletteVocabulary);
    $defaultPaletteKey = $paletteKeys ? (string)$paletteKeys[0] : '';
}

$defaultGoalKey = 'everyday';
if (!isset($goalVocabulary[$defaultGoalKey])) {
    $goalKeys = array_keys($goalVocabulary);
    $defaultGoalKey = $goalKeys ? (string)$goalKeys[0] : '';
}

if (!function_exists('fetch_inventory_tag_snapshot')) {
    function fetch_inventory_tag_snapshot(mysqli $conn, int $inventoryId): ?array
    {
        $stmt = $conn->prepare('SELECT style_tags, palette_tags, goal_tags FROM inventory_quiz_tags WHERE inventory_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $inventoryId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('extract_primary_tag')) {
    function extract_primary_tag(?string $tags, string $fallback): string
    {
        if (!$tags) {
            return $fallback;
        }
        $parts = preg_split('/[,|]/', $tags);
        if (!is_array($parts)) {
            return $fallback;
        }
        foreach ($parts as $part) {
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                return $value;
            }
        }
        return $fallback;
    }
}

if (!function_exists('save_inventory_tag_snapshot')) {
    function save_inventory_tag_snapshot(mysqli $conn, int $inventoryId, string $styleTag, string $paletteTag, string $goalTag, int $adminId): void
    {
        $before = fetch_inventory_tag_snapshot($conn, $inventoryId) ?: ['style_tags' => null, 'palette_tags' => null, 'goal_tags' => null];
        $stmt = $conn->prepare('INSERT INTO inventory_quiz_tags (inventory_id, style_tags, palette_tags, goal_tags) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE style_tags = VALUES(style_tags), palette_tags = VALUES(palette_tags), goal_tags = VALUES(goal_tags)');
        if ($stmt) {
            $stmt->bind_param('isss', $inventoryId, $styleTag, $paletteTag, $goalTag);
            if ($stmt->execute()) {
                $after = ['style_tags' => $styleTag, 'palette_tags' => $paletteTag, 'goal_tags' => $goalTag];
                if ($before['style_tags'] !== $after['style_tags'] || $before['palette_tags'] !== $after['palette_tags'] || $before['goal_tags'] !== $after['goal_tags']) {
                    $action = ($before['style_tags'] === null && $before['palette_tags'] === null && $before['goal_tags'] === null) ? 'product_tags_created' : 'product_tags_updated';
                    log_admin_activity($adminId, $action, [
                        'inventory_id' => $inventoryId,
                        'before' => $before,
                        'after' => $after,
                    ]);
                }
            }
            $stmt->close();
        }
    }
}

if (!function_exists('fetch_product_snapshot')) {
    function fetch_product_snapshot(mysqli $conn, int $inventoryId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT inventory_id, product_name, stock_qty, material_type, price, image_url, COALESCE(is_archived, 0) AS is_archived\n             FROM inventory\n             WHERE inventory_id = ?"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $inventoryId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('diff_product_snapshots')) {
    function diff_product_snapshots(array $before, array $after): array
    {
        $fieldsToCheck = ['product_name', 'price', 'stock_qty', 'material_type', 'image_url', 'is_archived'];
        $changes = [];

        foreach ($fieldsToCheck as $field) {
            $previous = $before[$field] ?? null;
            $current = $after[$field] ?? null;
            if ($previous === $current) {
                continue;
            }

            if ((string)$previous === (string)$current) {
                continue;
            }

            $changes[$field] = [
                'from' => $previous,
                'to' => $current,
            ];
        }

        return $changes;
    }
}

$uploadError = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

$productError = $_SESSION['product_error'] ?? null;
unset($_SESSION['product_error']);

// Ensure we have an archive flag; run once and reuse moving forward.
$hasArchiveColumn = false;
$archivedColumnCheck = $conn->query("SHOW COLUMNS FROM inventory LIKE 'is_archived'");
if ($archivedColumnCheck) {
    if ($archivedColumnCheck->num_rows > 0) {
        $hasArchiveColumn = true;
    } else {
        if ($conn->query("ALTER TABLE inventory ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0")) {
            $hasArchiveColumn = true;
        }
    }
    $archivedColumnCheck->free();
}


// Handle POST: add or update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    $csrfPost = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfPost)) {
        $_SESSION['upload_error'] = 'Invalid CSRF token.';
        header('Location: products.php');
        exit();
    }
    $normalizeImagePath = function($rawPath) {
        $p = trim((string)$rawPath);
        $p = trim($p, " \"'\t\n\r");
        $p = str_replace('\\\\', '/', $p);
        $p = str_replace('\\\'', '/', $p);
        $p = str_replace('\\', '/', $p);
        if (preg_match('#([^/]+)$#', $p, $m)) {
            $filename = $m[1];
            if (strpos($p, 'image/') === 0) { return $p; }
            return 'image/' . $filename;
        }
        return $p ?: 'image/placeholder.png';
    };

    // --- SECURE FILE UPLOAD LOGIC ---
    $uploadedTo = null;
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image_file'];
        
        // 0. Max file size (e.g., 5 MB)
        $maxBytes = 5 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $_SESSION['upload_error'] = "File too large. Max 5 MB allowed.";
        }
        
        // 1. Whitelist File Extensions
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($_SESSION['upload_error']) && !in_array($extension, $allowedExtensions)) {
            $_SESSION['upload_error'] = "Invalid file type. Only JPG, PNG, and WEBP are allowed.";
        } else if (!isset($_SESSION['upload_error'])) {
            // 2. Verify MIME Type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $_SESSION['upload_error'] = "Invalid file content. The uploaded file is not a valid image.";
            } else {
                // 2b. Validate image dimensions using getimagesize
                $imgInfo = @getimagesize($file['tmp_name']);
                if ($imgInfo === false) {
                    $_SESSION['upload_error'] = "Uploaded file is not a valid image.";
                } else if ($imgInfo[0] <= 0 || $imgInfo[1] <= 0) {
                    $_SESSION['upload_error'] = "Invalid image dimensions.";
                }
            }
            if (!isset($_SESSION['upload_error'])) {
                // 3. Generate a Random, Secure Filename
                $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
                $target = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . $safeName;

                // 4. Move the file
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $uploadedTo = 'image/' . $safeName;
                } else {
                    $_SESSION['upload_error'] = "Failed to move uploaded file.";
                }
            }
        }
    }
    // --- END SECURE FILE UPLOAD LOGIC ---
    
    // Redirect immediately if there was an upload error
    if (isset($_SESSION['upload_error'])) {
        header('Location: products.php');
        exit();
    }

    $pname = $_POST['product_name'];
    $qty = (int)$_POST['stock_qty'];
    $mat = $_POST['material_type'];
    $price = (float)$_POST['price'];
    // Use the newly uploaded file if it exists, otherwise use the text input URL.
    $imgInput = $uploadedTo ?: $_POST['image_url'];
    $img = $normalizeImagePath($imgInput);

    $styleSelection = strtolower(trim($_POST['style_tag'] ?? ''));
    if (!isset($styleVocabulary[$styleSelection])) {
        $styleSelection = $defaultStyleKey;
    }

    $paletteSelection = strtolower(trim($_POST['palette_tag'] ?? ''));
    if (!isset($paletteVocabulary[$paletteSelection])) {
        $paletteSelection = $defaultPaletteKey;
    }

    $goalSelection = strtolower(trim($_POST['goal_tag'] ?? ''));
    if (!isset($goalVocabulary[$goalSelection])) {
        $goalSelection = $defaultGoalKey;
    }

    if (isset($_POST['add_product'])) {
        $stmt = $conn->prepare("INSERT INTO inventory (product_name, stock_qty, material_type, price, image_url) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sisds", $pname, $qty, $mat, $price, $img);
            if ($stmt->execute()) {
                $newProductId = $stmt->insert_id;
                $snapshot = fetch_product_snapshot($conn, $newProductId);
                log_admin_activity((int)$_SESSION['admin_id'], 'product_created', [
                    'inventory_id' => $newProductId,
                    'product_name' => $snapshot['product_name'] ?? $pname,
                    'price' => isset($snapshot['price']) ? (float)$snapshot['price'] : $price,
                    'stock_qty' => isset($snapshot['stock_qty']) ? (int)$snapshot['stock_qty'] : $qty,
                ]);
                if ($newProductId > 0) {
                    save_inventory_tag_snapshot($conn, $newProductId, $styleSelection, $paletteSelection, $goalSelection, (int)$_SESSION['admin_id']);
                }
            }
            $stmt->close();
        }
    }
    if (isset($_POST['update_product']) && isset($_POST['inventory_id'])) {
        $id = (int)$_POST['inventory_id'];
        $beforeSnapshot = fetch_product_snapshot($conn, $id);
        // If the user is updating but didn't provide a new image, keep the old one.
        $updateSucceeded = false;
        if (empty($img)) {
            $stmt = $conn->prepare("UPDATE inventory SET product_name = ?, stock_qty = ?, material_type = ?, price = ? WHERE inventory_id = ?");
            $stmt->bind_param("sisdi", $pname, $qty, $mat, $price, $id);
        } else {
            $stmt = $conn->prepare("UPDATE inventory SET product_name = ?, stock_qty = ?, material_type = ?, price = ?, image_url = ? WHERE inventory_id = ?");
            $stmt->bind_param("sisdsi", $pname, $qty, $mat, $price, $img, $id);
        }
        if ($stmt && $stmt->execute()) {
            $updateSucceeded = true;
            if ($beforeSnapshot) {
                $afterSnapshot = fetch_product_snapshot($conn, $id);
                if ($afterSnapshot) {
                    $changes = diff_product_snapshots($beforeSnapshot, $afterSnapshot);
                    if (!empty($changes)) {
                        log_admin_activity((int)$_SESSION['admin_id'], 'product_updated', [
                            'inventory_id' => $id,
                            'product_name' => $afterSnapshot['product_name'] ?? $pname,
                            'changes' => $changes,
                        ]);
                    }
                }
            }
        }
        if ($stmt) {
            $stmt->close();
        }
        if ($updateSucceeded && !empty($id)) {
            save_inventory_tag_snapshot($conn, $id, $styleSelection, $paletteSelection, $goalSelection, (int)$_SESSION['admin_id']);
        }
    }
    header('Location: products.php');
    exit();
}


// Handle delete via GET
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $beforeSnapshot = fetch_product_snapshot($conn, $id);

    // Prevent deleting inventory entries that are still referenced by orders.
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS order_count FROM orders WHERE inventory_id = ?"
    );
    $countStmt->bind_param("i", $id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $orderCountRow = $countResult ? $countResult->fetch_assoc() : ['order_count' => 0];
    $countStmt->close();

    if ((int)$orderCountRow['order_count'] > 0) {
        if ($hasArchiveColumn) {
            $archiveStmt = $conn->prepare(
                "UPDATE inventory SET is_archived = 1 WHERE inventory_id = ?"
            );
            $archiveStmt->bind_param("i", $id);
            if ($archiveStmt->execute()) {
                $_SESSION['product_error'] = 'Product has associated orders and was archived instead of deleted.';
                if ($beforeSnapshot) {
                    $afterSnapshot = fetch_product_snapshot($conn, $id);
                    if ($afterSnapshot) {
                        $changes = diff_product_snapshots($beforeSnapshot, $afterSnapshot);
                        log_admin_activity((int)$_SESSION['admin_id'], 'product_archived', [
                            'inventory_id' => $id,
                            'product_name' => $beforeSnapshot['product_name'] ?? '',
                            'changes' => $changes,
                        ]);
                    }
                }
            } else {
                $_SESSION['product_error'] = 'Unable to update product status. Please try again later.';
            }
            $archiveStmt->close();
        } else {
            $_SESSION['product_error'] = 'Unable to delete this product because it has existing orders.';
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM inventory WHERE inventory_id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            $_SESSION['product_error'] = 'Product could not be deleted. Please try again later.';
        } else {
            if ($beforeSnapshot) {
                log_admin_activity((int)$_SESSION['admin_id'], 'product_deleted', [
                    'inventory_id' => $id,
                    'product_name' => $beforeSnapshot['product_name'] ?? '',
                    'price' => $beforeSnapshot['price'] ?? null,
                    'stock_qty' => $beforeSnapshot['stock_qty'] ?? null,
                ]);
            }
        }
        $stmt->close();
    }

    header('Location: products.php');
    exit();
}

// If editing, load the product
$editing = false;
$editProduct = null;
$editTagSnapshot = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE inventory_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editProduct = $stmt->get_result()->fetch_assoc();
    if ($editProduct) {
        $editing = true;
        $editTagSnapshot = fetch_inventory_tag_snapshot($conn, (int)$editProduct['inventory_id']);
    }
}

$currentStyleSelection = $defaultStyleKey;
$currentPaletteSelection = $defaultPaletteKey;
$currentGoalSelection = $defaultGoalKey;

if ($editing) {
    $currentStyleSelection = extract_primary_tag($editTagSnapshot['style_tags'] ?? '', $defaultStyleKey);
    $currentPaletteSelection = extract_primary_tag($editTagSnapshot['palette_tags'] ?? '', $defaultPaletteKey);
    $currentGoalSelection = extract_primary_tag($editTagSnapshot['goal_tags'] ?? '', $defaultGoalKey);
}

$productQuery = $hasArchiveColumn
    ? "SELECT * FROM inventory WHERE is_archived = 0 ORDER BY product_name"
    : "SELECT * FROM inventory ORDER BY product_name";
$products = $conn->query($productQuery);
$inventoryTagLookup = loadInventoryQuizMetadata($conn);
?>
<?php $ADMIN_TITLE = 'Manage Products'; require_once __DIR__ . '/_header.php'; ?>
<?php $ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50'; ?>
<?php $ADMIN_TITLE = 'Manage Products'; require_once __DIR__ . '/_header.php'; ?>
<div class="flex-1 p-10">
        <h1 class="text-3xl font-bold mb-6 text-white">Manage Products</h1>
        
        <?php if ($uploadError): ?>
        <div class="bg-red-500/20 border border-red-300/40 text-red-50 px-4 py-3 rounded relative mb-4" role="alert" aria-live="polite">
            <strong class="font-bold">Upload Error:</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($uploadError); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($productError): ?>
        <div class="bg-yellow-400/20 border border-yellow-200/30 text-yellow-50 px-4 py-3 rounded relative mb-4" role="alert" aria-live="polite">
                    <strong class="font-bold">Action Blocked:</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($productError); ?></span>
                </div>
                <?php endif; ?>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-md mb-8 backdrop-blur">
            <h2 class="text-2xl font-bold mb-4 text-white"><?php echo $editing ? 'Edit Product' : 'Add New Product'; ?></h2>
            <form method="POST" action="products.php" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <?php if ($editing): ?><input type="hidden" name="inventory_id" value="<?php echo (int)$editProduct['inventory_id']; ?>"><?php endif; ?>
                <input type="text" name="product_name" placeholder="Product Name" class="px-4 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo $editing ? htmlspecialchars($editProduct['product_name']) : ''; ?>" required>
                <input type="number" name="stock_qty" placeholder="Stock Quantity" class="px-4 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo $editing ? (int)$editProduct['stock_qty'] : ''; ?>" required>
                <input type="text" name="material_type" placeholder="Material Type" class="px-4 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo $editing ? htmlspecialchars($editProduct['material_type']) : ''; ?>">
                <input type="number" step="0.01" name="price" placeholder="Price" class="px-4 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo $editing ? htmlspecialchars($editProduct['price']) : ''; ?>" required>
                <div>
                    <label for="style_tag" class="block text-sm font-medium text-indigo-100">Style Tag</label>
                    <select name="style_tag" id="style_tag" class="mt-1 block w-full px-4 py-2 border border-white/20 bg-white/10 text-white rounded" required>
                        <?php foreach ($styleVocabulary as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $currentStyleSelection === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="palette_tag" class="block text-sm font-medium text-indigo-100">Palette Tag</label>
                    <select name="palette_tag" id="palette_tag" class="mt-1 block w-full px-4 py-2 border border-white/20 bg-white/10 text-white rounded" required>
                        <?php foreach ($paletteVocabulary as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $currentPaletteSelection === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="goal_tag" class="block text-sm font-medium text-indigo-100">Goal Tag</label>
                    <select name="goal_tag" id="goal_tag" class="mt-1 block w-full px-4 py-2 border border-white/20 bg-white/10 text-white rounded" required>
                        <?php foreach ($goalVocabulary as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $currentGoalSelection === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label for="image_url" class="block text-sm font-medium text-indigo-100">Image Path or URL</label>
                    <input type="text" name="image_url" id="image_url" placeholder="e.g., image/filename.jpg" class="mt-1 block w-full px-4 py-2 border border-white/20 bg-white/10 text-white rounded-md placeholder:text-indigo-200" value="<?php echo $editing ? htmlspecialchars($editProduct['image_url']) : ''; ?>">
                    <?php if ($editing && $editProduct['image_url']): ?>
                        <div class="mt-2">
                            <span class="text-sm text-indigo-100/80">Current image:</span>
                            <img src="../<?php echo htmlspecialchars($editProduct['image_url']); ?>" alt="Current product image" class="h-16 w-16 object-cover rounded-md border mt-1">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-span-1 md:col-span-2">
                     <label for="image_file" class="block text-sm font-medium text-indigo-100">Or Upload New Image (Overrides Path)</label>
                    <input type="file" name="image_file" id="image_file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-indigo-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-500/20 file:text-white hover:file:bg-indigo-500/30">
                </div>
                <?php if ($editing): ?>
                    <div class="col-span-1 md:col-span-2 flex gap-3">
                        <button type="submit" name="update_product" class="bg-indigo-500 hover:bg-indigo-600 transition text-white py-2 px-4 rounded">Update Product</button>
                        <a href="products.php" class="py-2 px-4 rounded border border-white/20 hover:bg-white/10 transition">Cancel</a>
                    </div>
                <?php else: ?>
                    <button type="submit" name="add_product" class="bg-indigo-500 hover:bg-indigo-600 transition text-white py-2 px-4 rounded">Add Product</button>
                <?php endif; ?>
            </form>
        </div>
        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-md backdrop-blur">
            <table class="min-w-full">
                <thead><tr><th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Name</th><th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Stock</th><th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Price</th><th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Persona Tags</th><th class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-left text-indigo-100">Actions</th></tr></thead>
                <tbody>
                    <?php while ($row = $products->fetch_assoc()): ?>
                    <tr>
                        <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-white"><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-white">
                            <?php echo htmlspecialchars($row['stock_qty']); ?>
                            <?php if ((int)$row['stock_qty'] <= 10): ?>
                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Low Stock
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10 text-white">₹<?php echo htmlspecialchars($row['price']); ?></td>
                        <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10">
                            <?php
                                $tagMeta = $inventoryTagLookup[(int)$row['inventory_id']] ?? null;
                                $styleKey = $tagMeta['style'][0] ?? null;
                                $paletteKey = $tagMeta['palette'][0] ?? null;
                                $goalKey = $tagMeta['goal'][0] ?? null;
                                $tagBadges = [];
                                if ($styleKey && isset($styleVocabulary[$styleKey])) {
                                    $tagBadges[] = ['label' => $styleVocabulary[$styleKey], 'tone' => 'bg-indigo-500/20 text-indigo-100'];
                                }
                                if ($paletteKey && isset($paletteVocabulary[$paletteKey])) {
                                    $tagBadges[] = ['label' => $paletteVocabulary[$paletteKey], 'tone' => 'bg-emerald-500/20 text-emerald-100'];
                                }
                                if ($goalKey && isset($goalVocabulary[$goalKey])) {
                                    $tagBadges[] = ['label' => $goalVocabulary[$goalKey], 'tone' => 'bg-amber-500/20 text-amber-100'];
                                }
                            ?>
                            <?php if (empty($tagBadges)): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-500/20 text-red-100">Needs tags</span>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($tagBadges as $badge): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $badge['tone']; ?>"><?php echo htmlspecialchars($badge['label']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 sm:px-4 sm:py-3 border-b border-white/10">
                            <a href="products.php?action=edit&id=<?php echo (int)$row['inventory_id']; ?>" class="text-indigo-200 hover:text-indigo-100">Edit</a>
                            <a href="products.php?action=delete&id=<?php echo (int)$row['inventory_id']; ?>" class="text-red-200 hover:text-red-100 ml-3" onclick="return confirm('Delete this product?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>