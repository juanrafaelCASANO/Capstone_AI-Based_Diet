<?php
/**
 * AI-Based Diet and Nutritional Planner System
 */

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
date_default_timezone_set('Asia/Manila');
require_once '../config.php';

// Redirect kung ang user ay hindi naka-login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* =========================================================
   OPENAI AI CLASS (Meal & Alternatives Generator)
========================================================= */
class NutriAI {
    private $apiKey;
    private $chatApiUrl = "https://api.openai.com/v1/chat/completions";

    public function __construct() {
        // Kukuha sa environment variable ng server (Vercel), o gagamit ng fallback para sa local testing
        $this->apiKey = getenv('OPENAI_API_KEY');
    }

    public function generateMealPlan($formData) {
        $cleanKey = trim($this->apiKey);
        if (empty($cleanKey)) throw new Exception("API Key is missing.");

        $numDays = isset($formData['num_days']) ? (int)$formData['num_days'] : 7;
        
        $allergies = trim($formData['allergies'] ?? 'None');
        if (empty($allergies) || strtolower($allergies) === 'none') {
            $allergies = 'None';
            $allergyContext = "";
        } else {
            $allergyContext = "CRITICAL ALLERGIES WARNING: The user is strictly allergic to [ {$allergies} ]. YOU MUST ABSOLUTELY EXCLUDE these ingredients or any derivatives from all meals across all days.";
        }

        $activityLevel = $formData['activityLevel'] ?? 'Moderate';
        $goal = $formData['goal'] ?? 'Maintain Weight';
        $dietType = $formData['dietType'] ?? 'Balanced';

        $systemPrompt = "You are a professional Nutritionist AI. Generate a complete {$numDays}-DAY meal plan. Return ONLY a valid JSON object. $allergyContext "
                      . "Structure: { \"summary\": { \"total_avg_calories\": number, \"primary_focus\": string }, "
                      . "\"days\": [ { \"day_number\": number, \"meals\": [ { \"type\": \"Breakfast|Lunch|Dinner|Snack\", \"name\": \"string with specific portion\", "
                      . "\"calories\": number, \"protein\": \"string\", \"carbs\": \"string\", \"fats\": \"string\", \"ingredients\": [\"string with exact quantity\"] } ] } ] } "
                      . "CRITICAL: Make sure the 'day_number' in the 'days' array increments sequentially from 1 up to {$numDays} (e.g., 1, 2, 3, etc.), and do NOT repeat day numbers.";

        $userQuery = "User: {$formData['age']}yo, {$formData['weight']}kg, {$formData['height']}cm. Goal: {$goal}. Diet: {$dietType}. Activity: {$activityLevel}. Allergies: {$allergies}. Generate for exactly {$numDays} days with unique and correct day numbers.";

        $result = $this->fetchFromChat($systemPrompt, $userQuery, $cleanKey);

        if ($result && isset($result['days']) && is_array($result['days'])) {
            foreach ($result['days'] as $index => &$day) {
                $day['day_number'] = $index + 1;
            }
            unset($day);
        }

        return $result;
    }

    public function getAlternativeMeals($mealType, $targetCalories, $diet, $allergies) {
        $cleanKey = trim($this->apiKey);
        $allergyContext = (!empty($allergies) && strtolower($allergies) !== 'none') ? "CRITICAL ALLERGIES: $allergies. STRICTLY EXCLUDE THESE." : "";

        $systemPrompt = "You are a Nutritionist AI. The user wants to REGENERATE A MEAL and needs 3 DIFFERENT OPTIONS. Return ONLY a valid JSON object. $allergyContext "
                      . "Structure: { \"alternatives\": [ { \"type\": \"$mealType\", \"name\": \"string with portion\", \"calories\": number, \"protein\": \"string\", \"carbs\": \"string\", \"fats\": \"string\", \"ingredients\": [\"string with quantity\"] } ] }";
        
        $userQuery = "Generate 3 different $mealType alternatives. Target calories: around $targetCalories. Diet type: $diet. Make them delicious and varied.";

        return $this->fetchFromChat($systemPrompt, $userQuery, $cleanKey);
    }

    private function fetchFromChat($systemPrompt, $userQuery, $key, $retries = 3, $backoff = 2) {
        $payload = [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userQuery]
            ],
            "response_format" => ["type" => "json_object"],
            "temperature" => 0.8 
        ];

        $ch = curl_init($this->chatApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $key]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 90); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $jsonStr = $data['choices'][0]['message']['content'] ?? null;
            if ($jsonStr) return json_decode(trim(str_replace(['```json', '```'], '', $jsonStr)), true);
        }

        if ($retries > 0 && ($httpCode === 429 || $httpCode >= 500)) {
            sleep($backoff);
            return $this->fetchFromChat($systemPrompt, $userQuery, $key, $retries - 1, $backoff * 2);
        }
        return null;
    }

    public function generateMealImage($mealName) {
        $cleanKey = trim($this->apiKey);

        if (!empty($cleanKey)) {
            $apiUrl = "https://api.openai.com/v1/images/generations";
            $payload = [
                "model" => "dall-e-2",
                "prompt" => "A high-quality studio food photograph of a freshly cooked plated meal: " . $mealName . ", appetizing gourmet presentation, soft lighting.",
                "n" => 1,
                "size" => "512x512"
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cleanKey
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (!empty($data['data'][0]['url'])) {
                    $dataUri = $this->urlToBase64($data['data'][0]['url']);
                    if ($dataUri) return ['success' => true, 'data_uri' => $dataUri];
                }
            }
        }

        $realPhotoUri = $this->fetchCookedDishPhoto($mealName);
        if ($realPhotoUri) {
            return ['success' => true, 'data_uri' => $realPhotoUri];
        }

        $fallbackUrl = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80';
        return ['success' => true, 'data_uri' => $this->urlToBase64($fallbackUrl)];
    }

    private function fetchCookedDishPhoto($mealName) {
        $clean = preg_replace('/\([^)]*\)|[0-9]+g|[0-9]+\s*serving[s]?/i', '', $mealName);
        $clean = trim($clean);

        if (preg_match('/(longganisa|silog|tapsilog|tocino|adobo|sinigang|pancit|lumpia|tinola|kaldereta|bicol express|menudo|sisig)/i', $clean, $matches)) {
            $searchQuery = "Filipino " . $matches[1] . " dish";
        } elseif (preg_match('/(omelette|egg|pancake|waffle|toast|oatmeal)/i', $clean, $matches)) {
            $searchQuery = $matches[1] . " cooked breakfast plate";
        } elseif (preg_match('/(chicken|pork|beef|fish|salmon|shrimp|salad|rice|pasta|soup)/i', $clean, $matches)) {
            $searchQuery = $matches[1] . " cooked meal plate";
        } else {
            $searchQuery = $clean . " food dish";
        }

        $wikiUrl = "https://en.wikipedia.org/w/api.php?action=query&generator=search&gsrsearch=" 
                 . urlencode($searchQuery) 
                 . "&gsrlimit=3&prop=pageimages&pithumbsize=800&format=json";

        $ch = curl_init($wikiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $data = json_decode($res, true);
            if (!empty($data['query']['pages'])) {
                foreach ($data['query']['pages'] as $page) {
                    if (!empty($page['thumbnail']['source'])) {
                        return $this->urlToBase64($page['thumbnail']['source']);
                    }
                }
            }
        }
        return null;
    }

    private function urlToBase64($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $imgData) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($imgData) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($imgData);
        }
        return null;
    }
}

/* =========================================================
   AJAX HANDLERS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $planner = new NutriAI();
        
        if ($_POST['action'] === 'get_alternatives') {
            $result = $planner->getAlternativeMeals($_POST['meal_type'], (int)$_POST['calories'], $_POST['diet_type'] ?? 'None', $_POST['allergies'] ?? 'None');
            echo json_encode(['success' => (bool)$result, 'alternatives' => $result['alternatives'] ?? [], 'error' => $result ? '' : 'Failed to fetch.']);
            exit;
        }

        if ($_POST['action'] === 'get_food_image') {
            $mealName = $_POST['meal_name'] ?? 'healthy food meal';
            $res = $planner->generateMealImage($mealName);
            
            echo json_encode([
                'success' => true,
                'image'   => $res['data_uri']
            ]);
            exit;
        }

        if ($_POST['action'] === 'replace_meal') {
            $dayIndex = (int)$_POST['day_index'];
            $mealIndex = (int)$_POST['meal_index'];
            $newMeal = json_decode($_POST['new_meal'], true);

            if (isset($_SESSION['meal_plan'])) {
                $currentPlan = $_SESSION['meal_plan'];
                $currentPlan['days'][$dayIndex]['meals'][$mealIndex] = $newMeal;
                $_SESSION['meal_plan'] = $currentPlan;
                $updateStmt = $conn->prepare("UPDATE users SET meal_plan = ? WHERE id = ?");
                $updateStmt->execute([json_encode($currentPlan), $user_id]);
                echo json_encode(['success' => true, 'meal' => $newMeal]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Session expired.']);
            }
            exit;
        }

        if ($_POST['action'] === 'toggle_meal_status') {
            $dayIndex = (int)$_POST['day_index'];
            $mealIndex = (int)$_POST['meal_index'];
            $isCompleted = filter_var($_POST['completed'], FILTER_VALIDATE_BOOLEAN) || $_POST['completed'] === 'true';
            $calories = (int)$_POST['calories'];

            $stmtUser = $conn->prepare("SELECT completed_meals, total_calories, meal_progress FROM users WHERE id = ?");
            $stmtUser->execute([$user_id]);
            $userData = $stmtUser->fetch();

            $completedMeals = (int)($userData['completed_meals'] ?? 0);
            $totalCalories = (int)($userData['total_calories'] ?? 0);
            $progress = json_decode($userData['meal_progress'] ?? '{}', true);
            if (!is_array($progress)) {
                $progress = [];
            }

            $mealKey = "day_{$dayIndex}_meal_{$mealIndex}";
            $alreadyChecked = !empty($progress[$mealKey]);

            if ($isCompleted && !$alreadyChecked) {
                $progress[$mealKey] = true;
                $completedMeals += 1;
                $totalCalories += $calories;
            } elseif (!$isCompleted && $alreadyChecked) {
                unset($progress[$mealKey]);
                $completedMeals = max(0, $completedMeals - 1);
                $totalCalories = max(0, $totalCalories - $calories);
            }

            $updateStmt = $conn->prepare("UPDATE users SET completed_meals = ?, total_calories = ?, meal_progress = ? WHERE id = ?");
            $saved = $updateStmt->execute([$completedMeals, $totalCalories, json_encode($progress), $user_id]);

            if ($saved) {
                echo json_encode(['success' => true, 'completed_meals' => $completedMeals, 'total_calories' => $totalCalories]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database update failed.']);
            }
            exit;
        }

        if ($_POST['action'] === 'share_meal_plan') {
            $nutritionist_id = (int)($_POST['nutritionist_id'] ?? 0);
            if ($nutritionist_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Please select a valid nutritionist.']);
                exit;
            }

            if (!isset($_SESSION['meal_plan'])) {
                echo json_encode(['success' => false, 'error' => 'No active meal plan found to share.']);
                exit;
            }

            $clientStmt = $conn->prepare("SELECT fullname, age, height, weight, goal, activity, diet_type, allergies FROM users WHERE id = ?");
            $clientStmt->execute([$user_id]);
            $clientData = $clientStmt->fetch(PDO::FETCH_ASSOC);

            $cWeight  = $clientData['weight'] ?? '---';
            $cHeight  = $clientData['height'] ?? '---';
            $cAge     = $clientData['age'] ?? '---';
            $rawGoal  = trim($clientData['goal'] ?? 'Maintain Weight');
            $cGoal    = (stripos($rawGoal, 'lose') !== false) ? 'Lose Weight' : ((stripos($rawGoal, 'gain') !== false) ? 'Gain Weight' : ((stripos($rawGoal, 'muscle') !== false) ? 'Muscle Building' : 'Maintain Weight'));
            $cActivity = $clientData['activity'] ?? 'Moderate';
            $cDiet    = $clientData['diet_type'] ?? 'Balanced';
            $rawAllergy = trim($clientData['allergies'] ?? '');
            $cAllergy = (empty($rawAllergy) || strtolower($rawAllergy) === 'none') ? 'None' : $rawAllergy;

            $plan = $_SESSION['meal_plan'];
            
            $summaryHtml = '<div style="background: #ffffff; border: 1px solid #e3ebe6; border-radius: 16px; padding: 16px; box-shadow: 0 4px 15px rgba(27,61,48,0.06); max-width: 480px; font-family: Inter, sans-serif; color: #193028;">';
            $summaryHtml .= '<div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e3ebe6; padding-bottom: 10px; margin-bottom: 12px;">';
            $summaryHtml .= '<div style="display: flex; align-items: center; gap: 8px;"><span style="font-size: 20px;">🥗</span><div><strong style="font-size: 14px; display: block;">Shared Weekly Meal Plan</strong><span style="font-size: 11px; color: #6c7b75;">Client Custom Diet Strategy</span></div></div>';
            $summaryHtml .= '<span style="background: #e8f5ed; color: #3b8f63; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 99px;">' . ($plan['summary']['total_avg_calories'] ?? '---') . ' kcal/day</span>';
            $summaryHtml .= '</div>';

            $summaryHtml .= '<div style="background: #f4f8f5; border: 1px solid #d5e5dc; border-radius: 10px; padding: 10px; margin-bottom: 12px; font-size: 11px; color: #245848;">';
            $summaryHtml .= '<div style="font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; color: #173b32;">👤 Client Body Profile & Goals:</div>';
            $summaryHtml .= '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px;">';
            $summaryHtml .= '<div><b>Age/Wt/Ht:</b> ' . $cAge . 'yo | ' . $cWeight . 'kg | ' . $cHeight . 'cm</div>';
            $summaryHtml .= '<div><b>Goal:</b> ' . htmlspecialchars($cGoal) . '</div>';
            $summaryHtml .= '<div><b>Activity:</b> ' . htmlspecialchars($cActivity) . '</div>';
            $summaryHtml .= '<div><b>Diet/Allergy:</b> ' . htmlspecialchars($cDiet) . ' (' . htmlspecialchars($cAllergy) . ')</div>';
            $summaryHtml .= '</div>';
            $summaryHtml .= '</div>';

            $summaryHtml .= '<div onwheel="event.stopPropagation();" ontouchmove="event.stopPropagation();" onscroll="event.stopPropagation();" style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto; padding-right: 4px; overscroll-behavior: contain; touch-action: pan-y;">';
            foreach ($plan['days'] as $d) {
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
            $summaryHtml .= '<div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;"><span style="font-size: 10px; color: #6c7b75; font-style: italic;">Generated by AI Diet Planner System</span>';
            $summaryHtml .= '<a href="nutritionist_edit_plan.php?client_id=' . $user_id . '" style="background: #0c6bba; color: #fff; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-decoration: none;">✏️ Edit Plan</a></div>';
            $summaryHtml .= '</div>';

            $msgStmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, status) VALUES (?, ?, ?, NOW(), 0)");
            $sent = $msgStmt->execute([$user_id, $nutritionist_id, trim($summaryHtml)]);

            if ($sent) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send message to database.']);
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/* =========================================================
   FETCH USER PROFILE DATA, UNREAD MESSAGES & SAVED MEAL PLAN
========================================================= */
$stmt = $conn->prepare("SELECT fullname, age, height, weight, goal, activity, diet_type, allergies, meal_plan, meal_progress FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$userFullName  = $user['fullname'] ?? 'User';
$userAge       = !empty($user['age']) ? $user['age'] : 25;
$userWeight    = !empty($user['weight']) ? $user['weight'] : 70;
$userHeight    = !empty($user['height']) ? $user['height'] : 170;

$rawGoal       = trim($user['goal'] ?? 'Maintain Weight');
if (stripos($rawGoal, 'lose') !== false) {
    $userGoal = 'Lose Weight';
} elseif (stripos($rawGoal, 'gain') !== false) {
    $userGoal = 'Gain Weight';
} elseif (stripos($rawGoal, 'muscle') !== false) {
    $userGoal = 'Muscle Building';
} else {
    $userGoal = !empty($rawGoal) ? $rawGoal : 'Maintain Weight';
}

$userActivity  = $user['activity'] ?? 'Moderate';
$userDietType  = $user['diet_type'] ?? 'Balanced';

$rawAllergies  = trim($user['allergies'] ?? '');
$userAllergies = (empty($rawAllergies) || strtolower($rawAllergies) === 'none') ? 'None' : $rawAllergies;

$mealProgress  = json_decode($user['meal_progress'] ?? '{}', true);
if (!is_array($mealProgress)) {
    $mealProgress = [];
}

$nutri_list_stmt = $conn->query("SELECT id, fullname FROM nutritionist WHERE status = 'approved' OR status IS NULL");
$all_nutritionists = $nutri_list_stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_count = 0;
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 0");
    $count_stmt->execute([$user_id]);
    $row = $count_stmt->fetch();
    if ($row) {
        $unread_count = (int)$row['total'];
    }
} catch (Exception $e) {
    $unread_count = 0;
}

$mealPlan = null;
if (isset($_SESSION['meal_plan'])) {
    $mealPlan = $_SESSION['meal_plan'];
} elseif (!empty($user['meal_plan'])) {
    $mealPlan = json_decode($user['meal_plan'], true);
    $_SESSION['meal_plan'] = $mealPlan;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (isset($_POST['reset_plan'])) {
        unset($_SESSION['meal_plan']);
        $clearStmt = $conn->prepare("UPDATE users SET meal_plan = NULL, meal_progress = NULL, completed_meals = 0, total_calories = 0 WHERE id = ?");
        $clearStmt->execute([$user_id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        $planner = new NutriAI();
        
        $_POST['goal'] = $userGoal;
        $_POST['allergies'] = $userAllergies;
        $_POST['dietType'] = $userDietType;
        $_POST['age'] = $userAge;
        $_POST['weight'] = $userWeight;
        $_POST['height'] = $userHeight;
        $_POST['activityLevel'] = $userActivity;

        $mealPlan = $planner->generateMealPlan($_POST);
        if ($mealPlan && isset($mealPlan['days']) && is_array($mealPlan['days'])) {
            // Smart Date Adjustment: Kung gabi o 8 PM onwards nag-generate, sa kinabukasan magsisimula ang Day 1
            $currentTime = new DateTime();
            $currentHour = (int)$currentTime->format('G');

            if ($currentHour >= 20) {
                $startDate = new DateTime('tomorrow');
            } else {
                $startDate = new DateTime(); 
            }

            foreach ($mealPlan['days'] as $index => &$day) {
                $day['day_number'] = $index + 1;
                $day['date'] = $startDate->format('F d, Y');
                $startDate->modify('+1 day');
            }
            unset($day);

            $_SESSION['meal_plan'] = $mealPlan;
            $updateStmt = $conn->prepare("UPDATE users SET meal_plan = ? WHERE id = ?");
            $updateStmt->execute([json_encode($mealPlan), $user_id]);
        } else {
            $error = "The AI service returned an empty response. Please try again.";
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
<title>AI Diet Planner · Weekly Meal Plan</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --navy:#173b32;
  --navy-2:#245848;
  --green:#3b8f63;
  --green-soft:#e8f5ed;
  --cream:#f7faf6;
  --card:#ffffff;
  --text:#193028;
  --muted:#6c7b75;
  --border:#e3ebe6;
  --danger:#dc2626;
  --shadow:0 10px 30px rgba(27,61,48,.08);
  --radius:18px;
}
*{box-sizing:border-box}
body{margin:0;background:var(--cream);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;}
a,button,input,select{font:inherit} a{color:inherit;text-decoration:none}
.app{min-height:100vh;display:flex;position:relative;}

/* SIDEBAR STYLES MATCHING DASHBOARD */
.sidebar{
  width:260px;
  flex:0 0 260px;
  min-height:100vh;
  position:sticky;
  top:0;
  align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),#123027);
  color:#fff;
  padding:24px 18px;
  display:flex;
  flex-direction:column;
  transition:transform .25s ease;
  z-index:20;
}
.brand{
  display:flex;align-items:center;gap:12px;
  padding:6px 10px 28px;
}
.brand-icon{
  width:42px;height:42px;border-radius:13px;
  display:grid;place-items:center;background:rgba(255,255,255,.12);
  font-size:23px;
}
.brand strong{display:block;font-size:17px}
.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
.nav-label{
  padding:0 12px 8px;color:#9fc2b4;
  text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800;
}
.nav{display:grid;gap:6px}
.nav a{
  display:flex;align-items:center;gap:12px;
  text-decoration:none;padding:13px 12px;border-radius:12px;
  color:#d9ebe4;font-size:14px;font-weight:650;
  transition:background .2s,transform .2s;
  position:relative;
}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}

.nav-badge{
  margin-left:auto;
  background:var(--danger);
  color:#fff;
  font-size:11px;
  font-weight:800;
  padding:2px 8px;
  border-radius:999px;
}

.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;
  transition:background .2s,transform .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2);transform:translateX(2px)}

/* LOGOUT & NOTIFICATION MODAL STYLES */
.logout-modal-overlay {
  position: fixed; inset: 0; background: rgba(23, 59, 50, 0.5); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
}
.logout-modal-overlay.active { opacity: 1; visibility: visible; }
.logout-modal-card {
  background: #ffffff; width: 90%; max-width: 380px; border-radius: 20px; padding: 28px 24px;
  text-align: center; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: scale(0.85);
  transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
.logout-modal-icon {
  width: 56px; height: 56px; background: #fff3f1; color: #b42318; font-size: 26px;
  border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px;
}
.logout-modal-card h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: var(--text); }
.logout-modal-card p { margin: 0 0 24px; font-size: 14px; color: var(--muted); line-height: 1.5; }
.logout-modal-actions { display: flex; gap: 12px; }
.btn-modal-cancel {
  flex: 1; height: 44px; background: var(--cream); color: var(--text); border: 1px solid var(--border);
  border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s;
}
.btn-modal-cancel:hover { background: var(--green-soft); }
.btn-modal-logout {
  flex: 1; height: 44px; background: #b42318; color: #ffffff; border: none; border-radius: 12px;
  font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
  text-decoration: none; transition: background 0.2s, transform 0.2s;
}
.btn-modal-logout:hover { background: #901c12; transform: translateY(-1px); }
.btn-modal-ok {
  flex: 1; height: 44px; background: var(--green); color: #ffffff; border: none; border-radius: 12px;
  font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.2s, transform 0.2s;
}
.btn-modal-ok:hover { background: #2e714e; transform: translateY(-1px); }

/* MAIN CONTENT LAYOUT */
.main{flex:1;padding:26px clamp(18px,4vw,48px) 48px; min-width:0;}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}
.menu-btn{display:none;border:1px solid var(--border);background:#fff;width:44px;height:44px;border-radius:12px;cursor:pointer;color:var(--text)}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.topbar h1{font-size:clamp(25px,3vw,34px);margin:5px 0 0;letter-spacing:-.7px}
.profile{
  display:flex;align-items:center;gap:10px;padding:7px 11px 7px 7px;
  background:#fff;border:1px solid var(--border);border-radius:999px;
  text-decoration:none;transition:border-color .2s,box-shadow .2s;
}
.profile:hover{border-color:#b9d5ca;box-shadow:0 4px 12px rgba(27,61,48,.06)}
.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-weight:800}
.profile-name{font-size:13px;font-weight:700;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.panel{background:#fff;border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);overflow:hidden}
.panel-head{padding:26px clamp(20px,4vw,36px);border-bottom:1px solid var(--border)}
.form{padding:clamp(20px,4vw,36px)}
.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:18px;}
.field label{display:block;font-size:13px;font-weight:750;margin:0 0 8px}
.field input, .field select{width:100%;min-height:48px;padding:0 14px;border:1px solid var(--border);border-radius:12px;background:#f4f8f5;color:var(--muted);outline:none;font-size:16px;}
.field input:disabled { cursor:not-allowed; }

.field select.active-select { background:#fff; cursor:pointer; color:var(--text); border-color:var(--green); box-shadow: 0 4px 10px rgba(59,143,99,0.1); }

.actions{display:flex;gap:10px;margin-top:24px; flex-wrap:wrap;}
.btn{min-height:46px;padding:0 18px;border-radius:12px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:750;font-size:14px;cursor:pointer; text-decoration:none;}
.btn-primary{background:var(--green);color:#fff;box-shadow:0 8px 18px rgba(59,143,99,.18)}
.btn-secondary{background:#fff;color:var(--navy);border-color:var(--border)}
.btn-share { background: #0c6bba; color: #fff; box-shadow: 0 8px 18px rgba(12,107,186,.18); }

.result-layout { display: flex; gap: 24px; align-items: flex-start; }
.plan-col { flex: 1; min-width: 0; display: grid; gap: 18px; }
.alt-col { width: 380px; flex-shrink: 0; position: sticky; top: 24px; }

.alt-panel { background: #fff; border: 1px solid var(--border); border-radius: 20px; padding: 24px; box-shadow: var(--shadow); }
.alt-panel h3 { margin: 0 0 16px; font-size: 20px; display:flex; align-items:center; gap:8px;}
.alt-panel .empty-state { text-align:center; color:var(--muted); padding: 40px 20px; font-size:14px; background:#f4f8f5; border-radius:12px; border:1px dashed #c8ebd5; }
.alt-card { border:1px solid var(--border); border-radius:16px; padding:16px; margin-bottom:16px; background:#fafcfb;}
.alt-card h4 { margin:0 0 8px; font-size:16px;}
.alt-card p { margin:0 0 12px; font-size:12px; color:var(--muted);}
.btn-select-alt { width:100%; background:var(--green-soft); color:var(--green); border:1px solid #c8ebd5; border-radius:8px; padding:10px; font-weight:700; cursor:pointer;}

.summary{background:linear-gradient(135deg,var(--navy),#245848);border-radius:22px;padding:26px;color:#fff;display:flex;align-items:center;justify-content:space-between;}
.summary h2{margin:0;font-size:clamp(25px,3vw,34px);}
.calorie{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:14px 20px;text-align:center;}
.calorie small{display:block;color:#b8d2c8;text-transform:uppercase;font-size:10px;font-weight:800;}
.calorie strong{display:block;color:#9be4b8;font-size:23px;}
.day{background:#fff;border:1px solid var(--border);border-radius:20px;overflow:hidden}
.day-head{display:flex;align-items:center;justify-content:space-between;padding:17px 22px;background:#f4f8f5;border-bottom:1px solid var(--border)}
.day-head h3{margin:0;font-size:17px; display:flex; gap:10px;}
.day-date {font-size:13px; color:var(--muted); font-weight:600;}
.day-head button{border:1px solid var(--border);background:#fff;border-radius:99px;padding:7px 10px;cursor:pointer;font-size:12px;font-weight:750;}
.meals{padding:20px 22px}
.meal{display:grid;grid-template-columns:135px minmax(0,1fr);gap:20px;padding:0 0 22px;margin-bottom:22px;border-bottom:1px solid var(--border); transition: opacity 0.2s;}
.meal.completed { opacity: 0.65; }
.meal.completed .m-name { text-decoration: line-through; color: var(--muted); }
.meal:last-child{margin:0;padding:0;border:0}
.meal-type-wrapper { display: flex; flex-direction: column; gap: 8px; }
.meal-type{font-size:11px;font-weight:850;text-transform:uppercase;color:var(--green);background:var(--green-soft);padding:6px 9px;border-radius:999px; width: fit-content;}
.meal-cal{font-size:12px;color:var(--muted);font-weight:650}
.meal-actions-mini {display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;}
.btn-mini {background:#fff; border:1px solid var(--border); border-radius:8px; padding:6px 10px; font-size:11px; font-weight:700; color:var(--navy); cursor:pointer;}
.meal h4{font-size:19px;margin:0 0 12px}
.macros{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
.macro{background:#f7faf8;border:1px solid var(--border);border-radius:11px;padding:10px;text-align:center}
.macro small{display:block;text-transform:uppercase;font-size:9px;font-weight:800;color:#8a9892}.macro b{display:block;font-size:12px;}
.ingredients{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.ingredient{background:#f3f5f4;border:1px solid var(--border);padding:5px 9px;border-radius:999px;font-size:11px;color:#596963}

.meal-checkbox-wrapper { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; font-weight: 700; color: var(--green); cursor: pointer; }
.meal-checkbox-wrapper input { width: 16px; height: 16px; accent-color: var(--green); cursor: pointer; }

/* KABI LOADING OVERLAY STYLES */
.kabi-loading-overlay {
  position: fixed;
  inset: 0;
  background: rgba(23, 59, 50, 0.92);
  backdrop-filter: blur(12px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  flex-direction: column;
  gap: 22px;
  color: #fff;
  opacity: 0;
  transition: opacity 0.3s ease;
}
.kabi-loading-overlay.active {
  display: flex;
  opacity: 1;
}
.kabi-spinner-box {
  position: relative;
  width: 120px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.kabi-ring {
  position: absolute;
  inset: 0;
  border: 4px solid rgba(255, 255, 255, 0.15);
  border-top: 4px solid #3b8f63;
  border-radius: 50%;
  animation: kabiSpin 1.2s linear infinite;
}
.kabi-logo-center {
  width: 84px;
  height: 84px;
  background: #ffffff;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 40px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
  animation: kabiPulse 1.5s ease-in-out infinite;
}
@keyframes kabiSpin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
@keyframes kabiPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}

/* MODALS */
.modal-overlay {position: fixed; inset: 0; background: rgba(23, 59, 50, 0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;opacity: 0; visibility: hidden; transition: 0.25s;}
.modal-overlay.active { opacity: 1; visibility: visible; }
.modal-card {background: #ffffff; width: 90%; max-width: 450px; border-radius: 20px; padding: 28px 24px; position:relative; max-height: 90vh; overflow-y: auto;}
.modal-close {position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer;}

.img-container { background: #f4f8f5; border-radius: 12px; width: 100%; height: 210px; margin-bottom: 20px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); }
.img-container img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; display: none; }
.img-container.loaded img { display: block; }
.img-loader { position: absolute; display: flex; flex-direction: column; align-items: center; color: var(--muted); font-size: 12px; gap: 8px; }

.overlay{display:none}
@media(max-width:1100px) { .result-layout { flex-direction: column-reverse; } .alt-col { width: 100%; position: static; } }
@media(max-width:720px){ 
  .app{display:block} 
  .sidebar{
    position:fixed;left:0;top:0;bottom:0;min-height:100vh;
    transform:translateX(-105%);box-shadow:20px 0 40px rgba(0,0,0,.16);
  } 
  .sidebar.open{transform:translateX(0)} 
  .overlay{
    position:fixed;inset:0;background:rgba(12,28,22,.42);z-index:15;
  }
  .overlay.show{display:block}
  .main{padding:17px 15px 35px} 
  .menu-btn{display:grid;place-items:center} 
  .meal{grid-template-columns:1fr;gap:10px} 
  .form-grid{grid-template-columns:1fr} 
  .profile-name{display:none}
}
</style>
</head>
<body>
<div class="app">
  <div class="overlay" id="overlay" aria-hidden="true"></div>

  <!-- KABI LOADING OVERLAY NA MAY COUNTDOWN TIMER -->
  <div id="kabiLoadingOverlay" class="kabi-loading-overlay">
    <div class="kabi-spinner-box">
      <div class="kabi-ring"></div>
      <div class="kabi-logo-center">🥗</div>
    </div>
    <div style="text-align: center; max-width: 360px; padding: 0 15px;">
      <h3 style="margin: 0 0 6px; font-size: 21px; font-weight: 700; letter-spacing: -0.5px;">Kabi is prearing a meal plan...</h3>
      <p style="margin: 0 0 14px; font-size: 13px; color: #b8d2c8; line-height: 1.5;">Please bear with us, building your custom nutrition strategy and delicious options!</p>
      <div id="kabiCountdown" style="font-size: 13px; font-weight: 700; color: #9be4b8; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); padding: 6px 16px; border-radius: 99px; display: inline-block;">
        Tinatayang natitira: ~12 segundo
      </div>
    </div>
  </div>

  <!-- SIDEBAR NAVIGATION -->
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div>
        <strong>AI Diet Planner</strong>
        <span>Personal nutrition assistant</span>
      </div>
    </div>

    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a class="active" href="generate_weekly.php"><span class="nav-icon">▦</span>Weekly Meal Plan</a>
      <a href="leaderboard.php"><span class="nav-icon">🏆</span>Leaderboard</a>
      <a href="user_inbox.php">
        <span class="nav-icon">📥</span>Inbox
        <?php if($unread_count > 0): ?>
          <span class="nav-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>

    <div class="sidebar-bottom">
      <a class="logout" href="javascript:void(0);" onclick="showLogoutModal();"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
        <div><div class="eyebrow">Custom Meal Planner</div><h1>Build your nutrition plan</h1></div>
      </div>
      <a class="profile" href="profile.php" title="View & Edit Profile">
        <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($userFullName, 0, 1))); ?></div>
        <span class="profile-name"><?php echo htmlspecialchars($userFullName); ?></span>
      </a>
    </header>

    <?php if (!$mealPlan): ?>
      <section class="panel">
        <div class="panel-head"><h2>Create Your Diet Strategy</h2></div>
        <form method="POST" class="form" id="plannerForm">
          <div class="form-grid">
            <div class="field">
              <label>How many days?</label>
              <select name="num_days" class="active-select" required>
                <option value="1">1 Day Plan</option>
                <option value="2">2 Days Plan</option>
                <option value="3">3 Days Plan</option>
                <option value="5">5 Days Plan</option>
                <option value="7" selected>7 Days Plan</option>
              </select>
            </div>
            <div class="field"><label>Weight (kg)</label><input type="number" value="<?php echo $userWeight; ?>" disabled><input type="hidden" name="weight" value="<?php echo $userWeight; ?>"></div>
            <div class="field"><label>Height (cm)</label><input type="number" value="<?php echo $userHeight; ?>" disabled><input type="hidden" name="height" value="<?php echo $userHeight; ?>"></div>
          </div>
          
          <div class="form-grid">
            <input type="hidden" name="age" value="<?php echo $userAge; ?>">
            <input type="hidden" name="activityLevel" value="<?php echo htmlspecialchars($userActivity); ?>">
            
            <div class="field">
              <label>Goal</label>
              <input type="text" value="<?php echo htmlspecialchars($userGoal); ?>" disabled>
              <input type="hidden" name="goal" value="<?php echo htmlspecialchars($userGoal); ?>">
            </div>

            <div class="field">
              <label>Diet Type</label>
              <input type="text" value="<?php echo htmlspecialchars($userDietType); ?>" disabled>
              <input type="hidden" name="dietType" value="<?php echo htmlspecialchars($userDietType); ?>">
            </div>
            
            <div class="field">
              <label>Food Allergies</label>
              <input type="text" value="<?php echo htmlspecialchars($userAllergies); ?>" disabled>
              <input type="hidden" name="allergies" value="<?php echo htmlspecialchars($userAllergies); ?>">
            </div>
          </div>
          <div class="actions"><button class="btn btn-primary" type="submit">✨ Generate Meal Plan</button></div>
        </form>
      </section>

    <?php else: ?>
      <input type="hidden" id="sys_diet" value="<?php echo htmlspecialchars($userDietType); ?>">
      <input type="hidden" id="sys_allergies" value="<?php echo htmlspecialchars($userAllergies); ?>">

      <div class="result-layout">
        <div class="plan-col">
          <div class="summary">
            <div>
              <div class="eyebrow" style="color:#9be4b8">Your personalized plan</div>
              <h2>Custom Meal Plan</h2>
            </div>
            <div class="calorie"><small>Daily Average</small><strong><?php echo htmlspecialchars((string)($mealPlan['summary']['total_avg_calories'] ?? '---')); ?> kcal</strong></div>
          </div>

          <?php foreach ($mealPlan['days'] as $dayIndex => $day): ?>
            <section class="day">
              <div class="day-head">
                <h3>Day <?php echo htmlspecialchars((string)$day['day_number']); ?> <span class="day-date">• <?php echo htmlspecialchars($day['date'] ?? ''); ?></span></h3>
                <button type="button" class="toggle-day">Collapse</button>
              </div>
              <div class="meals">
                <?php foreach ($day['meals'] as $mealIndex => $meal): $uid = "meal_{$dayIndex}_{$mealIndex}";
                  $mealKey = "day_{$dayIndex}_meal_{$mealIndex}";
                  $isChecked = !empty($mealProgress[$mealKey]);
                ?>
                  <article class="meal <?php echo $isChecked ? 'completed' : ''; ?>" id="article_<?php echo $uid; ?>">
                    <div class="meal-type-wrapper">
                      <span class="meal-type"><?php echo htmlspecialchars($meal['type']); ?></span>
                      <div class="meal-cal">🔥 <?php echo htmlspecialchars((string)$meal['calories']); ?> cal</div>
                      
                      <label class="meal-checkbox-wrapper">
                        <input type="checkbox" <?php echo $isChecked ? 'checked' : ''; ?> 
                               onchange="toggleMealStatus(this, <?php echo $dayIndex; ?>, <?php echo $mealIndex; ?>, <?php echo $meal['calories']; ?>)">
                        <span>Done</span>
                      </label>

                      <div class="meal-actions-mini">
                        <button class="btn-mini" onclick="openMealOverview('<?php echo htmlspecialchars(addslashes(json_encode($meal))); ?>')">👁️ View</button>
                        <button class="btn-mini" style="color:#0c6bba; border-color:#b6dcf9; background:#e8f4fc;" onclick="fetchAlternatives(this, <?php echo $dayIndex; ?>, <?php echo $mealIndex; ?>, '<?php echo $meal['type']; ?>', <?php echo $meal['calories']; ?>)">🔄 Alts</button>
                      </div>
                    </div>
                    <div>
                      <h4 class="m-name"><?php echo htmlspecialchars($meal['name']); ?></h4>
                      <div class="macros">
                        <div class="macro"><small>Protein</small><b class="m-pro"><?php echo htmlspecialchars($meal['protein']); ?></b></div>
                        <div class="macro"><small>Carbs</small><b class="m-car"><?php echo htmlspecialchars($meal['carbs']); ?></b></div>
                        <div class="macro"><small>Fats</small><b class="m-fat"><?php echo htmlspecialchars($meal['fats']); ?></b></div>
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
          
          <div class="actions" style="margin-bottom: 20px;">
            <form method="POST" style="display: inline;">
              <input type="hidden" name="reset_plan" value="1">
              <button type="submit" class="btn btn-primary">↻ Create New Plan</button>
            </form>
            <button type="button" class="btn btn-share" onclick="openShareModal()">📤 Share Meal Plan to Nutritionist</button>
          </div>
        </div>

        <div class="alt-col">
          <div class="alt-panel" id="alternativesPanel">
            <h3><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--green)"></i> Meal Alternatives</h3>
            <div id="altContent">
              <div class="empty-state">
                <i class="fa-solid fa-bowl-food" style="font-size:24px; margin-bottom:12px; color:#9fc2b4;"></i><br>
                Want a different meal? Click the <b>"🔄 Alts"</b> button to see other options here.
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon">🚪</div>
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to log out of your session?</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-cancel" onclick="closeLogoutModal();">Cancel</button>
      <a href="../auth/logout.php" class="btn-modal-logout">Yes, Logout</a>
    </div>
  </div>
</div>

<!-- SHARE MEAL PLAN MODAL -->
<div id="shareModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon" style="background: #e8f5ed; color: var(--green);">📤</div>
    <h3>Share Meal Plan</h3>
    <p>Select a nutritionist you want to send this weekly meal plan summary to:</p>
    
    <div class="field" style="margin-bottom:20px; text-align: left;">
      <label style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; margin-bottom: 6px;">Choose Nutritionist</label>
      <select id="selectNutritionist" style="width:100%; min-height:44px; padding:0 12px; border:1px solid var(--border); border-radius:12px; background:#fff; font-size:14px; color: var(--text);">
        <?php if (count($all_nutritionists) > 0): ?>
          <?php foreach($all_nutritionists as $n): ?>
            <option value="<?php echo $n['id']; ?>"><?php echo htmlspecialchars($n['fullname']); ?></option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="">No approved nutritionists available</option>
        <?php endif; ?>
      </select>
    </div>

    <div class="logout-modal-actions">
      <button class="btn-modal-cancel" onclick="closeShareModal()">Cancel</button>
      <button class="btn-modal-ok" onclick="submitShareMealPlan()" id="btnSendShare">Send to Chat</button>
    </div>
  </div>
</div>

<!-- SUCCESS NOTIFICATION CARD MODAL -->
<div id="successModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon" style="background: #e8f5ed; color: var(--green);">✓</div>
    <h3>Success</h3>
    <p>Meal plan successfully shared to your conversation with the nutritionist!</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-ok" onclick="closeSuccessModal()">OK</button>
    </div>
  </div>
</div>

<!-- MEAL OVERVIEW MODAL -->
<div id="mealOverviewModal" class="modal-overlay">
  <div class="modal-card">
    <button class="modal-close" onclick="closeModal('mealOverviewModal')">&times;</button>
    <div class="eyebrow" id="mo_type" style="margin-bottom:8px;"></div>
    <h3 id="mo_name" style="margin-bottom:16px;"></h3>
    <div class="img-container" id="mo_img_container">
      <div class="img-loader" id="mo_img_loader">
        <i class="fa-solid fa-spinner fa-spin" style="font-size:22px; color:var(--green);"></i>
        <span>Loading food photo...</span>
      </div>
      <img id="mo_generated_img" src="" alt="Food Image">
    </div>
    <div class="macros" style="margin-bottom:16px;">
      <div class="macro"><small>Calories</small><b id="mo_cal"></b></div>
      <div class="macro"><small>Protein</small><b id="mo_pro"></b></div>
      <div class="macro"><small>Carbs</small><b id="mo_car"></b></div>
      <div class="macro"><small>Fats</small><b id="mo_fat"></b></div>
    </div>
    <h4 style="font-size:14px; margin:0 0 10px;">Ingredients:</h4>
    <ul id="mo_ingredients" style="font-size:13px; color:var(--muted); padding-left:20px; line-height:1.6; margin:0 0 20px;"></ul>
  </div>
</div>

<script>
function showModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// Countdown Timer para sa natitirang segundo habang nag-aantay mag-generate
let loadingTimer = null;
let remainingSeconds = 12;

document.getElementById('plannerForm')?.addEventListener('submit', function() {
  const overlay = document.getElementById('kabiLoadingOverlay');
  if (overlay) {
    overlay.classList.add('active');
  }
  
  remainingSeconds = 12;
  const counterEl = document.getElementById('kabiCountdown');
  if (counterEl) {
    counterEl.textContent = `Estimated remaining: ~${remainingSeconds} seconds`;
  }
  
  if (loadingTimer) clearInterval(loadingTimer);
  loadingTimer = setInterval(() => {
    remainingSeconds--;
    if (remainingSeconds > 0) {
      if (counterEl) {
        counterEl.textContent = `Estimated remaining: ~${remainingSeconds} seconds`;
      }
    } else {
      if (counterEl) {
        counterEl.textContent = `Almost done...`;
      }
    }
  }, 1000);
});

function openShareModal() {
  showModal('shareModal');
}

function closeShareModal() {
  closeModal('shareModal');
}

function closeSuccessModal() {
  closeModal('successModal');
}

async function submitShareMealPlan() {
  const nutriId = document.getElementById('selectNutritionist').value;
  if (!nutriId) {
    alert('Please select a nutritionist.');
    return;
  }

  const btn = document.getElementById('btnSendShare');
  btn.textContent = 'Sending...';
  btn.disabled = true;

  const formData = new FormData();
  formData.append('action', 'share_meal_plan');
  formData.append('nutritionist_id', nutriId);

  try {
    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    
    const textRes = await res.text();
    let data;
    try {
      data = JSON.parse(textRes);
    } catch (parseErr) {
      console.error("Server response:", textRes);
      alert('May error sa server response. Pakitingnan ang console.');
      btn.textContent = 'Send to Chat';
      btn.disabled = false;
      return;
    }

    if (data.success) {
      closeShareModal();
      showModal('successModal');
    } else {
      alert('Error: ' + (data.error || 'Failed to share meal plan.'));
    }
  } catch (err) {
    console.error(err);
    alert('Network error while sharing. Pakitingnan ang koneksyon.');
  }

  btn.textContent = 'Send to Chat';
  btn.disabled = false;
}

function showLogoutModal() {
  document.getElementById('logoutModal').classList.add('active');
}

function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('active');
}

document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) closeLogoutModal();
});

document.getElementById('shareModal').addEventListener('click', function(e) {
  if (e.target === this) closeShareModal();
});

document.getElementById('successModal').addEventListener('click', function(e) {
  if (e.target === this) closeSuccessModal();
});

document.querySelectorAll('.modal-overlay').forEach(modal => {
  modal.addEventListener('click', e => { if(e.target === modal) closeModal(modal.id); });
});

async function toggleMealStatus(checkbox, dayIdx, mealIdx, calories) {
  const article = document.getElementById(`article_meal_${dayIdx}_${mealIdx}`);
  const isCompleted = checkbox.checked;

  if (isCompleted) { article.classList.add('completed'); } 
  else { article.classList.remove('completed'); }

  const formData = new FormData();
  formData.append('action', 'toggle_meal_status');
  formData.append('day_index', dayIdx);
  formData.append('meal_index', mealIdx);
  formData.append('completed', isCompleted);
  formData.append('calories', calories);

  try {
    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    const data = await res.json();
    
    if (!data.success) {
      alert("Error saving meal progress: " + (data.error || "Please ensure your 'users' table has a 'meal_progress' column."));
      checkbox.checked = !isCompleted; 
      isCompleted ? article.classList.remove('completed') : article.classList.add('completed');
    }
  } catch (err) {
    alert("Network error: Could not save progress to database.");
    checkbox.checked = !isCompleted; 
    isCompleted ? article.classList.remove('completed') : article.classList.add('completed');
  }
}

async function openMealOverview(mealJsonStr) {
  try {
    const meal = JSON.parse(mealJsonStr);
    document.getElementById('mo_type').textContent = meal.type;
    document.getElementById('mo_name').textContent = meal.name;
    document.getElementById('mo_cal').textContent = meal.calories + ' kcal';
    document.getElementById('mo_pro').textContent = meal.protein;
    document.getElementById('mo_car').textContent = meal.carbs;
    document.getElementById('mo_fat').textContent = meal.fats;
    
    const ul = document.getElementById('mo_ingredients');
    ul.innerHTML = '';
    meal.ingredients.forEach(ing => {
      let li = document.createElement('li');
      li.textContent = ing;
      ul.appendChild(li);
    });

    const imgContainer = document.getElementById('mo_img_container');
    const imgLoader = document.getElementById('mo_img_loader');
    const generatedImg = document.getElementById('mo_generated_img');

    imgContainer.classList.remove('loaded');
    imgLoader.style.display = 'flex';
    generatedImg.onerror = null;
    generatedImg.onload = null;
    generatedImg.src = '';
    showModal('mealOverviewModal');

    let imageUrl = '';

    try {
      const formData = new FormData();
      formData.append('action', 'get_food_image');
      formData.append('meal_name', meal.name);

      const res = await fetch(window.location.href, { method: 'POST', body: formData });
      const data = await res.json();
      
      if (data && data.image) {
        imageUrl = data.image; 
      }
    } catch (err) {
      console.warn('Network error fetching image:', err);
    }

    generatedImg.onload = function() { 
      imgContainer.classList.add('loaded'); 
      imgLoader.style.display = 'none'; 
    };

    generatedImg.onerror = function() { 
      imgContainer.classList.add('loaded'); 
      imgLoader.style.display = 'none'; 
    };

    generatedImg.src = imageUrl;

  } catch(e) { 
    alert("Error loading meal details."); 
  }
}

(function(){
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const menuBtn = document.getElementById('menuBtn');

  function setMenu(open){
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('show', open);
    menuBtn.setAttribute('aria-expanded', String(open));
    overlay.setAttribute('aria-hidden', String(!open));
  }

  menuBtn?.addEventListener('click', function(){
    setMenu(!sidebar.classList.contains('open'));
  });

  overlay?.addEventListener('click', function(){ setMenu(false); });

  sidebar.querySelectorAll('a').forEach(function(link){
    link.addEventListener('click', function(){
      if(window.innerWidth <= 720) setMenu(false);
    });
  });

  window.addEventListener('resize', function(){
    if(window.innerWidth > 720) setMenu(false);
  });
})();

document.querySelectorAll('.toggle-day').forEach(button => {
  button.addEventListener('click', () => {
    const meals = button.closest('.day').querySelector('.meals');
    const expanded = meals.style.display !== 'none';
    meals.style.display = expanded ? 'none' : 'block';
    button.textContent = expanded ? 'Expand' : 'Collapse';
  });
});

let currentTargetDay = null;
let currentTargetMeal = null;

async function fetchAlternatives(btn, dayIdx, mealIdx, mealType, cals) {
  const container = document.getElementById('altContent');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳...';
  btn.disabled = true;

  if(window.innerWidth <= 1100) document.getElementById('alternativesPanel').scrollIntoView({ behavior: 'smooth' });

  container.innerHTML = `<div style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:30px; margin-bottom:15px; color:var(--green)"></i><br>Generating 3 alternative options...</div>`;
  
  currentTargetDay = dayIdx;
  currentTargetMeal = mealIdx;

  const dietVal = document.getElementById('sys_diet') ? document.getElementById('sys_diet').value : 'None';
  const allergyVal = document.getElementById('sys_allergies') ? document.getElementById('sys_allergies').value : 'None';

  const formData = new FormData();
  formData.append('action', 'get_alternatives');
  formData.append('meal_type', mealType);
  formData.append('calories', cals);
  formData.append('diet_type', dietVal);
  formData.append('allergies', allergyVal);

  try {
    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    const data = await res.json();
    
    if(data.success && data.alternatives && data.alternatives.length > 0) {
      container.innerHTML = `<p style="font-size:13px; color:var(--muted); margin:0 0 16px;">Here are your options for Day ${dayIdx + 1} ${mealType}:</p>`;
      data.alternatives.forEach((altMeal) => {
        const jsonStr = JSON.stringify(altMeal).replace(/'/g, "\\'").replace(/"/g, '&quot;');
        container.innerHTML += `
          <div class="alt-card">
            <h4>${altMeal.name}</h4>
            <p>🔥 ${altMeal.calories} cal | 🍗 Pro: ${altMeal.protein} | 🌾 Carbs: ${altMeal.carbs}</p>
            <button class="btn-select-alt" onclick="selectAlternative('${jsonStr}', this)">✓ Select This Meal</button>
          </div>
        `;
      });
    } else {
      container.innerHTML = `<div class="empty-state" style="color:var(--danger);">Failed to generate alternatives.</div>`;
    }
  } catch(e) {
    container.innerHTML = `<div class="empty-state" style="color:var(--danger);">Network Error.</div>`;
  }
  
  btn.innerHTML = originalText;
  btn.disabled = false;
}

async function selectAlternative(mealJsonStr, btnElement) {
  if (currentTargetDay === null || currentTargetMeal === null) return;
  btnElement.innerHTML = '⏳ Saving...';
  btnElement.disabled = true;

  const formData = new FormData();
  formData.append('action', 'replace_meal');
  formData.append('day_index', currentTargetDay);
  formData.append('meal_index', currentTargetMeal);
  formData.append('new_meal', mealJsonStr.replace(/&quot;/g, '"'));

  try {
    const res = await fetch(window.location.href, { method: 'POST', body: formData });
    const data = await res.json();
    
    if(data.success && data.meal) {
      const article = document.getElementById(`article_meal_${currentTargetDay}_${currentTargetMeal}`);
      article.querySelector('.m-name').textContent = data.meal.name;
      article.querySelector('.meal-cal').textContent = `🔥 ${data.meal.calories} cal`;
      article.querySelector('.m-pro').textContent = data.meal.protein;
      article.querySelector('.m-car').textContent = data.meal.carbs;
      article.querySelector('.m-fat').textContent = data.meal.fats;
      
      4
      const ingredientsDiv = article.querySelector('.ingredients');
      ingredientsDiv.innerHTML = data.meal.ingredients.map(i => `<span class="ingredient">${i}</span>`).join('');
      
      const viewBtn = article.querySelector('.meal-actions-mini button:first-child');
      viewBtn.setAttribute('onclick', `openMealOverview('${JSON.stringify(data.meal).replace(/'/g, "\\'")}')`);
      
      document.getElementById('altContent').innerHTML = `
        <div class="empty-state" style="border-color:var(--green); background:var(--green-soft);">
          <i class="fa-solid fa-check-circle" style="font-size:24px; color:var(--green);"></i><br>
          Meal successfully updated! Select another meal for more alternatives.
        </div>`;
    } else {
      alert('Failed to replace meal.');
      btnElement.innerHTML = '✓ Select This Meal';
      btnElement.disabled = false;
    }
  } catch(e) {
    alert('Network error while saving.');
    btnElement.innerHTML = '✓ Select This Meal';
    btnElement.disabled = false;
  }
}
</script>
</body>
</html>