<?php
session_start();

// Guard: admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'dbconfig.php';

header('Content-Type: application/json');

$rows = [];

$result = $conn->query(
    "SELECT u.*, COUNT(i.item_id) AS items_reported
     FROM user u
     LEFT JOIN item i ON u.user_id = i.reported_by
     WHERE u.username != 'admin'
     GROUP BY u.user_id
     ORDER BY u.created_at DESC"
);

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode([
    'users'       => $rows,
    'total_users' => (int) $conn->query("SELECT COUNT(*) FROM user WHERE username != 'admin'")->fetch_row()[0],
]);

$conn->close();
?>
