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
    "SELECT i.*, u.username
     FROM item i
     LEFT JOIN user u ON i.reported_by = u.user_id
     ORDER BY i.date_found DESC"
);

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// Also return updated stat counts so the cards refresh too
echo json_encode([
    'items'          => $rows,
    'total_items'    => (int) $conn->query("SELECT COUNT(*) FROM item")->fetch_row()[0],
    'total_unclaimed'=> (int) $conn->query("SELECT COUNT(*) FROM item WHERE status='unclaimed'")->fetch_row()[0],
    'total_claimed'  => (int) $conn->query("SELECT COUNT(*) FROM item WHERE status='claimed'")->fetch_row()[0],
]);

$conn->close();
?>
