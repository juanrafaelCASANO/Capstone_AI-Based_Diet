<?php
session_start();
require_once '../config.php';

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

ini_set('display_errors', 1);
error_reporting(E_ALL);

// [BAGONG IDAGDAG] Profanity Filter Function para palitan ng asterisks ang mga mura
function filterBadWords($text) {
    // Maaari kang magdagdag ng iba pang salita sa listahang ito
    $bawal_na_salita = ['gago', 'puta', 'ulol', 'tanga', 'bobo', 'pakshet', 'gaga']; 
    
    foreach ($bawal_na_salita as $salita) {
        $replacement = str_repeat('*', mb_strlen($salita));
        $text = str_ireplace($salita, $replacement, $text);
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = intval($_SESSION['user_id']); // Ang ID mo bilang Client
    $receiver_id = intval($_POST['nutritionist_id'] ?? ($_POST['receiver_id'] ?? 0)); // Ang ID ng Nutritionist
    
    // [INAYOS] Dito natin sinala ang mensahe gamit ang filter function bago i-save o gamitin
    $raw_message = trim($_POST['message'] ?? '');
    $message = filterBadWords($raw_message);

    if (!empty($message) && $sender_id > 0 && $receiver_id > 0) {
        try {
            $conn->beginTransaction();

            // 1. I-save muna ang na-filter na mensahe na ikaw ang nag-send (Mapupunta sa kanan)[cite: 15]
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$sender_id, $receiver_id, $message]);

            // 2. Suriin ang availability status, main status, at kung kailan siya huling aktibo (last_active_at)[cite: 15]
            $nutriStatusStmt = $conn->prepare("
                SELECT availability_status, status, last_active_at 
                FROM nutritionist 
                WHERE id = ? 
                LIMIT 1
            ");
            $nutriStatusStmt->execute([$receiver_id]);
            $nutriInfo = $nutriStatusStmt->fetch(PDO::FETCH_ASSOC);

            // Alamin kung ang nutritionist ay talagang online o active pa sa usapan[cite: 15]
            $isNutritionistOnline = false;
            if ($nutriInfo) {
                $avail = strtolower(trim($nutriInfo['availability_status'] ?? 'available'));
                $mainStatus = strtolower(trim($nutriInfo['status'] ?? 'active'));
                $lastActive = $nutriInfo['last_active_at'] ?? null;
                
                // Kung ang account ay hindi active o ang availability ay naka-offline/unavailable, 
                // ituturing nating HINDI online ang nutritionist para sumalo si AI.
                if ($mainStatus === 'active' && ($avail === 'available' || $avail === 'online')) {
                    if ($lastActive) {
                        $timeDiff = time() - strtotime($lastActive);
                        // Kung nag-update ang activity sa loob ng huling 10 minuto (600 seconds), saka lang online[cite: 15]
                        if ($timeDiff < 600) {
                            $isNutritionistOnline = true;
                        }
                    } else {
                        // Kung walang last_active timestamp pero available ang status, i-check natin kung sakali
                        $isNutritionistOnline = true;
                    }
                }
            }

            // 3. Kung ang Nutritionist ay HINDI online (offline / unavailable / on leave), saka lamang paganahin ang AI Assistant[cite: 15]
            if (!$isNutritionistOnline) {
                // I-check kung nag-message na ang AI NGAYONG ARAW para sa user na ito[cite: 15]
                $checkTodayStmt = $conn->prepare("
                    SELECT COUNT(*) as cnt 
                    FROM messages 
                    WHERE sender_id = ? 
                      AND receiver_id = ? 
                      AND (message LIKE '🤖 [AI Assistant]%' OR message LIKE '🐮 [Kabi]:%')
                      AND DATE(created_at) = CURRENT_DATE
                ");
                $checkTodayStmt->execute([$receiver_id, $sender_id]);
                $rowToday = $checkTodayStmt->fetch(PDO::FETCH_ASSOC);
                
                $alreadyGreetedToday = ($rowToday && $rowToday['cnt'] > 0);

                // Buuin ang system prompt batay sa kung na-notify na ba siya ngayong araw o hindi pa[cite: 15]
                if (!$alreadyGreetedToday) {
                    $systemPrompt = 'You are a smart AI Nutritionist Assistant named Kabi for an AI Diet Planner. Because this is the first message from the user today, your response MUST start by briefly letting them know that their assigned nutritionist is currently offline or unavailable, and you are temporarily assisting them. Then provide helpful nutritional advice in simple, conversational English. Keep your focus strictly on food, health, and nutrition.';
                } else {
                    $systemPrompt = 'You are a smart AI Nutritionist Assistant named Kabi for an AI Diet Planner. Provide helpful nutritional advice to the user in simple, conversational English. Do not mention the nutritionist availability notice anymore since it was already mentioned earlier today. Always steer the conversation back to health and nutrition if the user asks about unrelated topics.';
                }

                // Tawagin ang OpenAI API[cite: 15]
                $url = 'https://api.openai.com/v1/chat/completions';
                $apiKey = getenv('MESSAGING_OPENAI_KEY');
                $data = [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ["role" => "system", "content" => $systemPrompt],
                        ["role" => "user", "content" => $message]
                    ],
                    'max_tokens' => 300
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ]);

                $response = curl_exec($ch);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    $aiReply = "cURL Error: " . $curlError;
                } else {
                    $result = json_decode($response, true);
                    if (isset($result['choices'][0]['message']['content'])) {
                        $aiReply = $result['choices'][0]['message']['content'];
                    } else {
                        $aiReply = "OpenAI Error Response: " . json_encode($result);
                    }
                }

                // I-save ang sagot ng AI sa database[cite: 15]
                $stmtAi = $conn->prepare("
                    INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmtAi->execute([$receiver_id, $sender_id, "🐮 [Kabi]: " . $aiReply]);
            }

            $conn->commit();

            echo "success";
            exit;

        } catch (PDOException $e) {
            $conn->rollBack();
            echo "Database Error: " . $e->getMessage();
            exit;
        }
    }
}
echo "Invalid Request";
?>