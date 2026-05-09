<?php
session_start();

// Guard: admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

require_once 'dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit();
}

// Collect POST data
$item_name      = trim($_POST['item_name']      ?? '');
$description    = trim($_POST['description']    ?? '');
$location_found = trim($_POST['location_found'] ?? '');
$date_found     = trim($_POST['date_found']     ?? '');
$status         = trim($_POST['status']         ?? 'unclaimed');
$reported_by    = $_SESSION['user_id'];

// Validation
if (empty($item_name) || empty($location_found) || empty($date_found)) {
    header('Location: admin.php?section=items&error=missing_fields');
    exit();
}

// Insert
// Handle image upload
$image_path = null;
if (!empty($_FILES['item_image']['name'])) {
    $upload_dir = __DIR__ . '/uploads/items/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed) && $_FILES['item_image']['size'] <= 5 * 1024 * 1024) {
        $filename = uniqid('item_', true) . '.' . $ext;
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_dir . $filename)) {
            $image_path = 'uploads/items/' . $filename;
        }
    }
}

$stmt = $conn->prepare("INSERT INTO item (item_name, description, image_path, location_found, date_found, status, reported_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssssi', $item_name, $description, $image_path, $location_found, $date_found, $status, $reported_by);

if ($stmt->execute()) {
    header('Location: admin.php?section=items&success=added');
} else {
    header('Location: admin.php?section=items&error=insert_failed');
}

$stmt->close();
$conn->close();
exit();
?>
