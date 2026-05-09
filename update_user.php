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

$user_id  = (int) ($_POST['user_id']  ?? 0);
$username = trim($_POST['username']    ?? '');
$email    = trim($_POST['email']       ?? '');
$password =        $_POST['password']  ?? '';

if ($user_id === 0 || empty($username) || empty($email)) {
    header('Location: admin.php?section=users&error=missing_fields');
    exit();
}

// Don't allow editing the admin account through this form
$check = $conn->prepare("SELECT username FROM user WHERE user_id = ?");
$check->bind_param('i', $user_id);
$check->execute();
$check->bind_result($current_username);
$check->fetch();
$check->close();

if ($current_username === 'admin') {
    header('Location: admin.php?section=users&error=cannot_edit_admin');
    exit();
}

// If password was provided, update it too. Otherwise update only username and email.
if (!empty($password)) {
    $stmt = $conn->prepare(
        "UPDATE user SET username = ?, email = ?, password = ? WHERE user_id = ?"
    );
    $stmt->bind_param('sssi', $username, $email, $password, $user_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE user SET username = ?, email = ? WHERE user_id = ?"
    );
    $stmt->bind_param('ssi', $username, $email, $user_id);
}

if ($stmt->execute()) {
    header('Location: admin.php?section=users&success=user_updated');
} else {
    header('Location: admin.php?section=users&error=user_update_failed');
}

$stmt->close();
$conn->close();
exit();
?>
