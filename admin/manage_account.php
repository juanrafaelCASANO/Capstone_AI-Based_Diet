<?php
session_start();
require_once '../config.php';

// ======================================================
// ADMIN SECURITY
// ======================================================
if (!isset($_SESSION['role'])) { header("Location: ../auth/login.php"); exit(); } elseif ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$errors = [];$success = "";

// ======================================================
// TEXTBEE SMS HELPER FUNCTION
// ======================================================
function sendTextBeeSMS($phoneNumber, $message) {
    $textbeeKey = getenv('TEXTBEE_API_KEY') ?: (defined('TEXTBEE_API_KEY') ? TEXTBEE_API_KEY : 'txb_3fsPevHJvqEIoVVr9cR2QmVxKsB4wRm4');
    
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
        'x-api-key: ' . $textbeeKey // ITAMA ITO: Ginawang $textbeeKey
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
    $password =$_POST['password'] ?? '';
    $confirm_password =$_POST['confirm_password'] ?? '';
    $certification = trim($_POST['certification'] ?? '');
    $experience = trim($_POST['experience'] ?? '');

    if (empty($fullname))$errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))$errors[] = "Valid email is required.";
    if (strlen($password) < 6)$errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password)$errors[] = "Passwords do not match.";
    if (empty($_FILES['profile_pic']['name']))$errors[] = "Profile picture is required.";
    if (empty($_FILES['document']['name'][0]))$errors[] = "Verification documents are required.";

    if (empty($errors)) {$doc_dir = "../uploads/nutritionists/";
        $pic_dir = "../uploads/profile_pics/";

        if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
        if (!is_dir($pic_dir)) mkdir($pic_dir, 0755, true);

        $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));
        $pic_uploaded = move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $pic_dir .$pic_filename);

        $uploaded_docs = [];
        foreach ($_FILES['document']['name'] as $key =>$name) {
            if ($_FILES['document']['error'][$key] === UPLOAD_ERR_OK) {$doc_filename = time() . "_" . $key . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($name));
                if (move_uploaded_file($_FILES['document']['tmp_name'][$key], $doc_dir .$doc_filename)) {
                    $uploaded_docs[] =$doc_filename;
                }
            }
        }

        if ($pic_uploaded) {
            if (!empty($uploaded_docs)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $documents_json = json_encode($uploaded_docs);

                $stmt =$conn->prepare("INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, certification, experience, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                
                if ($stmt->execute([$fullname,$pic_filename, $email,$phone, $hashed_password,$certification, $experience,$documents_json])) {
                    header("Location: manage_account.php?msg=success");
                    exit();
                } else {
                    $errors[] = "Database Error occurred.";
                }
            } else {
                $errors[] = "File upload failed. Check folder permissions.";
            }
        } else {
            $errors[] = "File upload failed. Check folder permissions.";
        }
    }
}

// Handle Approval & Send SMS Notification
if (isset($_GET['approve_id'])) {
    $id = (int)$_GET['approve_id'];
    
    $stmt =$conn->prepare("SELECT fullname, phone FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    $nutri =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($nutri) {
        $updateStmt =$conn->prepare("UPDATE nutritionist SET status = 'active' WHERE id = ?");
        $updateStmt->execute([$id]);

        $message = "Hello " . $nutri['fullname'] . ", great news! Your nutritionist account on AI Diet Planner has been APPROVED. You can now log in.";
        sendTextBeeSMS($nutri['phone'],$message);
    }

    header("Location: manage_account.php?msg=approved");
    exit();
}

// Handle Rejection & Send SMS Notification
if (isset($_GET['reject_id'])) {
    $id = (int)$_GET['reject_id'];
    
    $stmt =$conn->prepare("SELECT fullname, phone FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    $nutri =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($nutri) {
        $updateStmt =$conn->prepare("UPDATE nutritionist SET status = 'rejected' WHERE id = ?");
        $updateStmt->execute([$id]);

        $message = "Hello " . $nutri['fullname'] . ", we regret to inform you that your nutritionist account application on AI Diet Planner has been REJECTED.";
        sendTextBeeSMS($nutri['phone'],$message);
    }

    header("Location: manage_account.php?msg=rejected");
    exit();
}

// Handle Deactivation with Reason & SMS Notification
if (isset($_POST['confirm_deactivate'])) {
    $id = (int)$_POST['deactivate_id'];
    $category = trim($_POST['deactivation_reason_category'] ?? 'Policy Violation');
    $remarks = trim($_POST['deactivation_remarks'] ?? '');

    $stmt = $conn->prepare("SELECT fullname, phone FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    $nutri = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($nutri) {
        $updateStmt = $conn->prepare("UPDATE nutritionist SET status = 'inactive' WHERE id = ?");
        $updateStmt->execute([$id]);

        function tableExists($conn, $tableName) {
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = CURRENT_SCHEMA() AND table_name = ?");
                $stmt->execute([$tableName]);
                return (int)$stmt->fetchColumn() > 0;
            } catch (Exception $e) { return false; }
        }

        if (tableExists($conn, 'activity_logs')) {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (action, description, created_at) VALUES (?, ?, NOW())");
            $logStmt->execute(['Deactivated Nutritionist', "Admin deactivated nutritionist {$nutri['fullname']}. Reason: {$category} - {$remarks}"]);
        }

        $message = "Hello " . $nutri['fullname'] . ", your nutritionist account on AI Diet Planner has been DEACTIVATED.\nReason: " . $category . "\nDetails: " . $remarks . "\nYou may submit a reactivation request via the login page.";
        sendTextBeeSMS($nutri['phone'], $message);
    }

    header("Location: manage_account.php?msg=deactivated_with_reason");
    exit();
}

// Handle Active Status Toggle with SMS Abiso
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $new_status =$_GET['toggle_status'] === 'active' ? 'active' : 'inactive';
    
    $stmt =$conn->prepare("SELECT fullname, phone FROM nutritionist WHERE id = ?");
    $stmt->execute([$id]);
    $nutri =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($nutri && $new_status === 'active') {
        $updateStmt =$conn->prepare("UPDATE nutritionist SET status = ? WHERE id = ?");
        $updateStmt->execute([$new_status,$id]);

        $message = "Hello " . $nutri['fullname'] . ", great news! Your nutritionist account on AI Diet Planner has been REACTIVATED. You can now log in.";
        sendTextBeeSMS($nutri['phone'],$message);
    }

    header("Location: manage_account.php?msg=status_updated");
    exit();
}

// ======================================================
// FETCH EXPERTS & DATA ANALYSIS
// ======================================================
$experts = [];$db_error = null;

try {
    $stmt =$conn->query("SELECT id, fullname, email, phone, certification, experience, status, profile_pic, document FROM nutritionist ORDER BY id DESC");
    $experts =$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error =$e->getMessage();
}

$totalAccounts = count($experts);$juniorCount = 0;   
$midCount = 0;      
$seniorCount = 0;   
$totalExp = 0;

foreach ($experts as$exp) {
    $expText =$exp['experience'] ?? '';
    preg_match('/\d+/', $expText, $matches);$yrs = isset($matches[0]) ? (int)$matches[0] : 0;
    
    $totalExp +=$yrs;

    if ($yrs < 3) {$juniorCount++;
    } elseif ($yrs <= 5) {$midCount++;
    } else {
        $seniorCount++;
    }
}

$avgExperience =$totalAccounts > 0 ? round($totalExp / $totalAccounts, 1) : 0;
$adminName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f4f7ff 0%, #eef3ff 50%, #f8fafc 100%); color: #172033; min-height: 100vh; }
        a { text-decoration: none; }
        button, input, select, textarea { font-family: inherit; }

        .admin-layout { display: flex; min-height: 100vh; width: 100%; }
        .admin-sidebar { width: 255px; min-width: 255px; min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0; z-index: 1000; padding: 22px 15px; background: #0f172a; color: #cbd5e1; display: flex; flex-direction: column; transition: transform .25s ease; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 25px; color: #fff; }
        .sidebar-logo { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; font-size: 19px; box-shadow: 0 8px 20px rgba(59,130,246,.28); }
        .sidebar-brand h2 { margin: 0; color: #fff; font-size: 15px; font-weight: 800; }
        .sidebar-brand span { color: #94a3b8; font-size: 11px; }
        .sidebar-section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: #64748b; font-weight: 700; padding: 14px 12px 8px; }
        .sidebar-nav { display: flex; flex-direction: column; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 13px; border-radius: 11px; margin-bottom: 4px; color: #cbd5e1; font-size: 13px; font-weight: 600; transition: .2s; cursor: pointer; border: none; background: transparent; width: 100%; text-align: left; }
        .sidebar-link i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-link:hover { background: rgba(59,130,246,.10); color: #fff; }
        .sidebar-link.active { background: rgba(37, 99, 235, .22); color: #93c5fd; }
        .sidebar-bottom { margin-top: auto; }
        .admin-mini { border-top: 1px solid #263244; padding: 16px 8px 4px; display: flex; align-items: center; gap: 10px; }
        .avatar { width: 37px; height: 37px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #818cf8); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 13px; }
        .admin-mini strong { color: #fff; font-size: 12px; display: block; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .admin-mini span { color: #64748b; font-size: 10px; }

        .main-content { flex: 1; min-width: 0; margin-left: 255px; }
        .page { width: 100%; margin: 0; padding: 32px 40px 50px; }

        .top-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
        .title-area { display: flex; align-items: center; gap: 15px; }
        .title-icon { width: 58px; height: 58px; border-radius: 16px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; font-size: 23px; box-shadow: 0 10px 25px rgba(37,99,235,.25); }
        .title-area h1 { font-size: 28px; font-weight: 800; color: #111827; margin-bottom: 4px; }
        .title-area p { color: #64748b; font-size: 13px; }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 16px; border-radius: 10px; background: white; color: #2563eb; font-weight: 600; border: 1px solid #e2e8f0; transition: .25s; }
        .back-btn:hover { transform: translateX(-3px); border-color: #bfdbfe; box-shadow: 0 6px 18px rgba(15,23,42,.08); }
        .add-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 17px; border-radius: 10px; background: linear-gradient(135deg, #16a34a, #22c55e); color: white; font-weight: 700; border: none; box-shadow: 0 7px 18px rgba(34,197,94,.25); cursor: pointer; transition: .25s; }
        .add-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(34,197,94,.32); }

        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 25px; }
        .stat-card { position: relative; overflow: hidden; background: rgba(255,255,255,.88); border: 1px solid rgba(226,232,240,.8); border-radius: 17px; padding: 20px; box-shadow: 0 8px 25px rgba(15,23,42,.05); transition: .3s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(15,23,42,.09); }
        .stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .stat-icon { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 17px; }
        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #f0fdf4; color: #16a34a; }
        .icon-orange { background: #fff7ed; color: #ea580c; }
        .icon-purple { background: #faf5ff; color: #9333ea; }
        .icon-red { background: #fef2f2; color: #dc2626; }
        .stat-label { color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .stat-number { font-size: 27px; font-weight: 800; color: #111827; }

        .main-card { background: white; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 10px 35px rgba(15,23,42,.06); overflow: hidden; }
        .toolbar { padding: 20px; display: flex; justify-content: space-between; align-items: center; gap: 15px; border-bottom: 1px solid #eef2f7; background: #ffffff; }
        .toolbar-title h2 { font-size: 17px; font-weight: 800; color: #111827; }
        .toolbar-title span { display: block; margin-top: 3px; color: #94a3b8; font-size: 12px; }
        .toolbar-controls { display: flex; align-items: center; gap: 10px; }
        .search-box { position: relative; }
        .search-box i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-box input { width: 260px; height: 40px; padding: 0 14px 0 38px; border: 1px solid #e2e8f0; border-radius: 9px; outline: none; color: #334155; transition: .2s; }
        .search-box input:focus { border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .filter-select { height: 40px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 9px; background: white; color: #475569; outline: none; cursor: pointer; }

        .table-wrapper { overflow-x: auto; }
        .account-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .account-table th { padding: 14px 20px; background: #f8fafc; color: #64748b; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 2; }
        .account-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .account-table tbody tr { transition: .2s; }
        .account-table tbody tr:hover { background: #f8fbff; }

        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; border: 1px solid #dbeafe; overflow: hidden; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        .user-id { color: #94a3b8; font-size: 11px; margin-top: 2px; }

        .view-credentials-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 12px; font-weight: 700; cursor: pointer; transition: .2s; }
        .view-credentials-btn:hover { background: #dbeafe; border-color: #93c5fd; transform: translateY(-1px); }

        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-active { background: #dcfce7; color: #15803d; }
        .status-inactive { background: #fee2e2; color: #b91c1c; }
        .status-rejected { background: #f3f4f6; color: #4b5563; }

        .actions { display: flex; align-items: center; gap: 6px; }
        .action-btn { width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 9px; border: none; cursor: pointer; transition: .2s; }
        .edit-btn { background: #eef2ff; color: #4f46e5; }
        .edit-btn:hover { background: #e0e7ff; transform: translateY(-2px); }

        .status-toggle-btn { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-make-inactive { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .btn-make-inactive:hover { background: #ffedd5; transform: translateY(-1px); }
        .btn-make-active { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .btn-make-active:hover { background: #dcfce7; transform: translateY(-1px); }

        .empty-state { padding: 70px 20px; text-align: center; color: #94a3b8; }
        .empty-icon { width: 70px; height: 70px; margin: 0 auto 15px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8; font-size: 25px; }
        .error-message { margin: 20px; padding: 14px 16px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; font-size: 13px; }

        .modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000; backdrop-filter: blur(5px); overflow-y: auto; }
        .modal.active { display: flex; }
        .modal-box { width: 100%; max-width: 750px; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 25px 70px rgba(15,23,42,.25); animation: modalIn .25s ease; max-height: 90vh; overflow-y: auto; }
        @keyframes modalIn { from { opacity: 0; transform: translateY(15px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 20px; color: #111827; font-weight: 800; }
        .close-modal { width: 35px; height: 35px; border: none; border-radius: 8px; background: #f1f5f9; cursor: pointer; color: #475569; font-size: 16px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 12px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"], .form-group textarea { width: 100%; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 9px; outline: none; color: #1e293b; font-size: 14px; transition: .2s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 8px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; font-size: 13px; color: #64748b; cursor: pointer; }

        .password-box { position: relative; width: 100%; }
        .password-box input { padding-right: 40px; }
        .toggle-password { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 15px; }

        .submit-btn { width: 100%; height: 46px; margin-top: 10px; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 6px 18px rgba(37,99,235,.25); transition: .2s; }
        .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(37,99,235,.35); }
        .error-alert { background: #fee2e2; color: #991b1b; padding: 12px 14px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; border-left: 4px solid #dc2626; }

        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px; }
        .doc-item { border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; cursor: pointer; text-align: center; padding: 6px; transition: .2s; }
        .doc-item:hover { border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,.15); }
        .doc-item img, .doc-item .pdf-icon { width: 100%; height: 90px; object-fit: cover; border-radius: 6px; }
        .doc-item .pdf-icon { display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #dc2626; font-size: 28px; }
        .doc-name { font-size: 11px; color: #475569; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .confirm-modal-box { max-width: 420px; text-align: center; padding: 35px 25px; border-radius: 24px; }
        .confirm-icon-frame { width: 65px; height: 65px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .confirm-icon-approve { background: #f0fdf4; color: #16a34a; }
        .confirm-icon-reject { background: #fefce8; color: #ca8a04; }
        .confirm-icon-logout { background: #fef2f2; color: #dc2626; }
        .confirm-icon-status { background: #fff7ed; color: #ea580c; }

        .confirm-modal-box h3 { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 8px; }
        .confirm-modal-box p { font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 25px; }
        .confirm-modal-actions { display: flex; gap: 12px; }
        .confirm-btn-cancel, .confirm-btn-action { flex: 1; height: 44px; border-radius: 11px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; transition: .2s; }
        .confirm-btn-cancel { background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; }
        .confirm-btn-cancel:hover { background: #f8fafc; }
        .confirm-btn-success { background: #16a34a; color: white; box-shadow: 0 4px 14px rgba(22,163,74,.3); }
        .confirm-btn-warning { background: #ca8a04; color: white; box-shadow: 0 4px 14px rgba(202,138,4,.3); }
        .confirm-btn-danger { background: #dc2626; color: white; box-shadow: 0 4px 14px rgba(220,38,38,.3); }
        .confirm-btn-orange { background: #ea580c; color: white; box-shadow: 0 4px 14px rgba(234,88,12,.3); }

        .cred-btn-approve { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 10px 18px; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 6px; }
        .cred-btn-approve:hover:not(:disabled) { background: #bbf7d0; transform: translateY(-2px); }
        .cred-btn-reject { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 18px; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 6px; }
        .cred-btn-reject:hover:not(:disabled) { background: #fecaca; transform: translateY(-2px); }
        .cred-btn-approve:disabled, .cred-btn-reject:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(80%); }

        .verification-box { background: #eff6ff; border: 1px dashed #93c5fd; border-radius: 12px; padding: 18px; margin-top: 15px; }
        .custom-checkbox { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; user-select: none; margin-bottom: 12px; }
        .custom-checkbox:last-child { margin-bottom: 0; }
        .custom-checkbox input { display: none; }
        .checkmark { width: 22px; height: 22px; min-width: 22px; background-color: #fff; border: 2px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: 0.2s; margin-top: 1px; }
        .custom-checkbox input:checked ~ .checkmark { background-color: #2563eb; border-color: #2563eb; }
        .custom-checkbox input:checked ~ .checkmark::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: white; font-size: 12px; }
        .check-text { font-size: 13.5px; color: #475569; font-weight: 500; line-height: 1.4; }
        .custom-checkbox input:checked ~ .check-text { color: #1e3a8a; font-weight: 600; }

        @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 900px) { .admin-sidebar { transform: translateX(-100%); } .admin-sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .main-content .page { padding-left: 25px; padding-right: 25px; } }
        @media (max-width: 800px) { .page { padding: 20px 15px 40px; } .top-header { align-items: flex-start; flex-direction: column; } .header-actions { width: 100%; } .back-btn, .add-btn { flex: 1; justify-content: center; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .toolbar { align-items: stretch; flex-direction: column; } .toolbar-controls { width: 100%; flex-direction: column; } .search-box, .search-box input, .filter-select { width: 100%; } .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
    </style>
</head>

<body>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo"><i class="fa-solid fa-leaf"></i></div>
            <div><h2>AI Diet Planner</h2><span>Administration</span></div>
        </div>
        
        <div class="sidebar-section-title">Main Menu</div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="meals.php" class="sidebar-link"><i class="fa-solid fa-utensils"></i><span>Meal Management</span></a>
            <a href="manage_account.php" class="sidebar-link active"><i class="fa-solid fa-users-gear"></i><span>Accounts</span></a>
            <a href="manage_reports.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i><span>Reports</span></a>
            <a href="activity_logs.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i><span>Activity Logs</span></a>
        </nav>

        <div class="sidebar-section-title">System</div>
        <nav class="sidebar-nav">
            <button type="button" class="sidebar-link" onclick="openLogoutModal()">
                <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
            </button>
        </nav>

        <div class="sidebar-bottom">
            <div class="admin-mini">
                <div class="avatar">
                    <?= strtoupper(substr($adminName, 0, 1)) ?>
                </div>
                <div>
                    <strong><?= htmlspecialchars($adminName) ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="page">
            <header class="top-header">
                <div class="title-area">
                    <div class="title-icon"><i class="fa-solid fa-user-nurse"></i></div>
                    <div>
                        <h1>Manage Accounts</h1>
                        <p>Create, audit, approve, and oversee expert nutritionist accounts.</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i>Dashboard</a>
                    <button type="button" class="add-btn" onclick="openModal()"><i class="fa-solid fa-plus"></i>Add New Expert</button>
                </div>
            </header>

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Total Experts</div><div class="stat-number"><?= number_format($totalAccounts) ?></div></div>
                        <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Junior (< 3Yrs)</div><div class="stat-number"><?= number_format($juniorCount) ?></div></div>
                        <div class="stat-icon icon-green"><i class="fa-solid fa-seedling"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Mid-Level (3-5Yrs)</div><div class="stat-number"><?= number_format($midCount) ?></div></div>
                        <div class="stat-icon icon-orange"><i class="fa-solid fa-user-check"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Senior (> 5Yrs)</div><div class="stat-number"><?= number_format($seniorCount) ?></div></div>
                        <div class="stat-icon icon-purple"><i class="fa-solid fa-award"></i></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <div><div class="stat-label">Avg Experience</div><div class="stat-number"><?= $avgExperience ?> <span style="font-size:14px; font-weight:600;">yrs</span></div></div>
                        <div class="stat-icon icon-red"><i class="fa-solid fa-chart-line"></i></div>
                    </div>
                </div>
            </section>

            <section class="main-card">
                <div class="toolbar">
                    <div class="toolbar-title">
                        <h2>Account Database</h2>
                        <span id="resultCount"><?= number_format($totalAccounts) ?> expert(s)</span>
                    </div>
                    <div class="toolbar-controls">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search expert name or email..." autocomplete="off">
                        </div>
                        <select id="expFilter" class="filter-select">
                            <option value="all">All Experience Levels</option>
                            <option value="junior">Junior (< 3 Years)</option>
                            <option value="mid">Mid-Level (3 - 5 Years)</option>
                            <option value="senior">Senior (> 5 Years)</option>
                        </select>
                    </div>
                </div>

                <?php if ($db_error): ?>
                    <div class="error-message"><strong><i class="fa-solid fa-triangle-exclamation"></i> Database Error:</strong> <?= htmlspecialchars($db_error) ?></div>
                <?php endif; ?>

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

                                    $status = strtolower($row['status'] ?? 'pending');
                                    if ($status === 'approved') { $status = 'active'; }

                                    $profile_pic = trim($row['profile_pic'] ?? '');
                                    $document_raw = trim($row['document'] ?? '');
                                    $certification = $row['certification'] ?? '';
                                    $experience_desc = $row['experience'] ?? '';

                                    $imageSrc = '';
                                    if ($profile_pic !== '') {
                                        if (strpos($profile_pic, 'http') === 0 || strpos($profile_pic, '/') === 0) {
                                            $imageSrc = $profile_pic;
                                        } else {
                                            $imageSrc = '../uploads/profile_pics/' . $profile_pic;
                                        }
                                    }

                                    $doc_list = [];
                                    if ($document_raw !== '') {
                                        $decoded = json_decode($document_raw, true);
                                        if (is_array($decoded)) {
                                            $doc_list = $decoded;
                                        } else {
                                            $doc_list = strpos($document_raw, ',') !== false ? explode(',', $document_raw) : [$document_raw];
                                        }
                                    }
                                    
                                    $clean_docs = [];
                                    foreach ($doc_list as $d) {
                                        $dt = trim($d);
                                        if ($dt !== '') { $clean_docs[] = $dt; }
                                    }

                                    $initials = 'E';
                                    $parts = explode(' ', trim($fullname));
                                    if (count($parts) >= 2) {
                                        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
                                    } else if (!empty($parts[0])) {
                                        $initials = strtoupper(substr($parts[0], 0, 2));
                                    }
                                ?>
                                    <tr class="account-row" data-name="<?= htmlspecialchars(strtolower($fullname)) ?>" data-email="<?= htmlspecialchars(strtolower($email)) ?>" data-exp="<?= $exp ?>">
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
                                            <span style="color:#475569; font-weight:500; font-size:13px; display:block;"><?= htmlspecialchars($email) ?></span>
                                            <span style="color:#94a3b8; font-size:12px;"><i class="fa-solid fa-phone" style="font-size:10px; margin-right:3px;"></i> <?= htmlspecialchars($phone) ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="view-credentials-btn" onclick='openCredentialsModal(<?= json_encode($clean_docs) ?>, <?= json_encode($fullname) ?>, <?= json_encode($experience_desc) ?>, <?= json_encode($certification) ?>, <?= $id ?>, <?= json_encode($status) ?>)'>
                                                <i class="fa-solid fa-file-shield"></i> View Credentials
                                            </button>
                                        </td>
                                        <td>
                                            <?php if ($status === 'active'): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php elseif ($status === 'inactive'): ?>
                                                <span class="status-badge status-inactive">Inactive</span>
                                            <?php elseif ($status === 'rejected'): ?>
                                                <span class="status-badge status-rejected">Rejected</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="edit_expert.php?id=<?= $id ?>" class="action-btn edit-btn" title="Edit Expert"><i class="fa-solid fa-pen"></i></a>
                                                <?php if ($status === 'active'): ?>
                                                    <button type="button" class="status-toggle-btn btn-make-inactive" title="Deactivate Account" onclick="openDeactivateModal(<?= $id ?>, '<?= htmlspecialchars($fullname, ENT_QUOTES) ?>', '<?= htmlspecialchars($phone, ENT_QUOTES) ?>')">
                                                        <i class="fa-solid fa-user-slash"></i> Deactivate
                                                    </button>
                                                <?php elseif ($status === 'inactive'): ?>
                                                    <button type="button" class="status-toggle-btn btn-make-active" title="Activate Account" onclick="openConfirmModal('toggle', 'manage_account.php?toggle_status=active&id=<?= $id ?>', '<?= htmlspecialchars($fullname, ENT_QUOTES) ?>', 'active')">
                                                        <i class="fa-solid fa-user-check"></i> Activate
                                                    </button>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; color: #94a3b8; font-style: italic;">Review in credentials</span>
                                                <?php endif; ?>
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
            <button type="button" class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="error-alert"><?php foreach ($errors as $err) echo "• " . htmlspecialchars($err) . "<br>"; ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width"><label>Full Name</label><input type="text" name="fullname" placeholder="e.g. Dr. Jane Doe" required></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="name@example.com" required></div>
                <div class="form-group"><label>Phone Number (11 digits)</label><input type="text" name="phone" placeholder="09123456789" required></div>
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
                <div class="form-group"><label>Profile Photo</label><div class="file-input-wrapper"><input type="file" name="profile_pic" accept="image/*" required></div></div>
                <div class="form-group"><label>Verification Documents</label><div class="file-input-wrapper"><input type="file" name="document[]" accept="image/*,.pdf,.doc,.docx" multiple required></div></div>
                <div class="form-group full-width"><label>Certifications & Licenses</label><textarea name="certification" placeholder="List professional licenses or certifications..." required></textarea></div>
                <div class="form-group full-width"><label>Professional Experience</label><textarea name="experience" placeholder="Describe relevant work history..." required></textarea></div>
                <div class="form-group full-width"><button type="submit" name="add_expert" class="submit-btn">Create Expert Account</button></div>
            </div>
        </form>
    </div>
</div>

<!-- VIEW CREDENTIALS MODAL -->
<div class="modal" id="credentialsModal">
    <div class="modal-box" style="max-width: 750px;">
        <div class="modal-header">
            <h3 id="credModalTitle">Expert Credentials</h3>
            <button type="button" class="close-modal" onclick="closeCredentialsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 6px;"><i class="fa-solid fa-briefcase" style="color: #16a34a; margin-right: 4px;"></i> Professional Experience</label>
                <p id="credExpText" style="font-size: 14px; color: #1e293b; white-space: pre-wrap; line-height: 1.5;"></p>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 6px;"><i class="fa-solid fa-user-tag" style="color: #2563eb; margin-right: 4px;"></i> Type of Nutritionist</label>
                <p id="credTypeField" style="font-size: 14px; color: #1e293b; white-space: pre-wrap; line-height: 1.5;"></p>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 6px;"><i class="fa-solid fa-file-lines" style="color: #9333ea; margin-right: 4px;"></i> Resume / CV & Certifications (Click to View)</label>
                <div id="docGridContainer" class="doc-grid"></div>
                <div id="noDocText" style="display: none; color: #94a3b8; font-size: 13px;">No documents uploaded</div>
            </div>
            
            <div id="verificationSection" class="verification-box" style="display: none;">
                <label style="font-size: 12px; font-weight: 800; color: #1e3a8a; display: block; margin-bottom: 10px; text-transform: uppercase;"><i class="fa-solid fa-clipboard-check" style="margin-right: 4px;"></i> Admin Verification Required</label>
                <p style="font-size: 13px; color: #3b82f6; margin-bottom: 14px; line-height: 1.4;">Please review documents carefully. Check the boxes below to unlock approval and rejection actions.</p>
                <label class="custom-checkbox"><input type="checkbox" id="checkResume"><span class="checkmark"></span><span class="check-text">I have thoroughly reviewed the uploaded <strong>Resume / CV</strong>.</span></label>
                <label class="custom-checkbox"><input type="checkbox" id="checkCert"><span class="checkmark"></span><span class="check-text">I have verified the authenticity of the <strong>Certifications & Licenses</strong>.</span></label>
            </div>

            <div id="credActionContainer" style="display: none; justify-content: flex-end; gap: 12px; margin-top: 5px;">
                <button type="button" id="credRejectBtn" class="cred-btn-reject" disabled><i class="fa-solid fa-xmark"></i> Reject Application</button>
                <button type="button" id="credApproveBtn" class="cred-btn-approve" disabled><i class="fa-solid fa-check"></i> Approve Expert</button>
            </div>
        </div>
    </div>
</div>

<!-- FILE PREVIEW SUB-MODAL -->
<div class="modal" id="filePreviewModal" style="z-index: 1050;">
    <div class="modal-box" style="max-width: 800px; height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>Document Viewer</h3>
            <button type="button" class="close-modal" onclick="document.getElementById('filePreviewModal').classList.remove('active')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="flex: 1; background: #000; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;">
            <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none; display: none; background: white;"></iframe>
            <img id="previewImage" src="" style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;">
            <div id="noPreviewMessage" style="display: none; flex-direction: column; align-items: center; gap: 15px; color: white;">
                <i class="fa-solid fa-file-word" style="font-size: 50px; color: #cbd5e1;"></i>
                <p>Preview is not available for this document type.</p>
                <a id="downloadFallbackBtn" href="#" target="_blank" class="submit-btn" style="width:auto; padding:10px 20px; text-decoration:none;"><i class="fa-solid fa-download"></i> Download File</a>
            </div>
        </div>
    </div>
</div>

<!-- DEACTIVATE WITH REASON MODAL -->
<div class="modal" id="deactivateModal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Deactivate Nutritionist Account</h3>
            <button type="button" class="close-modal" onclick="closeDeactivateModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="manage_account.php">
            <input type="hidden" name="deactivate_id" id="deactivateNutriId">
            <input type="hidden" name="deactivate_phone" id="deactivateNutriPhone">
            
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
                    You are about to deactivate <strong id="deactivateNutriName"></strong>'s account. Please provide a reason so they are informed via SMS.
                </p>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Reason for Deactivation</label>
                    <select name="deactivation_reason_category" class="filter-select" style="width: 100%; margin-bottom: 10px;" required>
                        <option value="" disabled selected>Select a reason category...</option>
                        <option value="Policy Violation">Policy Violation / Terms Breach</option>
                        <option value="Inactivity">Prolonged Inactivity</option>
                        <option value="Client Complaints">Multiple Client Complaints</option>
                        <option value="Expired Credentials">Expired or Invalid Credentials</option>
                        <option value="Other">Other Reason</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Additional Details / Remarks</label>
                    <textarea name="deactivation_remarks" placeholder="Provide specific details or instructions for reactivation..." style="width: 100%; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 9px; outline: none; font-size: 14px; min-height: 90px;" required></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    <button type="button" class="confirm-btn-cancel" onclick="closeDeactivateModal()" style="flex:1; height:44px; border-radius:11px; font-weight:700; cursor:pointer; background:#fff; border:1px solid #e2e8f0;">Cancel</button>
                    <button type="submit" name="confirm_deactivate" class="confirm-btn-action confirm-btn-danger" style="flex:1; height:44px; border-radius:11px; font-weight:700; cursor:pointer; background:#dc2626; color:#fff; border:none;">Confirm Deactivation</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- CONFIRMATION MODAL -->
<div class="modal" id="actionConfirmModal">
    <div class="modal-box confirm-modal-box">
        <div id="confirmIconFrame" class="confirm-icon-frame"><i id="confirmIcon" class="fa-solid fa-door-closed"></i></div>
        <h3 id="confirmTitle">Confirm Action</h3>
        <p id="confirmDesc">Are you sure you want to proceed?</p>
        <div class="confirm-modal-actions">
            <button type="button" class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <a id="confirmActionBtn" href="#" class="confirm-btn-action">Yes, Proceed</a>
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

        let matchesSearch = name.includes(search) || email.includes(search);
        let matchesFilter = true;

        if (filter === 'junior') matchesFilter = exp < 3;
        else if (filter === 'mid') matchesFilter = exp >= 3 && exp <= 5;
        else if (filter === 'senior') matchesFilter = exp > 5;

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

function openModal() { document.getElementById('addModal').classList.add('active'); }
function closeModal() { document.getElementById('addModal').classList.remove('active'); }

document.getElementById('addModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeCredentialsModal();
        closeConfirmModal();
        closeDeactivateModal();
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

function openDeactivateModal(nutriId, nutriName, nutriPhone) {
    document.getElementById('deactivateNutriId').value = nutriId;
    document.getElementById('deactivateNutriPhone').value = nutriPhone;
    document.getElementById('deactivateNutriName').textContent = nutriName;
    document.getElementById('deactivateModal').classList.add('active');
}

function closeDeactivateModal() {
    document.getElementById('deactivateModal').classList.remove('active');
}

function openConfirmModal(type, targetUrl, expertName, statusType) {
    const modal = document.getElementById('actionConfirmModal');
    const iconFrame = document.getElementById('confirmIconFrame');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const desc = document.getElementById('confirmDesc');
    const actionBtn = document.getElementById('confirmActionBtn');

    iconFrame.className = "confirm-icon-frame";
    actionBtn.className = "confirm-btn-action";

    if (type === 'toggle') {
        iconFrame.classList.add('confirm-icon-status');
        icon.className = statusType === 'inactive' ? "fa-solid fa-user-slash" : "fa-solid fa-user-check";
        title.textContent = statusType === 'inactive' ? "Deactivate Account" : "Activate Account";
        desc.innerHTML = `Are you sure you want to set <strong>${expertName}</strong>'s account to <strong>${statusType}</strong>? An SMS notification will be sent.`;
        actionBtn.textContent = statusType === 'inactive' ? "Yes, Deactivate" : "Yes, Activate";
        actionBtn.classList.add('confirm-btn-orange');
    } else if (type === 'approve_verified') {
        iconFrame.classList.add('confirm-icon-approve');
        icon.className = "fa-solid fa-shield-check";
        title.textContent = "Final Verification Assurance";
        desc.innerHTML = `You are about to approve <strong>${expertName}</strong>. Are you sure you have verified their credentials?`;
        actionBtn.textContent = "Yes, Verify & Approve";
        actionBtn.classList.add('confirm-btn-success');
    } else if (type === 'reject_verified') {
        iconFrame.classList.add('confirm-icon-reject');
        icon.className = "fa-solid fa-triangle-exclamation";
        title.textContent = "Reject Verified Application";
        desc.innerHTML = `You are about to reject <strong>${expertName}</strong>'s application. Proceed?`;
        actionBtn.textContent = "Yes, Reject";
        actionBtn.classList.add('confirm-btn-warning');
    }

    actionBtn.href = targetUrl;
    modal.classList.add('active');
}

function openLogoutModal() {
    const modal = document.getElementById('actionConfirmModal');
    const iconFrame = document.getElementById('confirmIconFrame');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const desc = document.getElementById('confirmDesc');
    const actionBtn = document.getElementById('confirmActionBtn');

    iconFrame.className = "confirm-icon-frame confirm-icon-logout";
    icon.className = "fa-solid fa-door-closed";
    title.textContent = "Confirm Logout";
    desc.textContent = "Are you sure you want to log out of your session?";
    actionBtn.textContent = "Yes, Logout";
    actionBtn.className = "confirm-btn-action confirm-btn-danger";

    actionBtn.href = "../auth/login.php";
    modal.classList.add('active');
}

function closeConfirmModal() { document.getElementById('actionConfirmModal').classList.remove('active'); }

document.getElementById('actionConfirmModal').addEventListener('click', function(e) { if (e.target === this) closeConfirmModal(); });

function openCredentialsModal(docList, expertName, experienceText, certificationText, expertId, expertStatus) {
    const modal = document.getElementById('credentialsModal');
    const title = document.getElementById('credModalTitle');
    const typeField = document.getElementById('credTypeField');
    const expText = document.getElementById('credExpText');
    const gridContainer = document.getElementById('docGridContainer');
    const noDocText = document.getElementById('noDocText');
    
    const actionContainer = document.getElementById('credActionContainer');
    const verifySection = document.getElementById('verificationSection');
    const btnApprove = document.getElementById('credApproveBtn');
    const btnReject = document.getElementById('credRejectBtn');
    const chkResume = document.getElementById('checkResume');
    const chkCert = document.getElementById('checkCert');

    chkResume.checked = false;
    chkCert.checked = false;
    btnApprove.disabled = true;
    btnReject.disabled = true;

    title.textContent = "Credentials - " + expertName;
    expText.textContent = experienceText || 'No experience provided';
    
    let cleanType = certificationText || '';
    if (cleanType.startsWith('Type: ')) cleanType = cleanType.substring(6);
    typeField.textContent = cleanType || 'Not specified';

    gridContainer.innerHTML = '';

    if (docList && docList.length > 0) {
        noDocText.style.display = 'none';
        docList.forEach(function(doc, index) {
            let docUrl = doc;
            let isAbsolute = docUrl.indexOf('http') === 0 || docUrl.indexOf('../') === 0 || docUrl.indexOf('/') === 0;
            if (docUrl.indexOf('uploads/') === 0) { docUrl = '../' + docUrl; isAbsolute = true; }
            if (!isAbsolute) { docUrl = '../uploads/nutritionists/' + docUrl; }

            const ext = docUrl.split('.').pop().toLowerCase();
            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            
            const item = document.createElement('div');
            item.className = 'doc-item';
            
            if (ext === 'pdf') {
                item.innerHTML = `<div class="pdf-icon"><i class="fa-solid fa-file-pdf"></i></div><div class="doc-name">Doc ${index + 1}</div>`;
            } else if (imageExts.includes(ext)) {
                item.innerHTML = `<img src="${encodeURI(docUrl)}" alt="Doc" onerror="this.src='https://via.placeholder.com/120x90?text=No+Image';"><div class="doc-name">Img ${index + 1}</div>`;
            } else {
                item.innerHTML = `<div class="pdf-icon" style="color:#475569;"><i class="fa-solid fa-file-word"></i></div><div class="doc-name">File ${index + 1}</div>`;
            }

            item.onclick = function() {
                const iframe = document.getElementById('previewIframe');
                const image = document.getElementById('previewImage');
                const noPreview = document.getElementById('noPreviewMessage');
                const dlBtn = document.getElementById('downloadFallbackBtn');
                
                iframe.style.display = 'none';
                image.style.display = 'none';
                noPreview.style.display = 'none';

                if (ext === 'pdf') { iframe.src = encodeURI(docUrl); iframe.style.display = 'block'; }
                else if (imageExts.includes(ext)) { image.src = encodeURI(docUrl); image.style.display = 'block'; }
                else { dlBtn.href = encodeURI(docUrl); noPreview.style.display = 'flex'; }
                document.getElementById('filePreviewModal').classList.add('active');
            };

            gridContainer.appendChild(item);
        });
    } else {
        noDocText.style.display = 'block';
    }

    if (expertStatus === 'pending') {
        actionContainer.style.display = 'flex';
        verifySection.style.display = 'block';
        
        const checkLogic = function() {
            if (chkResume.checked && chkCert.checked) {
                btnApprove.disabled = false;
                btnReject.disabled = false;
            } else {
                btnApprove.disabled = true;
                btnReject.disabled = true;
            }
        };

        chkResume.onchange = checkLogic;
        chkCert.onchange = checkLogic;

        btnApprove.onclick = function() {
            closeCredentialsModal();
            openConfirmModal('approve_verified', 'manage_account.php?approve_id=' + expertId, expertName);
        };
        btnReject.onclick = function() {
            closeCredentialsModal();
            openConfirmModal('reject_verified', 'manage_account.php?reject_id=' + expertId, expertName);
        };
    } else {
        actionContainer.style.display = 'none';
        verifySection.style.display = 'none';
    }

    modal.classList.add('active');
}

function closeCredentialsModal() { document.getElementById('credentialsModal').classList.remove('active'); }

document.getElementById('credentialsModal').addEventListener('click', function(e) { if (e.target === this) closeCredentialsModal(); });
</script>

</body>
</html>