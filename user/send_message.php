<?php
session_start();
require_once '../config.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

ini_set('display_errors', 1);
error_reporting(E_ALL);

function filterBadWords($text) {
    // Pinalawak na listahan ng mga mura at bad words (Tagalog at English)
    $bawal_na_salita = [
        // Tagalog
        'gago', 'gaga', 'puta', 'pota', 'putangina', 'pucha', 'ulol', 'tanga', 'bobo', 
        'pakshet', 'punyeta', 'kupal', 'tarantado', 'hayop', 'leche', 'bwisit', 
        'hinayupak', 'siraulo', 'baliw', 'inamo', 'kinginamo', 'tanginamo', 'inaka', 'walanya', 'walanghiya'[cite: 19]
        // English
        'shit', 'fuck', 'bitch', 'asshole', 'bastard', 'damn', 'crap', 'dick', 
        'pussy', 'slut', 'whore', 'cunt', 'motherfucker', 'dickhead'
    ]; 

    foreach ($bawal_na_salita as $salita) {
        $replacement = str_repeat('*', mb_strlen($salita));
        $text = str_ireplace($salita, $replacement, $text);
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = intval($_SESSION['user_id']); 
    $receiver_id = intval($_POST['nutritionist_id'] ?? ($_POST['receiver_id'] ?? 0)); 
    
    $raw_message = trim($_POST['message'] ?? '');
    $message = filterBadWords($raw_message);

    if (!empty($message) && $sender_id > 0 && $receiver_id > 0) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, sender_type, created_at) 
                VALUES (?, ?, ?, 'user', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$sender_id, $receiver_id, $message]);

            $nutriStatusStmt = $conn->prepare("
                SELECT availability_status, status, last_active_at 
                FROM nutritionist 
                WHERE id = ? LIMIT 1
            ");
            $nutriStatusStmt->execute([$receiver_id]);
            $nutriInfo = $nutriStatusStmt->fetch(PDO::FETCH_ASSOC);

            $isNutritionistOnline = false;
            if ($nutriInfo) {
                $avail = strtolower(trim($nutriInfo['availability_status'] ?? 'available'));
                $mainStatus = strtolower(trim($nutriInfo['status'] ?? 'active'));
                $lastActive = $nutriInfo['last_active_at'] ?? null;
                
                if ($mainStatus === 'active' && ($avail === 'available' || $avail === 'online')) {
                    if ($lastActive) {
                        $timeDiff = time() - strtotime($lastActive);
                        if ($timeDiff < 600) {
                            $isNutritionistOnline = true;
                        }
                    } else {
                        $isNutritionistOnline = true;
                    }
                }
            }

            if (!$isNutritionistOnline) {
                $checkTodayStmt = $conn->prepare("
                    SELECT COUNT(*) as cnt 
                    FROM messages 
                    WHERE sender_id = ? AND receiver_id = ? 
                      AND (message LIKE '🤖 [AI Assistant]%' OR message LIKE '🐮 [Kabi]:%')
                      AND DATE(created_at) = CURRENT_DATE
                ");
                $checkTodayStmt->execute([$receiver_id, $sender_id]);
                $rowToday = $checkTodayStmt->fetch(PDO::FETCH_ASSOC);
                
                $alreadyGreetedToday = ($rowToday && $rowToday['cnt'] > 0);

                if (!$alreadyGreetedToday) {
                    $systemPrompt = 'You are a smart AI Nutritionist Assistant named Kabi for an AI Diet Planner. Because this is the first message from the user today, your response MUST start by briefly letting them know that their assigned nutritionist is currently offline or unavailable, and you are temporarily assisting them. Then provide helpful nutritional advice in simple, conversational English. Keep your focus strictly on food, health, and nutrition.';
                } else {
                    $systemPrompt = 'You are a smart AI Nutritionist Assistant named Kabi for an AI Diet Planner. Provide helpful nutritional advice to the user in simple, conversational English. Do not mention the nutritionist availability notice anymore since it was already mentioned earlier today. Always steer the conversation back to health and nutrition if the user asks about unrelated topics.';
                }

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

                if (!$curlError) {
                    $result = json_decode($response, true);
                    if (isset($result['choices'][0]['message']['content'])) {
                        $aiReply = $result['choices'][0]['message']['content'];
                        
                        $stmtAi = $conn->prepare("
                            INSERT INTO messages (sender_id, receiver_id, message, sender_type, created_at) 
                            VALUES (?, ?, ?, 'ai', CURRENT_TIMESTAMP)
                        ");
                        $stmtAi->execute([$receiver_id, $sender_id, "🐮 [Kabi]: " . $aiReply]);
                    }
                }
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