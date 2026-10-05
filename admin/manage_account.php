<?php
session_start();
require_once '../config.php';

// ======================================================
// ADMIN SECURITY
// ======================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$errors = [];
$success = "";

// ======================================================
// TEXTBEE SMS HELPER FUNCTION
// ======================================================
function sendTextBeeSMS($phoneNumber, $message) {
    $apiKey = 'txb_3skNWW7LOoXe9MjSrRHXiC8WWUcXq806'; // Palitan ng iyong TextBee API Key
    
    $phoneNumber = trim($phoneNumber);
    if (strpos($phoneNumber, '09') === 0) {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    }

    $url = 'https://api.textbee.dev/api/v1/gateway/send-sms';
    
    $data = [
        'recipients' => [$phoneNumber],
        'message' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// ======================================================
// ACTIONS: ADD EXPERT (Multiple Files Support)
// ======================================================
if (isset($_POST['add_expert'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $certification = trim($_POST['certification'] ?? '');
    $experience = trim($_POST['experience'] ?? '');

    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($_FILES['profile_pic']['name'])) $errors[] = "Profile picture is required.";
    if (empty($_FILES['document']['name'][0])) $errors[] = "Verification documents are required.";

    if (empty($errors)) {
        $doc_dir = "../uploads/nutritionists/";
        $pic_dir = "../uploads/profile_pics/";

        if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
        if (!is_dir($pic_dir)) mkdir($pic_dir, 0755, true);

        $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));
        $pic_uploaded = move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $pic_dir . $pic_filename);

        $uploaded_docs = [];
        foreach ($_FILES['document']['name'] as $key => $name) {
            if ($_FILES['document']['error'][$key] === UPLOAD_ERR_OK) {
                $doc_filename = time() . "_" . $key . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($name));
                if (move_uploaded_file($_FILES['document']['tmp_name'][$key], $doc_dir . $doc_filename)) {
                    $uploaded_docs[] = $doc_filename;
                }
            }
        }

        if ($pic_uploaded && !empty($uploaded_docs)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $documents_json = json_encode($uploaded_docs);

            $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, certification, experience, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            
            if ($stmt->execute([$fullname, $pic_filename, $email, $phone, $hashed_password, $certification, $experience, $documents_json])) {
                header("Location: manage_account.php?msg=success");
                exit();
            } else {
                $errors[] = "Database Error occurred.";
            }
        } else {
            $errors[] = "File upload failed. Check folder permissions.";
        }
    }
}

// Handle Approval & Send SMS Notification
if (isset($_GET['approve_id'])) {
    $id = (int)$_GET['approve_id'];
    
    $stmt = $conn->prepare("SELECT fullname, phone FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    $nutri = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($nutri) {
        $updateStmt = $conn->prepare("UPDATE nutritionist SET status = 'approved' WHERE id = ?");
        $updateStmt->execute([$id]);

        $message = "Hello " . $nutri['fullname'] . ", great news! Your nutritionist account on AI Diet Planner has been APPROVED. You can now log in.";
        sendTextBeeSMS($nutri['phone'], $message);
    }

    header("Location: manage_account.php?msg=approved");
    exit();
}

// Handle Rejection & Send SMS Notification
if (isset($_GET['reject_id'])) {
    $id = (int)$_GET['reject_id'];
    
    $stmt = $conn->prepare("SELECT fullname, phone FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    $nutri = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($nutri) {
        $updateStmt = $conn->prepare("UPDATE nutritionist SET status = 'rejected' WHERE id = ?");
        $updateStmt->execute([$id]);

        $message = "Hello " . $nutri['fullname'] . ", we regret to inform you that your nutritionist account application on AI Diet Planner has been REJECTED.";
        sendTextBeeSMS($nutri['phone'], $message);
    }

    header("Location: manage_account.php?msg=rejected");
    exit();
}

if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_account.php?msg=deleted");
    exit();
}

// ======================================================
// FETCH EXPERTS & DATA ANALYSIS
// ======================================================
$experts = [];
$db_error = null;

try {
    $stmt = $conn->query("SELECT id, fullname, email, phone, certification, experience, status, profile_pic, document FROM nutritionist ORDER BY id DESC");
    $experts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

// Statistics Calculations
$totalAccounts = count($experts);
$juniorCount = 0;   
$midCount = 0;      
$seniorCount = 0;   
$totalExp = 0;

foreach ($experts as $exp) {
    $expText = $exp['experience'] ?? '';
    preg_match('/\d+/', $expText, $matches);
    $yrs = isset($matches[0]) ? (int)$matches[0] : 0;
    
    $totalExp += $yrs;

    if ($yrs < 3) {
        $juniorCount++;
    } elseif ($yrs <= 5) {
        $midCount++;
    } else {
        $seniorCount++;
    }
}

$avgExperience = $totalAccounts > 0 ? round($totalExp / $totalAccounts, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | Admin</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f4f7ff 0%, #eef3ff 50%, #f8fafc 100%);
            color: #172033;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }

        button, input, select, textarea {
            font-family: inherit;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .admin-sidebar {
            width: 255px;
            min-width: 255px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            padding: 22px 15px;
            background: #0f172a;
            color: #cbd5e1;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px 25px;
            color: #fff;
        }

        .sidebar-logo {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            font-size: 19px;
            box-shadow: 0 8px 20px rgba(59,130,246,.28);
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
        }

        .sidebar-brand span {
            color: #94a3b8;
            font-size: 11px;
        }

        .sidebar-section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #64748b;
            font-weight: 700;
            padding: 14px 12px 8px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border-radius: 11px;
            margin-bottom: 4px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }

        .sidebar-link i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .sidebar-link:hover {
            background: rgba(59,130,246,.10);
            color: #fff;
        }

        .sidebar-link.active {
            background: #1b356f;
            color: #93c5fd;
        }

        .sidebar-system {
            margin-top: 28px;
        }

        .sidebar-link.signout {
            color: #cbd5e1;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            margin-left: 255px;
        }

        .page {
            width: 100%;
            margin: 0;
            padding: 32px 40px 50px;
        }

        .top-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .title-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
            font-size: 23px;
            box-shadow: 0 10px 25px rgba(37,99,235,.25);
        }

        .title-area h1 {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }

        .title-area p {
            color: #64748b;
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 10px;
            background: white;
            color: #2563eb;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: .25s;
        }

        .back-btn:hover {
            transform: translateX(-3px);
            border-color: #bfdbfe;
            box-shadow: 0 6px 18px rgba(15,23,42,.08);
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 10px;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            color: white;
            font-weight: 700;
            border: none;
            box-shadow: 0 7px 18px rgba(34,197,94,.25);
            cursor: pointer;
            transition: .25s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34,197,94,.32);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,.88);
            border: 1px solid rgba(226,232,240,.8);
            border-radius: 17px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(15,23,42,.05);
            transition: .3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(15,23,42,.09);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            width: 80px;
            height: 80px;
            right: -25px;
            bottom: -25px;
            border-radius: 50%;
            background: rgba(37,99,235,.06);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 17px;
        }

        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #f0fdf4; color: #16a34a; }
        .icon-orange { background: #fff7ed; color: #ea580c; }
        .icon-purple { background: #faf5ff; color: #9333ea; }
        .icon-red { background: #fef2f2; color: #dc2626; }

        .stat-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 800;
            color: #111827;
        }

        .main-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 35px rgba(15,23,42,.06);
            overflow: hidden;
        }

        .toolbar {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #eef2f7;
            background: #ffffff;
        }

        .toolbar-title h2 {
            font-size: 17px;
            font-weight: 800;
            color: #111827;
        }

        .toolbar-title span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 12px;
        }

        .toolbar-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            width: 260px;
            height: 40px;
            padding: 0 14px 0 38px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            outline: none;
            color: #334155;
            transition: .2s;
        }

        .search-box input:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59,130,246,.1);
        }

        .filter-select {
            height: 40px;
            padding: 0 12px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: white;
            color: #475569;
            outline: none;
            cursor: pointer;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .account-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .account-table th {
            padding: 14px 20px;
            background: #f8fafc;
            color: #64748b;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .account-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .account-table tbody tr {
            transition: .2s;
        }

        .account-table tbody tr:hover {
            background: #f8fbff;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            border: 1px solid #dbeafe;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 14px;
        }

        .user-id {
            color: #94a3b8;
            font-size: 11px;
            margin-top: 2px;
        }

        /* VIEW CREDENTIALS BUTTON STYLE */
        .view-credentials-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .view-credentials-btn:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }

        .actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            transition: .2s;
        }

        .approve-btn { background: #dcfce7; color: #15803d; }
        .approve-btn:hover { background: #bbf7d0; transform: translateY(-2px); }

        .reject-btn { background: #fef9c3; color: #ca8a04; }
        .reject-btn:hover { background: #fef08a; transform: translateY(-2px); }

        .edit-btn { background: #eef2ff; color: #4f46e5; }
        .edit-btn:hover { background: #e0e7ff; transform: translateY(-2px); }

        .delete-btn { background: #fef2f2; color: #dc2626; }
        .delete-btn:hover { background: #fee2e2; transform: translateY(-2px); }

        .empty-state {
            padding: 70px 20px;
            text-align: center;
            color: #94a3b8;
        }

        .empty-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #94a3b8;
            font-size: 25px;
        }

        .error-message {
            margin: 20px;
            padding: 14px 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            font-size: 13px;
        }

        /* MODAL STYLING */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
        }

        .modal-box {
            width: 100%;
            max-width: 750px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 25px 70px rgba(15,23,42,.25);
            animation: modalIn .25s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(15px) scale(.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #111827;
            font-weight: 800;
        }

        .close-modal {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            cursor: pointer;
            color: #475569;
            font-size: 16px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 12px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            outline: none;
            color: #1e293b;
            font-size: 14px;
            transition: .2s;
        }

        .form-group input:focus, 
        .form-group textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 13px;
            color: #64748b;
            cursor: pointer;
        }

        .password-box {
            position: relative;
            width: 100%;
        }

        .password-box input {
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 15px;
        }

        .submit-btn {
            width: 100%;
            height: 46px;
            margin-top: 10px;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            font-weight: 700;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(37,99,235,.25);
            transition: .2s;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(37,99,235,.35);
        }

        .error-alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            border-left: 4px solid #dc2626;
        }

        /* Document Thumbnails Grid */
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .doc-item {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            cursor: pointer;
            text-align: center;
            padding: 6px;
            transition: .2s;
        }
        .doc-item:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(37,99,235,.15);
        }
        .doc-item img, .doc-item .pdf-icon {
            width: 100%;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
        }
        .doc-item .pdf-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #dc2626;
            font-size: 28px;
        }
        .doc-name {
            font-size: 11px;
            color: #475569;
            margin-top: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* CONFIRMATION CARD MODAL SPECIFIC STYLES */
        .confirm-modal-box {
            max-width: 420px;
            text-align: center;
            padding: 35px 25px;
            border-radius: 24px;
        }
        .confirm-icon-frame {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        .confirm-icon-delete { background: #fef2f2; color: #dc2626; }
        .confirm-icon-approve { background: #f0fdf4; color: #16a34a; }
        .confirm-icon-reject { background: #fefce8; color: #ca8a04; }
        .confirm-icon-logout { background: #fef2f2; color: #dc2626; }

        .confirm-modal-box h3 {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
        }
        .confirm-modal-box p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 25px;
        }
        .confirm-modal-actions {
            display: flex;
            gap: 12px;
        }
        .confirm-btn-cancel, .confirm-btn-action {
            flex: 1;
            height: 44px;
            border-radius: 11px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s;
        }
        .confirm-btn-cancel {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }
        .confirm-btn-cancel:hover {
            background: #f8fafc;
        }
        .confirm-btn-danger {
            background: #dc2626;
            color: white;
            box-shadow: 0 4px 14px rgba(220,38,38,.3);
        }
        .confirm-btn-danger:hover {
            background: #b91c1c;
        }
        .confirm-btn-success {
            background: #16a34a;
            color: white;
            box-shadow: 0 4px 14px rgba(22,163,74,.3);
        }
        .confirm-btn-success:hover {
            background: #15803d;
        }
        .confirm-btn-warning {
            background: #ca8a04;
            color: white;
            box-shadow: 0 4px 14px rgba(202,138,4,.3);
        }
        .confirm-btn-warning:hover {
            background: #a16207;
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 900px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .main-content .page { padding-left: 25px; padding-right: 25px; }
        }

        @media (max-width: 800px) {
            .page { padding: 20px 15px 40px; }
            .top-header { align-items: flex-start; flex-direction: column; }
            .header-actions { width: 100%; }
            .back-btn, .add-btn { flex: 1; justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-controls { width: 100%; flex-direction: column; }
            .search-box, .search-box input, .filter-select { width: 100%; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">
                <i class="fa-solid fa-leaf"></i>
            </div>
            <div>
                <h2>AI Diet Planner</h2>
                <span>Administration</span>
            </div>
        </div>

        <div class="sidebar-section-title">Main Menu</div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link">
                <i class="fa-solid fa-grid-2"></i>
                <span>Dashboard</span>
            </a>
            <a href="meals.php" class="sidebar-link">
                <i class="fa-solid fa-utensils"></i>
                <span>Meal Management</span>
            </a>
            <a href="manage_account.php" class="sidebar-link active">
                <i class="fa-solid fa-users-gear"></i>
                <span>Accounts</span>
            </a>
            <a href="activity_logs.php" class="sidebar-link">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Activity Logs</span>
            </a>
        </nav>

        <div class="sidebar-system">
            <div class="sidebar-section-title">System</div>
            <nav class="sidebar-nav">
                <button type="button" class="sidebar-link signout" onclick="openConfirmModal('logout', '../auth/login.php', '')">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Sign Out</span>
                </button>
            </nav>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        <div class="page">

            <!-- HEADER -->
            <header class="top-header">
                <div class="title-area">
                    <div class="title-icon">
                        <i class="fa-solid fa-user-nurse"></i>
                    </div>
                    <div>
                        <h1>Manage Accounts</h1>
                        <p>Create, audit, approve, and oversee expert nutritionist accounts.</p>
                    </div>
                </div>

                <div class="header-actions">
                    <a href="dashboard.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Dashboard
                    </a>
                    <button type="button" class="add-btn" onclick="openModal()">
                        <i class="fa-solid fa-plus"></i>
                        Add New Expert
                    </button>
                </div>
            </header>

            <!-- STATISTICS GRID -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Total Experts</div>
                            <div class="stat-number"><?= number_format($totalAccounts) ?></div>
                        </div>
                        <div class="stat-icon icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Junior (< 3Yrs)</div>
                            <div class="stat-number"><?= number_format($juniorCount) ?></div>
                        </div>
                        <div class="stat-icon icon-green">
                            <i class="fa-solid fa-seedling"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Mid-Level (3-5Yrs)</div>
                            <div class="stat-number"><?= number_format($midCount) ?></div>
                        </div>
                        <div class="stat-icon icon-orange">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Senior (> 5Yrs)</div>
                            <div class="stat-number"><?= number_format($seniorCount) ?></div>
                        </div>
                        <div class="stat-icon icon-purple">
                            <i class="fa-solid fa-award"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-label">Avg Experience</div>
                            <div class="stat-number"><?= $avgExperience ?> <span style="font-size:14px; font-weight:600;">yrs</span></div>
                        </div>
                        <div class="stat-icon icon-red">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MAIN CARD SECTION -->
            <section class="main-card">

                <!-- TOOLBAR -->
                <div class="toolbar">
                    <div class="toolbar-title">
                        <h2>Account Database</h2>
                        <span id="resultCount"><?= number_format($totalAccounts) ?> expert(s)</span>
                    </div>

                    <div class="toolbar-controls">
                        <!-- SEARCH -->
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search expert name or email..." autocomplete="off">
                        </div>

                        <!-- FILTER -->
                        <select id="expFilter" class="filter-select">
                            <option value="all">All Experience Levels</option>
                            <option value="junior">Junior (< 3 Years)</option>
                            <option value="mid">Mid-Level (3 - 5 Years)</option>
                            <option value="senior">Senior (> 5 Years)</option>
                        </select>
                    </div>
                </div>

                <?php if ($db_error): ?>
                    <div class="error-message">
                        <strong><i class="fa-solid fa-triangle-exclamation"></i> Database Error:</strong>
                        <?= htmlspecialchars($db_error) ?>
                    </div>
                <?php endif; ?>

                <!-- TABLE -->
                <div class="table-wrapper">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th>Expert Details</th>
                                <th>Contact Info</th>
                                <th>Credentials</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="accountTableBody">
                            <?php if (!empty($experts)): ?>
                                <?php foreach ($experts as $row): 
                                    $id = (int)$row['id'];
                                    $fullname = $row['fullname'] ?? 'N/A';
                                    $email = $row['email'] ?? 'N/A';
                                    $phone = $row['phone'] ?? 'N/A';
                                    $expText = $row['experience'] ?? '0';
                                    preg_match('/\d+/', $expText, $matches);
                                    $exp = isset($matches[0]) ? (int)$matches[0] : 0;

                                    $status = $row['status'] ?? 'pending';
                                    $profile_pic = trim($row['profile_pic'] ?? '');
                                    $document_raw = $row['document'] ?? '';
                                    $certification = $row['certification'] ?? '';
                                    $experience_desc = $row['experience'] ?? '';

                                    $imageSrc = '';
                                    if ($profile_pic !== '') {
                                        $imageSrc = (strpos($profile_pic, 'http') === 0 || strpos($profile_pic, '/') === 0) 
                                            ? $profile_pic 
                                            : '../uploads/profile_pics/' . $profile_pic;
                                    }

                                    // Parse multiple documents JSON array
                                    $doc_list = [];
                                    if (!empty($document_raw)) {
                                        $decoded = json_decode($document_raw, true);
                                        if (is_array($decoded)) {
                                            $doc_list = $decoded;
                                        } else {
                                            $doc_list = [$document_raw];
                                        }
                                    }

                                    $initials = 'E';
                                    $parts = explode(' ', trim($fullname));
                                    if (count($parts) >= 2) {
                                        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
                                    } else if (!empty($parts[0])) {
                                        $initials = strtoupper(substr($parts[0], 0, 2));
                                    }
                                ?>
                                    <tr class="account-row" 
                                        data-name="<?= htmlspecialchars(strtolower($fullname)) ?>" 
                                        data-email="<?= htmlspecialchars(strtolower($email)) ?>"
                                        data-exp="<?= $exp ?>">
                                        
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php if ($imageSrc !== ''): ?>
                                                        <img src="<?= htmlspecialchars($imageSrc) ?>" alt="<?= htmlspecialchars($fullname) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center;"><?= $initials ?></span>
                                                    <?php else: ?>
                                                        <?= $initials ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="user-name"><?= htmlspecialchars($fullname) ?></div>
                                                    <div class="user-id">ID #<?= $id ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span style="color:#475569; font-weight:500; font-size:13px; display:block;">
                                                <?= htmlspecialchars($email) ?>
                                            </span>
                                            <span style="color:#94a3b8; font-size:12px;">
                                                <i class="fa-solid fa-phone" style="font-size:10px; margin-right:3px;"></i> <?= htmlspecialchars($phone) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <button type="button" class="view-credentials-btn" 
                                                onclick='openCredentialsModal(
                                                    <?= json_encode($doc_list) ?>, 
                                                    <?= json_encode($fullname) ?>,
                                                    <?= json_encode($experience_desc) ?>,
                                                    <?= json_encode($certification) ?>
                                                )'>
                                                <i class="fa-solid fa-file-shield"></i> View Credentials
                                            </button>
                                        </td>

                                        <td>
                                            <?php if ($status === 'approved'): ?>
                                                <span class="status-badge status-approved">Approved</span>
                                            <?php elseif ($status === 'rejected'): ?>
                                                <span class="status-badge status-rejected">Rejected</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="actions">
                                                <?php if ($status === 'pending'): ?>
                                                    <button type="button" class="action-btn approve-btn" title="Approve Account" onclick="openConfirmModal('approve', 'manage_account.php?approve_id=<?= $id ?>', '<?= htmlspecialchars($fullname, ENT_QUOTES) ?>')">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                    <button type="button" class="action-btn reject-btn" title="Reject Account" onclick="openConfirmModal('reject', 'manage_account.php?reject_id=<?= $id ?>', '<?= htmlspecialchars($fullname, ENT_QUOTES) ?>')">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <a href="edit_expert.php?id=<?= $id ?>" class="action-btn edit-btn" title="Edit Expert">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <button type="button" class="action-btn delete-btn" title="Delete Account" onclick="openConfirmModal('delete', 'manage_account.php?delete_id=<?= $id ?>', '<?= htmlspecialchars($fullname, ENT_QUOTES) ?>')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <div class="empty-icon"><i class="fa-solid fa-user-slash"></i></div>
                                        <h3 style="color:#475569; margin-bottom:5px;">No Experts Found</h3>
                                        <p style="font-size:13px;">Add your first nutritionist account to the system.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </div>
    </main>
</div>

<!-- ADD EXPERT MODAL -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Expert</h3>
            <button type="button" class="close-modal" onclick="closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-alert">
                <?php foreach ($errors as $err) echo "• " . htmlspecialchars($err) . "<br>"; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                
                <div class="form-group full-width">
                    <label>Full Name</label>
                    <input type="text" name="fullname" placeholder="e.g. Dr. Jane Doe" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@example.com" required>
                </div>

                <div class="form-group">
                    <label>Phone Number (11 digits)</label>
                    <input type="text" name="phone" placeholder="09123456789" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-box">
                        <input type="password" name="password" id="modal_password" placeholder="At least 6 characters" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('modal_password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-box">
                        <input type="password" name="confirm_password" id="modal_confirm_password" placeholder="Re-enter password" required>
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('modal_confirm_password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Profile Photo</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="profile_pic" accept="image/*" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Verification Documents (Multiple PDF/Images)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="document[]" accept="image/*,.pdf" multiple required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Certifications & Licenses</label>
                    <textarea name="certification" placeholder="List your professional licenses, degrees, or certifications..." required></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Professional Experience</label>
                    <textarea name="experience" placeholder="Describe your relevant work history and areas of expertise..." required></textarea>
                </div>

                <div class="form-group full-width">
                    <button type="submit" name="add_expert" class="submit-btn">
                        Create Expert Account
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- VIEW CREDENTIALS MODAL -->
<div class="modal" id="credentialsModal">
    <div class="modal-box" style="max-width: 750px;">
        <div class="modal-header">
            <h3 id="credModalTitle">Expert Credentials</h3>
            <button type="button" class="close-modal" onclick="closeCredentialsModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 6px;">
                    <i class="fa-solid fa-briefcase" style="color: #16a34a; margin-right: 4px;"></i> Professional Experience
                </label>
                <p id="credExpText" style="font-size: 14px; color: #1e293b; white-space: pre-wrap; line-height: 1.5;"></p>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 6px;">
                    <i class="fa-solid fa-award" style="color: #2563eb; margin-right: 4px;"></i> Certifications & Licenses
                </label>
                <p id="credCertText" style="font-size: 14px; color: #1e293b; white-space: pre-wrap; line-height: 1.5;"></p>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 6px;">
                    <i class="fa-solid fa-file-shield" style="color: #9333ea; margin-right: 4px;"></i> Verification Documents (Click to Preview)
                </label>
                <div id="docGridContainer" class="doc-grid"></div>
                <div id="noDocText" style="display: none; color: #94a3b8; font-size: 13px;">No documents uploaded</div>
            </div>
        </div>
    </div>
</div>

<!-- SINGLE FILE PREVIEW SUB-MODAL -->
<div class="modal" id="filePreviewModal" style="z-index: 1050;">
    <div class="modal-box" style="max-width: 800px; height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>Document Preview</h3>
            <button type="button" class="close-modal" onclick="document.getElementById('filePreviewModal').classList.remove('active')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="flex: 1; background: #000; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
            <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none; display: none;"></iframe>
            <img id="previewImage" src="" style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;">
        </div>
    </div>
</div>

<!-- CUSTOM UNIVERSAL ACTION CONFIRMATION MODAL CARD -->
<div class="modal" id="actionConfirmModal">
    <div class="modal-box confirm-modal-box">
        <div id="confirmIconFrame" class="confirm-icon-frame">
            <i id="confirmIcon" class="fa-solid fa-door-closed"></i>
        </div>
        <h3 id="confirmTitle">Confirm Logout</h3>
        <p id="confirmDesc">Are you sure you want to log out of your session?</p>
        <div class="confirm-modal-actions">
            <button type="button" class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <a id="confirmActionBtn" href="#" class="confirm-btn-action">Yes, Logout</a>
        </div>
    </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const expFilter = document.getElementById('expFilter');
const rows = document.querySelectorAll('.account-row');
const resultCount = document.getElementById('resultCount');

function filterAccounts() {
    const search = searchInput.value.toLowerCase().trim();
    const filter = expFilter.value;
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const email = row.dataset.email || '';
        const exp = parseInt(row.dataset.exp || '0', 10);

        const matchesSearch = name.includes(search) || email.includes(search);
        let matchesFilter = true;

        if (filter === 'junior') {
            matchesFilter = exp < 3;
        } else if (filter === 'mid') {
            matchesFilter = exp >= 3 && exp <= 5;
        } else if (filter === 'senior') {
            matchesFilter = exp > 5;
        }

        if (matchesSearch && matchesFilter) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    resultCount.textContent = visible + ' expert(s)';
}

if (searchInput) searchInput.addEventListener('input', filterAccounts);
if (expFilter) expFilter.addEventListener('change', filterAccounts);

function openModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeModal() {
    document.getElementById('addModal').classList.remove('active');
}

document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeCredentialsModal();
        closeConfirmModal();
        document.getElementById('filePreviewModal').classList.remove('active');
    }
});

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

// DYNAMIC UNIVERSAL CONFIRMATION CARD MODAL TRIGGER
function openConfirmModal(type, targetUrl, expertName) {
    const modal = document.getElementById('actionConfirmModal');
    const iconFrame = document.getElementById('confirmIconFrame');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const desc = document.getElementById('confirmDesc');
    const actionBtn = document.getElementById('confirmActionBtn');

    // Reset classes
    iconFrame.className = "confirm-icon-frame";
    actionBtn.className = "confirm-btn-action";

    if (type === 'delete') {
        iconFrame.classList.add('confirm-icon-delete');
        icon.className = "fa-solid fa-trash";
        title.textContent = "Delete Account";
        desc.innerHTML = `Are you sure you want to permanently delete <strong>${expertName}</strong>'s account? This action cannot be undone.`;
        actionBtn.textContent = "Yes, Delete";
        actionBtn.classList.add('confirm-btn-danger');
    } else if (type === 'approve') {
        iconFrame.classList.add('confirm-icon-approve');
        icon.className = "fa-solid fa-check";
        title.textContent = "Approve Account";
        desc.innerHTML = `Are you sure you want to approve <strong>${expertName}</strong>? An SMS notification will be sent automatically.`;
        actionBtn.textContent = "Yes, Approve";
        actionBtn.classList.add('confirm-btn-success');
    } else if (type === 'reject') {
        iconFrame.classList.add('confirm-icon-reject');
        icon.className = "fa-solid fa-xmark";
        title.textContent = "Reject Account";
        desc.innerHTML = `Are you sure you want to reject <strong>${expertName}</strong>'s application? An SMS alert will be sent.`;
        actionBtn.textContent = "Yes, Reject";
        actionBtn.classList.add('confirm-btn-warning');
    } else if (type === 'logout') {
        iconFrame.classList.add('confirm-icon-logout');
        icon.className = "fa-solid fa-door-closed";
        title.textContent = "Confirm Logout";
        desc.textContent = "Are you sure you want to log out of your session?";
        actionBtn.textContent = "Yes, Logout";
        actionBtn.classList.add('confirm-btn-danger');
    }

    actionBtn.href = targetUrl;
    modal.classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('actionConfirmModal').classList.remove('active');
}

document.getElementById('actionConfirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

function openCredentialsModal(docList, expertName, experienceText, certificationText) {
    const modal = document.getElementById('credentialsModal');
    const title = document.getElementById('credModalTitle');
    const certText = document.getElementById('credCertText');
    const expText = document.getElementById('credExpText');
    const gridContainer = document.getElementById('docGridContainer');
    const noDocText = document.getElementById('noDocText');

    title.textContent = "Credentials - " + expertName;
    certText.textContent = certificationText || 'No certification provided';
    expText.textContent = experienceText || 'No experience provided';
    gridContainer.innerHTML = '';

    if (docList && docList.length > 0) {
        noDocText.style.display = 'none';
        docList.forEach((doc, index) => {
            const docUrl = '../uploads/nutritionists/' + doc;
            const ext = doc.split('.').pop().toLowerCase();

            const item = document.createElement('div');
            item.className = 'doc-item';
            
            if (ext === 'pdf') {
                item.innerHTML = `<div class="pdf-icon"><i class="fa-solid fa-file-pdf"></i></div><div class="doc-name">Document ${index + 1}</div>`;
            } else {
                item.innerHTML = `<img src="${docUrl}" alt="Doc"><div class="doc-name">Document ${index + 1}</div>`;
            }

            item.onclick = function() {
                const iframe = document.getElementById('previewIframe');
                const image = document.getElementById('previewImage');
                if (ext === 'pdf') {
                    iframe.src = docUrl;
                    iframe.style.display = 'block';
                    image.style.display = 'none';
                } else {
                    image.src = docUrl;
                    image.style.display = 'block';
                    iframe.style.display = 'none';
                }
                document.getElementById('filePreviewModal').classList.add('active');
            };

            gridContainer.appendChild(item);
        });
    } else {
        noDocText.style.display = 'block';
    }

    modal.classList.add('active');
}

function closeCredentialsModal() {
    const modal = document.getElementById('credentialsModal');
    modal.classList.remove('active');
}

document.getElementById('credentialsModal').addEventListener('click', function(e) {
    if (e.target === this) closeCredentialsModal();
});
</script>

</body>
</html>