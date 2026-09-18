<?php
session_start();
require_once '../config.php';

// Security Check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: manage_account.php");
    exit();
}

// Fetch current data
$stmt = $conn->prepare("SELECT * FROM nutritionist WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) exit("Expert not found.");

// Handle Update
if(isset($_POST['update'])){
    $name = $_POST['fullname'];
    $email = $_POST['email'];
    $exp = $_POST['experience'];
    
    // Check if password is being changed
    if (!empty($_POST['new_password'])) {
        $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE nutritionist SET fullname=?, email=?, experience=?, password=? WHERE id=?");
        $stmt->bind_param("ssisi", $name, $email, $exp, $new_pass, $id);
    } else {
        $stmt = $conn->prepare("UPDATE nutritionist SET fullname=?, email=?, experience=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $email, $exp, $id);
    }

    if($stmt->execute()) {
        header("Location: manage_account.php?msg=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Expert | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 40px; color: #1e293b; }
        .form-card { 
            background: #fff; 
            max-width: 500px; 
            margin: auto; 
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
        }
        .header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
        .header i { font-size: 24px; color: #2563eb; background: #eff6ff; padding: 12px; border-radius: 12px; }
        
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #64748b; }
        input { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 20px; 
            border: 1px solid #e2e8f0; 
            border-radius: 10px; 
            box-sizing: border-box; 
            font-family: inherit;
        }
        input:focus { outline: 2px solid #2563eb; border-color: transparent; }

        .btn-update { 
            background: #2563eb; 
            color: white; 
            border: none; 
            padding: 14px; 
            width: 100%; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: 700; 
            transition: 0.3s;
        }
        .btn-update:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-cancel { 
            display: block; 
            text-align: center; 
            margin-top: 15px; 
            color: #94a3b8; 
            text-decoration: none; 
            font-size: 14px; 
        }
        .pass-hint { font-size: 12px; color: #94a3b8; margin-top: -15px; margin-bottom: 20px; display: block; }
    </style>
</head>
<body>

    <div class="form-card">
        <div class="header">
            <i class="fa-solid fa-user-pen"></i>
            <div>
                <h2 style="margin:0;">Edit Expert</h2>
                <p style="margin:0; font-size:14px; color:#64748b;">Update account for ID #<?= $id ?></p>
            </div>
        </div>

        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>

            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <label>Experience (Years)</label>
            <input type="number" name="experience" value="<?= htmlspecialchars($user['experience']) ?>" required>

            <label>New Password</label>
            <input type="password" name="new_password" placeholder="••••••••">
            <small class="pass-hint">Leave blank to keep current password.</small>

            <button type="submit" name="update" class="btn-update">Save Changes</button>
            <a href="manage_account.php" class="btn-cancel">Go Back</a>
        </form>
    </div>

</body>
</html>