<?php
// ============================================================
// get_health.php — AJAX endpoint (GET, returns JSON)
// Returns: today's record + profile + goals + last-7-days
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid = (int) $_SESSION['user_id'];

// ── Accept optional ?date= param; default to today ───────────
$rawDate = trim($_GET['date'] ?? '');
if ($rawDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
    $d = DateTime::createFromFormat('Y-m-d', $rawDate);
    $today = ($d && $d->format('Y-m-d') === $rawDate) ? $rawDate : date('Y-m-d');
} else {
    $today = date('Y-m-d');
}
$stmt  = $conn->prepare(
    'SELECT steps, water, sleep, bmi FROM health_records WHERE user_id = ? AND record_date = ?'
);
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── User profile ─────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT age, gender, height, weight FROM user_profile WHERE user_id = ?'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Goals ────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT steps_goal, water_goal, sleep_goal FROM goals WHERE user_id = ?'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$goals = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Last 7 days history ───────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT record_date, steps, water, sleep, bmi
     FROM health_records
     WHERE user_id = ?
     ORDER BY record_date DESC
     LIMIT 7'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success' => true,
    'date'    => $today,
    'record'  => $rec     ?: ['steps' => 0, 'water' => 0, 'sleep' => 0, 'bmi' => 0],
    'profile' => $profile ?: ['age' => null, 'gender' => null, 'height' => null, 'weight' => null],
    'goals'   => $goals   ?: ['steps_goal' => 10000, 'water_goal' => 2.0, 'sleep_goal' => 8.0],
    'history' => $history,
]);