<?php
require_once '../config.php';

$user_id = $_POST['user_id'];
$nutritionist_id = $_POST['nutritionist_id'];

$stmt = $conn->prepare("
    SELECT sender, message
    FROM messages
    WHERE user_id = ? AND nutritionist_id = ?
    ORDER BY created_at ASC
");
$stmt->bind_param("ii", $user_id, $nutritionist_id);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()){
    $class = $row['sender'] == 'nutritionist' ? 'nutritionist' : 'user';
    echo "<div class='message $class'>".htmlspecialchars($row['message'])."</div>";
}
