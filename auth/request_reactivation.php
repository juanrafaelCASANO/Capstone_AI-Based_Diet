<?php
session_start();
require_once '../config.php';

$error = "";
$success = "";

if (isset($_POST['request_reactivation'])) {
    $email = trim($_POST['email'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($email) || empty($reason)) {
        $error = "Mangyaring punan ang lahat ng kinakailangang impormasyon.";
    } else {
        // Suriin kung ang nutritionist ay umiiral at inactive
        $stmt = $conn->prepare("SELECT id, fullname, status FROM nutritionist WHERE email = ?");
        $stmt->execute([$email]);
        $nutri = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nutri) {
            $error = "Walang nahanap na nutritionist account gamit ang email na ito.";
        } else {
            $uploaded_docs = [];

            // I-handle ang Multiple File Upload papuntang Supabase Storage
            if (!empty($_FILES['document']['name'][0])) {
                $supabaseUrl = getenv('SUPABASE_URL') ?: 'https://mbsjtesmrowfilvxcaqb.supabase.co';
                // Kunin ang Supabase Service Key o Anon Key mula sa environment variables
                $serviceKey = getenv('SUPABASE_SERVICE_KEY') ?: getenv('SUPABASE_KEY') ?: '';
                $bucket = 'nutritionist-files';

                foreach ($_FILES['document']['name'] as $key => $name) {
                    if ($_FILES['document']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['document']['tmp_name'][$key];
                        $safeName = time() . "_" . $key . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($name));
                        
                        $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $safeName;
                        $fileData = file_get_contents($tmpName);

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: Bearer ' . $serviceKey,
                            'Content-Type: application/octet-stream',
                            'x-upsert: true'
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode >= 200 && $httpCode < 300) {
                            $uploaded_docs[] = $safeName;
                        }
                    }
                }
            }

            if (empty($uploaded_docs)) {
                $error = "Kinakailangan na mag-upload ng kahit isang valid na dokumento para sa reactivation.";
            } else {
                $documents_json = json_encode($uploaded_docs);

                // I-save ang kahilingan sa database (Maaaring i-adjust ang table ayon sa iyong schema)
                try {
                    // Halimbawa: I-update ang status o mag-insert sa reactivation requests table
                    $updateStmt = $conn->prepare("UPDATE nutritionist SET certification = CONCAT(certification, '\nReactivation Request: ', ?), document = ? WHERE id = ?");
                    $updateStmt->execute([$reason, $documents_json, $nutri['id']]);

                    $success = "Matagumpay na naipadala ang iyong kahilingan sa pag-reactivate ng account. Mag-aabang ang admin sa iyong mga dokumento.";
                } catch (Exception $e) {
                    $error = "Nagkaroon ng error sa database: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Account Reactivation | AI Diet Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f4f7ff 0%, #eef3ff 50%, #f8fafc 100%); color: #172033; min-height: 100vh; display: grid; place-items: center; padding: 20px; }
        .card { width: 100%; max-width: 500px; background: white; border-radius: 20px; padding: 35px; box-shadow: 0 10px 35px rgba(15,23,42,.08); border: 1px solid #e2e8f0; }
        .brand { text-align: center; margin-bottom: 25px; }
        .brand-icon { width: 55px; height: 55px; margin: 0 auto 12px; border-radius: 15px; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; display: grid; place-items: center; font-size: 22px; box-shadow: 0 8px 20px rgba(37,99,235,.25); }
        .brand h2 { font-size: 22px; font-weight: 800; color: #111827; }
        .brand p { font-size: 13px; color: #64748b; margin-top: 4px; }
        .form-group { margin-bottom: 16px; display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none; font-size: 14px; color: #1e293b; transition: .2s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .form-group textarea { resize: vertical; min-height: 90px; }
        .file-box { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 12px; background: #f8fafc; text-align: center; cursor: pointer; }
        .file-box input { cursor: pointer; }
        .submit-btn { width: 100%; height: 46px; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 6px 18px rgba(37,99,235,.25); transition: .2s; margin-top: 5px; }
        .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(37,99,235,.35); }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 9px; font-size: 13px; margin-bottom: 20px; border-left: 4px solid #dc2626; }
        .alert-success { background: #dcfce7; color: #15803d; padding: 12px; border-radius: 9px; font-size: 13px; margin-bottom: 20px; border-left: 4px solid #16a34a; }
        .back-link { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: #2563eb; font-weight: 600; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <div class="brand">
        <div class="brand-icon"><i class="fa-solid fa-rotate-right"></i></div>
        <h2>Account Reactivation</h2>
        <p>Magsumite ng kahilingan at mga dokumento para sa muling pagbubukas ng iyong account.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="Ilagay ang iyong rehistradong email" required>
        </div>

        <div class="form-group">
            <label>Dahilan ng Kahilingan</label>
            <textarea name="reason" placeholder="Ikwento o ilatag ang dahilan kung bakit dapat i-reactivate ang iyong account..." required></textarea>
        </div>

        <div class="form-group">
            <label>Mga Dokumento / Patunay (Multiple files allowed)</label>
            <div class="file-box">
                <input type="file" name="document[]" accept="image/*,.pdf,.doc,.docx" multiple required>
            </div>
            <span style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Maaari kang pumili ng maramihang files nang sabay-sabay.</span>
        </div>

        <button type="submit" name="request_reactivation" class="submit-btn">Ipadala ang Kahilingan</button>
    </form>

    <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Bumalik sa Login</a>
</div>

</body>
</html>