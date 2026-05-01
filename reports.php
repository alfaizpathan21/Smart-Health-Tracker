<?php
// ============================================================
// reports.php — Analytics & Reports page
// ============================================================
require_once 'config.php';
requireLogin();

$uid  = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';

// ── Fetch last 30 days of health records ─────────────────────
$stmt = $conn->prepare(
    'SELECT record_date, steps, water, sleep, bmi
     FROM health_records
     WHERE user_id = ?
     ORDER BY record_date ASC
     LIMIT 30'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$health_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch last 30 days of food calories ──────────────────────
$stmt = $conn->prepare(
    'SELECT log_date,
            COALESCE(SUM(calories), 0) AS total_calories,
            COALESCE(SUM(protein),  0) AS total_protein,
            COALESCE(SUM(carbs),    0) AS total_carbs,
            COALESCE(SUM(fats),     0) AS total_fats
     FROM food_logs
     WHERE user_id = ?
     GROUP BY log_date
     ORDER BY log_date ASC
     LIMIT 30'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$food_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Weekly summary (last 7 days) ──────────────────────────────
$stmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(steps), 0)       AS total_steps,
        COALESCE(AVG(sleep), 0)       AS avg_sleep,
        COALESCE(SUM(water), 0)       AS total_water,
        COUNT(*)                      AS days_logged
     FROM health_records
     WHERE user_id = ? AND record_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$weekly = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// ── Previous week for comparison ─────────────────────────────
$stmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(steps), 0) AS total_steps,
        COALESCE(AVG(sleep), 0) AS avg_sleep,
        COALESCE(SUM(water), 0) AS total_water
     FROM health_records
     WHERE user_id = ?
       AND record_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
       AND record_date <  DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$prevWeekly = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// ── Weekly calories ───────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT COALESCE(SUM(calories), 0) AS total_calories
     FROM food_logs
     WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$weeklyFood = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// ── Build chart data arrays (PHP → JSON) ─────────────────────
// Health chart data
$chartDates   = array_column($health_rows, 'record_date');
$chartSteps   = array_column($health_rows, 'steps');
$chartSleep   = array_column($health_rows, 'sleep');
$chartWater   = array_column($health_rows, 'water');

// Calorie chart — merge dates
$calMap = [];
foreach ($food_rows as $fr) { $calMap[$fr['log_date']] = $fr['total_calories']; }
$chartCalDates = array_column($food_rows, 'log_date');
$chartCal      = array_column($food_rows, 'total_calories');

// ── Insights generator ────────────────────────────────────────
$insights = [];
$stepsThisWeek = (int)($weekly['total_steps'] ?? 0);
$stepsLastWeek = (int)($prevWeekly['total_steps'] ?? 0);
$avgSleep      = round((float)($weekly['avg_sleep'] ?? 0), 1);
$avgSleepPrev  = round((float)($prevWeekly['avg_sleep'] ?? 0), 1);

if ($stepsThisWeek > $stepsLastWeek && $stepsLastWeek > 0) {
    $diff = $stepsThisWeek - $stepsLastWeek;
    $insights[] = ['icon' => '🏃', 'type' => 'positive',
        'text' => "You walked " . number_format($diff) . " more steps this week vs last week. Great momentum!"];
} elseif ($stepsThisWeek < $stepsLastWeek && $stepsLastWeek > 0) {
    $insights[] = ['icon' => '⚡', 'type' => 'warning',
        'text' => "Your step count dipped this week. Try a short walk to hit your daily goal."];
}
if ($avgSleep > $avgSleepPrev && $avgSleepPrev > 0) {
    $insights[] = ['icon' => '😴', 'type' => 'positive',
        'text' => "Your sleep improved by " . round($avgSleep - $avgSleepPrev, 1) . " hrs on average this week. Keep it up!"];
} elseif ($avgSleep < 6 && $avgSleep > 0) {
    $insights[] = ['icon' => '🌙', 'type' => 'warning',
        'text' => "You're averaging only {$avgSleep} hrs of sleep. Aim for at least 7–8 hrs for better recovery."];
}
$totalWater = round((float)($weekly['total_water'] ?? 0), 1);
if ($totalWater > 0) {
    $avgDaily = round($totalWater / max(1, (int)($weekly['days_logged'] ?? 1)), 1);
    if ($avgDaily >= 2.0) {
        $insights[] = ['icon' => '💧', 'type' => 'positive',
            'text' => "Excellent hydration! You averaged {$avgDaily}L of water per day this week."];
    } else {
        $insights[] = ['icon' => '💧', 'type' => 'info',
            'text' => "Your average water intake was {$avgDaily}L/day. Try to reach 2L daily for optimal hydration."];
    }
}
$weeklyCal = (int)($weeklyFood['total_calories'] ?? 0);
if ($weeklyCal > 0) {
    $avgDailyCal = round($weeklyCal / 7);
    if ($avgDailyCal > 2800) {
        $insights[] = ['icon' => '🍽️', 'type' => 'warning',
            'text' => "Your average daily calorie intake is {$avgDailyCal} kcal — slightly above the recommended range for most adults."];
    } elseif ($avgDailyCal < 1200) {
        $insights[] = ['icon' => '🍽️', 'type' => 'warning',
            'text' => "Average daily calories logged: {$avgDailyCal} kcal. Make sure you're eating enough to fuel your day."];
    } else {
        $insights[] = ['icon' => '🍽️', 'type' => 'positive',
            'text' => "Nice balance! Your average daily calorie intake this week is {$avgDailyCal} kcal."];
    }
}
if (empty($insights)) {
    $insights[] = ['icon' => '📊', 'type' => 'info',
        'text' => 'Log more health data to unlock personalised weekly insights here.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports | Smart Health Tracker</title>
<style>
/* ── Shared theme ── */
:root { --primary:#0f4c75; --accent:#3aa8ff; --shadow:0 24px 80px rgba(15,76,117,0.18); }
* { box-sizing:border-box; }
html,body {
  margin:0; min-height:100%;
  font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
  background: radial-gradient(circle at top left,rgba(58,168,255,0.22),transparent 35%),
              radial-gradient(circle at bottom right,rgba(15,76,117,0.35),transparent 25%),
              linear-gradient(180deg,#051128 0%,#092040 45%,#0f4c75 100%);
  color:#eef5ff;
}
body { overflow-x:hidden; }
header {
  position:sticky; top:0; z-index:10;
  backdrop-filter:blur(18px); background:rgba(5,17,40,0.72);
  border-bottom:1px solid rgba(255,255,255,0.08);
  display:flex; align-items:center; justify-content:space-between; padding:18px 40px;
}
header h1 { font-size:1.4rem; letter-spacing:0.04em; margin:0; }
nav { display:flex; gap:22px; align-items:center; flex-wrap:wrap; }
nav a { color:#e6f0ff; text-decoration:none; font-weight:600; transition:color 0.25s; }
nav a:hover, nav a.active { color:var(--accent); }
.logout-btn {
  padding:10px 20px; border-radius:999px; border:1px solid rgba(255,80,80,0.3);
  background:rgba(255,80,80,0.12); color:#ffb3b3;
  text-decoration:none; font-weight:600; font-size:0.9rem; transition:background 0.2s;
}
.logout-btn:hover { background:rgba(255,80,80,0.22); }
.sections { display:grid; gap:28px; max-width:1180px; margin:0 auto 60px; padding:0 40px; }
.section-title { padding:40px 40px 16px; max-width:1180px; margin:0 auto; }
.section-title h2 { font-size:1.8rem; margin:0 0 4px; }
.section-title p  { color:rgba(238,245,255,0.7); margin:0; }
.card {
  background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.10);
  border-radius:24px; padding:28px; box-shadow:var(--shadow);
}
.card h3  { margin:0 0 6px; font-size:1.15rem; }
.card .sub { color:rgba(238,245,255,0.65); margin:0 0 20px; font-size:0.88rem; }
/* Summary stats */
.stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:16px; }
.stat-box {
  background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.10);
  border-radius:18px; padding:20px; text-align:center;
}
.stat-box .val { font-size:1.9rem; font-weight:700; margin:0 0 4px; color:#fff; }
.stat-box .lbl { font-size:0.78rem; color:rgba(238,245,255,0.6); }
.stat-box .chg { font-size:0.8rem; margin-top:6px; }
.chg.up   { color:#7df5b0; }
.chg.down { color:#ffb3b3; }
.chg.neu  { color:rgba(238,245,255,0.5); }
/* Insights */
.insights-list { display:grid; gap:12px; }
.insight-item {
  display:flex; align-items:flex-start; gap:14px;
  background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.09);
  border-radius:16px; padding:16px;
}
.insight-icon { font-size:1.5rem; flex-shrink:0; margin-top:2px; }
.insight-text { font-size:0.92rem; line-height:1.6; color:rgba(238,245,255,0.88); }
.insight-item.positive { border-color:rgba(80,220,130,0.2); }
.insight-item.warning  { border-color:rgba(255,140,50,0.22); }
.insight-item.info     { border-color:rgba(58,168,255,0.18); }
/* Chart wrapper */
.chart-wrap { position:relative; height:260px; }
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.pill {
  display:inline-block; padding:3px 12px; border-radius:999px;
  font-size:0.75rem; font-weight:700; letter-spacing:0.04em;
  background:rgba(58,168,255,0.2); color:#a8d9ff;
  border:1px solid rgba(58,168,255,0.25); margin-bottom:10px;
}
@media (max-width:820px) {
  header { flex-direction:column; align-items:flex-start; gap:14px; }
  .sections { padding:0 20px; }
  .section-title { padding-left:20px; padding-right:20px; }
  .two-col { grid-template-columns:1fr; }
  .chart-wrap { height:200px; }
}
</style>
</head>
<body>

<header>
  <h1>💙 Smart Health Tracker</h1>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="profile.php">Profile</a>
    <a href="food.php">Food</a>
    <a href="reports.php" class="active">Reports</a>
    <a href="ai_coach.php">AI Coach</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<div class="section-title">
  <h2>📊 Reports &amp; Analytics</h2>
  <p>Your health trends, weekly summaries, and personalised insights.</p>
</div>

<main class="sections">

  <!-- ── WEEKLY SUMMARY ── -->
  <section class="card">
    <span class="pill">This Week</span>
    <h3>7-Day Summary</h3>
    <p class="sub">Aggregated totals and averages for the past 7 days.</p>

    <div class="stats-row">
      <div class="stat-box">
        <div class="val"><?= number_format((int)($weekly['total_steps'] ?? 0)) ?></div>
        <div class="lbl">Total Steps</div>
        <?php
          $diff = (int)($weekly['total_steps'] ?? 0) - (int)($prevWeekly['total_steps'] ?? 0);
          $cls  = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'neu');
          $arrow = $diff > 0 ? '▲' : ($diff < 0 ? '▼' : '–');
        ?>
        <div class="chg <?= $cls ?>"><?= $arrow ?> <?= number_format(abs($diff)) ?> vs last week</div>
      </div>
      <div class="stat-box">
        <div class="val"><?= number_format((float)($weekly['avg_sleep'] ?? 0), 1) ?>h</div>
        <div class="lbl">Avg Sleep / Night</div>
        <?php
          $diff2 = round((float)($weekly['avg_sleep'] ?? 0) - (float)($prevWeekly['avg_sleep'] ?? 0), 1);
          $cls2  = $diff2 > 0 ? 'up' : ($diff2 < 0 ? 'down' : 'neu');
          $arr2  = $diff2 > 0 ? '▲' : ($diff2 < 0 ? '▼' : '–');
        ?>
        <div class="chg <?= $cls2 ?>"><?= $arr2 ?> <?= abs($diff2) ?>h vs last week</div>
      </div>
      <div class="stat-box">
        <div class="val"><?= number_format((float)($weekly['total_water'] ?? 0), 1) ?>L</div>
        <div class="lbl">Total Water Intake</div>
        <div class="chg neu"><?= (int)($weekly['days_logged'] ?? 0) ?> days logged</div>
      </div>
      <div class="stat-box">
        <div class="val"><?= number_format((int)($weeklyFood['total_calories'] ?? 0)) ?></div>
        <div class="lbl">Total Calories (kcal)</div>
        <div class="chg neu">~<?= number_format(round((int)($weeklyFood['total_calories'] ?? 0) / 7)) ?> kcal/day avg</div>
      </div>
    </div>
  </section>

  <!-- ── INSIGHTS ── -->
  <section class="card">
    <span class="pill">Insights</span>
    <h3>Personalised Observations</h3>
    <p class="sub">Pattern-based observations drawn from your logged data.</p>
    <div class="insights-list">
      <?php foreach ($insights as $ins): ?>
        <div class="insight-item <?= $ins['type'] ?>">
          <div class="insight-icon"><?= $ins['icon'] ?></div>
          <div class="insight-text"><?= htmlspecialchars($ins['text']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── STEPS CHART ── -->
  <section class="card">
    <span class="pill">Activity</span>
    <h3>Steps Over Time</h3>
    <p class="sub">Your daily step count across the logged period.</p>
    <div class="chart-wrap">
      <canvas id="chart-steps"></canvas>
    </div>
  </section>

  <!-- ── SLEEP + WATER CHARTS ── -->
  <div class="two-col">
    <section class="card">
      <span class="pill">Rest</span>
      <h3>Sleep Pattern</h3>
      <p class="sub">Hours of sleep logged per night.</p>
      <div class="chart-wrap">
        <canvas id="chart-sleep"></canvas>
      </div>
    </section>
    <section class="card">
      <span class="pill">Hydration</span>
      <h3>Water Intake</h3>
      <p class="sub">Daily water intake in litres.</p>
      <div class="chart-wrap">
        <canvas id="chart-water"></canvas>
      </div>
    </section>
  </div>

  <!-- ── CALORIE CHART ── -->
  <section class="card">
    <span class="pill">Nutrition</span>
    <h3>Calorie Intake Over Time</h3>
    <p class="sub">Total daily calories logged from food tracker.</p>
    <div class="chart-wrap">
      <canvas id="chart-cal"></canvas>
    </div>
  </section>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── PHP data → JS ────────────────────────────────────────────
const healthDates = <?= json_encode($chartDates) ?>;
const healthSteps = <?= json_encode(array_map('intval',   $chartSteps)) ?>;
const healthSleep = <?= json_encode(array_map('floatval', $chartSleep)) ?>;
const healthWater = <?= json_encode(array_map('floatval', $chartWater)) ?>;
const calDates    = <?= json_encode($chartCalDates) ?>;
const calData     = <?= json_encode(array_map('floatval', $chartCal)) ?>;

// ── Shared chart defaults ─────────────────────────────────────
Chart.defaults.color              = 'rgba(238,245,255,0.65)';
Chart.defaults.borderColor        = 'rgba(255,255,255,0.07)';
Chart.defaults.plugins.legend.display = false;

const gridOpts = {
  color: 'rgba(255,255,255,0.07)',
  drawBorder: false
};

function shortDate(d) {
  if (!d) return '';
  const parts = d.split('-');
  return parts[2] + '/' + parts[1];   // DD/MM
}

function makeLineChart(id, labels, values, color, label, fill = true) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels.map(shortDate),
      datasets: [{
        label,
        data: values,
        borderColor: color,
        backgroundColor: fill ? color.replace('1)', '0.12)').replace('rgb','rgba') : 'transparent',
        borderWidth: 2.5,
        pointRadius: 4,
        pointBackgroundColor: color,
        pointBorderColor: '#092040',
        pointBorderWidth: 2,
        tension: 0.35,
        fill,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { tooltip: { callbacks: { label: ctx => label + ': ' + ctx.parsed.y } } },
      scales: {
        x: { grid: gridOpts, ticks: { maxTicksLimit: 10 } },
        y: { grid: gridOpts, beginAtZero: true }
      }
    }
  });
}

// ── Render charts ─────────────────────────────────────────────
if (healthDates.length) {
  makeLineChart('chart-steps', healthDates, healthSteps, 'rgb(58,168,255)', 'Steps');
  makeLineChart('chart-sleep', healthDates, healthSleep, 'rgb(167,139,250)', 'Sleep (hrs)');
  makeLineChart('chart-water', healthDates, healthWater, 'rgb(56,230,200)', 'Water (L)');
} else {
  ['chart-steps','chart-sleep','chart-water'].forEach(id => {
    const c = document.getElementById(id);
    if (c) c.parentElement.innerHTML = '<p style="text-align:center;color:rgba(238,245,255,0.4);padding:60px 0;">No health records logged yet.</p>';
  });
}

if (calDates.length) {
  // Bar chart for calories
  new Chart(document.getElementById('chart-cal'), {
    type: 'bar',
    data: {
      labels: calDates.map(shortDate),
      datasets: [{
        label: 'Calories (kcal)',
        data: calData,
        backgroundColor: 'rgba(255,200,80,0.35)',
        borderColor: 'rgba(255,200,80,0.9)',
        borderWidth: 1.5,
        borderRadius: 8,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' kcal' } } },
      scales: {
        x: { grid: gridOpts },
        y: { grid: gridOpts, beginAtZero: true }
      }
    }
  });
} else {
  const c = document.getElementById('chart-cal');
  if (c) c.parentElement.innerHTML = '<p style="text-align:center;color:rgba(238,245,255,0.4);padding:60px 0;">No food entries logged yet.</p>';
}
</script>

</body>
</html>
