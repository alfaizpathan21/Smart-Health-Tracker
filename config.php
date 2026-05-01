<?php
// ============================================================
// config.php — Database connection + session bootstrap
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // XAMPP default
define('DB_PASS', '');           // XAMPP default (blank)
define('DB_NAME', 'health_tracker');

// Start session once, safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create MySQLi connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$conn->set_charset('utf8mb4');

// ── Helper: redirect shorthand ──────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── Helper: is user logged in? ──────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ── Helper: require login (call at top of protected pages) ──
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('newindex.php');
    }
}
?>
