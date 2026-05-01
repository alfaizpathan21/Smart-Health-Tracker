<?php
// ============================================================
// ai_coach.php — Rule-based AI Wellness Coach
// ============================================================
require_once 'config.php';
requireLogin();

$uid  = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';
$firstName = explode(' ', trim($name))[0];

// ── Fetch today's health data ─────────────────────────────────
$today = date('Y-m-d');
$stmt  = $conn->prepare(
    'SELECT steps, water, sleep, bmi FROM health_records WHERE user_id = ? AND record_date = ?'
);
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc() ?? ['steps'=>0,'water'=>0,'sleep'=>0,'bmi'=>0];
$stmt->close();

// ── Fetch today's calories ────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT COALESCE(SUM(calories), 0) AS total_cal FROM food_logs WHERE user_id = ? AND log_date = ?'
);
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$foodToday = $stmt->get_result()->fetch_assoc() ?? ['total_cal' => 0];
$stmt->close();

// ── Fetch goals ───────────────────────────────────────────────
$stmt = $conn->prepare('SELECT steps_goal, water_goal, sleep_goal FROM goals WHERE user_id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$goals = $stmt->get_result()->fetch_assoc() ?? ['steps_goal'=>10000,'water_goal'=>2.0,'sleep_goal'=>8.0];
$stmt->close();

// ── Build rule-based suggestions ─────────────────────────────
$suggestions = [];

$steps  = (int)$rec['steps'];
$water  = (float)$rec['water'];
$sleep  = (float)$rec['sleep'];
$bmi    = (float)$rec['bmi'];
$cal    = (float)$foodToday['total_cal'];
$sGoal  = (int)$goals['steps_goal'];
$wGoal  = (float)$goals['water_goal'];
$lGoal  = (float)$goals['sleep_goal'];

// Steps analysis
$stepsPct = $sGoal > 0 ? ($steps / $sGoal) * 100 : 0;
if ($steps === 0) {
    $suggestions[] = ['icon'=>'🏃','type'=>'warning',
        'title'=>'Get Moving!',
        'text'=>"No steps logged yet today, {$firstName}. Even a 10-minute walk makes a big difference for your energy and mood."];
} elseif ($stepsPct < 50) {
    $remaining = number_format($sGoal - $steps);
    $suggestions[] = ['icon'=>'👟','type'=>'warning',
        'title'=>'Halfway to Your Goal',
        'text'=>"You're at {$steps} steps — about " . round($stepsPct) . "% of your {$sGoal}-step goal. You need {$remaining} more to hit it today!"];
} elseif ($stepsPct < 100) {
    $remaining = number_format($sGoal - $steps);
    $suggestions[] = ['icon'=>'🔥','type'=>'info',
        'title'=>'Almost There!',
        'text'=>"Great work! You're at " . number_format($steps) . " steps. Just {$remaining} more to reach your goal — you can do it!"];
} else {
    $suggestions[] = ['icon'=>'🏆','type'=>'positive',
        'title'=>'Step Goal Achieved!',
        'text'=>"Incredible! You've surpassed your {$sGoal}-step goal today with " . number_format($steps) . " steps. Outstanding effort!"];
}

// Water analysis
if ($water === 0.0) {
    $suggestions[] = ['icon'=>'💧','type'=>'warning',
        'title'=>'Stay Hydrated',
        'text'=>"You haven't logged any water today. Proper hydration boosts concentration, energy, and metabolism. Aim for {$wGoal}L today!"];
} elseif ($water < $wGoal * 0.6) {
    $remaining = round($wGoal - $water, 1);
    $suggestions[] = ['icon'=>'💧','type'=>'warning',
        'title'=>'Increase Your Water Intake',
        'text'=>"You've had {$water}L of water. Drink {$remaining}L more to hit your {$wGoal}L goal. Set a reminder every 2 hours!"];
} elseif ($water < $wGoal) {
    $suggestions[] = ['icon'=>'🥤','type'=>'info',
        'title'=>'Almost Fully Hydrated',
        'text'=>"You're at {$water}L — almost at your {$wGoal}L goal. One or two more glasses and you're there!"];
} else {
    $suggestions[] = ['icon'=>'💙','type'=>'positive',
        'title'=>'Hydration Goal Met!',
        'text'=>"Excellent! You've hit your {$wGoal}L water goal. Staying hydrated supports every system in your body — keep it up!"];
}

// Sleep analysis
if ($sleep === 0.0) {
    $suggestions[] = ['icon'=>'😴','type'=>'info',
        'title'=>"Log Last Night's Sleep",
        'text'=>"Don't forget to log your sleep! Tracking sleep helps you understand patterns and improve recovery over time."];
} elseif ($sleep < 5) {
    $suggestions[] = ['icon'=>'🌙','type'=>'warning',
        'title'=>'Critical Sleep Deficit',
        'text'=>"Only {$sleep} hours of sleep recorded. Chronic sleep deprivation affects immunity, mental health, and performance. Prioritise rest tonight!"];
} elseif ($sleep < 6.5) {
    $suggestions[] = ['icon'=>'😪','type'=>'warning',
        'title'=>'You Need More Rest',
        'text'=>"You got {$sleep} hours of sleep — below the recommended 7–9 hours. Try winding down 30 minutes earlier tonight."];
} elseif ($sleep >= $lGoal) {
    $suggestions[] = ['icon'=>'⭐','type'=>'positive',
        'title'=>'Great Sleep!',
        'text'=>"You got {$sleep} hours of sleep — right in the sweet spot. Quality sleep is the foundation of every health goal!"];
} else {
    $suggestions[] = ['icon'=>'🌛','type'=>'info',
        'title'=>'Decent Rest',
        'text'=>"You slept {$sleep} hours. A little more rest would help — your goal is {$lGoal} hours for full recovery."];
}

// BMI analysis
if ($bmi > 0) {
    if ($bmi < 18.5) {
        $suggestions[] = ['icon'=>'⚖️','type'=>'warning',
            'title'=>'Underweight BMI',
            'text'=>"Your BMI is {$bmi} (Underweight). Focus on nutrient-dense meals and consult a professional to build healthy weight safely."];
    } elseif ($bmi <= 24.9) {
        $suggestions[] = ['icon'=>'✅','type'=>'positive',
            'title'=>'Healthy BMI Range',
            'text'=>"Your BMI of {$bmi} falls in the healthy range (18.5–24.9). Maintain your balanced diet and activity level!"];
    } elseif ($bmi <= 29.9) {
        $suggestions[] = ['icon'=>'⚠️','type'=>'warning',
            'title'=>'Overweight BMI',
            'text'=>"Your BMI is {$bmi} (Overweight). Even a 5–10% weight reduction through diet and exercise significantly improves health outcomes."];
    } else {
        $suggestions[] = ['icon'=>'🩺','type'=>'warning',
            'title'=>'High BMI — Take Action',
            'text'=>"Your BMI of {$bmi} falls in the obese range. Consider consulting a healthcare provider for a personalised nutrition and activity plan."];
    }
}

// Calorie analysis
if ($cal > 0) {
    if ($cal > 3000) {
        $suggestions[] = ['icon'=>'🍽️','type'=>'warning',
            'title'=>'High Calorie Intake',
            'text'=>"You've consumed " . round($cal) . " kcal today — above the typical daily need. Opt for lighter meals or increase activity to balance."];
    } elseif ($cal > 2000) {
        $suggestions[] = ['icon'=>'🥗','type'=>'info',
            'title'=>'Moderate Calorie Day',
            'text'=>"You've logged " . round($cal) . " kcal today. Make sure these are nutrient-rich calories with plenty of vegetables, protein, and whole grains."];
    } elseif ($cal > 0) {
        $suggestions[] = ['icon'=>'🌿','type'=>'positive',
            'title'=>'Balanced Intake',
            'text'=>"You've logged " . round($cal) . " kcal today — a well-balanced amount. Keep prioritising whole foods and lean proteins."];
    }
}

// Motivational tip (always shown last)
$tips = [
    "Small consistent actions compound into massive results over time. Log every day!",
    "Progress, not perfection. Every healthy choice today is an investment in tomorrow.",
    "Your body achieves what your mind believes. Stay committed to your goals.",
    "Health is not a destination — it's a daily practice. You're doing great!",
    "Rest, hydration, movement, and nutrition: four pillars of a thriving life.",
];
$dailyTip = $tips[date('N') % count($tips)]; // Rotates daily by day of week
$suggestions[] = ['icon'=>'✨','type'=>'motivational',
    'title'=>'Daily Motivation',
    'text'=> $dailyTip];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI Coach | Smart Health Tracker</title>
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
.sections { display:grid; gap:28px; max-width:1000px; margin:0 auto 60px; padding:0 40px; }
.section-title { padding:40px 40px 16px; max-width:1000px; margin:0 auto; }
.section-title h2 { font-size:1.8rem; margin:0 0 4px; }
.section-title p  { color:rgba(238,245,255,0.7); margin:0; }
.card {
  background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.10);
  border-radius:24px; padding:28px; box-shadow:var(--shadow);
}
.card h3  { margin:0 0 6px; }
.card .sub { color:rgba(238,245,255,0.65); margin:0 0 20px; font-size:0.88rem; }
/* Suggestion cards */
.suggestion-list { display:grid; gap:14px; }
.suggestion {
  display:flex; align-items:flex-start; gap:16px;
  border-radius:18px; padding:18px 20px;
  border: 1px solid transparent;
  transition: transform 0.25s, border-color 0.25s;
}
.suggestion:hover { transform:translateY(-3px); }
.suggestion.positive    { background:rgba(80,220,130,0.08); border-color:rgba(80,220,130,0.18); }
.suggestion.warning     { background:rgba(255,140,50,0.08);  border-color:rgba(255,140,50,0.20); }
.suggestion.info        { background:rgba(58,168,255,0.08);  border-color:rgba(58,168,255,0.18); }
.suggestion.motivational { background:rgba(167,139,250,0.10); border-color:rgba(167,139,250,0.22); }
.sug-icon  { font-size:1.8rem; flex-shrink:0; }
.sug-body  {}
.sug-title { font-weight:700; font-size:1rem; margin:0 0 5px; color:#fff; }
.sug-text  { font-size:0.9rem; line-height:1.65; color:rgba(238,245,255,0.82); margin:0; }
/* Status bar */
.status-row {
  display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px;
  margin-bottom:24px;
}
.status-chip {
  background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.09);
  border-radius:14px; padding:14px; text-align:center;
}
.status-chip .sv { font-size:1.4rem; font-weight:700; }
.status-chip .sl { font-size:0.75rem; color:rgba(238,245,255,0.6); margin-top:3px; }
/* Chat UI */
.chat-wrap {
  background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
  border-radius:20px; padding:20px; min-height:300px; max-height:400px;
  overflow-y:auto; margin-bottom:16px; display:flex; flex-direction:column; gap:12px;
}
.msg { max-width:80%; padding:12px 16px; border-radius:16px; font-size:0.92rem; line-height:1.6; }
.msg.bot  {
  background:rgba(58,168,255,0.14); border:1px solid rgba(58,168,255,0.18);
  color:#d4eeff; border-bottom-left-radius:4px; align-self:flex-start;
}
.msg.user {
  background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.14);
  color:#eef5ff; border-bottom-right-radius:4px; align-self:flex-end;
}
.msg .sender { font-size:0.72rem; font-weight:700; margin-bottom:5px; opacity:0.7; }
.chat-input-row { display:flex; gap:12px; }
.chat-input-row input {
  flex:1; padding:12px 16px; border-radius:14px;
  border:1px solid rgba(255,255,255,0.18);
  background:rgba(255,255,255,0.08); color:#eef5ff;
  outline:none; font-size:0.95rem; transition:border-color 0.2s;
}
.chat-input-row input:focus { border-color:rgba(58,168,255,0.5); }
.chat-input-row input::placeholder { color:rgba(238,245,255,0.35); }
.chat-send {
  padding:12px 22px; border-radius:14px; border:none;
  background:linear-gradient(135deg,#3aa8ff,#0f4c75);
  color:white; font-size:0.92rem; cursor:pointer; font-weight:600;
  transition:transform 0.2s, box-shadow 0.2s; white-space:nowrap;
}
.chat-send:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(58,168,255,0.25); }
.pill {
  display:inline-block; padding:3px 12px; border-radius:999px;
  font-size:0.75rem; font-weight:700; letter-spacing:0.04em;
  background:rgba(58,168,255,0.2); color:#a8d9ff;
  border:1px solid rgba(58,168,255,0.25); margin-bottom:10px;
}
.typing { display:none; align-self:flex-start; }
.typing span {
  display:inline-block; width:8px; height:8px; border-radius:50%;
  background:rgba(58,168,255,0.7); margin:0 2px;
  animation:bounce 1.2s infinite;
}
.typing span:nth-child(2) { animation-delay:0.2s; }
.typing span:nth-child(3) { animation-delay:0.4s; }
@keyframes bounce { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-8px)} }
@media (max-width:820px) {
  header { flex-direction:column; align-items:flex-start; gap:14px; }
  .sections { padding:0 20px; }
  .section-title { padding-left:20px; padding-right:20px; }
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
    <a href="reports.php">Reports</a>
    <a href="ai_coach.php" class="active">AI Coach</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<div class="section-title">
  <h2>🤖 AI Wellness Coach</h2>
  <p>Personalised suggestions based on your health data today — and a wellness chat to guide your journey.</p>
</div>

<main class="sections">

  <!-- ── TODAY STATUS ── -->
  <section class="card">
    <span class="pill">Today's Snapshot</span>
    <h3>Your Data Right Now</h3>
    <p class="sub">Used to generate today's personalised suggestions.</p>
    <div class="status-row">
      <div class="status-chip">
        <div class="sv"><?= number_format($steps) ?></div>
        <div class="sl">Steps</div>
      </div>
      <div class="status-chip">
        <div class="sv"><?= number_format($water, 1) ?>L</div>
        <div class="sl">Water</div>
      </div>
      <div class="status-chip">
        <div class="sv"><?= $sleep > 0 ? number_format($sleep, 1).'h' : '—' ?></div>
        <div class="sl">Sleep</div>
      </div>
      <div class="status-chip">
        <div class="sv"><?= $bmi > 0 ? number_format($bmi, 1) : '—' ?></div>
        <div class="sl">BMI</div>
      </div>
      <div class="status-chip">
        <div class="sv"><?= $cal > 0 ? round($cal) : '—' ?></div>
        <div class="sl">Calories</div>
      </div>
    </div>
  </section>

  <!-- ── SUGGESTIONS ── -->
  <section class="card">
    <span class="pill">Suggestions</span>
    <h3>Your Personalised Wellness Tips</h3>
    <p class="sub">Rule-based recommendations generated from your health data.</p>
    <div class="suggestion-list">
      <?php foreach ($suggestions as $s): ?>
        <div class="suggestion <?= $s['type'] ?>">
          <div class="sug-icon"><?= $s['icon'] ?></div>
          <div class="sug-body">
            <p class="sug-title"><?= htmlspecialchars($s['title']) ?></p>
            <p class="sug-text"><?= htmlspecialchars($s['text']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── WELLNESS CHAT ── -->
  <section class="card">
    <span class="pill">Chat</span>
    <h3>Ask Your Wellness Coach</h3>
    <p class="sub">Type a question or topic and get instant, evidence-based guidance.</p>

    <div class="chat-wrap" id="chat-wrap">
      <div class="msg bot">
        <div class="sender">💙 Coach</div>
        Hello <?= htmlspecialchars($firstName) ?>! I'm your wellness coach. Ask me anything about steps, sleep, hydration, nutrition, BMI, or healthy habits. I'm here to help!
      </div>
    </div>

    <div class="typing" id="typing">
      <span></span><span></span><span></span>
    </div>

    <div class="chat-input-row">
      <input type="text" id="chat-input"
             placeholder="e.g. How can I sleep better? / What's a good step goal?"
             onkeydown="if(event.key==='Enter') sendChat()">
      <button class="chat-send" onclick="sendChat()">Send ✈️</button>
    </div>
  </section>

</main>

<script>
// ── Today's data passed from PHP ─────────────────────────────
const userData = {
  steps: <?= $steps ?>,
  water: <?= $water ?>,
  sleep: <?= $sleep ?>,
  bmi:   <?= $bmi ?>,
  cal:   <?= $cal ?>,
  stepsGoal: <?= (int)$goals['steps_goal'] ?>,
  waterGoal: <?= (float)$goals['water_goal'] ?>,
  sleepGoal: <?= (float)$goals['sleep_goal'] ?>,
  name:  <?= json_encode($firstName) ?>
};

// ── Rule-based chat responses ────────────────────────────────
const rules = [
  // Steps
  { keys:['step','walk','running','jog','active'],
    fn: () => {
      const pct = userData.stepsGoal > 0 ? Math.round(userData.steps/userData.stepsGoal*100) : 0;
      if (userData.steps === 0) return "You haven't logged any steps today. Start with a 15-minute walk — even that counts! Gradually build to your " + userData.stepsGoal.toLocaleString() + "-step goal.";
      if (pct < 100) return `You're at ${userData.steps.toLocaleString()} steps (${pct}% of your ${userData.stepsGoal.toLocaleString()} goal). Break the remaining steps into 2–3 short walks. Parking farther away and taking stairs are easy wins!`;
      return `You've already hit your ${userData.stepsGoal.toLocaleString()} step goal today — amazing! Consistency is the key to long-term health. Keep it up!`;
    }
  },
  // Water / hydration
  { keys:['water','hydrat','drink','fluid','thirst'],
    fn: () => {
      const gap = (userData.waterGoal - userData.water).toFixed(1);
      if (userData.water === 0) return `You haven't logged any water yet today. Aim for ${userData.waterGoal}L. A great trick: drink a glass before every meal and after every bathroom break.`;
      if (gap > 0) return `You've had ${userData.water}L of water today — ${gap}L short of your ${userData.waterGoal}L goal. Set a phone reminder every 2 hours to drink a glass!`;
      return `Excellent hydration! You've met your ${userData.waterGoal}L goal. Staying hydrated supports energy, skin, and digestion. Keep it up!`;
    }
  },
  // Sleep
  { keys:['sleep','rest','tired','fatigue','insomnia','night'],
    fn: () => {
      if (userData.sleep === 0) return "Sleep is the foundation of recovery. Aim for 7–9 hours per night. Wind down 30–60 min before bed: dim lights, no screens, and try deep breathing.";
      if (userData.sleep < 6) return `You only got ${userData.sleep} hours of sleep — that's below the healthy minimum. Try a consistent bedtime, avoid caffeine after 2pm, and create a dark, cool sleep environment.`;
      if (userData.sleep < userData.sleepGoal) return `You slept ${userData.sleep} hours. You're close to your ${userData.sleepGoal}-hour goal! Small improvements: stick to a regular sleep schedule and avoid blue light before bed.`;
      return `Great sleep! ${userData.sleep} hours is within the healthy range. Quality sleep regulates hormones, boosts immunity, and sharpens focus.`;
    }
  },
  // BMI
  { keys:['bmi','weight','body mass','overweight','underweight','obese'],
    fn: () => {
      if (userData.bmi <= 0) return "Your BMI hasn't been calculated yet. Go to your Dashboard, enter your height and weight, and click 'Save Profile & BMI' to see your BMI instantly.";
      if (userData.bmi < 18.5) return `Your BMI is ${userData.bmi} (Underweight). Focus on nutrient-dense foods like avocados, nuts, whole grains, and lean protein. Strength training can help build healthy muscle mass.`;
      if (userData.bmi < 25) return `Your BMI is ${userData.bmi} — in the healthy range (18.5–24.9). Maintain this with balanced nutrition and regular activity. Great work!`;
      if (userData.bmi < 30) return `Your BMI is ${userData.bmi} (Overweight). Even a 5–10% weight reduction significantly lowers health risks. Focus on portion control, increasing fibre, and 150 min of moderate exercise weekly.`;
      return `Your BMI is ${userData.bmi} (Obese). I strongly encourage consulting a healthcare professional for a personalised plan. Small, sustainable changes — not crash diets — lead to lasting results.`;
    }
  },
  // Calories / food
  { keys:['calori','food','eat','diet','meal','nutriti','protein','carb','fat','macro'],
    fn: () => {
      if (userData.cal === 0) return "You haven't logged any food today. Head to the Food Tracker to log your meals and track your macros — calories, protein, carbs, and fats.";
      if (userData.cal > 2800) return `You've logged ${Math.round(userData.cal)} kcal today — above average daily needs. Balance it with a lighter dinner: salad, lean protein, and vegetables work great.`;
      if (userData.cal > 1800) return `You've consumed ${Math.round(userData.cal)} kcal today — a reasonable amount. Focus on the quality of those calories: prioritise vegetables, lean protein, and complex carbs.`;
      return `You've logged ${Math.round(userData.cal)} kcal today. Make sure you're eating enough to fuel your activity level. Include a variety of nutrients: protein for muscles, fibre for gut health, and healthy fats for brain function.`;
    }
  },
  // Motivation
  { keys:['motivat','inspire','goal','habit','routine','consistent'],
    fn: () => "Building lasting health habits takes time. The secret? Start with one small change, repeat it daily for 21 days until it's automatic, then add the next. Log your data every day — what gets measured gets improved!"
  },
  // Stress / mental health
  { keys:['stress','anxiety','mental','mood','happy','depress'],
    fn: () => "Mental wellness is just as vital as physical health. Regular exercise (even walking) reduces stress hormones by up to 48%. Deep breathing, mindfulness, and good sleep are proven mood boosters. Don't hesitate to reach out to a healthcare professional if needed."
  },
  // General greeting
  { keys:['hi','hello','hey','helo','good morning','good evening','how are you'],
    fn: () => `Hi ${userData.name}! I'm your wellness coach, always here to help. You can ask me about your steps, sleep, water intake, diet, BMI, or general health tips. What would you like to explore today?`
  },
  // Help
  { keys:['help','what can','topics','ask you'],
    fn: () => "You can ask me about: 🏃 Steps & activity · 💧 Hydration · 😴 Sleep · ⚖️ BMI & weight · 🍽️ Nutrition & calories · 💪 Motivation & habits · 🧠 Stress & mental wellness. Just type your question!"
  },
];

// Fallback responses
const fallbacks = [
  "That's a great question! For the most personalised advice, log your daily health data consistently and check your Reports page for trends.",
  "I'm best at helping with steps, sleep, hydration, nutrition, and BMI. Could you rephrase your question around one of those topics?",
  "Consistent healthy habits — regular movement, good sleep, and balanced nutrition — are the foundation of long-term wellness. Keep logging your data!",
  "I'd recommend speaking with a healthcare professional for specific medical advice. For wellness tracking, I'm always here to guide you!",
];

// ── Send chat message ─────────────────────────────────────────
function sendChat() {
  const input = document.getElementById('chat-input');
  const text  = input.value.trim();
  if (!text) return;

  addMsg(text, 'user');
  input.value = '';

  // Show typing indicator
  const typing = document.getElementById('typing');
  typing.style.display = 'flex';
  scrollChat();

  setTimeout(() => {
    typing.style.display = 'none';
    const response = getResponse(text.toLowerCase());
    addMsg(response, 'bot');
  }, 800 + Math.random() * 600);
}

function getResponse(text) {
  for (const rule of rules) {
    if (rule.keys.some(k => text.includes(k))) {
      return rule.fn();
    }
  }
  return fallbacks[Math.floor(Math.random() * fallbacks.length)];
}

function addMsg(text, who) {
  const wrap  = document.getElementById('chat-wrap');
  const div   = document.createElement('div');
  div.className = 'msg ' + who;
  const sender  = document.createElement('div');
  sender.className = 'sender';
  sender.textContent = who === 'bot' ? '💙 Coach' : '👤 You';
  div.appendChild(sender);
  div.appendChild(document.createTextNode(text));
  wrap.appendChild(div);
  scrollChat();
}

function scrollChat() {
  const wrap = document.getElementById('chat-wrap');
  wrap.scrollTop = wrap.scrollHeight;
}

// Allow Enter key in chat
document.getElementById('chat-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') sendChat();
});
</script>

</body>
</html>
