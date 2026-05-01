<?php
// ============================================================
// delete_food.php — AJAX endpoint (POST, returns JSON)
// Deletes a food log entry owned by the logged-in user
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid     = (int) $_SESSION['user_id'];
$food_id = (int) ($_POST['food_id'] ?? 0);

if (!$food_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid food entry.']);
    exit;
}

// Only delete records that belong to the current user (security)
$stmt = $conn->prepare('DELETE FROM food_logs WHERE food_id = ? AND user_id = ?');
$stmt->bind_param('ii', $food_id, $uid);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Entry not found or already deleted.']);
}
$stmt->close();
