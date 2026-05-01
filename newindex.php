<?php
// ============================================================
// login.php (newindex.php equivalent) — Authenticate user
// ============================================================
require_once 'config.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email']    ?? '');   // email OR username
    $password   =      $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both email/username and password.';
    } else {
        // Accept login via email OR username
        $stmt = $conn->prepare(
            'SELECT user_id, name, password FROM users WHERE email = ? OR username = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $stmt->bind_result($uid, $name, $hashed);
        $stmt->fetch();
        $stmt->close();

        if ($uid && password_verify($password, $hashed)) {
            $_SESSION['user_id'] = $uid;
            $_SESSION['name']    = $name;
            redirect('dashboard.php');
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Smart Health Tracker | Login</title>
<style>
/* ── ORIGINAL CSS — DO NOT MODIFY ── */
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body {
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  background: radial-gradient(circle at top left, rgba(58,168,255,0.22), transparent 32%),
              radial-gradient(circle at bottom right, rgba(5,20,60,0.35), transparent 28%),
              linear-gradient(135deg, #0d47a1 0%, #092040 40%, #042f5d 100%);
}
.main-box {
  width:min(980px,92vw);
  display:grid; grid-template-columns:1fr 1.1fr;
  border-radius:28px; overflow:hidden;
  box-shadow:0 40px 120px rgba(3,20,60,0.35);
  background:rgba(255,255,255,0.08);
  border:1px solid rgba(255,255,255,0.14);
  backdrop-filter:blur(18px);
}
.login-box,.info-box { padding:50px; }
.login-box { background:rgba(255,255,255,0.08); }
.logo {
  width:60px; height:60px;
  background:linear-gradient(135deg,#3da8ff,#0d47a1);
  color:white; border-radius:18px;
  display:grid; place-items:center;
  font-size:28px; font-weight:700; margin-bottom:28px;
}
.login-box h2 { font-size:2.2rem; color:#eef5ff; margin-bottom:10px; }
.login-box p { color:rgba(238,245,255,0.76); margin-bottom:32px; line-height:1.7; }
.input-group { position:relative; margin-bottom:18px; }
.input-group label {
  position:absolute; top:12px; left:16px;
  font-size:0.9rem; color:rgba(255,255,255,0.7);
  pointer-events:none; transition:transform 0.2s ease,opacity 0.2s ease;
}
.input-group input {
  width:100%; padding:18px 16px 14px;
  border-radius:16px; border:1px solid rgba(255,255,255,0.18);
  background:rgba(255,255,255,0.08); color:#eef5ff; outline:none; font-size:1rem;
}
.input-group input:focus { border-color:rgba(61,168,255,0.5); }
.input-group input:focus + label,
.input-group input:not(:placeholder-shown) + label {
  transform:translateY(-28px); opacity:0.95; font-size:0.78rem;
}
.input-group input::placeholder { color:transparent; }
button {
  width:100%; padding:15px; margin-top:12px;
  border:none; border-radius:16px;
  background:linear-gradient(135deg,#3da8ff,#0d47a1);
  color:white; font-size:1rem; cursor:pointer;
  transition:transform 0.25s ease,box-shadow 0.25s ease;
}
button:hover { transform:translateY(-2px); box-shadow:0 18px 30px rgba(61,168,255,0.24); }
.login-box .helper { margin-top:18px; color:rgba(238,245,255,0.75); font-size:0.95rem; text-align:center; }
.login-box .helper a { color:#a8d9ff; text-decoration:none; font-weight:700; }
.info-box {
  position:relative;
  background:linear-gradient(135deg,rgba(5,20,60,0.94),rgba(11,45,92,0.95));
  color:white; display:flex; flex-direction:column; justify-content:center; gap:18px;
}
.info-box h1 { font-size:clamp(2.1rem,3vw,3.1rem); line-height:1.05; }
.info-box p { font-size:1.05rem; line-height:1.8; max-width:470px; color:rgba(238,245,255,0.85); }
.info-box .highlight { color:#67d1ff; font-weight:700; }
.info-box .card {
  margin-top:28px;
  background:rgba(255,255,255,0.09); border:1px solid rgba(255,255,255,0.14);
  border-radius:24px; padding:24px;
  box-shadow:0 24px 70px rgba(0,0,0,0.12);
  animation:float 3.5s ease-in-out infinite;
}
.info-box .card h3 { margin-bottom:12px; font-size:1.1rem; }
.info-box .card p { margin:0; color:rgba(238,245,255,0.82); line-height:1.7; }
.floating-shape {
  position:absolute; width:100px; height:100px;
  border-radius:50%; background:rgba(58,168,255,0.16); filter:blur(18px);
}
.floating-shape.one { top:20px; right:30px; }
.floating-shape.two { bottom:20px; left:40px; width:140px; height:140px; }
@keyframes float { 0%,100%{transform:translateY(0px)} 50%{transform:translateY(-12px)} }
/* ── ADDED: alert ── */
.alert {
  padding:12px 16px; border-radius:12px; margin-bottom:16px;
  font-size:0.9rem; line-height:1.5;
  background:rgba(255,80,80,0.18); border:1px solid rgba(255,80,80,0.3); color:#ffb3b3;
}
@media (max-width:840px) {
  .main-box { grid-template-columns:1fr; }
  .info-box,.login-box { padding:34px; }
}
</style>
</head>
<body>
<div class="main-box">
  <div class="login-box">
    <div class="logo">💙</div>
    <h2>Welcome Back</h2>
    <p>Sign in to access your smart health dashboard with advanced insights.</p>

    <?php if ($error): ?>
      <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="newindex.php" novalidate>
      <div class="input-group">
        <input id="email" name="email" type="text" placeholder="Email or Username"
               autocomplete="username" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <label for="email">Email or Username</label>
      </div>
      <div class="input-group">
        <input id="password" name="password" type="password" placeholder="Password"
               autocomplete="current-password">
        <label for="password">Password</label>
      </div>
      <button type="submit">Login</button>
    </form>
    <div class="helper">New user? <a href="register.php">Create account</a></div>
  </div>

  <div class="info-box">
    <div class="floating-shape one"></div>
    <div class="floating-shape two"></div>
    <h1>Modern Health Tracking with an Interactive Interface</h1>
    <p>Jump into an experience that blends elegant glassmorphism, motion-based feedback, and actionable health recommendations.</p>
    <div class="card">
      <h3>Next-gen experience</h3>
      <p>Login to a clean dashboard built for performance, adaptive layouts, and subtle animation that keeps you engaged.</p>
      <ul style="margin:18px 0 0 18px;color:rgba(238,245,255,0.82);line-height:1.8;">
        <li>AI wellness suggestions to refine your routine</li>
        <li>Sleep and hydration priorities for tomorrow</li>
        <li>Smart reminders that adapt to your schedule</li>
      </ul>
    </div>
  </div>
</div>
</body>
</html>
