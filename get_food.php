<?php
// ============================================================
// get_food.php — AJAX endpoint (GET, returns JSON)
// Returns: today's food entries + daily macro totals
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid  = (int) $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// ── Today's food entries ─────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT food_id, food_name, calories, protein, carbs, fats, created_at
     FROM food_logs
     WHERE user_id = ? AND log_date = ?
     ORDER BY created_at ASC'
);
$stmt->bind_param('is', $uid, $date);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Daily totals ─────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(calories), 0) AS total_calories,
        COALESCE(SUM(protein),  0) AS total_protein,
        COALESCE(SUM(carbs),    0) AS total_carbs,
        COALESCE(SUM(fats),     0) AS total_fats
     FROM food_logs
     WHERE user_id = ? AND log_date = ?'
);
$stmt->bind_param('is', $uid, $date);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Last 7 days calorie history (for mini chart) ─────────────
$stmt = $conn->prepare(
    'SELECT log_date, COALESCE(SUM(calories), 0) AS total_calories
     FROM food_logs
     WHERE user_id = ?
     GROUP BY log_date
     ORDER BY log_date DESC
     LIMIT 7'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$calorie_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success'         => true,
    'date'            => $date,
    'entries'         => $entries,
    'totals'          => $totals,
    'calorie_history' => $calorie_history,
]);
