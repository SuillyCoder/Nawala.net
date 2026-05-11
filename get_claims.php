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
    "SELECT i.item_id, i.item_name, u.username,
            i.claim_date, i.claim_status
     FROM item i
     LEFT JOIN user u ON i.claimed_by = u.user_id
     WHERE i.claim_status IS NOT NULL
     ORDER BY i.claim_date DESC"
);

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode([
    'claims'       => $rows,
    'total_claims' => (int) $conn->query("SELECT COUNT(*) FROM item WHERE claim_status IS NOT NULL")->fetch_row()[0],
]);

$conn->close();
?>
