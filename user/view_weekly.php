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
    <title>NutriAI - PHP Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <nav class="bg-white border-b border-slate-200 py-4 mb-8 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="bg-emerald-500 p-2 rounded-lg text-white font-bold">N</div>
                <span class="text-xl font-bold tracking-tight text-slate-800">NutriAI</span>
            </div>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest">PHP AI Extraction Engine</span>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 pb-20">
        <?php if (!$mealPlan): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden">
                <div class="p-8 border-b border-slate-100">
                    <h2 class="text-2xl font-bold text-slate-800">Create Your Diet Strategy</h2>
                    <p class="text-slate-500 mt-1">Personalized 3-day meal plan based on your metrics.</p>
                </div>

                <form method="POST" class="p-8 space-y-6">
                    <?php if ($error): ?>
                        <div class="p-4 bg-red-50 text-red-600 rounded-xl border border-red-100 text-sm font-medium">
                            <p class="font-bold mb-1">⚠️ Error Detail:</p>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Age</label>
                            <input type="number" name="age" value="25" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Weight (kg)</label>
                            <input type="number" name="weight" value="70" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Height (cm)</label>
                            <input type="number" name="height" value="170" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Health Goal</label>
                            <select name="goal" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                                <option>Weight Loss</option>
                                <option>Muscle Gain</option>
                                <option>General Health</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Diet Type</label>
                            <select name="dietType" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                                <option>Balanced</option>
                                <option>Keto</option>
                                <option>Vegan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Activity</label>
                            <select name="activityLevel" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none">
                                <option>Sedentary</option>
                                <option>Moderate</option>
                                <option>Very Active</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-emerald-100 mt-4">
                        Generate Plan Using Gemini AI
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div class="bg-slate-900 rounded-3xl p-8 text-white flex flex-col md:flex-row justify-between items-center gap-6">
                    <div>
                        <h2 class="text-3xl font-bold">Your Meal Plan</h2>
                        <p class="text-slate-400 mt-2">Strategy: <span class="text-emerald-400"><?php echo htmlspecialchars($mealPlan['summary']['primary_focus'] ?? 'Custom Plan'); ?></span></p>
                    </div>
                    <div class="bg-white/10 px-6 py-4 rounded-2xl text-center">
                        <p class="text-xs uppercase font-bold text-slate-400">Daily Average</p>
                        <p class="text-2xl font-bold text-emerald-400"><?php echo $mealPlan['summary']['total_avg_calories'] ?? '---'; ?> kcal</p>
                    </div>
                </div>

                <?php foreach ($mealPlan['days'] as $day): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="bg-slate-50 px-8 py-4 border-b border-slate-100">
                            <h3 class="text-lg font-bold text-slate-800">Day <?php echo $day['day_number']; ?></h3>
                        </div>
                        <div class="p-8 space-y-8">
                            <?php foreach ($day['meals'] as $meal): ?>
                                <div class="flex flex-col md:flex-row gap-6 pb-8 last:pb-0 border-b last:border-0 border-slate-100">
                                    <div class="md:w-48 shrink-0">
                                        <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold uppercase mb-2">
                                            <?php echo htmlspecialchars($meal['type']); ?>
                                        </span>
                                        <div class="text-sm font-semibold text-slate-500">🔥 <?php echo $meal['calories']; ?> Calories</div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-xl font-bold text-slate-800 mb-3"><?php echo htmlspecialchars($meal['name']); ?></h4>
                                        <div class="grid grid-cols-3 gap-4 mb-4">
                                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                                                <div class="text-[10px] uppercase font-bold text-slate-400">Protein</div>
                                                <div class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($meal['protein']); ?></div>
                                            </div>
                                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                                                <div class="text-[10px] uppercase font-bold text-slate-400">Carbs</div>
                                                <div class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($meal['carbs']); ?></div>
                                            </div>
                                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                                                <div class="text-[10px] uppercase font-bold text-slate-400">Fats</div>
                                                <div class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($meal['fats']); ?></div>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($meal['ingredients'] as $ing): ?>
                                                <span class="text-xs bg-slate-100 px-3 py-1 rounded-full text-slate-600 font-medium border border-slate-200">
                                                    <?php echo htmlspecialchars($ing); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <a href="?" class="block w-full py-4 bg-slate-800 text-white text-center font-bold rounded-2xl hover:bg-slate-700 transition-all">
                    Create New Plan
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>