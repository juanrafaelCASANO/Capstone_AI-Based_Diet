<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') exit();

$errors = [];
$success = "";

// --- HANDLE ADD EXPERT (with file uploads & registration logic) ---
if(isset($_POST['add_expert'])){
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $certification = trim($_POST['certification']);
    $experience = trim($_POST['experience']);

    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($_FILES['profile_pic']['name'])) $errors[] = "Profile picture is required.";
    if (empty($_FILES['document']['name'])) $errors[] = "Verification document is required.";

    if (empty($errors)) {
        $doc_dir = "../uploads/nutritionists/";
        $pic_dir = "../uploads/profile_pics/";

        if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
        if (!is_dir($pic_dir)) mkdir($pic_dir, 0755, true);

        $doc_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["document"]["name"]));
        $pic_filename = time() . "_pic_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["profile_pic"]["name"]));

        if (move_uploaded_file($_FILES["document"]["tmp_name"], $doc_dir . $doc_filename) && 
            move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $pic_dir . $pic_filename)) {
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // MySQLi Prepared Statement with extended nutritionist fields
            $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, profile_pic, email, phone, password, certification, experience, document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            $stmt->bind_param("ssssssss", $fullname, $pic_filename, $email, $phone, $hashed_password, $certification, $experience, $doc_filename);
            
            if($stmt->execute()){
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

// --- LOGIC: HANDLE DELETE ---
if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM nutritionist WHERE id = $id");
    header("Location: manage_account.php?msg=deleted");
    exit();
}

$result = $conn->query("SELECT id, fullname, email, experience FROM nutritionist ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Experts | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 20px; color: #0f172a; }
        .container { max-width: 1100px; margin: auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        th { font-weight: 600; color: #475569; background: #f8fafc; }
        
        .btn-add { background: #2563eb; color: #fff; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-add:hover { background: #1d4ed8; }
        
        /* MODAL STYLES */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 20px; overflow-y: auto; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 620px; position: relative; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
        .close-btn { position: absolute; right: 20px; top: 20px; cursor: pointer; font-size: 20px; color: #64748b; background: none; border: none; }
        .close-btn:hover { color: #0f172a; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }

        input[type="text"], input[type="email"], input[type="password"], textarea { 
            width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; 
            font-family: 'Inter', sans-serif; font-size: 14px; color: #0f172a; 
        }
        input:focus, textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
        textarea { resize: vertical; min-height: 70px; }

        .file-input-wrapper input[type="file"] {
            width: 100%; padding: 8px; border: 1px dashed #cbd5e1; border-radius: 8px;
            background: #f8fafc; font-size: 13px; color: #64748b; cursor: pointer;
        }

        .password-box { position: relative; width: 100%; }
        .password-box input { padding-right: 40px; }
        .toggle-password { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 15px; }

        label { font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .error-alert { background: #fee2e2; color: #991b1b; padding: 12px 14px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; border-left: 4px solid #dc2626; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="dashboard.php" style="text-decoration:none; color:#2563eb; font-weight:600;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add New Expert</button>
    </div>

    <div class="card">
        <h3>Account Database (Nutritionists)</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Experience</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['experience']) ?></td>
                    <td>
                        <a href="edit_expert.php?id=<?= $row['id'] ?>" style="color:#2563eb; text-decoration:none; margin-right:10px;">Edit</a>
                        <a href="add_expert.php?delete_id=<?= $row['id'] ?>" style="color:#dc2626; text-decoration:none;" onclick="return confirm('Delete this account?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="modal" style="display: <?= !empty($errors) ? 'flex' : 'none' ?>;">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <h2 style="margin-top:0; font-size: 20px; font-weight: 700; color: #1e293b;">Add New Expert</h2>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 10px;">Register a new nutritionist with complete verification credentials.</p>
        
        <?php if (!empty($errors)): ?>
            <div class="error-alert">
                <?php foreach ($errors as $err) echo "• " . htmlspecialchars($err) . "<br>"; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Full Name</label>
                    <input type="text" name="fullname" required placeholder="Dr. Jane Doe">
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="jane@example.com">
                </div>

                <div class="form-group">
                    <label>Phone Number (11 digits)</label>
                    <input type="text" name="phone" placeholder="09123456789" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-box">
                        <input type="password" name="password" id="modal_password" required placeholder="••••••••">
                        <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('modal_password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-box">
                        <input type="password" name="confirm_password" id="modal_confirm_password" required placeholder="••••••••">
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
                    <label>Verification Document (PDF/Image)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="document" accept="image/*,.pdf" required>
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
                    <button type="submit" name="add_expert" class="btn-add" style="width:100%; justify-content:center; margin-top:10px;">Create Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() { document.getElementById('addModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('addModal').style.display = 'none'; }
    
    window.onclick = function(event) {
        let modal = document.getElementById('addModal');
        if (event.target == modal) { closeModal(); }
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
</script>

</body>
</html>