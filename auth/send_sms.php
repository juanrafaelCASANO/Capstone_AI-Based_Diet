<?php

// ============================================================
// SMS SERVICE PROVIDER (Infobip + Firebase / Mock Support)
// ============================================================

require_once __DIR__ . '/../config.php';


function sendSMS_Infobip($phone, $message)
{
    
    $phone = preg_replace('/\D/', '', $phone);

    if (substr($phone, 0, 2) === '09') {
        $phone = '63' . substr($phone, 1);
    } elseif (substr($phone, 0, 1) === '9') {
        $phone = '63' . $phone;
    }

    // Validate Philippine mobile number format
    if (!preg_match('/^639\d{9}$/', $phone)) {
        return [
            'success' => false,
            'error' => 'Invalid Philippine mobile number format. Example: 09123456789.'
        ];
    }

    
    if (!defined('INFOBIP_API_KEY') || empty(INFOBIP_API_KEY) || INFOBIP_API_KEY === 'YOUR_INFOBIP_API_KEY') {
        // Awtomatikong ipinagpapalagay na successful para sa local development / testing
        return [
            'success' => true,
            'message_id' => 'MOCK_SMS_' . time(),
            'status' => 'DELIVERED_MOCK',
            'recipient' => $phone,
            'note' => 'Development Mode: Sent via Mock Gateway (Free/Firebase).'
        ];
    }

    if (!defined('INFOBIP_BASE_URL') || empty(INFOBIP_BASE_URL)) {
        return [
            'success' => false,
            'error' => 'Infobip Base URL is missing in config.php.'
        ];
    }

    $apiKey = INFOBIP_API_KEY;
    $baseUrl = rtrim(INFOBIP_BASE_URL, '/');

    
    $payload = [
        'messages' => [
            [
                'sender' => defined('INFOBIP_SENDER') && !empty(INFOBIP_SENDER)
                    ? INFOBIP_SENDER
                    : 'ServiceSMS',

                'destinations' => [
                    [
                        'to' => $phone
                    ]
                ],

                'content' => [
                    'text' => $message
                ]
            ]
        ]
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/sms/3/messages',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);

    // Check cURL Error
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Decode API Response
    $data = json_decode($response, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid Infobip response. HTTP ' . $httpCode . ': ' . $response
        ];
    }

    // Handle HTTP Errors
    if ($httpCode < 200 || $httpCode >= 300) {
        $errorMessage = 'Infobip API error. HTTP ' . $httpCode;

        if (isset($data['requestError']['serviceException']['text'])) {
            $errorMessage .= ': ' . $data['requestError']['serviceException']['text'];
        } elseif (isset($data['requestError']['policyException']['text'])) {
            $errorMessage .= ': ' . $data['requestError']['policyException']['text'];
        } else {
            $errorMessage .= ': ' . $response;
        }

        return [
            'success' => false,
            'error' => $errorMessage
        ];
    }

    // Success Handling
    if (isset($data['messages'][0])) {
        $sms = $data['messages'][0];

        return [
            'success' => true,
            'message_id' => $sms['messageId'] ?? null,
            'status' => $sms['status']['name'] ?? 'PENDING',
            'recipient' => $sms['destination'] ?? $phone
        ];
    }

    return [
        'success' => false,
        'error' => 'Unexpected Infobip response: ' . $response
    ];
}

?>