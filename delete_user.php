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

$user_id = (int) ($_POST['user_id'] ?? 0);

if ($user_id === 0) {
    header('Location: admin.php?section=users&error=invalid_id');
    exit();
}

// Don't allow deleting the admin account
$check = $conn->prepare("SELECT username FROM user WHERE user_id = ?");
$check->bind_param('i', $user_id);
$check->execute();
$check->bind_result($current_username);
$check->fetch();
$check->close();

if ($current_username === 'admin') {
    header('Location: admin.php?section=users&error=cannot_delete_admin');
    exit();
}

// Note: foreign key on item.reported_by is RESTRICT,
// so this will fail if the user has reported any items.
$stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
$stmt->bind_param('i', $user_id);

if ($stmt->execute()) {
    header('Location: admin.php?section=users&success=user_deleted');
} else {
    header('Location: admin.php?section=users&error=user_has_items');
}

$stmt->close();
$conn->close();
exit();
?>
