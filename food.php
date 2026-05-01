<?php
// ============================================================
// food.php — Food intake tracker page
// ============================================================
require_once 'config.php';
requireLogin();

$uid  = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Food Tracker | Smart Health Tracker</title>
<style>
/* ── Shared theme — identical to dashboard.php ── */
:root {
  --primary: #0f4c75;
  --accent:  #3aa8ff;
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
.logout-btn {
  padding: 10px 20px; border-radius: 999px; border: 1px solid rgba(255,80,80,0.3);
  background: rgba(255,80,80,0.12); color: #ffb3b3;
  text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;
}
.logout-btn:hover { background: rgba(255,80,80,0.22); }
.sections { display: grid; gap: 28px; max-width: 1100px; margin: 0 auto 60px; padding: 0 40px; }
.section-title { padding: 40px 40px 16px; max-width: 1100px; margin: 0 auto; }
.section-title h2 { font-size: 1.8rem; margin: 0 0 4px; }
.section-title p  { color: rgba(238,245,255,0.7); margin: 0; }
.card {
  background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.10);
  border-radius: 24px; padding: 28px; box-shadow: var(--shadow);
}
.card h3 { margin: 0 0 6px; font-size: 1.15rem; }
.card > .sub { color: rgba(238,245,255,0.7); margin: 0 0 20px; font-size: 0.9rem; }
.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.input-row.four { grid-template-columns: repeat(4, 1fr); }
.input-row.five { grid-template-columns: 2fr 1fr 1fr 1fr 1fr; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label { font-size: 0.82rem; color: rgba(238,245,255,0.7); }
.field-group input {
  padding: 11px 14px; border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.08); color: #eef5ff;
  outline: none; font-size: 0.95rem; width: 100%;
  transition: border-color 0.2s;
}
.field-group input:focus { border-color: rgba(58,168,255,0.5); }
.add-btn {
  margin-top: 18px; padding: 12px 28px; border-radius: 14px; border: none;
  background: linear-gradient(135deg, #3aa8ff, #0f4c75);
  color: white; font-size: 0.95rem; cursor: pointer; font-weight: 600;
  transition: transform 0.2s, box-shadow 0.2s;
}
.add-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(58,168,255,0.25); }
/* Macro summary cards */
.macro-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 16px; margin-bottom: 24px;
}
.macro-card {
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.10);
  border-radius: 18px; padding: 20px; text-align: center;
}
.macro-card .macro-val { font-size: 1.8rem; font-weight: 700; margin: 0 0 4px; }
.macro-card .macro-label { font-size: 0.78rem; color: rgba(238,245,255,0.65); }
.macro-card.calories .macro-val { color: #ffd580; }
.macro-card.protein  .macro-val { color: #7df5b0; }
.macro-card.carbs    .macro-val { color: #a8d9ff; }
.macro-card.fats     .macro-val { color: #ffb870; }
/* Progress bar for macros */
.macro-bar { height: 6px; background: rgba(255,255,255,0.10); border-radius: 999px; overflow: hidden; margin-top: 8px; }
.macro-fill { height: 100%; border-radius: inherit; transition: width 0.8s ease; }
.calories .macro-fill { background: linear-gradient(90deg, #ffd580, #ffb830); }
.protein  .macro-fill { background: linear-gradient(90deg, #7df5b0, #3acf7c); }
.carbs    .macro-fill { background: linear-gradient(90deg, #a8d9ff, #3aa8ff); }
.fats     .macro-fill { background: linear-gradient(90deg, #ffb870, #ff8c32); }
/* Food log table */
.food-table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 0.9rem; }
.food-table th {
  text-align: left; padding: 10px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.12);
  color: rgba(238,245,255,0.6); font-weight: 600;
}
.food-table td {
  padding: 11px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  color: rgba(238,245,255,0.85);
}
.food-table tr:last-child td { border-bottom: none; }
.food-table tr:hover td { background: rgba(255,255,255,0.04); }
.food-table .del-btn {
  background: none; border: none; color: rgba(255,80,80,0.6); cursor: pointer;
  font-size: 1rem; padding: 2px 6px; border-radius: 6px; transition: color 0.2s;
}
.food-table .del-btn:hover { color: #ff7b7b; }
.empty-state {
  text-align: center; padding: 40px 20px;
  color: rgba(238,245,255,0.45); font-size: 0.95rem;
}
.empty-state .emoji { font-size: 2rem; margin-bottom: 10px; }
/* Date picker row */
.date-row {
  display: flex; align-items: center; gap: 14px; margin-bottom: 20px; flex-wrap: wrap;
}
.date-row label { font-size: 0.85rem; color: rgba(238,245,255,0.65); }
.date-row input[type="date"] {
  padding: 8px 14px; border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.08); color: #eef5ff;
  outline: none; font-size: 0.9rem;
}
.date-row input[type="date"]:focus { border-color: rgba(58,168,255,0.5); }
/* Feature pill */
.pill {
  display: inline-block; padding: 3px 12px; border-radius: 999px;
  font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em;
  background: rgba(58,168,255,0.2); color: #a8d9ff;
  border: 1px solid rgba(58,168,255,0.25); margin-bottom: 10px;
}
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
@media (max-width: 820px) {
  header { flex-direction: column; align-items: flex-start; gap: 14px; }
  .sections { padding: 0 20px; }
  .section-title { padding-left: 20px; padding-right: 20px; }
  .input-row.five, .input-row.four { grid-template-columns: 1fr 1fr; }
  .input-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<header>
  <h1>💙 Smart Health Tracker</h1>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="profile.php">Profile</a>
    <a href="food.php" class="active">Food</a>
    <a href="reports.php">Reports</a>
    <a href="ai_coach.php">AI Coach</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<div class="section-title">
  <h2>🥗 Food Intake Tracker</h2>
  <p>Log your meals and track your daily nutrition goals.</p>
</div>

<main class="sections">

  <!-- ── ADD FOOD FORM ── -->
  <section class="card">
    <span class="pill">Add Entry</span>
    <h3>Log a Meal or Snack</h3>
    <p class="sub">Enter the food details below. Calories are required; macros are optional.</p>

    <!-- Date picker row -->
    <div class="date-row" style="margin-bottom:16px;">
      <label>Log date:</label>
      <input type="date" id="inp-log-date"
             value="<?= date('Y-m-d') ?>"
             max="<?= date('Y-m-d') ?>">
      <span style="font-size:0.82rem;color:rgba(238,245,255,0.45);">You can log food for any past date.</span>
    </div>

    <div class="input-row five">
      <div class="field-group">
        <label>Food Name *</label>
        <input type="text" id="inp-food-name" placeholder="e.g. Grilled Chicken">
      </div>
      <div class="field-group">
        <label>Calories (kcal)</label>
        <input type="number" id="inp-calories" placeholder="e.g. 350" min="0" max="9999" step="1">
      </div>
      <div class="field-group">
        <label>Protein (g)</label>
        <input type="number" id="inp-protein" placeholder="e.g. 30" min="0" max="999" step="0.1">
      </div>
      <div class="field-group">
        <label>Carbs (g)</label>
        <input type="number" id="inp-carbs" placeholder="e.g. 10" min="0" max="999" step="0.1">
      </div>
      <div class="field-group">
        <label>Fats (g)</label>
        <input type="number" id="inp-fats" placeholder="e.g. 8" min="0" max="999" step="0.1">
      </div>
    </div>
    <button class="add-btn" onclick="addFood()">+ Add Food</button>
  </section>

  <!-- ── DAILY MACRO SUMMARY ── -->
  <section class="card">
    <span class="pill">Today's Summary</span>
    <h3>Nutrition Overview</h3>
    <p class="sub">Your total macro intake for the selected date.</p>

    <div class="date-row">
      <label>Viewing date:</label>
      <input type="date" id="log-date"
             value="<?= date('Y-m-d') ?>"
             max="<?= date('Y-m-d') ?>"
             onchange="loadFood()">
    </div>

    <div class="macro-grid">
      <div class="macro-card calories">
        <div class="macro-val" id="total-cal">0</div>
        <div class="macro-label">Calories (kcal)</div>
        <div class="macro-bar"><div class="macro-fill" id="bar-cal" style="width:0%"></div></div>
      </div>
      <div class="macro-card protein">
        <div class="macro-val" id="total-pro">0g</div>
        <div class="macro-label">Protein</div>
        <div class="macro-bar"><div class="macro-fill" id="bar-pro" style="width:0%"></div></div>
      </div>
      <div class="macro-card carbs">
        <div class="macro-val" id="total-carb">0g</div>
        <div class="macro-label">Carbohydrates</div>
        <div class="macro-bar"><div class="macro-fill" id="bar-carb" style="width:0%"></div></div>
      </div>
      <div class="macro-card fats">
        <div class="macro-val" id="total-fat">0g</div>
        <div class="macro-label">Fats</div>
        <div class="macro-bar"><div class="macro-fill" id="bar-fat" style="width:0%"></div></div>
      </div>
    </div>

    <!-- Daily food log table -->
    <table class="food-table">
      <thead>
        <tr>
          <th>Food</th>
          <th>Calories</th>
          <th>Protein</th>
          <th>Carbs</th>
          <th>Fats</th>
          <th>Time</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="food-tbody">
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="emoji">🍽️</div>
            <div>Loading food log…</div>
          </div>
        </td></tr>
      </tbody>
    </table>
  </section>

</main>

<div class="toast" id="toast"></div>

<script>
// ── Toast ────────────────────────────────────────────────────
const toast = (msg, err = false) => {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast show' + (err ? ' error' : '');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3200);
};

// ── Add food ─────────────────────────────────────────────────
async function addFood() {
  const name    = document.getElementById('inp-food-name').value.trim();
  const logDate = document.getElementById('inp-log-date').value;
  if (!name)    { toast('Please enter a food name.', true); return; }
  if (!logDate) { toast('Please select a log date.', true); return; }

  const fd = new FormData();
  fd.append('food_name', name);
  fd.append('log_date',  logDate);
  fd.append('calories',  document.getElementById('inp-calories').value || 0);
  fd.append('protein',   document.getElementById('inp-protein').value  || 0);
  fd.append('carbs',     document.getElementById('inp-carbs').value    || 0);
  fd.append('fats',      document.getElementById('inp-fats').value     || 0);

  const r   = await fetch('add_food.php', { method: 'POST', body: fd });
  const res = await r.json();

  if (res.success) {
    toast('Food logged for ' + logDate + '! ✓');
    // Clear food inputs but keep the chosen date
    ['inp-food-name','inp-calories','inp-protein','inp-carbs','inp-fats']
      .forEach(id => document.getElementById(id).value = '');
    // Sync the view panel to show the date that was just logged
    document.getElementById('log-date').value = logDate;
    loadFood();
  } else {
    toast(res.message, true);
  }
}

// ── Load food log ────────────────────────────────────────────
async function loadFood() {
  const date  = document.getElementById('log-date').value;
  const r     = await fetch('get_food.php?date=' + date);
  const data  = await r.json();

  if (!data.success) { toast('Failed to load food log.', true); return; }

  const tbody = document.getElementById('food-tbody');
  const tot   = data.totals;

  // Update macro totals
  const maxCal = 2500, maxPro = 150, maxCarb = 300, maxFat = 80;
  document.getElementById('total-cal').textContent  = Math.round(tot.total_calories);
  document.getElementById('total-pro').textContent  = parseFloat(tot.total_protein).toFixed(1) + 'g';
  document.getElementById('total-carb').textContent = parseFloat(tot.total_carbs).toFixed(1)   + 'g';
  document.getElementById('total-fat').textContent  = parseFloat(tot.total_fats).toFixed(1)    + 'g';

  document.getElementById('bar-cal').style.width  = Math.min(100, (tot.total_calories / maxCal * 100).toFixed(1)) + '%';
  document.getElementById('bar-pro').style.width  = Math.min(100, (tot.total_protein  / maxPro  * 100).toFixed(1)) + '%';
  document.getElementById('bar-carb').style.width = Math.min(100, (tot.total_carbs    / maxCarb * 100).toFixed(1)) + '%';
  document.getElementById('bar-fat').style.width  = Math.min(100, (tot.total_fats     / maxFat  * 100).toFixed(1)) + '%';

  // Update table
  if (!data.entries.length) {
    tbody.innerHTML = `<tr><td colspan="7">
      <div class="empty-state">
        <div class="emoji">🍽️</div>
        <div>No food logged for this date. Add your first entry above!</div>
      </div></td></tr>`;
    return;
  }

  tbody.innerHTML = data.entries.map(e => {
    const time = e.created_at ? e.created_at.split(' ')[1].substring(0, 5) : '—';
    return `<tr>
      <td style="font-weight:600;">${escHtml(e.food_name)}</td>
      <td style="color:#ffd580;">${Math.round(e.calories)} kcal</td>
      <td style="color:#7df5b0;">${parseFloat(e.protein).toFixed(1)}g</td>
      <td style="color:#a8d9ff;">${parseFloat(e.carbs).toFixed(1)}g</td>
      <td style="color:#ffb870;">${parseFloat(e.fats).toFixed(1)}g</td>
      <td style="color:rgba(238,245,255,0.55);">${time}</td>
      <td><button class="del-btn" onclick="deleteFood(${e.food_id})" title="Remove">✕</button></td>
    </tr>`;
  }).join('');
}

// ── Delete food ──────────────────────────────────────────────
async function deleteFood(id) {
  if (!confirm('Remove this food entry?')) return;
  const fd = new FormData();
  fd.append('food_id', id);
  const r   = await fetch('delete_food.php', { method: 'POST', body: fd });
  const res = await r.json();
  if (res.success) { toast('Entry removed.'); loadFood(); }
  else toast(res.message, true);
}

// ── XSS helper ───────────────────────────────────────────────
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Init
loadFood();
</script>

</body>
</html>
