<?php
// ============================================================
// save_health.php — AJAX endpoint (POST, returns JSON)
// Handles: save_record | save_profile | save_goals
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid    = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ── Action: save daily health record ────────────────────────
if ($action === 'save_record') {
    $steps = (int)   ($_POST['steps'] ?? 0);
    $water = (float) ($_POST['water'] ?? 0);
    $sleep = (float) ($_POST['sleep'] ?? 0);
    $bmi   = (float) ($_POST['bmi']   ?? 0);
    $date  = date('Y-m-d');

    // Clamp to sensible ranges
    $steps = max(0, min($steps, 100000));
    $water = max(0, min($water, 20));
    $sleep = max(0, min($sleep, 24));
    $bmi   = max(0, min($bmi, 100));

    // INSERT … ON DUPLICATE KEY UPDATE (unique key: user_id + record_date)
    $stmt = $conn->prepare(
        'INSERT INTO health_records (user_id, steps, water, sleep, bmi, record_date)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           steps = VALUES(steps),
           water = VALUES(water),
           sleep = VALUES(sleep),
           bmi   = IF(VALUES(bmi) > 0, VALUES(bmi), bmi)'
    );
    $stmt->bind_param('iiidds', $uid, $steps, $water, $sleep, $bmi, $date);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Record saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// ── Action: save profile (height / weight / age / gender) ───
if ($action === 'save_profile') {
    $age    = (int)   ($_POST['age']    ?? 0);
    $gender = trim(   $_POST['gender']  ?? '');
    $height = (float) ($_POST['height'] ?? 0);
    $weight = (float) ($_POST['weight'] ?? 0);

    $allowedGenders = ['male', 'female', 'other'];
    if (!in_array($gender, $allowedGenders)) $gender = 'other';
    $age    = max(1, min(120, $age));
    $height = max(0.5, min(3.0, $height));
    $weight = max(1, min(500, $weight));

    $stmt = $conn->prepare(
        'UPDATE user_profile SET age = ?, gender = ?, height = ?, weight = ? WHERE user_id = ?'
    );
    $stmt->bind_param('isddi', $age, $gender, $height, $weight, $uid);

    if ($stmt->execute()) {
        // Calculate and store today's BMI
        $bmi  = round($weight / ($height * $height), 2);
        $date = date('Y-m-d');
        $stmt2 = $conn->prepare(
            'INSERT INTO health_records (user_id, bmi, record_date)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE bmi = VALUES(bmi)'
        );
        $stmt2->bind_param('ids', $uid, $bmi, $date);
        $stmt2->execute();
        $stmt2->close();

        echo json_encode(['success' => true, 'bmi' => $bmi]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile update failed.']);
    }
    $stmt->close();
    exit;
}

// ── Action: save goals ───────────────────────────────────────
if ($action === 'save_goals') {
    $steps_goal = (int)   ($_POST['steps_goal'] ?? 10000);
    $water_goal = (float) ($_POST['water_goal'] ?? 2.0);
    $sleep_goal = (float) ($_POST['sleep_goal'] ?? 8.0);

    $stmt = $conn->prepare(
        'UPDATE goals SET steps_goal = ?, water_goal = ?, sleep_goal = ? WHERE user_id = ?'
    );
    $stmt->bind_param('iddi', $steps_goal, $water_goal, $sleep_goal, $uid);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Goals updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Goals update failed.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
