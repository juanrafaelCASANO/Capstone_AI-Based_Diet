<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access');
}

$nutri_id = (int)$_SESSION['user_id'];
$user_id = (int)($_POST['user_id'] ?? 0);

if (!$user_id || !$nutri_id) {
    exit('Missing IDs');
}

// Kunin ang lahat ng mensahe sa pagitan ng Nutritionist at Client na ito
$query = "SELECT sender_id, receiver_id, message, created_at 
          FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
             OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute([$nutri_id, $user_id, $user_id, $nutri_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sender = (int)$row['sender_id'];
    
    // MAHALAGA: Dito sinusuri kung ang nagpadala ay ang Nutritionist o ang Client
    $is_nutritionist = ($sender === $nutri_id);
    $formatted_time = date('g:i A', strtotime($row['created_at']));

    if ($is_nutritionist) {
        // Kapag ang Nutritionist ang nagpadala -> KANAN (Green/Teal bubble)
        echo '<div style="display: flex; width: 100%; justify-content: flex-end; margin-bottom: 8px;">';
        echo '  <div style="background: #0d9488; color: #ffffff; padding: 12px 16px; border-radius: 18px; border-bottom-right-radius: 4px; max-width: 70%; word-break: break-word;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div style="font-size: 10px; opacity: 0.8; margin-top: 4px; text-align: right;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    } else {
        // Kapag ang Client/User ang nagpadala -> KALIWA (White/Gray bubble)
        echo '<div style="display: flex; width: 100%; justify-content: flex-start; margin-bottom: 8px;">';
        echo '  <div style="background: #ffffff; color: #112d25; padding: 12px 16px; border-radius: 18px; border-bottom-left-radius: 4px; max-width: 70%; word-break: break-word; border: 1px solid #dbece5;">';
        echo '    <div style="font-size: 14px; line-height: 1.5;">' . htmlspecialchars_decode($row['message'], ENT_QUOTES) . '</div>';
        echo '    <div style="font-size: 10px; color: #5c736c; margin-top: 4px; text-align: right;">' . $formatted_time . '</div>';
        echo '  </div>';
        echo '</div>';
    }
}
?>