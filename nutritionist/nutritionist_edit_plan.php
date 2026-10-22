<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutri_id = $_SESSION['user_id'];
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if ($client_id <= 0) {
    header("Location: inbox.php");
    exit;
}

$stmt = $conn->prepare("SELECT fullname, age, height, weight, goal, activity, diet_type, allergies, meal_plan FROM users WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client || empty($client['meal_plan'])) {
    header("Location: inbox.php?error=no_plan");
    exit;
}

$client_name = $client['fullname'] ?? 'Client';
$mealPlan = json_decode($client['meal_plan'], true);

// OpenAI Function para sa AI-powered macros computation
function getAIMacrosForMeal($mealName) {
    // Kukuha muna sa environment variable ng server (tulad ng Vercel), 
    // kapag wala, gagamitin ang iyong key para sa local testing
    $apiKey = getenv('OPENAI_API_KEY');
    $apiUrl = "https://api.openai.com/v1/chat/completions";
    // ... natitirang code ng iyong function ...
}

    $systemPrompt = "You are a professional Nutritionist AI. Given a meal name, estimate its nutritional values accurately. Return ONLY a valid JSON object with this structure: { \"calories\": number, \"protein\": \"string with unit e.g. 30g\", \"carbs\": \"string with unit e.g. 45g\", \"fats\": \"string with unit e.g. 15g\" }";
    
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => "Analyze this meal and provide calories and macros: " . $mealName]
        ],
        "response_format" => ["type" => "json_object"],
        "temperature" => 0.3
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . trim($apiKey)]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $jsonStr = $data['choices'][0]['message']['content'] ?? null;
        if ($jsonStr) {
            return json_decode(trim(str_replace(['```json', '```'], '', $jsonStr)), true);
        }
    }
    return null;
}

$success_shared = false;

// Kapag sinave ng nutritionist ang in-edit na meal plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    try {
        $updated_days = $_POST['days'] ?? [];
        $totalCaloriesAll = 0;
        $totalMealsCount = 0;
        
        foreach ($mealPlan['days'] as $index => &$day) {
            if (isset($updated_days[$index])) {
                foreach ($day['meals'] as $mIndex => &$meal) {
                    if (isset($updated_days[$index]['meals'][$mIndex])) {
                        $inputMeal = $updated_days[$index]['meals'][$mIndex];
                        $newName = trim($inputMeal['name'] ?? $meal['name']);
                        
                        if ($newName !== $meal['name']) {
                            $aiData = getAIMacrosForMeal($newName);
                            if ($aiData) {
                                $meal['name'] = $newName;
                                $meal['calories'] = (int)($aiData['calories'] ?? 400);
                                $meal['protein'] = $aiData['protein'] ?? '25g';
                                $meal['carbs'] = $aiData['carbs'] ?? '40g';
                                $meal['fats'] = $aiData['fats'] ?? '15g';
                            } else {
                                $meal['name'] = $newName;
                                $meal['calories'] = (int)($inputMeal['calories'] ?? $meal['calories']);
                                $meal['protein'] = trim($inputMeal['protein'] ?? $meal['protein']);
                                $meal['carbs'] = trim($inputMeal['carbs'] ?? $meal['carbs']);
                                $meal['fats'] = trim($inputMeal['fats'] ?? $meal['fats']);
                            }
                        } else {
                            $meal['name'] = $newName;
                            $meal['calories'] = (int)($inputMeal['calories'] ?? $meal['calories']);
                            $meal['protein'] = trim($inputMeal['protein'] ?? $meal['protein']);
                            $meal['carbs'] = trim($inputMeal['carbs'] ?? $meal['carbs']);
                            $meal['fats'] = trim($inputMeal['fats'] ?? $meal['fats']);
                        }

                        $totalCaloriesAll += $meal['calories'];
                        $totalMealsCount++;
                    }
                }
            }
        }
        unset($day, $meal);

        if ($totalMealsCount > 0) {
            $daysCount = count($mealPlan['days']);
            $avgDailyCalories = round($totalCaloriesAll / max(1, $daysCount));
            $mealPlan['summary']['total_avg_calories'] = (int)$avgDailyCalories;
        }

        // 1. I-update sa database ang meal plan ng client
        $updateStmt = $conn->prepare("UPDATE users SET meal_plan = ? WHERE id = ?");
        $updateStmt->execute([json_encode($mealPlan), $client_id]);

        // 2. Buuin ang HTML Card para isend sa chat (Walang Edit button para sa Client)
        $cWeight  = $client['weight'] ?? '---';
        $cHeight  = $client['height'] ?? '---';
        $cAge     = $client['age'] ?? '---';
        $cGoal    = $client['goal'] ?? '---';
        $cActivity = $client['activity'] ?? '---';
        $cDiet    = $client['diet_type'] ?? '---';
        $cAllergy = $client['allergies'] ?? 'None';

        $summaryHtml = '<div style="background: #ffffff; border: 1px solid #e3ebe6; border-radius: 16px; padding: 16px; box-shadow: 0 4px 15px rgba(27,61,48,0.06); max-width: 480px; font-family: Inter, sans-serif; color: #193028;">';
        $summaryHtml .= '<div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e3ebe6; padding-bottom: 10px; margin-bottom: 12px;">';
        $summaryHtml .= '<div style="display: flex; align-items: center; gap: 8px;"><span style="font-size: 20px;">🥗</span><div><strong style="font-size: 14px; display: block;">Updated Weekly Meal Plan (By Nutritionist)</strong><span style="font-size: 11px; color: #6c7b75;">Custom Professional Adjustment</span></div></div>';
        $summaryHtml .= '<span style="background: #e8f5ed; color: #3b8f63; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 99px;">' . ($mealPlan['summary']['total_avg_calories'] ?? '---') . ' kcal/day</span>';
        $summaryHtml .= '</div>';

        // Client Body Profile Box
        $summaryHtml .= '<div style="background: #f4f8f5; border: 1px solid #d5e5dc; border-radius: 10px; padding: 10px; margin-bottom: 12px; font-size: 11px; color: #245848;">';
        $summaryHtml .= '<div style="font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; color: #173b32;">👤 Client Body Profile & Goals:</div>';
        $summaryHtml .= '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px;">';
        $summaryHtml .= '<div><b>Age/Wt/Ht:</b> ' . $cAge . 'yo | ' . $cWeight . 'kg | ' . $cHeight . 'cm</div>';
        $summaryHtml .= '<div><b>Goal:</b> ' . htmlspecialchars($cGoal) . '</div>';
        $summaryHtml .= '<div><b>Activity:</b> ' . htmlspecialchars($cActivity) . '</div>';
        $summaryHtml .= '<div><b>Diet/Allergy:</b> ' . htmlspecialchars($cDiet) . ' (' . htmlspecialchars($cAllergy) . ')</div>';
        $summaryHtml .= '</div>';
        $summaryHtml .= '</div>';

        // Scrollable Days container
        $summaryHtml .= '<div onwheel="event.stopPropagation();" ontouchmove="event.stopPropagation();" onscroll="event.stopPropagation();" style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto; padding-right: 4px; overscroll-behavior: contain; touch-action: pan-y;">';
        foreach ($mealPlan['days'] as $d) {
            $summaryHtml .= '<div style="background: #f7faf6; border: 1px solid #e3ebe6; border-radius: 10px; padding: 10px;">';
            $summaryHtml .= '<div style="font-size: 12px; font-weight: 700; color: #3b8f63; margin-bottom: 6px;">Day ' . $d['day_number'] . ' <span style="color: #6c7b75; font-weight: 400;">(' . ($d['date'] ?? '') . ')</span></div>';
            foreach ($d['meals'] as $m) {
                $summaryHtml .= '<div style="font-size: 12px; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">';
                $summaryHtml .= '<span><b>' . $m['type'] . ':</b> ' . htmlspecialchars($m['name']) . '</span>';
                $summaryHtml .= '<span style="color: #6c7b75; font-size: 11px; background: #fff; padding: 2px 6px; border-radius: 6px; border: 1px solid #e3ebe6;">' . $m['calories'] . ' cal</span>';
                $summaryHtml .= '</div>';
            }
            $summaryHtml .= '</div>';
        }
        $summaryHtml .= '</div>';
        // Inalis ang edit button sa loob ng sent card para hindi makita o magalaw ng client
        $summaryHtml .= '<div style="margin-top: 12px; text-align: right;"><span style="font-size: 10px; color: #6c7b75; font-style: italic;">Updated & Verified by Professional Nutritionist</span></div>';
        $summaryHtml .= '</div>';

        // 3. I-save sa messages table
        $msgStmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, status) VALUES (?, ?, ?, NOW(), 0)");
        $msgStmt->execute([$nutri_id, $client_id, trim($summaryHtml)]);

        $_SESSION['meal_plan'] = $mealPlan;
        $success_shared = true;
        $success_message = "Meal plan successfully updated, synchronized, and sent to the client's chat conversation!";
    } catch (Exception $e) {
        $error_message = "Failed to update meal plan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Meal Plan for <?php echo htmlspecialchars($client_name); ?> | Nutritionist Portal</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🩺</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --navy: #0f3a2e;
  --teal: #0d9488;
  --teal-soft: #ccfbf1;
  --cream: #f4fbf7;
  --card: #ffffff;
  --text: #112d25;
  --muted: #5c736c;
  --border: #dbece5;
  --shadow: 0 10px 30px rgba(15,58,46,.08);
  --radius: 18px;
  --success: #10b981;
}
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--cream); color: var(--text); }
a { color: inherit; text-decoration: none; }
.app { min-height: 100vh; display: flex; }

/* SIDEBAR */
.sidebar {
  width: 260px; flex: 0 0 260px; height: 100vh; position: sticky; top: 0;
  background: linear-gradient(180deg, var(--navy), #0a2820); color: #fff; padding: 24px 18px;
  display: flex; flex-direction: column; overflow: hidden; z-index: 20;
}
.brand { display: flex; align-items: center; gap: 12px; padding: 6px 10px 28px; flex-shrink: 0; }
.brand-icon { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: rgba(255,255,255,.12); font-size: 23px; }
.brand strong { display: block; font-size: 17px; } 
.brand span { display: block; color: #99f6e4; font-size: 12px; margin-top: 2px; }

.nav-label { padding: 0 12px 8px; color: #80e0d0; text-transform: uppercase; font-size: 10px; font-weight: 800; letter-spacing: .11em; }
.nav { display: grid; gap: 6px; }
.nav a { display: flex; align-items: center; gap: 12px; padding: 13px 12px; border-radius: 12px; color: #e6fffa; font-size: 14px; font-weight: 650; }
.nav a.active { background: rgba(255,255,255,.14); color: #fff; }
.nav-icon { width: 24px; text-align: center; font-size: 17px; }

.sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,.1); padding-top: 16px; }
.sidebar-bottom a.logout {
  display: flex; align-items: center; gap: 12px; padding: 13px 12px;
  border-radius: 12px; color: #f8d7da !important; font-size: 14px; font-weight: 650;
  transition: background .2s, transform .2s;
}
.sidebar-bottom a.logout:hover { background: rgba(220,53,69,.2); transform: translateX(2px); }

/* MAIN CONTENT */
.main { flex: 1; padding: 26px 48px; max-width: 1050px; }
.topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.welcome h1 { font-size: 26px; margin: 4px 0 0; }
.back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--teal); font-weight: 700; font-size: 13px; margin-bottom: 12px; }

.alert-success { background: #d1fae5; color: #065f46; padding: 14px 18px; border-radius: 12px; font-weight: 600; margin-bottom: 20px; border: 1px solid #a7f3d0; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.alert-error { background: #fee2e2; color: #991b1b; padding: 14px 18px; border-radius: 12px; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca; display: flex; align-items: center; gap: 10px; }

.day-section { background: var(--card); border: 1px solid var(--border); border-radius: 18px; padding: 24px; margin-bottom: 20px; box-shadow: var(--shadow); }
.day-title { font-size: 18px; font-weight: 700; color: var(--navy); margin-bottom: 16px; border-bottom: 2px solid var(--cream); padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }

.meal-item { background: #f9fbf9; border: 1px solid var(--border); border-radius: 14px; padding: 16px; margin-bottom: 14px; }
.meal-header { font-size: 13px; font-weight: 800; text-transform: uppercase; color: var(--teal); margin-bottom: 10px; display:flex; justify-content:space-between; align-items:center; }
.ai-badge { background: var(--teal-soft); color: var(--teal); font-size: 10px; padding: 2px 8px; border-radius: 99px; font-weight: 700; }

.form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 10px; }
@media(max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

.field label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; }
.field input { width: 100%; height: 42px; padding: 0 12px; border: 1px solid var(--border); border-radius: 10px; background: #fff; font-size: 14px; color: var(--text); outline: none; }
.field input:focus { border-color: var(--teal); }

.save-action { position: sticky; bottom: 20px; background: rgba(244,251,247,0.9); backdrop-filter: blur(8px); padding: 16px 0; display: flex; justify-content: space-between; align-items: center; z-index: 10; border-top: 1px solid var(--border); margin-top: 20px; }
.btn-save { background: var(--teal); color: #fff; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; box-shadow: 0 4px 15px rgba(13,148,136,.25); transition: background .2s, transform .2s; }
.btn-save:hover { background: #0f766e; transform: translateY(-1px); }
.btn-back-chat { background: #f1f5f9; color: var(--navy); border: 1px solid var(--border); padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background .2s; }
.btn-back-chat:hover { background: #e2e8f0; }
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon">🩺</div>
      <div><strong>NutriPanel</strong><span>Nutritionist portal</span></div>
    </div>
    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="add_meal.php"><span class="nav-icon">🍲</span>Add Meal</a>
      <a href="inbox.php" class="active"><span class="nav-icon">📥</span>Inbox</a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <a class="logout" href="../auth/logout.php"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <a href="chat_reply.php?user_id=<?php echo $client_id; ?>" class="back-link">
      <i class="fa-solid fa-arrow-left"></i> Back to Chat Session
    </a>

    <header class="topbar">
      <div class="welcome">
        <h1>Edit Meal Plan for <?php echo htmlspecialchars($client_name); ?></h1>
        <p style="color:var(--muted); font-size:14px; margin:4px 0 0;">Modify the client's meals. Changes will instantly update their dashboard and send a clean card to the chat.</p>
      </div>
    </header>

    <?php if (isset($success_message)): ?>
      <div class="alert-success">
        <div style="display:flex; align-items:center; gap:10px;">
          <i class="fa-solid fa-check-circle" style="font-size:18px;"></i>
          <span><?php echo $success_message; ?></span>
        </div>
        <a href="chat_reply.php?user_id=<?php echo $client_id; ?>" class="btn-back-chat" style="background:#fff; color:#065f46; padding:6px 12px; font-size:12px;">
          <i class="fa-solid fa-comments"></i> Return to Chat
        </a>
      </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
      <div class="alert-error">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
        <span><?php echo $error_message; ?></span>
      </div>
    <?php endif; ?>

    <form method="POST">
      <?php if (!empty($mealPlan['days'])): ?>
        <?php foreach ($mealPlan['days'] as $dIndex => $day): ?>
          <div class="day-section">
            <div class="day-title">
              <span>Day <?php echo htmlspecialchars((string)$day['day_number']); ?> <span style="font-size:13px; color:var(--muted); font-weight:400;">(<?php echo htmlspecialchars($day['date'] ?? ''); ?>)</span></span>
            </div>

            <?php if (!empty($day['meals'])): ?>
              <?php foreach ($day['meals'] as $mIndex => $meal): ?>
                <div class="meal-item">
                  <div class="meal-header">
                    <span><?php echo htmlspecialchars($meal['type']); ?></span>
                    <span class="ai-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Powered</span>
                  </div>
                  
                  <input type="hidden" name="days[<?php echo $dIndex; ?>][meals][<?php echo $mIndex; ?>][type]" value="<?php echo htmlspecialchars($meal['type']); ?>">

                  <div class="form-grid">
                    <div class="field" style="grid-column: span 2;">
                      <label>Meal Name & Portion</label>
                      <input type="text" name="days[<?php echo $dIndex; ?>][meals][<?php echo $mIndex; ?>][name]" value="<?php echo htmlspecialchars($meal['name'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                      <label>Calories (kcal)</label>
                      <input type="number" name="days[<?php echo $dIndex; ?>][meals][<?php echo $mIndex; ?>][calories]" value="<?php echo htmlspecialchars($meal['calories'] ?? 0); ?>" required>
                    </div>
                    <div class="field">
                      <label>Protein</label>
                      <input type="text" name="days[<?php echo $dIndex; ?>][meals][<?php echo $mIndex; ?>][protein]" value="<?php echo htmlspecialchars($meal['protein'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                      <label>Carbs</label>
                      <input type="text" name="days[<?php echo $dIndex; ?>][meals][<?php echo $mIndex; ?>][carbs]" value="<?php echo htmlspecialchars($meal['carbs'] ?? ''); ?>" required>
                    </div>
                  </div>
                  <div class="form-grid" style="margin-top: 10px;">
                    <div class="field" style="max-width: 200px;">
                      <label>Fats</label>
                      <input type="text" name="days[<?php echo $dIndex; ?>][meals][<?php echo $mIndex; ?>][fats]" value="<?php echo htmlspecialchars($meal['fats'] ?? ''); ?>" required>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="save-action">
          <a href="chat_reply.php?user_id=<?php echo $client_id; ?>" class="btn-back-chat">
            <i class="fa-solid fa-arrow-left"></i> Back to Conversation
          </a>
          <button type="submit" name="save_plan" class="btn-save">
            <i class="fa-solid fa-floppy-disk"></i> Save, Sync & Send to Chat
          </button>
        </div>
      <?php else: ?>
        <p style="text-align:center; color:var(--muted); padding:40px;">No meals found in this plan.</p>
      <?php endif; ?>
    </form>
  </main>
</div>

</body>
</html>