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
$stmt = $conn->prepare(
    "INSERT INTO item (item_name, description, location_found, date_found, status, reported_by)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('sssssi', $item_name, $description, $location_found, $date_found, $status, $reported_by);

if ($stmt->execute()) {
    header('Location: admin.php?section=items&success=added');
} else {
    header('Location: admin.php?section=items&error=insert_failed');
}

$stmt->close();
$conn->close();
exit();
?>
