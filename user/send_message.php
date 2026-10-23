<?php
session_start();
require_once '../config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = intval($_SESSION['user_id']); // Ang ID mo bilang Client
    $receiver_id = intval($_POST['nutritionist_id'] ?? ($_POST['receiver_id'] ?? 0)); // Ang ID ng Nutritionist
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $sender_id > 0 && $receiver_id > 0) {
        try {
            $conn->beginTransaction();

            // 1. I-save muna ang tunay na mensahe na ikaw ang nag-send (Mapupunta sa kanan)
            $stmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$sender_id, $receiver_id, $message]);

            // 2. Suriin ang availability o status ng Nutritionist
            $nutriStatusStmt = $conn->prepare("
                SELECT availability_status, status 
                FROM nutritionist 
                WHERE id = ? 
                LIMIT 1
            ");
            $nutriStatusStmt->execute([$receiver_id]);
            $nutriInfo = $nutriStatusStmt->fetch(PDO::FETCH_ASSOC);

            // Alamin kung ang nutritionist ay naka-online o available
            // Halimbawa: kung ang availability_status ay 'available' (o active), HINDI magrereply ang AI.
            // Magrereply lamang ang AI kung sila ay 'on_leave', 'unavailable', o naka-log out/offline.
            $isNutritionistOnline = false;
            if ($nutriInfo) {
                $avail = $nutriInfo['availability_status'] ?? 'available';
                // Kung ang status ay available, ibig sabihin ay online/aktibo siya sa usapan
                if ($avail === 'available') {
                    $isNutritionistOnline = true;
                }
            }

            // 3. Kung ang Nutritionist ay HINDI online, saka lamang paganahin ang AI Assistant
            if (!$isNutritionistOnline) {
                // I-check kung nag-message na ang AI NGAYONG ARAW para sa user na ito
                $checkTodayStmt = $conn->prepare("
                    SELECT COUNT(*) as cnt 
                    FROM messages 
                    WHERE sender_id = ? 
                      AND receiver_id = ? 
                      AND message LIKE '🤖 [AI Assistant]%' 
                      AND DATE(created_at) = CURRENT_DATE
                ");
                $checkTodayStmt->execute([$receiver_id, $sender_id]);
                $rowToday = $checkTodayStmt->fetch(PDO::FETCH_ASSOC);
                
                $alreadyGreetedToday = ($rowToday && $rowToday['cnt'] > 0);

                // Buuin ang system prompt batay sa kung na-notify na ba siya ngayong araw o hindi pa
                if (!$alreadyGreetedToday) {
                    $systemPrompt = 'You are a smart AI Nutritionist Assistant for an AI Diet Planner. Because this is the first message from the user today, your response MUST start by briefly letting them know that their assigned nutritionist is not yet available today, and you are temporarily assisting them. Then provide helpful nutritional advice in simple, conversational English.';
                } else {
                    $systemPrompt = 'You are a smart AI Nutritionist Assistant for an AI Diet Planner. Provide helpful nutritional advice to the user in simple, conversational English. Do not mention the nutritionist availability notice anymore since it was already mentioned earlier today.';
                }

                // Tawagin ang OpenAI API
                $url = 'https://api.openai.com/v1/chat/completions';
                $apiKey = getenv('MESSAGING_OPENAI_KEY');
                $data = [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $message]
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

                // I-save ang sagot ng AI sa database
                $stmtAi = $conn->prepare("
                    INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmtAi->execute([$receiver_id, $sender_id, "🤖 [AI Assistant]: " . $aiReply]);
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