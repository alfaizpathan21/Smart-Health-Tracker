<?php
// ============================================================
// dashboard.php — Protected main dashboard (same UI style)
// ============================================================
require_once 'config.php';
requireLogin();

$uid  = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';

// ── Fetch today's record + profile + goals for initial render ─
$today = date('Y-m-d');

$stmt = $conn->prepare(
    'SELECT steps, water, sleep, bmi FROM health_records WHERE user_id = ? AND record_date = ?'
);
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc() ?? ['steps' => 0, 'water' => 0, 'sleep' => 0, 'bmi' => 0];
$stmt->close();

$stmt = $conn->prepare('SELECT age, gender, height, weight FROM user_profile WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

$stmt = $conn->prepare('SELECT steps_goal, water_goal, sleep_goal FROM goals WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$goals = $stmt->get_result()->fetch_assoc() ?? ['steps_goal' => 10000, 'water_goal' => 2.0, 'sleep_goal' => 8.0];
$stmt->close();

// ── BMI category helper ───────────────────────────────────────
function bmiCategory(float $bmi): string {
    if ($bmi <= 0)   return '—';
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25)   return 'Normal';
    if ($bmi < 30)   return 'Overweight';
    return 'Obese';
}

// ── Safe progress percentage ──────────────────────────────────
function pct($val, $goal): int {
    if (!$goal) return 0;
    return (int) min(100, round(($val / $goal) * 100));
}

$stepsPct = pct($rec['steps'], $goals['steps_goal']);
$waterPct = pct($rec['water'], $goals['water_goal']);
$sleepPct = pct($rec['sleep'], $goals['sleep_goal']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard | Smart Health Tracker</title>
<style>
/* ── ORIGINAL CSS from homepage — DO NOT MODIFY ── */
:root {
  --primary: #0f4c75;
  --accent: #3aa8ff;
  --surface: rgba(255,255,255,0.18);
  --surface-strong: rgba(255,255,255,0.28);
  --text: #f5f9ff;
  --shadow: 0 24px 80px rgba(15,76,117,0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: radial-gradient(circle at top left, rgba(58,168,255,0.22), transparent 35%),
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
nav { display: flex; gap: 22px; align-items: center; }
nav a { color: #e6f0ff; text-decoration: none; font-weight: 600; transition: color 0.25s; }
nav a:hover { color: var(--accent); }
.cta-button {
  padding: 12px 22px; border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.18);
  background: linear-gradient(135deg, rgba(58,168,255,0.96), rgba(15,76,117,0.88));
  color: white; cursor: pointer;
  transition: transform 0.25s, box-shadow 0.25s;
  text-decoration: none; font-weight: 600; font-size: 0.95rem;
}
.cta-button:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(58,168,255,0.22); }
.sections { display: grid; gap: 36px; max-width: 1180px; margin: 0 auto 60px; padding: 0 40px; }
.tracker-section {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 28px; padding: 28px;
}
.tracker-section h3 { margin: 0 0 10px; }
.tracker-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-top: 20px; }
.tracker-card {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 22px; padding: 22px;
  box-shadow: 0 18px 46px rgba(15,76,117,0.14);
  transition: transform 0.25s, border-color 0.25s;
}
.tracker-card:hover { transform: translateY(-6px); border-color: rgba(58,168,255,0.28); }
.tracker-card h4 { margin: 0 0 12px; }
.tracker-value { font-size: 2rem; font-weight: 700; margin: 0 0 8px; }
.progress-bar { height: 10px; background: rgba(255,255,255,0.12); border-radius: 999px; overflow: hidden; }
.progress-fill {
  height: 100%; width: 0;
  background: linear-gradient(135deg, #3aa8ff, #0f4c75);
  border-radius: inherit; transition: width 1s ease;
}
.progress-note { margin-top: 10px; font-size: 0.9rem; color: rgba(238,245,255,0.78); }
.card {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 24px; padding: 28px;
  box-shadow: var(--shadow);
  transition: transform 0.3s, border-color 0.3s;
}
.card:hover { transform: translateY(-4px); border-color: rgba(58,168,255,0.25); }
.card h3 { margin: 0 0 12px; }
.card p { color: rgba(238,245,255,0.75); line-height: 1.75; margin: 0 0 12px; }
.card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
.stat { padding: 24px; border-radius: 22px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); text-align: center; box-shadow: var(--shadow); }
.stat h3 { margin: 0; font-size: 2.2rem; color: #fff; }
.stat p { margin: 10px 0 0; color: rgba(238,245,255,0.8); }
/* ── ADDED: form elements inside cards ── */
.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 16px; }
.input-row.three { grid-template-columns: 1fr 1fr 1fr; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label { font-size: 0.82rem; color: rgba(238,245,255,0.7); }
.field-group input, .field-group select {
  padding: 11px 14px; border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.08); color: #eef5ff;
  outline: none; font-size: 0.95rem; width: 100%;
}
.field-group input:focus, .field-group select:focus { border-color: rgba(58,168,255,0.5); }
.field-group select option { background: #092040; }
.save-btn {
  margin-top: 16px; padding: 12px 24px; border-radius: 14px; border: none;
  background: linear-gradient(135deg, #3aa8ff, #0f4c75);
  color: white; font-size: 0.95rem; cursor: pointer; font-weight: 600;
  transition: transform 0.2s, box-shadow 0.2s;
}
.save-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(58,168,255,0.25); }
.section-title { padding: 40px 40px 0; max-width: 1180px; margin: 0 auto; }
.section-title h2 { font-size: 1.6rem; margin: 0 0 4px; }
.section-title p { color: rgba(238,245,255,0.7); margin: 0; }
.bmi-badge {
  display: inline-block; padding: 4px 14px; border-radius: 999px;
  font-size: 0.82rem; font-weight: 700; margin-left: 10px;
  background: rgba(58,168,255,0.2); color: #a8d9ff;
  border: 1px solid rgba(58,168,255,0.25);
}
.bmi-badge.underweight { background: rgba(255,200,80,0.2); color: #ffd580; border-color: rgba(255,200,80,0.3); }
.bmi-badge.normal      { background: rgba(80,220,130,0.2); color: #7df5b0; border-color: rgba(80,220,130,0.3); }
.bmi-badge.overweight  { background: rgba(255,140,50,0.2); color: #ffb870; border-color: rgba(255,140,50,0.3); }
.bmi-badge.obese       { background: rgba(255,80,80,0.2);  color: #ffb3b3; border-color: rgba(255,80,80,0.3); }
.toast {
  position: fixed; bottom: 30px; right: 30px; z-index: 999;
  padding: 14px 22px; border-radius: 16px; font-size: 0.95rem; font-weight: 600;
  background: rgba(58,168,255,0.18); border: 1px solid rgba(58,168,255,0.35);
  color: #a8d9ff; backdrop-filter: blur(12px);
  opacity: 0; transform: translateY(20px);
  transition: opacity 0.35s, transform 0.35s;
  pointer-events: none;
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.error { background: rgba(255,80,80,0.18); border-color: rgba(255,80,80,0.3); color: #ffb3b3; }
.history-table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 0.9rem; }
.history-table th {
  text-align: left; padding: 10px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.12);
  color: rgba(238,245,255,0.6); font-weight: 600;
}
.history-table td {
  padding: 10px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  color: rgba(238,245,255,0.85);
}
.history-table tr:last-child td { border-bottom: none; }
.history-table tr:hover td { background: rgba(255,255,255,0.04); }
.greeting { padding: 40px 40px 10px; max-width: 1180px; margin: 0 auto; }
.greeting h2 { font-size: clamp(1.8rem, 3vw, 2.8rem); margin: 0 0 8px; }
.greeting p  { color: rgba(238,245,255,0.75); margin: 0; line-height: 1.7; }
.logout-btn {
  padding: 10px 20px; border-radius: 999px; border: 1px solid rgba(255,80,80,0.3);
  background: rgba(255,80,80,0.12); color: #ffb3b3;
  cursor: pointer; font-weight: 600; font-size: 0.9rem;
  text-decoration: none; transition: background 0.2s;
}
.logout-btn:hover { background: rgba(255,80,80,0.22); }
@media (max-width: 820px) {
  header { flex-direction: column; align-items: flex-start; gap: 14px; }
  .sections { padding: 0 20px; }
  .greeting, .section-title { padding-left: 20px; padding-right: 20px; }
  .input-row { grid-template-columns: 1fr; }
  .input-row.three { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ── HEADER ── -->
<header>
  <h1>💙 Smart Health Tracker</h1>
  <nav>
        <a href="#trackers">Trackers</a>
        <a href="#history">History</a>
        <a href="profile.php">👤 Profile</a>
        <a href="food.php">🥗 Food</a>
        <a href="reports.php">📊 Reports</a>
        <a href="ai_coach.php">🤖 AI Coach</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
</header>

<!-- ── GREETING ── -->
<div class="greeting">
  <h2>Hello, <?= htmlspecialchars($name) ?> 👋</h2>
  <p>Here's your health summary for <?= date('l, F j, Y') ?>. Log today's data below.</p>
</div>

<!-- ── LIVE TRACKER CARDS ── -->
<div class="section-title" id="trackers">
  <h2>Today's Wellness Trackers</h2>
  <p>Your progress so far today, compared to your goals.</p>
</div>

<main class="sections">

  <!-- Tracker cards -->
  <section class="tracker-section">
    <div class="tracker-grid">
      <!-- Steps -->
      <div class="tracker-card">
        <h4>Steps</h4>
        <p class="tracker-value" id="disp-steps"><?= number_format($rec['steps']) ?></p>
        <div class="progress-bar">
          <div class="progress-fill" id="bar-steps" style="width:<?= $stepsPct ?>%"></div>
        </div>
        <p class="progress-note">Goal: <?= number_format($goals['steps_goal']) ?> steps
          — <span id="steps-pct"><?= $stepsPct ?>%</span> complete</p>
      </div>
      <!-- Hydration -->
      <div class="tracker-card">
        <h4>Hydration</h4>
        <p class="tracker-value" id="disp-water"><?= number_format((float)$rec['water'], 1) ?>L</p>
        <div class="progress-bar">
          <div class="progress-fill" id="bar-water" style="width:<?= $waterPct ?>%"></div>
        </div>
        <p class="progress-note">Goal: <?= number_format((float)$goals['water_goal'], 1) ?>L
          — <span id="water-pct"><?= $waterPct ?>%</span> complete</p>
      </div>
      <!-- Sleep -->
      <div class="tracker-card">
        <h4>Sleep</h4>
        <p class="tracker-value" id="disp-sleep"><?= number_format((float)$rec['sleep'], 1) ?> hrs</p>
        <div class="progress-bar">
          <div class="progress-fill" id="bar-sleep" style="width:<?= $sleepPct ?>%"></div>
        </div>
        <p class="progress-note">Goal: <?= number_format((float)$goals['sleep_goal'], 1) ?> hrs
          — <span id="sleep-pct"><?= $sleepPct ?>%</span> complete</p>
      </div>
      <!-- BMI -->
      <div class="tracker-card">
        <h4>BMI</h4>
        <p class="tracker-value" id="disp-bmi">
          <?= $rec['bmi'] > 0 ? number_format((float)$rec['bmi'], 1) : '—' ?>
          <?php
          $cat = bmiCategory((float)$rec['bmi']);
          $catClass = strtolower(str_replace(' ', '', $cat));
          if ($cat !== '—'): ?>
          <span class="bmi-badge <?= $catClass ?>"><?= $cat ?></span>
          <?php endif; ?>
        </p>
        <div class="progress-bar">
          <div class="progress-fill" id="bar-bmi" style="width:<?= $rec['bmi'] > 0 ? min(100, round(($rec['bmi']/40)*100)) : 0 ?>%"></div>
        </div>
        <p class="progress-note">Healthy range: 18.5 – 24.9</p>
      </div>
    </div>
  </section>

  <!-- ── LOG TODAY'S ACTIVITY ── -->
  <section id="log" class="card">
    <h3>Log Today's Activity</h3>
    <p>Update your steps, water intake, and sleep for today.</p>
    <div class="input-row three">
      <div class="field-group">
        <label>Steps taken today</label>
        <input type="number" id="inp-steps" placeholder="e.g. 8500" min="0" max="100000"
               value="<?= (int)$rec['steps'] ?>">
      </div>
      <div class="field-group">
        <label>Water intake (litres)</label>
        <input type="number" id="inp-water" placeholder="e.g. 1.8" min="0" max="20" step="0.1"
               value="<?= $rec['water'] > 0 ? number_format((float)$rec['water'],1) : '' ?>">
      </div>
      <div class="field-group">
        <label>Sleep last night (hours)</label>
        <input type="number" id="inp-sleep" placeholder="e.g. 7.5" min="0" max="24" step="0.5"
               value="<?= $rec['sleep'] > 0 ? number_format((float)$rec['sleep'],1) : '' ?>">
      </div>
    </div>
    <button class="save-btn" onclick="saveRecord()">Save Today's Record</button>
  </section>

  <!-- ── BMI CALCULATOR ── -->
  <section id="profile" class="card">
    <h3>BMI Calculator &amp; Profile</h3>
    <p>Enter your measurements to calculate your BMI and update your profile.</p>
    <div class="input-row">
      <div class="field-group">
        <label>Height (metres, e.g. 1.75)</label>
        <input type="number" id="inp-height" placeholder="1.75" min="0.5" max="3" step="0.01"
               value="<?= !empty($profile['height']) ? $profile['height'] : '' ?>">
      </div>
      <div class="field-group">
        <label>Weight (kg)</label>
        <input type="number" id="inp-weight" placeholder="70" min="1" max="500" step="0.1"
               value="<?= !empty($profile['weight']) ? $profile['weight'] : '' ?>">
      </div>
    </div>
    <div class="input-row">
      <div class="field-group">
        <label>Age</label>
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
    <!-- Live BMI preview -->
    <p style="margin-top:16px; font-size:0.95rem;">
      Calculated BMI: <strong id="bmi-preview">—</strong>
      <span class="bmi-badge" id="bmi-cat-badge" style="display:none"></span>
    </p>
    <button class="save-btn" onclick="saveProfile()">Save Profile &amp; BMI</button>
  </section>

  <!-- ── GOALS ── -->
  <section id="goals" class="card">
    <h3>Daily Goals</h3>
    <p>Customise your daily health targets.</p>
    <div class="input-row three">
      <div class="field-group">
        <label>Steps goal</label>
        <input type="number" id="inp-steps-goal" min="1000" max="100000"
               value="<?= (int)$goals['steps_goal'] ?>">
      </div>
      <div class="field-group">
        <label>Water goal (L)</label>
        <input type="number" id="inp-water-goal" min="0.5" max="10" step="0.1"
               value="<?= number_format((float)$goals['water_goal'],1) ?>">
      </div>
      <div class="field-group">
        <label>Sleep goal (hrs)</label>
        <input type="number" id="inp-sleep-goal" min="1" max="12" step="0.5"
               value="<?= number_format((float)$goals['sleep_goal'],1) ?>">
      </div>
    </div>
    <button class="save-btn" onclick="saveGoals()">Update Goals</button>
  </section>

  <!-- ── 7-DAY HISTORY ── -->
  <section id="history" class="card">
    <h3>Last 7 Days History</h3>
    <p>Your recent health records at a glance.</p>
    <table class="history-table" id="history-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Steps</th>
          <th>Water (L)</th>
          <th>Sleep (hrs)</th>
          <th>BMI</th>
        </tr>
      </thead>
      <tbody id="history-body">
        <tr><td colspan="5" style="color:rgba(238,245,255,0.5);text-align:center;padding:20px;">Loading…</td></tr>
      </tbody>
    </table>
  </section>

</main>

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<script>
// ── Helpers ──────────────────────────────────────────────────
const toast = (msg, err = false) => {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast show' + (err ? ' error' : '');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3000);
};

const post = async (action, data) => {
  const fd = new FormData();
  fd.append('action', action);
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch('save_health.php', { method: 'POST', body: fd });
  return r.json();
};

// ── Save daily record ────────────────────────────────────────
async function saveRecord() {
  const steps = document.getElementById('inp-steps').value;
  const water = document.getElementById('inp-water').value;
  const sleep = document.getElementById('inp-sleep').value;

  if (!steps && !water && !sleep) { toast('Please enter at least one value.', true); return; }

  // Include current BMI if available
  const bmiVal = parseFloat(document.getElementById('inp-height').value || 0);
  const wt     = parseFloat(document.getElementById('inp-weight').value || 0);
  let bmi = 0;
  if (bmiVal > 0 && wt > 0) bmi = (wt / (bmiVal * bmiVal)).toFixed(2);

  const res = await post('save_record', { steps: steps||0, water: water||0, sleep: sleep||0, bmi });
  if (res.success) {
    toast('Today\'s record saved! ✓');
    updateDisplays(steps||0, water||0, sleep||0);
    loadHistory();
  } else {
    toast(res.message, true);
  }
}

// ── Live BMI preview ─────────────────────────────────────────
function recalcBmi() {
  const h = parseFloat(document.getElementById('inp-height').value);
  const w = parseFloat(document.getElementById('inp-weight').value);
  const preview = document.getElementById('bmi-preview');
  const badge   = document.getElementById('bmi-cat-badge');
  if (h > 0 && w > 0) {
    const bmi = (w / (h * h)).toFixed(1);
    preview.textContent = bmi;
    const cat = bmiCategory(parseFloat(bmi));
    badge.textContent = cat;
    badge.className = 'bmi-badge ' + cat.toLowerCase().replace(' ','');
    badge.style.display = 'inline-block';
  } else {
    preview.textContent = '—';
    badge.style.display = 'none';
  }
}
document.getElementById('inp-height').addEventListener('input', recalcBmi);
document.getElementById('inp-weight').addEventListener('input', recalcBmi);

function bmiCategory(bmi) {
  if (!bmi || bmi <= 0) return '—';
  if (bmi < 18.5) return 'Underweight';
  if (bmi < 25)   return 'Normal';
  if (bmi < 30)   return 'Overweight';
  return 'Obese';
}

// ── Save profile ─────────────────────────────────────────────
async function saveProfile() {
  const h = document.getElementById('inp-height').value;
  const w = document.getElementById('inp-weight').value;
  if (!h || !w) { toast('Height and weight are required to calculate BMI.', true); return; }

  const res = await post('save_profile', {
    height: h,
    weight: w,
    age:    document.getElementById('inp-age').value    || 0,
    gender: document.getElementById('inp-gender').value || 'other'
  });
  if (res.success) {
    toast('Profile & BMI saved! ✓');
    // Update BMI card
    const bmi = parseFloat(res.bmi).toFixed(1);
    const cat = bmiCategory(parseFloat(bmi));
    document.getElementById('disp-bmi').innerHTML =
      bmi + ' <span class="bmi-badge ' + cat.toLowerCase().replace(' ','') + '">' + cat + '</span>';
    document.getElementById('bar-bmi').style.width = Math.min(100, Math.round((res.bmi/40)*100)) + '%';
    loadHistory();
  } else {
    toast(res.message, true);
  }
}

// ── Save goals ───────────────────────────────────────────────
async function saveGoals() {
  const res = await post('save_goals', {
    steps_goal: document.getElementById('inp-steps-goal').value,
    water_goal: document.getElementById('inp-water-goal').value,
    sleep_goal: document.getElementById('inp-sleep-goal').value,
  });
  if (res.success) {
    toast('Goals updated! ✓');
  } else {
    toast(res.message, true);
  }
}

// ── Update tracker displays without reload ───────────────────
function updateDisplays(steps, water, sleep) {
  const sg = parseFloat(document.getElementById('inp-steps-goal').value) || <?= (int)$goals['steps_goal'] ?>;
  const wg = parseFloat(document.getElementById('inp-water-goal').value) || <?= (float)$goals['water_goal'] ?>;
  const lg = parseFloat(document.getElementById('inp-sleep-goal').value) || <?= (float)$goals['sleep_goal'] ?>;

  document.getElementById('disp-steps').textContent = Number(steps).toLocaleString();
  document.getElementById('disp-water').textContent = parseFloat(water).toFixed(1) + 'L';
  document.getElementById('disp-sleep').textContent = parseFloat(sleep).toFixed(1) + ' hrs';

  const sp = Math.min(100, Math.round((steps/sg)*100));
  const wp = Math.min(100, Math.round((water/wg)*100));
  const lp = Math.min(100, Math.round((sleep/lg)*100));

  document.getElementById('bar-steps').style.width = sp + '%';
  document.getElementById('bar-water').style.width = wp + '%';
  document.getElementById('bar-sleep').style.width = lp + '%';
  document.getElementById('steps-pct').textContent = sp + '%';
  document.getElementById('water-pct').textContent = wp + '%';
  document.getElementById('sleep-pct').textContent = lp + '%';
}

// ── Load 7-day history ────────────────────────────────────────
async function loadHistory() {
  try {
    const res = await fetch('get_health.php');
    const data = await res.json();
    const tbody = document.getElementById('history-body');

    if (!data.success || !data.history.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="color:rgba(238,245,255,0.5);text-align:center;padding:20px;">No records yet. Log your first entry above!</td></tr>';
      return;
    }

    tbody.innerHTML = data.history.map(r => {
      const bmi = parseFloat(r.bmi) > 0 ? parseFloat(r.bmi).toFixed(1) : '—';
      const cat = parseFloat(r.bmi) > 0 ? bmiCategory(parseFloat(r.bmi)) : '';
      const badge = cat ? `<span class="bmi-badge ${cat.toLowerCase().replace(' ','')}" style="font-size:0.75rem;padding:2px 8px;">${cat}</span>` : '';
      return `<tr>
        <td>${r.record_date}</td>
        <td>${Number(r.steps).toLocaleString()}</td>
        <td>${parseFloat(r.water).toFixed(1)}</td>
        <td>${parseFloat(r.sleep).toFixed(1)}</td>
        <td>${bmi} ${badge}</td>
      </tr>`;
    }).join('');
  } catch(e) {
    console.error('History load failed', e);
  }
}

// Init: trigger BMI preview if values already present
recalcBmi();
loadHistory();

// Animate progress bars on load
setTimeout(() => {
  document.querySelectorAll('.progress-fill').forEach(el => {
    const w = el.style.width;
    el.style.width = '0';
    setTimeout(() => el.style.width = w, 50);
  });
}, 100);
</script>

</body>
</html>