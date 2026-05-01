<?php
// ============================================================
// profile.php — User profile management page
// ============================================================
require_once 'config.php';
requireLogin();

$uid  = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';

// Fetch account data
$stmt = $conn->prepare('SELECT name, email, username, created_at FROM users WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Fetch profile data
$stmt = $conn->prepare('SELECT age, gender, height, weight FROM user_profile WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Fetch latest BMI
$stmt = $conn->prepare(
    'SELECT bmi FROM health_records WHERE user_id = ? AND bmi > 0 ORDER BY record_date DESC LIMIT 1'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$bmiRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$latestBmi = $bmiRow['bmi'] ?? 0;

function bmiCategory(float $bmi): string {
    if ($bmi <= 0)   return '—';
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25)   return 'Normal';
    if ($bmi < 30)   return 'Overweight';
    return 'Obese';
}
$bmiCat      = bmiCategory((float)$latestBmi);
$bmiCatClass = strtolower(str_replace(' ', '', $bmiCat));

// Member since
$memberSince = !empty($user['created_at'])
    ? date('F Y', strtotime($user['created_at']))
    : 'Recently';

// Gender display label
$genderLabel = ['male' => 'Male', 'female' => 'Female', 'other' => 'Prefer not to say'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile | Smart Health Tracker</title>
<style>
/* ── Shared theme — identical to dashboard.php ── */
:root {
  --primary: #0f4c75;
  --accent:  #3aa8ff;
  --surface: rgba(255,255,255,0.18);
  --shadow:  0 24px 80px rgba(15,76,117,0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: radial-gradient(circle at top left,  rgba(58,168,255,0.22), transparent 35%),
              radial-gradient(circle at bottom right, rgba(15,76,117,0.35), transparent 25%),
              linear-gradient(180deg, #051128 0%, #092040 45%, #0f4c75 100%);
  color: #eef5ff;
}
body { overflow-x: hidden; }
header {
  position: sticky; top: 0; z-index: 10;
  backdrop-filter: blur(18px);
  background: rgba(5,17,40,0.72);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 40px;
}
header h1 { font-size: 1.4rem; letter-spacing: 0.04em; margin: 0; }
nav { display: flex; gap: 22px; align-items: center; flex-wrap: wrap; }
nav a { color: #e6f0ff; text-decoration: none; font-weight: 600; transition: color 0.25s; }
nav a:hover, nav a.active { color: var(--accent); }
.cta-button {
  padding: 12px 22px; border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.18);
  background: linear-gradient(135deg, rgba(58,168,255,0.96), rgba(15,76,117,0.88));
  color: white; cursor: pointer; font-weight: 600; font-size: 0.95rem;
  transition: transform 0.25s, box-shadow 0.25s; text-decoration: none;
}
.cta-button:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(58,168,255,0.22); }
.logout-btn {
  padding: 10px 20px; border-radius: 999px; border: 1px solid rgba(255,80,80,0.3);
  background: rgba(255,80,80,0.12); color: #ffb3b3;
  cursor: pointer; font-weight: 600; font-size: 0.9rem;
  text-decoration: none; transition: background 0.2s;
}
.logout-btn:hover { background: rgba(255,80,80,0.22); }
.sections { display: grid; gap: 28px; max-width: 1000px; margin: 0 auto 60px; padding: 0 40px; }
.card {
  background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.10);
  border-radius: 24px; padding: 28px; box-shadow: var(--shadow);
  transition: transform 0.3s, border-color 0.3s;
}
.card h3 { margin: 0 0 6px; font-size: 1.2rem; }
.card > p { color: rgba(238,245,255,0.7); margin: 0 0 20px; font-size: 0.92rem; }
.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.input-row.three { grid-template-columns: 1fr 1fr 1fr; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label { font-size: 0.82rem; color: rgba(238,245,255,0.7); }
.field-group input, .field-group select {
  padding: 11px 14px; border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.08); color: #eef5ff;
  outline: none; font-size: 0.95rem; width: 100%;
  transition: border-color 0.2s;
}
.field-group input:focus, .field-group select:focus { border-color: rgba(58,168,255,0.5); }
.field-group select option { background: #092040; }
.save-btn {
  margin-top: 18px; padding: 12px 28px; border-radius: 14px; border: none;
  background: linear-gradient(135deg, #3aa8ff, #0f4c75);
  color: white; font-size: 0.95rem; cursor: pointer; font-weight: 600;
  transition: transform 0.2s, box-shadow 0.2s;
}
.save-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(58,168,255,0.25); }
.section-title { padding: 40px 40px 16px; max-width: 1000px; margin: 0 auto; }
.section-title h2 { font-size: 1.8rem; margin: 0 0 4px; }
.section-title p  { color: rgba(238,245,255,0.7); margin: 0; }
/* Profile avatar area */
.profile-hero {
  display: flex; align-items: center; gap: 28px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 24px; padding: 28px; box-shadow: var(--shadow);
}
.avatar {
  width: 88px; height: 88px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, #3aa8ff, #0f4c75);
  display: flex; align-items: center; justify-content: center;
  font-size: 2.4rem; font-weight: 700; color: white;
  box-shadow: 0 8px 28px rgba(58,168,255,0.3);
}
.profile-meta h2 { margin: 0 0 4px; font-size: 1.6rem; }
.profile-meta p  { margin: 0; color: rgba(238,245,255,0.7); font-size: 0.95rem; }
/* Stats row in hero */
.hero-stats { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 14px; }
.hero-stat {
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.10);
  border-radius: 14px; padding: 12px 20px; text-align: center; min-width: 100px;
}
.hero-stat strong { display: block; font-size: 1.3rem; color: #fff; }
.hero-stat span   { font-size: 0.78rem; color: rgba(238,245,255,0.65); }
/* BMI badge */
.bmi-badge {
  display: inline-block; padding: 4px 14px; border-radius: 999px;
  font-size: 0.82rem; font-weight: 700; margin-left: 8px;
  background: rgba(58,168,255,0.2); color: #a8d9ff;
  border: 1px solid rgba(58,168,255,0.25);
}
.bmi-badge.underweight { background: rgba(255,200,80,0.2); color: #ffd580; border-color: rgba(255,200,80,0.3); }
.bmi-badge.normal      { background: rgba(80,220,130,0.2); color: #7df5b0; border-color: rgba(80,220,130,0.3); }
.bmi-badge.overweight  { background: rgba(255,140,50,0.2); color: #ffb870; border-color: rgba(255,140,50,0.3); }
.bmi-badge.obese       { background: rgba(255,80,80,0.2);  color: #ffb3b3; border-color: rgba(255,80,80,0.3); }
/* Toast */
.toast {
  position: fixed; bottom: 30px; right: 30px; z-index: 999;
  padding: 14px 22px; border-radius: 16px; font-size: 0.95rem; font-weight: 600;
  background: rgba(58,168,255,0.18); border: 1px solid rgba(58,168,255,0.35);
  color: #a8d9ff; backdrop-filter: blur(12px);
  opacity: 0; transform: translateY(20px);
  transition: opacity 0.35s, transform 0.35s; pointer-events: none;
}
.toast.show  { opacity: 1; transform: translateY(0); }
.toast.error { background: rgba(255,80,80,0.18); border-color: rgba(255,80,80,0.3); color: #ffb3b3; }
/* Divider */
.divider { height: 1px; background: rgba(255,255,255,0.08); margin: 20px 0; }
/* Readonly info row */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.info-item label { font-size: 0.78rem; color: rgba(238,245,255,0.55); display: block; margin-bottom: 4px; }
.info-item span  { font-size: 1rem; color: #eef5ff; font-weight: 600; }
@media (max-width: 820px) {
  header { flex-direction: column; align-items: flex-start; gap: 14px; }
  .sections { padding: 0 20px; }
  .section-title { padding-left: 20px; padding-right: 20px; }
  .input-row, .input-row.three { grid-template-columns: 1fr; }
  .profile-hero { flex-direction: column; text-align: center; }
  .hero-stats { justify-content: center; }
  .info-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<header>
  <h1>💙 Smart Health Tracker</h1>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="profile.php" class="active">Profile</a>
    <a href="food.php">Food</a>
    <a href="reports.php">Reports</a>
    <a href="ai_coach.php">AI Coach</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<div class="section-title">
  <h2>My Profile</h2>
  <p>View and manage your personal information and body measurements.</p>
</div>

<main class="sections">

  <!-- ── PROFILE HERO ── -->
  <div class="profile-hero">
    <div class="avatar"><?= strtoupper(mb_substr($user['name'] ?? 'U', 0, 1)) ?></div>
    <div class="profile-meta">
      <h2><?= htmlspecialchars($user['name'] ?? '') ?></h2>
      <p>@<?= htmlspecialchars($user['username'] ?? '') ?> &nbsp;·&nbsp; Member since <?= $memberSince ?></p>
      <div class="hero-stats">
        <div class="hero-stat">
          <strong><?= !empty($profile['age']) ? $profile['age'] : '—' ?></strong>
          <span>Age</span>
        </div>
        <div class="hero-stat">
          <strong><?= !empty($profile['height']) ? number_format((float)$profile['height'], 2).'m' : '—' ?></strong>
          <span>Height</span>
        </div>
        <div class="hero-stat">
          <strong><?= !empty($profile['weight']) ? number_format((float)$profile['weight'], 1).'kg' : '—' ?></strong>
          <span>Weight</span>
        </div>
        <div class="hero-stat">
          <strong>
            <?= $latestBmi > 0 ? number_format((float)$latestBmi, 1) : '—' ?>
            <?php if ($latestBmi > 0): ?>
              <span class="bmi-badge <?= $bmiCatClass ?>" style="font-size:0.7rem;padding:2px 8px;"><?= $bmiCat ?></span>
            <?php endif; ?>
          </strong>
          <span>BMI</span>
        </div>
        <div class="hero-stat">
          <strong><?= $genderLabel[$profile['gender'] ?? 'other'] ?? '—' ?></strong>
          <span>Gender</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ── EDIT ACCOUNT ── -->
  <section class="card">
    <h3>Account Information</h3>
    <p>Update your name, email address, and username.</p>

    <div class="info-grid">
      <div class="info-item"><label>Email</label><span><?= htmlspecialchars($user['email'] ?? '') ?></span></div>
      <div class="info-item"><label>Username</label><span>@<?= htmlspecialchars($user['username'] ?? '') ?></span></div>
    </div>

    <div class="divider"></div>

    <div class="input-row">
      <div class="field-group">
        <label>Full Name</label>
        <input type="text" id="inp-name" placeholder="Your full name"
               value="<?= htmlspecialchars($user['name'] ?? '') ?>">
      </div>
      <div class="field-group">
        <label>Username</label>
        <input type="text" id="inp-username" placeholder="username"
               value="<?= htmlspecialchars($user['username'] ?? '') ?>">
      </div>
    </div>
    <div class="input-row" style="margin-top:14px;">
      <div class="field-group">
        <label>Email Address</label>
        <input type="email" id="inp-email" placeholder="you@example.com"
               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
      </div>
    </div>
    <button class="save-btn" onclick="saveProfile()">Save Account Info</button>
  </section>

  <!-- ── EDIT BODY MEASUREMENTS ── -->
  <section class="card">
    <h3>Body Measurements</h3>
    <p>Keep your measurements up to date for accurate BMI tracking.</p>

    <div class="input-row">
      <div class="field-group">
        <label>Age (years)</label>
        <input type="number" id="inp-age" placeholder="25" min="1" max="120"
               value="<?= !empty($profile['age']) ? $profile['age'] : '' ?>">
      </div>
      <div class="field-group">
        <label>Gender</label>
        <select id="inp-gender">
          <option value="other"  <?= (($profile['gender'] ?? '') === 'other')  ? 'selected' : '' ?>>Prefer not to say</option>
          <option value="male"   <?= (($profile['gender'] ?? '') === 'male')   ? 'selected' : '' ?>>Male</option>
          <option value="female" <?= (($profile['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
        </select>
      </div>
    </div>
    <div class="input-row" style="margin-top:14px;">
      <div class="field-group">
        <label>Height (metres, e.g. 1.75)</label>
        <input type="number" id="inp-height" placeholder="1.75" min="0.5" max="3.0" step="0.01"
               value="<?= !empty($profile['height']) ? $profile['height'] : '' ?>" oninput="previewBmi()">
      </div>
      <div class="field-group">
        <label>Weight (kg)</label>
        <input type="number" id="inp-weight" placeholder="70" min="1" max="500" step="0.1"
               value="<?= !empty($profile['weight']) ? $profile['weight'] : '' ?>" oninput="previewBmi()">
      </div>
    </div>

    <p style="margin-top:16px; font-size:0.95rem; color:rgba(238,245,255,0.85);">
      BMI Preview: <strong id="bmi-preview">
        <?= $latestBmi > 0 ? number_format((float)$latestBmi, 1) : '—' ?>
      </strong>
      <span class="bmi-badge <?= $bmiCatClass ?>" id="bmi-badge"
            style="<?= $latestBmi <= 0 ? 'display:none' : '' ?>">
        <?= $bmiCat ?>
      </span>
    </p>

    <button class="save-btn" onclick="saveMeasurements()">Save Measurements &amp; Recalculate BMI</button>
  </section>

</main>

<div class="toast" id="toast"></div>

<script>
// ── Toast helper ─────────────────────────────────────────────
const toast = (msg, err = false) => {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast show' + (err ? ' error' : '');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3500);
};

// ── BMI preview ──────────────────────────────────────────────
function previewBmi() {
  const h = parseFloat(document.getElementById('inp-height').value);
  const w = parseFloat(document.getElementById('inp-weight').value);
  const preview = document.getElementById('bmi-preview');
  const badge   = document.getElementById('bmi-badge');
  if (h > 0 && w > 0) {
    const bmi = (w / (h * h)).toFixed(1);
    preview.textContent = bmi;
    const cat = bmiCategory(parseFloat(bmi));
    badge.textContent = cat;
    badge.className = 'bmi-badge ' + cat.toLowerCase().replace(' ', '');
    badge.style.display = 'inline-block';
  } else {
    preview.textContent = '—';
    badge.style.display = 'none';
  }
}

function bmiCategory(bmi) {
  if (!bmi || bmi <= 0) return '—';
  if (bmi < 18.5) return 'Underweight';
  if (bmi < 25)   return 'Normal';
  if (bmi < 30)   return 'Overweight';
  return 'Obese';
}

// ── Save account info ────────────────────────────────────────
async function saveProfile() {
  const name     = document.getElementById('inp-name').value.trim();
  const email    = document.getElementById('inp-email').value.trim();
  const username = document.getElementById('inp-username').value.trim();

  if (!name || !email || !username) {
    toast('Please fill in all account fields.', true); return;
  }

  const fd = new FormData();
  fd.append('name',     name);
  fd.append('email',    email);
  fd.append('username', username);
  // Pass current measurements so profile table is preserved
  fd.append('age',    document.getElementById('inp-age').value    || 0);
  fd.append('gender', document.getElementById('inp-gender').value || 'other');
  fd.append('height', document.getElementById('inp-height').value || 0);
  fd.append('weight', document.getElementById('inp-weight').value || 0);

  const r   = await fetch('update_profile.php', { method: 'POST', body: fd });
  const res = await r.json();
  toast(res.success ? 'Account info saved! ✓' : res.message, !res.success);
}

// ── Save body measurements ───────────────────────────────────
async function saveMeasurements() {
  const h = document.getElementById('inp-height').value;
  const w = document.getElementById('inp-weight').value;
  if (!h || !w) { toast('Height and weight are required.', true); return; }

  const fd = new FormData();
  fd.append('name',     document.getElementById('inp-name').value.trim()     || <?= json_encode($user['name'] ?? '') ?>);
  fd.append('email',    document.getElementById('inp-email').value.trim()    || <?= json_encode($user['email'] ?? '') ?>);
  fd.append('username', document.getElementById('inp-username').value.trim() || <?= json_encode($user['username'] ?? '') ?>);
  fd.append('age',      document.getElementById('inp-age').value    || 0);
  fd.append('gender',   document.getElementById('inp-gender').value || 'other');
  fd.append('height',   h);
  fd.append('weight',   w);

  const r   = await fetch('update_profile.php', { method: 'POST', body: fd });
  const res = await r.json();
  if (res.success) {
    toast('Measurements & BMI updated! ✓');
    if (res.bmi) {
      const bmi = parseFloat(res.bmi).toFixed(1);
      const cat = bmiCategory(parseFloat(bmi));
      document.getElementById('bmi-preview').textContent = bmi;
      const badge = document.getElementById('bmi-badge');
      badge.textContent = cat;
      badge.className = 'bmi-badge ' + cat.toLowerCase().replace(' ', '');
      badge.style.display = 'inline-block';
    }
  } else {
    toast(res.message, true);
  }
}

// Run BMI preview on load
previewBmi();
</script>

</body>
</html>
