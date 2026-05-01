<?php
// ============================================================
// update_profile.php — AJAX endpoint (POST, returns JSON)
// Updates: users (name, email, username) + user_profile (age, gender, height, weight)
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid = (int) $_SESSION['user_id'];

// ── Sanitise inputs ──────────────────────────────────────────
$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$username = trim($_POST['username'] ?? '');
$age      = (int)   ($_POST['age']    ?? 0);
$gender   = trim(   $_POST['gender']  ?? 'other');
$height   = (float) ($_POST['height'] ?? 0);
$weight   = (float) ($_POST['weight'] ?? 0);

// ── Validate ─────────────────────────────────────────────────
$errors = [];
if (empty($name))                               $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
if (strlen($username) < 3)                      $errors[] = 'Username must be at least 3 characters.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Clamp profile values ─────────────────────────────────────
$allowedGenders = ['male', 'female', 'other'];
if (!in_array($gender, $allowedGenders)) $gender = 'other';
$age    = max(0, min(120, $age));
$height = max(0, min(3.0, $height));
$weight = max(0, min(500, $weight));

// ── Check email/username uniqueness (excluding current user) ─
$stmt = $conn->prepare(
    'SELECT user_id FROM users WHERE (email = ? OR username = ?) AND user_id != ?'
);
$stmt->bind_param('ssi', $email, $username, $uid);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email or username already in use.']);
    $stmt->close();
    exit;
}
$stmt->close();

// ── Perform updates in a transaction ─────────────────────────
$conn->begin_transaction();
try {
    // Update users table
    $stmt = $conn->prepare(
        'UPDATE users SET name = ?, email = ?, username = ? WHERE user_id = ?'
    );
    $stmt->bind_param('sssi', $name, $email, $username, $uid);
    $stmt->execute();
    $stmt->close();

    // Update user_profile table
    $stmt = $conn->prepare(
        'UPDATE user_profile SET age = ?, gender = ?, height = ?, weight = ? WHERE user_id = ?'
    );
    $stmt->bind_param('isddi', $age, $gender, $height, $weight, $uid);
    $stmt->execute();
    $stmt->close();

    // Recalculate & persist BMI if height and weight provided
    $bmi = null;
    if ($height > 0 && $weight > 0) {
        $bmi  = round($weight / ($height * $height), 2);
        $date = date('Y-m-d');
        $stmt = $conn->prepare(
            'INSERT INTO health_records (user_id, bmi, record_date)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE bmi = VALUES(bmi)'
        );
        $stmt->bind_param('ids', $uid, $bmi, $date);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();

    // Refresh session name
    $_SESSION['name'] = $name;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'bmi'     => $bmi
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Update failed. Please try again.']);
}
