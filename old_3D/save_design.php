<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connection.php';

header('Content-Type: application/json');

// --- VALIDATION ---
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'));

if (!$data || !isset($data->frontPreview) || !isset($data->backPreview) || !isset($data->textureMap)) {
    echo json_encode(['success' => false, 'error' => 'Incomplete design data received.']);
    exit();
}

// --- FILE HANDLING ---
$upload_dir = 'uploads/designs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function save_base64_image($base64_string, $output_dir) {
    list(, $img_data) = explode(',', $base64_string);
    $decoded_data = base64_decode($img_data);
    $filename = uniqid() . '.png';
    $filepath = $output_dir . $filename;
    file_put_contents($filepath, $decoded_data);
    return $filepath;
}

try {
    $front_preview_path = save_base64_image($data->frontPreview, $upload_dir);
    $back_preview_path = save_base64_image($data->backPreview, $upload_dir);
    $texture_map_path = save_base64_image($data->textureMap, $upload_dir);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Could not save design images.']);
    exit();
}

// --- DATABASE INSERTION into the new table ---
$customer_id = $_SESSION['customer_id'];
$product_name = "Custom 3D Designed T-Shirt";
$price = 1299.00; // Set your price for custom shirts here

$stmt = $conn->prepare(
    "INSERT INTO custom_designs (customer_id, product_name, price, front_preview_url, back_preview_url, texture_map_url) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("isdsss", $customer_id, $product_name, $price, $front_preview_path, $back_preview_path, $texture_map_path);

if ($stmt->execute()) {
    $new_design_id = $conn->insert_id;

    // --- CRITICAL FIX: ADD TO A DEDICATED CART SESSION ---
    if (!isset($_SESSION['custom_cart'])) {
        $_SESSION['custom_cart'] = [];
    }
    // Each custom design is unique, quantity is always 1.
    $_SESSION['custom_cart'][$new_design_id] = 1;

    echo json_encode(['success' => true, 'message' => 'Design saved and added to cart!', 'design_id' => $new_design_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

