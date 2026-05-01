<?php
// ============================================================
// homepage.php — Public landing page (links updated to .php)
// ============================================================
require_once 'config.php';
$loggedIn = isLoggedIn();
$name = $_SESSION['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Smart Health Tracker</title>
<style>
/* ── ORIGINAL CSS — UNCHANGED ── */
:root {
  --primary: #0f4c75;
  --accent: #3aa8ff;
  --surface: rgba(255, 255, 255, 0.18);
  --surface-strong: rgba(255, 255, 255, 0.28);
  --text: #f5f9ff;
  --shadow: 0 24px 80px rgba(15, 76, 117, 0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: radial-gradient(circle at top left, rgba(58, 168, 255, 0.22), transparent 35%),
              radial-gradient(circle at bottom right, rgba(15, 76, 117, 0.35), transparent 25%),
              linear-gradient(180deg, #051128 0%, #092040 45%, #0f4c75 100%);
  color: #eef5ff;
}
body { overflow-x: hidden; }
header {
  position: sticky; top: 0; z-index: 10;
  backdrop-filter: blur(18px);
  background: rgba(5, 17, 40, 0.72);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 40px;
}
header h1 { font-size: 1.4rem; letter-spacing: 0.04em; margin: 0; }
nav { display: flex; gap: 22px; align-items: center; }
nav a { color: #e6f0ff; text-decoration: none; font-weight: 600; transition: color 0.25s ease; }
nav a:hover { color: var(--accent); }
.cta-button {
  padding: 12px 22px; border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: linear-gradient(135deg, rgba(58, 168, 255, 0.96), rgba(15, 76, 117, 0.88));
  color: white; cursor: pointer;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
  text-decoration: none;
}
.cta-button:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(58,168,255,0.22); }
.hero { position: relative; padding: 100px 40px 60px; max-width: 1180px; margin: 0 auto; }
.hero::before {
  content: ''; position: absolute; left: -120px; top: 40px;
  width: 320px; height: 320px; background: rgba(58, 168, 255, 0.18);
  border-radius: 50%; filter: blur(60px);
}
.hero::after {
  content: ''; position: absolute; right: -100px; bottom: 0;
  width: 220px; height: 220px; background: rgba(255, 255, 255, 0.08);
  border-radius: 50%; filter: blur(40px);
}
.hero-content { position: relative; max-width: 680px; }
.hero h2 { font-size: clamp(2.5rem, 4vw, 4.5rem); line-height: 1.02; margin: 0; }
.hero p { margin: 24px 0 34px; max-width: 620px; line-height: 1.8; color: rgba(238, 245, 255, 0.8); }
.hero-actions { display: flex; flex-wrap: wrap; gap: 16px; }
.blend-card {
  margin-top: 40px; max-width: 520px;
  backdrop-filter: blur(18px);
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 28px; padding: 28px 32px;
  box-shadow: var(--shadow);
}
.blend-card h3 { margin: 0 0 12px; font-size: 1.05rem; }
.blend-card p { margin: 0; line-height: 1.7; color: rgba(238, 245, 255, 0.75); }
.sections { display: grid; gap: 36px; max-width: 1180px; margin: 0 auto 60px; padding: 0 40px; }
.card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; }
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
  box-shadow: 0 18px 46px rgba(15, 76, 117, 0.14);
  transition: transform 0.25s ease, border-color 0.25s ease;
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
  transition: transform 0.3s ease, border-color 0.3s ease;
}
.card:hover { transform: translateY(-10px); border-color: rgba(58,168,255,0.25); }
.card-icon { width: 64px; height: 64px; display: grid; place-items: center; background: rgba(255,255,255,0.1); border-radius: 18px; margin-bottom: 20px; }
.card-icon img { width: 36px; }
.card h3 { margin: 0 0 12px; }
.card p { color: rgba(238,245,255,0.75); line-height: 1.75; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
.stat { padding: 24px; border-radius: 22px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); text-align: center; box-shadow: var(--shadow); }
.stat h3 { margin: 0; font-size: 2.2rem; color: #fff; }
.stat p { margin: 10px 0 0; color: rgba(238,245,255,0.8); }
.footer { text-align: center; padding: 30px 40px 40px; color: rgba(238,245,255,0.72); }
/* feature-pill & future-card — defined here (were missing in original) */
.feature-pill {
  display: inline-block; padding: 4px 14px; border-radius: 999px;
  font-size: 0.78rem; font-weight: 700; letter-spacing: 0.04em;
  background: rgba(58,168,255,0.2); color: #a8d9ff;
  border: 1px solid rgba(58,168,255,0.25); margin-bottom: 12px;
}
.future-highlights { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-top: 20px; }
.future-card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10); border-radius: 20px; padding: 22px; transition: transform 0.25s; }
.future-card:hover { transform: translateY(-4px); }
.future-card h3 { margin: 0 0 8px; font-size: 1rem; }
.future-card p { color: rgba(238,245,255,0.72); font-size: 0.9rem; line-height: 1.65; margin: 0; }
@media (max-width: 820px) {
  header { flex-direction: column; align-items: flex-start; gap: 14px; }
  .hero { padding-top: 70px; }
}
</style>
</head>
<body>
<header>
  <h1>💙 Smart Health Tracker</h1>
  <nav>
    <a href="#home">Home</a>
    <a href="#features">Features</a>
    <a href="#next">Next</a>
    <?php if ($loggedIn): ?>
      <a class="cta-button" href="dashboard.php">My Dashboard</a>
    <?php else: ?>
      <a class="cta-button" href="newindex.php">Login</a>
    <?php endif; ?>
  </nav>
</header>

<section class="hero" id="home">
  <div class="hero-content">
    <h2>Advance Your Health Journey with Next-Gen Intelligence</h2>
    <p>Experience a smarter wellness dashboard that tracks your BMI, steps, hydration, and daily habits using a polished interactive interface designed for the next generation.</p>
    <div class="hero-actions">
      <?php if ($loggedIn): ?>
        <a href="dashboard.php" class="cta-button">Go to Dashboard</a>
      <?php else: ?>
        <a href="register.php" class="cta-button">Start Free</a>
      <?php endif; ?>
      <a href="#features" class="cta-button" style="background:rgba(255,255,255,0.14);color:#eef5ff;border-color:rgba(255,255,255,0.18);">Explore Features</a>
    </div>
    <div class="blend-card">
      <h3>Live health tips</h3>
      <p>Tap into a responsive wellness assistant that recommends daily goals, hydration reminders, and activity boosts based on your personal routine.</p>
    </div>
  </div>
</section>

<main class="sections">
  <section id="features">
    <div class="card-grid">
      <article class="card">
        <div class="card-icon"><img src="https://cdn-icons-png.flaticon.com/512/1048/1048953.png" alt="BMI icon"></div>
        <h3>Smart BMI Insights</h3>
        <p>Instantly compare your body mass index with age-based recommendations and actionable next steps.</p>
      </article>
      <article class="card">
        <div class="card-icon"><img src="https://cdn-icons-png.flaticon.com/512/2936/2936886.png" alt="steps icon"></div>
        <h3>Motion &amp; Activity</h3>
        <p>Animated progress cards make your step count, workout streak, and calories burned feel motivating every time.</p>
      </article>
      <article class="card">
        <div class="card-icon"><img src="https://cdn-icons-png.flaticon.com/512/2913/2913465.png" alt="report icon"></div>
        <h3>Interactive Reports</h3>
        <p>View health summaries with dynamic charts, goal trends, and personalized recommendations in a futuristic dashboard.</p>
      </article>
      <article class="card">
        <div class="card-icon"><img src="https://cdn-icons-png.flaticon.com/512/1006/1006552.png" alt="next-gen icon"></div>
        <h3>AI Wellness Coach</h3>
        <p>Receive friendly prompts and next-gen suggestions to keep your wellness routine advanced and fun.</p>
      </article>
    </div>
  </section>

  <section id="tracker" class="tracker-section">
    <span class="feature-pill">Tracker</span>
    <h3>Live wellness trackers</h3>
    <p style="color:rgba(238,245,255,0.78);max-width:720px;margin-top:10px;">Keep an eye on your daily activity, hydration, and sleep performance with real-time progress indicators.</p>
    <div class="tracker-grid">
      <div class="tracker-card">
        <h4>Steps</h4>
        <p class="tracker-value">9,500</p>
        <div class="progress-bar"><div class="progress-fill" style="width:95%"></div></div>
        <p class="progress-note">Goal: 10,000 steps — almost there.</p>
      </div>
      <div class="tracker-card">
        <h4>Hydration</h4>
        <p class="tracker-value">1.8L</p>
        <div class="progress-bar"><div class="progress-fill" style="width:90%"></div></div>
        <p class="progress-note">Goal: 2.0L — keep drinking.</p>
      </div>
      <div class="tracker-card">
        <h4>Sleep</h4>
        <p class="tracker-value">7.5 hrs</p>
        <div class="progress-bar"><div class="progress-fill" style="width:83%"></div></div>
        <p class="progress-note">Goal: 9 hrs — good rest, still more to go.</p>
      </div>
    </div>
  </section>

  <section id="next">
    <div class="card">
      <h3>Next-gen design</h3>
      <p>From glassmorphism cards to motion-based hover states, the interface feels alive as you explore your health journey.</p>
    </div>
    <div class="card stats">
      <div class="stat"><h3 id="count1">0</h3><p>Steps tracked</p></div>
      <div class="stat"><h3 id="count2">0</h3><p>Healthy habits</p></div>
      <div class="stat"><h3 id="count3">0</h3><p>Lifestyle support</p></div>
    </div>
    <div class="card" style="grid-column:span 2;">
      <h3>Advanced next-gen features</h3>
      <p>Get intelligent wellness predictions, smart hydration reminders, adaptive sleep coaching, and seamless wearable sync for a truly forward-looking experience.</p>
      <div class="future-highlights">
        <div class="future-card">
          <span class="feature-pill">AI Planner</span>
          <h3>Habit prediction</h3>
          <p>Receive daily suggestions based on your routine, energy patterns, and progress history.</p>
        </div>
        <div class="future-card">
          <span class="feature-pill">Smart Sync</span>
          <h3>Wearable integration</h3>
          <p>Connect your devices and view health insights from steps, sleep, and heart rate in one place.</p>
        </div>
        <div class="future-card">
          <span class="feature-pill">Forecast</span>
          <h3>Wellness timeline</h3>
          <p>Track your upcoming health goals and see trend forecasts for better long-term planning.</p>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <p>© 2026 Smart Health Tracker | Designed with interactive next-gen UI principles.</p>
</footer>

<script>
/* ORIGINAL counter script — unchanged */
const counters = [
  {id:'count1', value:12000, suffix:''},
  {id:'count2', value:8,     suffix:''},
  {id:'count3', value:24,    suffix:'/7'}
];
counters.forEach(counter => {
  const el = document.getElementById(counter.id);
  if (!el) return;
  let current = 0;
  const target = counter.value;
  const step = Math.ceil(target / 80);
  const interval = setInterval(() => {
    current += step;
    if (current >= target) { current = target; clearInterval(interval); }
    el.textContent = current + counter.suffix;
  }, 25);
});
</script>
</body>
</html>
