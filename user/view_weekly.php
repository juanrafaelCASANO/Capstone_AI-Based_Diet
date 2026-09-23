<?php
/**
 * AI-Based Diet and Nutritional Planner System
 * A complete, single-file PHP solution for meal plan extraction.
 */

class NutriAI {
    // We trim the key to prevent issues with hidden spaces from copy-pasting
    private $apiKey = "AIzaSyCiX5989dytjy2koeu1t9_h0G5EvhAejYs"; 
    private $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent";

    public function generateMealPlan($formData) {
        $cleanKey = trim($this->apiKey);
        
        if (empty($cleanKey) || $cleanKey === "YOUR_ACTUAL_API_KEY_HERE") {
            throw new Exception("API Key is missing. Please add your Gemini API key to implementation.php");
        }

        $systemPrompt = "You are a professional Nutritionist AI. Generate a 3-day meal plan. "
                      . "Return ONLY a JSON object. No conversational text. "
                      . "Structure: { \"summary\": { \"total_avg_calories\": number, \"primary_focus\": string }, "
                      . "\"days\": [ { \"day_number\": number, \"meals\": [ { \"type\": \"Breakfast|Lunch|Dinner|Snack\", \"name\": \"string\", "
                      . "\"calories\": number, \"protein\": \"string\", \"carbs\": \"string\", \"fats\": \"string\", \"ingredients\": [] } ] } ] }";

        $userQuery = "User: {$formData['age']}yo, {$formData['weight']}kg, {$formData['height']}cm. Goal: {$formData['goal']}. Diet: {$formData['dietType']}. Activity: {$formData['activityLevel']}.";

        $payload = [
            "contents" => [["parts" => [["text" => $userQuery]]]],
            "systemInstruction" => ["parts" => [["text" => $systemPrompt]]],
            "generationConfig" => [
                "responseMimeType" => "application/json",
                "temperature" => 0.7
            ]
        ];

        return $this->fetchWithRetry($payload, $cleanKey);
    }

    private function fetchWithRetry($payload, $key, $retries = 3, $backoff = 2) {
        $url = $this->apiUrl . "?key=" . $key;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Helps if local SSL certs are missing

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $jsonStr = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            return $jsonStr ? json_decode($jsonStr, true) : null;
        }

        // Specific error handling for API Key issues
        if ($httpCode === 400 || $httpCode === 403) {
            throw new Exception("API Key Error (Code $httpCode): Your API key may be invalid, restricted, or pasted incorrectly.");
        }

        // Retry logic for rate limits (429) or server errors (500+)
        if ($retries > 0 && ($httpCode === 429 || $httpCode >= 500)) {
            sleep($backoff);
            return $this->fetchWithRetry($payload, $key, $retries - 1, $backoff * 2);
        }

        if ($curlError) {
            throw new Exception("Network Error: " . $curlError);
        }

        return null;
    }
}

// Logic for handling form submission
$mealPlan = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $planner = new NutriAI();
        $mealPlan = $planner->generateMealPlan($_POST);
        if (!$mealPlan) {
            $error = "The AI service returned an empty response. Please check your internet connection and try again.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Diet Planner · Weekly Plan</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<style>
:root{
  --navy:#173b32;--navy2:#123027;--green:#3b8f63;--green-soft:#e8f5ed;
  --cream:#f7faf6;--card:#fff;--text:#193028;--muted:#6c7b75;
  --border:#e3ebe6;--danger:#b42318;--danger-bg:#fff3f1;
  --shadow:0 10px 30px rgba(27,61,48,.08);--radius:18px
}
*{box-sizing:border-box}
body{margin:0;background:var(--cream);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
a,button,input,select{font:inherit}
a{color:inherit}
.app{min-height:100vh;display:flex}
.sidebar{width:260px;flex:0 0 260px;min-height:100vh;position:sticky;top:0;align-self:flex-start;background:linear-gradient(180deg,var(--navy),var(--navy2));color:#fff;padding:24px 18px;display:flex;flex-direction:column;z-index:20;transition:transform .25s ease}
.brand{display:flex;align-items:center;gap:12px;padding:6px 10px 28px}
.brand-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:23px}
.brand strong{display:block;font-size:17px}.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
.nav-label{padding:0 12px 8px;color:#9fc2b4;text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800}
.nav{display:grid;gap:6px}.nav a{display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;border-radius:12px;color:#d9ebe4;font-size:14px;font-weight:650;transition:.2s}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}.logout{color:#c6ddd4!important}
.main{min-width:0;flex:1;padding:26px clamp(18px,4vw,48px) 48px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}
.menu-btn{display:none;border:1px solid var(--border);background:#fff;width:44px;height:44px;border-radius:12px;cursor:pointer}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.topbar h1{font-size:clamp(25px,3vw,34px);line-height:1.15;margin:5px 0 0;letter-spacing:-.7px}
.profile{display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;background:#fff;border:1px solid var(--border);border-radius:999px}
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800}.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.panel{background:#fff;border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);overflow:hidden}
.panel-head{padding:26px clamp(20px,4vw,36px);border-bottom:1px solid var(--border)}
.panel-head h2{margin:0;font-size:clamp(22px,3vw,30px);letter-spacing:-.5px}.panel-head p{margin:7px 0 0;color:var(--muted);line-height:1.6}
.form{padding:clamp(20px,4vw,36px)}
.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.form-grid + .form-grid{margin-top:18px}
.field label{display:block;font-size:13px;font-weight:750;margin:0 0 8px}.field input,.field select{
  width:100%;min-height:48px;padding:0 14px;border:1px solid var(--border);border-radius:12px;background:#fff;color:var(--text);outline:none;font-size:16px
}
.field input:focus,.field select:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(59,143,99,.13)}
.hint{font-size:11px;color:var(--muted);margin-top:6px}
.error{padding:14px 16px;background:var(--danger-bg);color:var(--danger);border:1px solid #f2c7c2;border-radius:13px;margin-bottom:20px;font-size:13px;line-height:1.5}
.error b{display:block;margin-bottom:3px}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
.btn{min-height:46px;padding:0 18px;border-radius:12px;border:1px solid transparent;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:750;font-size:14px;cursor:pointer;transition:.2s}
.btn:hover{transform:translateY(-1px)}.btn-primary{background:var(--green);color:#fff;box-shadow:0 8px 18px rgba(59,143,99,.18)}.btn-secondary{background:#fff;color:var(--navy);border-color:var(--border)}

.result{display:grid;gap:18px}
.summary{background:linear-gradient(135deg,var(--navy),#245848);border-radius:22px;padding:26px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:18px}
.summary h2{margin:0;font-size:clamp(25px,3vw,34px);letter-spacing:-.7px}.summary p{margin:8px 0 0;color:#c4ddd4;font-size:13px}.summary p strong{color:#8fe0b1}
.calorie{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:14px 20px;text-align:center;min-width:150px}.calorie small{display:block;color:#b8d2c8;text-transform:uppercase;font-size:10px;font-weight:800;letter-spacing:.1em}.calorie strong{display:block;color:#9be4b8;font-size:23px;margin-top:4px}
.day{background:#fff;border:1px solid var(--border);border-radius:20px;overflow:hidden}
.day-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 22px;background:#f4f8f5;border-bottom:1px solid var(--border)}
.day-head h3{margin:0;font-size:17px}.day-head button{border:1px solid var(--border);background:#fff;border-radius:9px;padding:7px 10px;cursor:pointer;font-size:12px;font-weight:750;color:var(--muted)}
.meals{padding:20px 22px}.meal{display:grid;grid-template-columns:155px minmax(0,1fr);gap:20px;padding:0 0 22px;margin-bottom:22px;border-bottom:1px solid var(--border)}.meal:last-child{margin:0;padding:0;border:0}
.meal-type{font-size:11px;font-weight:850;text-transform:uppercase;letter-spacing:.08em;color:var(--green);background:var(--green-soft);display:inline-flex;padding:6px 9px;border-radius:999px}.meal-cal{font-size:12px;color:var(--muted);margin-top:9px;font-weight:650}
.meal h4{font-size:19px;margin:0 0 12px}.macros{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.macro{background:#f7faf8;border:1px solid var(--border);border-radius:11px;padding:10px;text-align:center}.macro small{display:block;text-transform:uppercase;font-size:9px;font-weight:800;color:#8a9892}.macro b{display:block;font-size:12px;margin-top:3px}
.ingredients{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.ingredient{background:#f3f5f4;border:1px solid var(--border);padding:5px 9px;border-radius:999px;font-size:11px;color:#596963}
.empty{padding:30px;text-align:center;color:var(--muted)}
.form-note{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:12px;margin-top:14px}
.overlay{display:none}

@media(max-width:900px){.sidebar{width:240px;flex-basis:240px}.form-grid{grid-template-columns:1fr 1fr}.form-grid .field:last-child{grid-column:1/-1}.meal{grid-template-columns:125px minmax(0,1fr)}}
@media(max-width:720px){
  .app{display:block}.sidebar{position:fixed;left:0;top:0;bottom:0;transform:translateX(-105%);box-shadow:20px 0 40px rgba(0,0,0,.16)}.sidebar.open{transform:translateX(0)}
  .overlay{position:fixed;inset:0;background:rgba(12,28,22,.42);z-index:15}.overlay.show{display:block}
  .main{padding:17px 15px 35px}.menu-btn{display:grid;place-items:center}.topbar{margin-bottom:20px}.profile-name{display:none}
  .panel{border-radius:19px}.form-grid{grid-template-columns:1fr}.form-grid .field:last-child{grid-column:auto}
  .summary{align-items:flex-start;flex-direction:column}.calorie{width:100%}.meal{grid-template-columns:1fr;gap:10px}.meals{padding:18px}.day-head{padding:15px 18px}
}
@media(max-width:420px){.actions .btn{width:100%}.topbar h1{font-size:25px}.macros{grid-template-columns:1fr 1fr}.macro:last-child{grid-column:1/-1}}
</style>
</head>
<body>
<div class="app">
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div><strong>AI Diet Planner</strong><span>Personal nutrition assistant</span></div>
    </div>
    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a class="active" href="generate_weekly.php"><span class="nav-icon">▦</span>Weekly Meal Plan</a>
      <a href="chat.php"><span class="nav-icon">◌</span>Nutritionist</a>
    </nav>
    <div class="sidebar-bottom"><a class="nav logout" href="../index.php"><span class="nav-icon">↪</span>Logout</a></div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        <div>
          <div class="eyebrow">Weekly meal planner</div>
          <h1>Build your nutrition plan</h1>
        </div>
      </div>
      <div class="profile"><div class="avatar">N</div><span class="profile-name">NutriAI</span></div>
    </header>

    <?php if (!$mealPlan): ?>
      <section class="panel">
        <div class="panel-head">
          <div class="eyebrow">Personalized planning</div>
          <h2>Create Your Diet Strategy</h2>
          <p>Enter your basic metrics and preferences to generate a personalized 3-day meal plan.</p>
        </div>
        <form method="POST" class="form" id="plannerForm">
          <?php if ($error): ?>
            <div class="error" role="alert"><b>⚠️ Something went wrong</b><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <div class="form-grid">
            <div class="field">
              <label for="age">Age</label>
              <input id="age" type="number" name="age" value="25" min="1" max="120" required>
              <div class="hint">Your age in years</div>
            </div>
            <div class="field">
              <label for="weight">Weight (kg)</label>
              <input id="weight" type="number" name="weight" value="70" min="1" max="500" step="0.1" required>
              <div class="hint">Current body weight</div>
            </div>
            <div class="field">
              <label for="height">Height (cm)</label>
              <input id="height" type="number" name="height" value="170" min="50" max="250" step="0.1" required>
              <div class="hint">Your height in centimeters</div>
            </div>
          </div>

          <div class="form-grid">
            <div class="field">
              <label for="goal">Health Goal</label>
              <select id="goal" name="goal">
                <option>Weight Loss</option><option>Muscle Gain</option><option>General Health</option>
              </select>
            </div>
            <div class="field">
              <label for="dietType">Diet Type</label>
              <select id="dietType" name="dietType">
                <option>Balanced</option><option>Keto</option><option>Vegan</option>
              </select>
            </div>
            <div class="field">
              <label for="activityLevel">Activity</label>
              <select id="activityLevel" name="activityLevel">
                <option>Sedentary</option><option>Moderate</option><option>Very Active</option>
              </select>
            </div>
          </div>

          <div class="actions">
            <button class="btn btn-primary" id="generateBtn" type="submit">✨ Generate Plan Using Gemini AI</button>
            <a class="btn btn-secondary" href="dashboard.php">← Back to Dashboard</a>
          </div>
          <div class="form-note">🔒 Your inputs are used to create this meal plan.</div>
        </form>
      </section>

    <?php else: ?>
      <section class="result" id="mealResult">
        <div class="summary">
          <div>
            <div class="eyebrow" style="color:#9be4b8">Your personalized plan</div>
            <h2>3-Day Meal Plan</h2>
            <p>Strategy: <strong><?php echo htmlspecialchars($mealPlan['summary']['primary_focus'] ?? 'Custom Plan'); ?></strong></p>
          </div>
          <div class="calorie">
            <small>Daily Average</small>
            <strong><?php echo htmlspecialchars((string)($mealPlan['summary']['total_avg_calories'] ?? '---')); ?> kcal</strong>
          </div>
        </div>

        <?php foreach ($mealPlan['days'] as $day): ?>
          <section class="day">
            <div class="day-head">
              <h3>Day <?php echo htmlspecialchars((string)$day['day_number']); ?></h3>
              <button type="button" class="toggle-day" aria-expanded="true">Collapse</button>
            </div>
            <div class="meals">
              <?php foreach ($day['meals'] as $meal): ?>
                <article class="meal">
                  <div>
                    <span class="meal-type"><?php echo htmlspecialchars($meal['type']); ?></span>
                    <div class="meal-cal">🔥 <?php echo htmlspecialchars((string)$meal['calories']); ?> calories</div>
                  </div>
                  <div>
                    <h4><?php echo htmlspecialchars($meal['name']); ?></h4>
                    <div class="macros">
                      <div class="macro"><small>Protein</small><b><?php echo htmlspecialchars($meal['protein']); ?></b></div>
                      <div class="macro"><small>Carbs</small><b><?php echo htmlspecialchars($meal['carbs']); ?></b></div>
                      <div class="macro"><small>Fats</small><b><?php echo htmlspecialchars($meal['fats']); ?></b></div>
                    </div>
                    <div class="ingredients">
                      <?php foreach ($meal['ingredients'] as $ing): ?>
                        <span class="ingredient"><?php echo htmlspecialchars($ing); ?></span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>

        <div class="actions">
          <a class="btn btn-primary" href="?">↻ Create New Plan</a>
          <a class="btn btn-secondary" href="dashboard.php">← Dashboard</a>
          <a class="btn btn-secondary" href="chat.php">💬 Nutritionist</a>
        </div>
      </section>
    <?php endif; ?>
  </main>
</div>

<script>
(function(){
  const sidebar=document.getElementById('sidebar');
  const overlay=document.getElementById('overlay');
  const menuBtn=document.getElementById('menuBtn');

  function setMenu(open){
    sidebar.classList.toggle('open',open);
    overlay.classList.toggle('show',open);
    menuBtn.setAttribute('aria-expanded',String(open));
    overlay.setAttribute('aria-hidden',String(!open));
  }
  menuBtn.addEventListener('click',()=>setMenu(!sidebar.classList.contains('open')));
  overlay.addEventListener('click',()=>setMenu(false));
  sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{if(innerWidth<=720)setMenu(false)}));
  addEventListener('resize',()=>{if(innerWidth>720)setMenu(false)});

  document.querySelectorAll('.toggle-day').forEach(button=>{
    button.addEventListener('click',()=>{
      const meals=button.closest('.day').querySelector('.meals');
      const expanded=button.getAttribute('aria-expanded')==='true';
      meals.hidden=expanded;
      button.setAttribute('aria-expanded',String(!expanded));
      button.textContent=expanded?'Expand':'Collapse';
    });
  });

  const form=document.getElementById('plannerForm');
  const generate=document.getElementById('generateBtn');
  if(form && generate){
    form.addEventListener('submit',()=>{
      generate.disabled=true;
      generate.textContent='⏳ Generating your plan…';
      generate.style.opacity='.75';
    });
  }
})();
</script>
</body>
</html>
