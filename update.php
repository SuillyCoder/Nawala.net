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
$item_id        = (int) ($_POST['item_id']        ?? 0);
$item_name      = trim($_POST['item_name']        ?? '');
$description    = trim($_POST['description']      ?? '');
$location_found = trim($_POST['location_found']   ?? '');
$date_found     = trim($_POST['date_found']       ?? '');
$status         = trim($_POST['status']           ?? 'unclaimed');

// Validation
if ($item_id === 0 || empty($item_name) || empty($location_found) || empty($date_found)) {
    header('Location: admin.php?section=items&error=missing_fields');
    exit();
}

// Update
$stmt = $conn->prepare(
    "UPDATE item
     SET item_name = ?, description = ?, location_found = ?, date_found = ?, status = ?
     WHERE item_id = ?"
);
$stmt->bind_param('sssssi', $item_name, $description, $location_found, $date_found, $status, $item_id);

if ($stmt->execute()) {
    header('Location: admin.php?section=items&success=updated');
} else {
    header('Location: admin.php?section=items&error=update_failed');
}

$stmt->close();
$conn->close();
exit();
?>
