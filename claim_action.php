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
$action  = trim($_POST['action']   ?? ''); // 'approved' or 'rejected'

if ($item_id === 0 || !in_array($action, ['approved', 'rejected'])) {
    header('Location: admin.php?section=claims&error=invalid_claim');
    exit();
}

// If approved → also update item status to 'claimed'
// If rejected → item status stays as is
if ($action === 'approved') {
    $stmt = $conn->prepare(
        "UPDATE item
         SET claim_status = 'approved', status = 'claimed'
         WHERE item_id = ?"
    );
} else {
    $stmt = $conn->prepare(
        "UPDATE item
         SET claim_status = 'rejected'
         WHERE item_id = ?"
    );
}

$stmt->bind_param('i', $item_id);

if ($stmt->execute()) {
    $msg = $action === 'approved' ? 'claim_approved' : 'claim_rejected';
    header("Location: admin.php?section=claims&success=$msg");
} else {
    header('Location: admin.php?section=claims&error=claim_failed');
}

$stmt->close();
$conn->close();
exit();
?>
