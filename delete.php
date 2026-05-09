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

$item_id = (int) ($_POST['item_id'] ?? 0);

if ($item_id === 0) {
    header('Location: admin.php?section=items&error=invalid_id');
    exit();
}

$stmt = $conn->prepare("DELETE FROM item WHERE item_id = ?");
$stmt->bind_param('i', $item_id);

if ($stmt->execute()) {
    header('Location: admin.php?section=items&success=deleted');
} else {
    header('Location: admin.php?section=items&error=delete_failed');
}

$stmt->close();
$conn->close();
exit();
?>
