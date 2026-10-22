<?php
// register_nutritionist.php
session_start();
require_once '../config.php';

$errors = [];
$success = "";

$firstname = $_POST['firstname'] ?? '';
$middlename = $_POST['middlename'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$address = $_POST['address'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$nutritionist_type = $_POST['nutritionist_type'] ?? '';
$experience = $_POST['experience'] ?? '';
$latitude = $_POST['latitude'] ?? '14.3498';
$longitude = $_POST['longitude'] ?? '121.0794';

// ======================================================
// SUPABASE STORAGE UPLOAD HELPER FUNCTION
// ======================================================
function uploadToSupabaseStorage($fileTmpPath, $fileName, $bucketName) {
    $projectRef = "mbsjtesmrowfilvxcaqb"; 
    $supabaseKey = getenv('SUPABASE_KEY') ?: 'YOUR_SUPABASE_SERVICE_ROLE_KEY';
    
    $fileData = file_get_contents($fileTmpPath);
    $url = "https://$projectRef.supabase.co/storage/v1/object/$bucketName/$fileName";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: application/octet-stream',
        'x-upsert: true'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return "https://$projectRef.supabase.co/storage/v1/object/public/$bucketName/$fileName";
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $nutritionist_type = $_POST['nutritionist_type'];
    $experience = trim($_POST['experience']);
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : 14.3498;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : 121.0794;

    if (empty($firstname)) $errors[] = "First name is required.";
    if (empty($lastname)) $errors[] = "Last name is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($nutritionist_type)) $errors[] = "Type of Nutritionist is required.";
    
    // Strict Password Requirements
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z).";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z).";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0-9).";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character (e.g. @, #, $, !).";
    }

    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($_FILES['resume']['name'])) $errors[] = "Resume or CV file is required.";
    if (empty($_FILES['certification_files']['name'][0])) $errors[] = "At least one certification document file is required.";

    if (empty($errors)) {
        $bucketName = "nutritionist-files";

        // Profile Picture Handling
        $pic_url = "default_avatar.png";
        if (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));
            $uploaded_url = uploadToSupabaseStorage($_FILES["profile_pic"]["tmp_name"], $pic_filename, $bucketName);
            if ($uploaded_url) {
                $pic_url = $uploaded_url;
            }
        }

        $uploaded_docs = []; 
        $resume_uploaded = false;

        // Upload Resume / CV to Supabase Storage
        if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $resume_filename = time() . "_resume_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["resume"]["name"]));
            $uploaded_url = uploadToSupabaseStorage($_FILES['resume']['tmp_name'], $resume_filename, $bucketName);
            
            if ($uploaded_url) {
                $resume_uploaded = true;
                $uploaded_docs[] = $uploaded_url; 
            }
        }

        // Upload Certification Files to Supabase Storage
        foreach ($_FILES['certification_files']['name'] as $key => $name) {
            if ($_FILES['certification_files']['error'][$key] === UPLOAD_ERR_OK) {
                $doc_filename = time() . "_cert_" . $key . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($name));
                $uploaded_url = uploadToSupabaseStorage($_FILES['certification_files']['tmp_name'][$key], $doc_filename, $bucketName);
                if ($uploaded_url) {
                    $uploaded_docs[] = $uploaded_url; 
                }
            }
        }

        if ($resume_uploaded && count($uploaded_docs) > 1) { 
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $documents_json = json_encode($uploaded_docs);

            $fullname = trim($firstname . ' ' . (!empty($middlename) ? $middlename . ' ' : '') . $lastname);

            $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, address, latitude, longitude, certification, experience, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $cert_data = "Type: " . $nutritionist_type;

            if ($stmt->execute([$fullname, $pic_url, $email, $phone, $hashed_password, $address, $latitude, $longitude, $cert_data, $experience, $documents_json])) {
                $success = "Registration successful! We will review your info soon.";
                $firstname = ""; $middlename = ""; $lastname = ""; $address = ""; $email = ""; $phone = ""; $nutritionist_type = ""; $experience = "";
            } else {
                $errors[] = "Database Error occurred.";
            }
        } else {
            $errors[] = "File upload to cloud failed. Please check Supabase Storage bucket and API key settings.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutritionist Registration - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="theme-color" content="#071a12">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />

    <style>
        :root{
            --bg:#f5f8f6;
            --surface:#ffffff;
            --surface-2:#f0f6f2;
            --text:#102019;
            --muted:#65736d;
            --line:rgba(16,32,25,.09);
            --primary:#16a36a;
            --primary-dark:#08734a;
            --primary-soft:#e3f6ed;
            --dark:#071a12;
            --dark-2:#0d281d;
            --shadow:0 24px 70px rgba(7,26,18,.10);
            --shadow-sm:0 12px 35px rgba(7,26,18,.08);
            --radius:28px;
            
            --error-bg: #fff1f2;
            --error-text: #9f1239;
            --error-border: #fecdd3;
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            line-height: 1.5;
        }

        a { text-decoration: none; color: inherit; }

        .header {
            padding: 24px clamp(20px, 5vw, 60px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-family: 'Manrope', sans-serif;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -.4px;
        }
        
        .logo-mark {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            border-radius: 13px;
            background: linear-gradient(145deg,#20bd7a,#078452);
            box-shadow: 0 9px 22px rgba(22,163,106,.24);
            font-size: 21px;
        }
        
        .logo span { color: var(--dark); }

        .back-link {
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            transition: .25s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-4px);
        }

        .wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 10px clamp(15px, 4vw, 40px) 60px;
        }

        .card {
            background: rgba(255,255,255,.85);
            width: 100%;
            max-width: 780px;
            padding: clamp(30px, 5vw, 45px);
            border-radius: var(--radius);
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: var(--shadow);
            backdrop-filter: blur(15px);
            animation: slideUp 0.6s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .card-header h2 {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(26px, 4vw, 32px);
            letter-spacing: -0.5px;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .card-header p {
            color: var(--muted);
            font-size: 15px;
        }

        .alert-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .alert-card-danger { background: var(--error-bg); border: 1px solid var(--error-border); color: var(--error-text); }
        .alert-card-success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); }
        
        .alert-card i { margin-top: 3px; font-size: 16px; }
        .alert-content { display: flex; flex-direction: column; gap: 4px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .form-group.full-width { grid-column: 1 / -1; }

        .section-divider {
            grid-column: 1 / -1;
            font-family: 'Manrope', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-top: 15px;
            margin-bottom: 5px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-divider::before {
            content: "";
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--primary);
        }

        label {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-2);
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .align-label-perfect {
            align-items: flex-start !important;
            min-height: auto !important;
            margin-top: 0px !important;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--surface-2);
            font-family: inherit;
            font-size: 14.5px;
            color: var(--text);
            transition: all 0.25s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2365736d'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 40px;
        }

        textarea.form-control { resize: vertical; min-height: 100px; }

        input[type="file"].form-control {
            padding: 10px 14px;
            background: var(--surface-2);
            color: var(--muted);
            cursor: pointer;
            border: 1px dashed var(--line);
        }

        input[type="file"]::-webkit-file-upload-button {
            background: #fff;
            border: 1px solid var(--line);
            padding: 8px 14px;
            border-radius: 8px;
            color: var(--dark-2);
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            font-size: 13px;
            margin-right: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        /* ADDRESS AUTOCOMPLETE SUGGESTIONS DROPDOWN */
        .address-row { display: flex; gap: 10px; }
        .btn-pick-map {
            background: var(--primary-soft); color: var(--primary-dark); border: 1px solid #c9ebd8;
            padding: 0 16px; border-radius: 12px; font-weight: 700; font-size: 13.5px;
            cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; transition: .2s;
        }
        .btn-pick-map:hover { background: #d4f2e1; }

        #address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 2px solid var(--primary);
            border-top: none;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            max-height: 220px;
            overflow-y: auto;
            z-index: 99999;
            display: none;
            margin-top: 2px;
        }
        .suggestion-item {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text);
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover { background: var(--primary-soft); color: var(--primary-dark); font-weight: 600; }

        /* MAP MODAL STYLING */
        .map-modal {
            position: fixed; inset: 0; background: rgba(15, 23, 42, .6);
            display: none; align-items: center; justify-content: center; padding: 20px; z-index: 9999;
            backdrop-filter: blur(5px);
        }
        .map-modal.active { display: flex; }
        .map-modal-box {
            width: 100%; max-width: 680px; background: white; border-radius: 24px; padding: 25px;
            box-shadow: 0 25px 70px rgba(15,23,42,.25);
        }
        .map-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .map-modal-header h3 { margin: 0; font-size: 18px; color: var(--dark); font-weight: 700; }
        .close-map-modal {
            width: 35px; height: 35px; border: none; border-radius: 8px; background: #f1f5f9;
            cursor: pointer; color: #475569; font-size: 16px; display: grid; place-items: center;
        }
        .close-map-modal:hover { background: #e2e8f0; }

        #modal-map-container {
            width: 100%;
            height: 340px;
            border-radius: 14px;
            border: 1px solid var(--line);
        }

        .map-footer-card {
            margin-top: 15px;
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .selected-address-info {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13.5px;
            color: var(--dark);
        }
        .selected-address-info i {
            color: var(--primary);
            font-size: 16px;
            margin-top: 2px;
        }
        .btn-choose-address {
            background: linear-gradient(135deg,#19ad70,#078151);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 20px rgba(8,129,81,.2);
            transition: transform 0.2s;
        }
        .btn-choose-address:hover { transform: translateY(-2px); }

        .password-box { position: relative; width: 100%; }
        .password-box .form-control { padding-right: 45px; }
        
        .toggle-password {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #9ba7a2; font-size: 16px; transition: color 0.25s ease;
        }
        .toggle-password:hover { color: var(--primary-dark); }

        .password-strength-container {
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .strength-bars {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            height: 4px;
            width: 100%;
        }
        .strength-bar {
            background-color: #e2e8f0;
            border-radius: 2px;
            transition: background-color 0.3s ease;
        }
        .strength-text {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-align: right;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 52px;
            background: linear-gradient(135deg,#19ad70,#078151);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            margin-top: 15px;
            box-shadow: 0 12px 26px rgba(8,129,81,.20);
            transition: transform .25s ease, box-shadow .25s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(8,129,81,.28);
        }

        .signup-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14.5px;
            color: var(--muted);
        }
        
        .signup-link a {
            color: var(--primary-dark);
            font-weight: 700;
            transition: .25s ease;
        }
        
        .signup-link a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 18px; }
        }
    </style>
</head>
<body>

<header class="header">
    <a href="../index.php" class="logo" aria-label="AI Diet Planner home">
        <span class="logo-mark">🥗</span>
        <span>AI Diet Planner</span>
    </a>
    <a href="register.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Account Type</a>
</header>

<main class="wrapper">
    <div class="card">
        <div class="card-header">
            <h2>Join as a Nutritionist</h2>
            <p>Register your credentials to start guiding clients and managing diets</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert-card alert-card-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div class="alert-content">
                    <?php foreach ($errors as $err): ?>
                        <span><?php echo htmlspecialchars($err); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-card alert-card-success">
                <i class="fa-solid fa-circle-check"></i>
                <div class="alert-content">
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                
                <div class="section-divider">Account Credentials</div>

                <div class="form-group">
                    <label for="firstname">First Name <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="firstname" name="firstname" class="form-control" placeholder="e.g. Jane" value="<?php echo htmlspecialchars($firstname); ?>" required>
                </div>

                <div class="form-group">
                    <label for="middlename">Middle Name <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                    <input type="text" id="middlename" name="middlename" class="form-control" placeholder="Enter middle name" value="<?php echo htmlspecialchars($middlename); ?>">
                </div>

                <div class="form-group">
                    <label for="lastname">Last Name <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="lastname" name="lastname" class="form-control" placeholder="e.g. Doe" value="<?php echo htmlspecialchars($lastname); ?>" required>
                </div>

                <!-- ADDRESS FIELD WITH AUTOCOMPLETE & MAP PIN -->
                <div class="form-group">
                    <label for="address">Address <span style="color: #d32f2f;">*</span></label>
                    <div class="address-row">
                        <input type="text" id="address" name="address" class="form-control" placeholder="e.g. Biñan, Laguna" autocomplete="off" value="<?php echo htmlspecialchars($address); ?>" required>
                        <button type="button" class="btn-pick-map" onclick="openMapModal()">
                            <i class="fa-solid fa-map-location-dot"></i> Pin Map
                        </button>
                    </div>
                    <!-- Dropdown suggestions container -->
                    <div id="address-suggestions"></div>

                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($latitude); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($longitude); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="color: #d32f2f;">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (11 digits) <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="09123456789" value="<?php echo htmlspecialchars($phone); ?>" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" required>
                </div>

                <div class="form-group">
                    <label for="password">Password <span style="color: #d32f2f;">*</span></label>
                    <div class="password-box">
                        <input type="password" id="password" name="password" class="form-control" placeholder="At least 8 characters" oninput="checkPasswordStrength(this.value)" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
                    </div>
                    <!-- Password Strength Bar Indicator -->
                    <div class="password-strength-container">
                        <div class="strength-bars">
                            <div class="strength-bar" id="bar-1"></div>
                            <div class="strength-bar" id="bar-2"></div>
                            <div class="strength-bar" id="bar-3"></div>
                            <div class="strength-bar" id="bar-4"></div>
                        </div>
                        <div class="strength-text" id="strength-text">Password strength</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color: #d32f2f;">*</span></label>
                    <div class="password-box">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('confirm_password', this)"></i>
                    </div>
                </div>

                <div class="form-group full-width" style="font-size: 13px; color: var(--muted); margin-top: -10px;">
                    Password must be at least 8 characters long and include an uppercase letter (A-Z), lowercase letter (a-z), number (0-9), and special symbol (e.g. @, #, $, !).
                </div>

                <div class="section-divider">Professional Credentials</div>

                <div class="form-group">
                    <label for="profile_pic">Profile Photo <span style="color: var(--muted); font-weight: 400; font-size: 12px; margin-left: 4px;">(Optional)</span></label>
                    <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label for="resume">Resume / CV <span style="color: #d32f2f;">*</span></label>
                    <input type="file" id="resume" name="resume" class="form-control" accept="image/*,.pdf,.doc,.docx" required>
                </div>

                <div class="form-group">
                    <label for="certification_files">Certifications & Licenses <span style="color: #d32f2f;">*</span></label>
                    <input type="file" id="certification_files" name="certification_files[]" class="form-control" accept="image/*,.pdf" multiple required>
                </div>

                <div class="form-group">
                    <label for="nutritionist_type" class="align-label-perfect">Type of Nutritionist <span style="color: #d32f2f;">*</span></label>
                    <select id="nutritionist_type" name="nutritionist_type" class="form-control" required>
                        <option value="">Select Type of Nutritionist</option>
                        <option value="Clinical Nutritionist" <?php if($nutritionist_type == 'Clinical Nutritionist') echo 'selected'; ?>>Clinical Nutritionist</option>
                        <option value="Sports Nutritionist" <?php if($nutritionist_type == 'Sports Nutritionist') echo 'selected'; ?>>Sports Nutritionist</option>
                        <option value="Pediatric Nutritionist" <?php if($nutritionist_type == 'Pediatric Nutritionist') echo 'selected'; ?>>Pediatric Nutritionist</option>
                        <option value="Holistic Nutritionist" <?php if($nutritionist_type == 'Holistic Nutritionist') echo 'selected'; ?>>Holistic Nutritionist</option>
                        <option value="Public Health Nutritionist" <?php if($nutritionist_type == 'Public Health Nutritionist') echo 'selected'; ?>>Public Health Nutritionist</option>
                        <option value="General Dietitian" <?php if($nutritionist_type == 'General Dietitian') echo 'selected'; ?>>General Dietitian</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label for="experience">Professional Experience <span style="color: #d32f2f;">*</span></label>
                    <textarea id="experience" name="experience" class="form-control" placeholder="Describe your relevant work history and areas of expertise..." required><?php echo htmlspecialchars($experience); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <button type="submit" class="btn-primary">Submit Registration <span>→</span></button>
                </div>

            </div>
        </form>

        <div class="signup-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>
</main>

<!-- MAP MODAL WITH GPS AUTO-DETECTION AND CLICK-TO-PIN -->
<div class="map-modal" id="mapModal">
    <div class="map-modal-box">
        <div class="map-modal-header">
            <h3>Pin Your Exact Location</h3>
            <button type="button" class="close-map-modal" onclick="closeMapModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div>
            <div id="modal-map-container"></div>
            
            <div class="map-footer-card">
                <div class="selected-address-info">
                    <i class="fa-solid fa-location-dot"></i>
                    <div>
                        <strong style="font-size: 12px; color: var(--muted); display: block; margin-bottom: 2px;">Selected Address:</strong>
                        <span id="modal-address-text">Click anywhere on the map to place a pin.</span>
                    </div>
                </div>
                <button type="button" id="btnChooseAddress" class="btn-choose-address" onclick="confirmSelectedAddress()" style="opacity: 0.6; pointer-events: none;">
                    <i class="fa-solid fa-check"></i> Choose Address
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
let mapInstance = null;
let markerInstance = null;
let debounceTimer = null;
let tempLat = null;
let tempLng = null;
let tempAddressText = "";

const addressInput = document.getElementById('address');
const suggestionsBox = document.getElementById('address-suggestions');

addressInput.addEventListener('input', function() {
    const query = this.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 3) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        return;
    }

    debounceTimer = setTimeout(() => {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=ph&limit=5`, {
            headers: { 'Accept-Language': 'en' }
        })
        .then(response => response.json())
        .then(data => {
            suggestionsBox.innerHTML = '';
            if (data && data.length > 0) {
                suggestionsBox.style.display = 'block';
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = item.display_name;
                    div.addEventListener('click', function() {
                        addressInput.value = item.display_name;
                        document.getElementById('longitude').value = item.lon;
                        document.getElementById('latitude').value = item.lat;
                        suggestionsBox.style.display = 'none';
                    });
                    suggestionsBox.appendChild(div);
                });
            } else {
                suggestionsBox.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Autocomplete error:', err);
            suggestionsBox.style.display = 'none';
        });
    }, 300);
});

document.addEventListener('click', function(e) {
    if (!addressInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.style.display = 'none';
    }
});

function openMapModal() {
    const modal = document.getElementById('mapModal');
    modal.classList.add('active');

    setTimeout(() => {
        if (!mapInstance) {
            mapInstance = L.map('modal-map-container').setView([14.3498, 121.0794], 14);

            L.tileLayer('https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=ZkO2ll8wJho8o1zc1Sug', {
                maxZoom: 19,
                attribution: '&copy; MapTiler &copy; OpenStreetMap contributors'
            }).addTo(mapInstance);

            mapInstance.on('click', function(e) {
                setPinPosition(e.latlng.lat, e.latlng.lng);
            });

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userLat = position.coords.latitude;
                        const userLng = position.coords.longitude;
                        mapInstance.setView([userLat, userLng], 15);
                        setPinPosition(userLat, userLng);
                    },
                    (error) => { console.log("Geolocation unavailable.", error); },
                    { timeout: 10000 }
                );
            }
        }

        mapInstance.invalidateSize();
    }, 250);
}

function closeMapModal() {
    document.getElementById('mapModal').classList.remove('active');
}

async function setPinPosition(lat, lng) {
    tempLat = lat;
    tempLng = lng;

    const customIcon = L.divIcon({
        className: 'custom-map-pin',
        html: '<div style="background-color: #16a36a; width: 36px; height: 36px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 2px solid white;"><i class="fa-solid fa-location-dot" style="transform: rotate(45deg); color: white; font-size: 16px;"></i></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -36]
    });

    if (markerInstance) {
        markerInstance.setLatLng([lat, lng]);
    } else {
        markerInstance = L.marker([lat, lng], { draggable: true, icon: customIcon }).addTo(mapInstance);
        markerInstance.on('dragend', function() {
            const pos = markerInstance.getLatLng();
            setPinPosition(pos.lat, pos.lng);
        });
    }

    mapInstance.panTo([lat, lng]);
    document.getElementById('modal-address-text').textContent = "Fetching address...";

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await response.json();
        if (data && data.display_name) {
            tempAddressText = data.display_name;
            document.getElementById('modal-address-text').textContent = tempAddressText;
        } else {
            tempAddressText = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            document.getElementById('modal-address-text').textContent = tempAddressText;
        }
    } catch (e) {
        console.error("Geocoding error", e);
        tempAddressText = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        document.getElementById('modal-address-text').textContent = tempAddressText;
    }

    const chooseBtn = document.getElementById('btnChooseAddress');
    chooseBtn.style.opacity = '1';
    chooseBtn.style.pointerEvents = 'auto';
}

function confirmSelectedAddress() {
    if (tempLat !== null && tempLng !== null) {
        document.getElementById('latitude').value = tempLat;
        document.getElementById('longitude').value = tempLng;
        if (tempAddressText) {
            document.getElementById('address').value = tempAddressText;
        }
        closeMapModal();
    }
}

function togglePasswordVisibility(fieldId, iconElement) {
    const passwordInput = document.getElementById(fieldId);
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        iconElement.classList.remove("fa-eye");
        iconElement.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        iconElement.classList.remove("fa-eye-slash");
        iconElement.classList.add("fa-eye");
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    const bars = [
        document.getElementById('bar-1'),
        document.getElementById('bar-2'),
        document.getElementById('bar-3'),
        document.getElementById('bar-4')
    ];
    const textEl = document.getElementById('strength-text');

    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    bars.forEach(bar => bar.style.backgroundColor = '#e2e8f0');

    if (password.length === 0) {
        textEl.textContent = 'Password strength';
        textEl.style.color = 'var(--muted)';
        return;
    }

    let color = '#ef4444'; 
    let text = 'Weak';

    if (strength === 2 || strength === 3) {
        color = '#f59e0b'; 
        text = 'Medium';
    } else if (strength >= 4) {
        color = '#10b981'; 
        text = 'Strong';
    }

    for (let i = 0; i < strength; i++) {
        bars[i].style.backgroundColor = color;
    }
    textEl.textContent = text;
    textEl.style.color = color;
}
</script>

</body>
</html>