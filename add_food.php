<?php
// ============================================================
// add_food.php — AJAX endpoint (POST, returns JSON)
// Inserts one food entry into food_logs for today
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid = (int) $_SESSION['user_id'];

$food_name = trim($_POST['food_name'] ?? '');
$calories  = (float) ($_POST['calories'] ?? 0);
$protein   = (float) ($_POST['protein']  ?? 0);
$carbs     = (float) ($_POST['carbs']    ?? 0);
$fats      = (float) ($_POST['fats']     ?? 0);

// Accept a specific date from the form, fallback to today
$raw_date = trim($_POST['log_date'] ?? '');
$date     = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date) && $raw_date <= date('Y-m-d'))
            ? $raw_date
            : date('Y-m-d');

if (empty($food_name)) {
    echo json_encode(['success' => false, 'message' => 'Food name is required.']);
    exit;
}

// Clamp to sensible ranges
$calories = max(0, min(9999, $calories));
$protein  = max(0, min(999, $protein));
$carbs    = max(0, min(999, $carbs));
$fats     = max(0, min(999, $fats));

$stmt = $conn->prepare(
    'INSERT INTO food_logs (user_id, food_name, calories, protein, carbs, fats, log_date)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('isdddds', $uid, $food_name, $calories, $protein, $carbs, $fats, $date);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Food entry added.', 'id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
}
$stmt->close();
