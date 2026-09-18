<?php
function sendSMS_Semaphore($phone, $message) {
    $apiKey = "YOUR_SEMAPHORE_API_KEY"; // Replace with your Semaphore API Key
    $senderName = "SEMAPHORE"; // Optional: Your custom approved Sender Name

    $ch = curl_init();
    $parameters = array(
        'apikey'     => $apiKey,
        'number'     => $phone,
        'message'    => $message,
        'sendername' => $senderName
    );

    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}
?>