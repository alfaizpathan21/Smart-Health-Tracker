<?php
// ============================================================
// register.php — Create account + profile + default goals
// ============================================================
require_once 'config.php';

// If already logged in, skip to dashboard
if (isLoggedIn()) redirect('dashboard.php');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Sanitise inputs ──────────────────────────────────
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $username = trim($_POST['username'] ?? '');
    $password =      $_POST['password'] ?? '';
    $confirm  =      $_POST['confirm']  ?? '';

    // ── 2. Validate ─────────────────────────────────────────
    if (empty($name))                          $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($username) < 3)                 $errors[] = 'Username must be at least 3 characters.';
    if (strlen($password) < 6)                 $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                $errors[] = 'Passwords do not match.';

    // ── 3. Check uniqueness ─────────────────────────────────
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? OR username = ?');
        $stmt->bind_param('ss', $email, $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Email or username already registered.';
        }
        $stmt->close();
    }

    // ── 4. Insert ───────────────────────────────────────────
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $conn->begin_transaction();
        try {
            // users row
            $stmt = $conn->prepare(
                'INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssss', $name, $username, $email, $hashed);
            $stmt->execute();
            $uid = $conn->insert_id;
            $stmt->close();

            // user_profile row (empty profile, filled later)
            $stmt = $conn->prepare('INSERT INTO user_profile (user_id) VALUES (?)');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();

            // default goals row
            $stmt = $conn->prepare('INSERT INTO goals (user_id) VALUES (?)');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = 'Registration successful! Redirecting to login…';
            header('Refresh: 2; url=newindex.php');

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Smart Health Tracker - Registration</title>
<style>
/* ── ORIGINAL CSS — DO NOT MODIFY ── */
:root {
  --bg: #081426;
  --panel: rgba(255,255,255,0.1);
  --panel-strong: rgba(255,255,255,0.18);
  --accent: #3da8ff;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, #041025 0%, #071a34 50%, #091f3d 100%);
  color: white;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.page-shell {
  width: min(460px, 92vw);
  padding: 34px;
  border-radius: 32px;
  background: radial-gradient(circle at top right, rgba(61,168,255,0.15), transparent 36%),
              rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.14);
  box-shadow: 0 30px 90px rgba(0,0,0,0.35);
}
.page-header { text-align: center; margin-bottom: 24px; }
.page-header h1 { margin: 0; font-size: 2rem; }
.page-header p { margin: 10px auto 0; max-width: 320px; color: rgba(255,255,255,0.75); }
.form-card {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.16);
  border-radius: 28px;
  padding: 28px;
  backdrop-filter: blur(20px);
}
.field { position: relative; margin-bottom: 18px; }
.field input {
  width: 100%;
  padding: 16px 16px 14px;
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.08);
  color: #eff6ff;
  outline: none;
  font-size: 1rem;
}
.field input:focus { border-color: rgba(61,168,255,0.5); }
.field label {
  position: absolute;
  top: 14px; left: 16px;
  color: rgba(255,255,255,0.7);
  pointer-events: none;
  transition: transform 0.2s ease, font-size 0.2s ease;
}
.field input:focus + label,
.field input:not(:placeholder-shown) + label {
  transform: translateY(-26px);
  font-size: 0.78rem;
  color: rgba(255,255,255,0.9);
}
.field input::placeholder { color: transparent; }
button {
  width: 100%; padding: 16px; margin-top: 8px;
  border: none; border-radius: 16px;
  background: linear-gradient(135deg, #3da8ff, #0d47a1);
  color: white; font-size: 1rem; cursor: pointer;
  transition: transform 0.24s ease, box-shadow 0.24s ease;
}
button:hover { transform: translateY(-2px); box-shadow: 0 18px 34px rgba(61,168,255,0.24); }
.feedback {
  margin-top: 15px; color: rgba(255,255,255,0.78);
  font-size: 0.95rem; text-align: center;
}
.feedback a { color: #a8d9ff; text-decoration: none; font-weight: 700; }
.strength { margin-top: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.72); }
.strength span { font-weight: 700; color: #a8d9ff; }
/* ── ADDED: alert styles ── */
.alert {
  padding: 12px 16px; border-radius: 12px; margin-bottom: 16px;
  font-size: 0.9rem; line-height: 1.5;
}
.alert-error { background: rgba(255,80,80,0.18); border: 1px solid rgba(255,80,80,0.3); color: #ffb3b3; }
.alert-success { background: rgba(61,168,255,0.15); border: 1px solid rgba(61,168,255,0.3); color: #a8d9ff; }
</style>
</head>
<body>
<div class="page-shell">
  <header class="page-header">
    <h1>💙 Smart Health Tracker</h1>
    <p>Create an account and step into a next-gen health ecosystem.</p>
  </header>
  <section class="form-card">

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
      <div class="field">
        <input id="name" name="name" type="text" placeholder="Full Name" autocomplete="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        <label for="name">Full Name</label>
      </div>
      <div class="field">
        <input id="email" name="email" type="email" placeholder="Email" autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        <label for="email">Email</label>
      </div>
      <div class="field">
        <input id="username" name="username" type="text" placeholder="Username" autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        <label for="username">Username</label>
      </div>
      <div class="field">
        <input id="password" name="password" type="password" placeholder="Password"
               autocomplete="new-password" oninput="updateStrength()" required>
        <label for="password">Password</label>
      </div>
      <div class="field">
        <input id="confirm" name="confirm" type="password" placeholder="Confirm Password"
               autocomplete="new-password" required>
        <label for="confirm">Confirm Password</label>
      </div>
      <div class="strength">Password strength: <span id="strengthText">Enter a password</span></div>
      <button type="submit">Register</button>
    </form>

    <div class="feedback">Already have an account? <a href="newindex.php">Login</a></div>
  </section>
</div>
<script>
/* ORIGINAL script — unchanged */
function updateStrength() {
  const value = document.getElementById('password').value;
  const strengthText = document.getElementById('strengthText');
  if (!value) { strengthText.textContent = 'Enter a password'; return; }
  let score = 0;
  if (value.length >= 8) score++;
  if (/[A-Z]/.test(value)) score++;
  if (/[0-9]/.test(value)) score++;
  if (/[^A-Za-z0-9]/.test(value)) score++;
  const map = ['Weak','Fair','Good','Strong','Excellent'];
  strengthText.textContent = map[score];
}
</script>
</body>
</html>
