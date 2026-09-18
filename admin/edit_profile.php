<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'nutritionist'){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Check if profile exists
$check = $conn->prepare("SELECT * FROM nutritionist_profile WHERE nutritionist_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$current = $check->get_result()->fetch_assoc();

if(isset($_POST['save_profile'])){
    $name = $_POST['name'];
    $specialty = $_POST['specialty'];
    $price = $_POST['price'];
    $photo_path = $current['photo'] ?? 'uploads/nutritionists/default.png';

    // Handle Photo Upload
    if(!empty($_FILES['photo']['name'])){
        $target_dir = "../uploads/nutritionists/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_name = time() . "_" . basename($_FILES["photo"]["name"]);
        if(move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir . $file_name)){
            $photo_path = "uploads/nutritionists/" . $file_name;
        }
    }

    if($current){
        // UPDATE existing profile
        $stmt = $conn->prepare("UPDATE nutritionist_profile SET name=?, specialty=?, price=?, photo=? WHERE nutritionist_id=?");
        $stmt->bind_param("ssisi", $name, $specialty, $price, $photo_path, $user_id);
    } else {
        // INSERT new profile
        $stmt = $conn->prepare("INSERT INTO nutritionist_profile (nutritionist_id, name, specialty, price, photo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $user_id, $name, $specialty, $price, $photo_path);
    }

    if($stmt->execute()){
        header("Location: dashboard.php?updated=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | AI Diet Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 40px; }
        .form-card { background: #fff; max-width: 500px; margin: auto; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        input, select { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .btn-save { background: #2563eb; color: white; border: none; padding: 14px; width: 100%; border-radius: 8px; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Edit Public Profile</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Display Name (As shown to clients)</label>
            <input type="text" name="name" value="<?= $current['name'] ?? '' ?>" required>

            <label>Specialty</label>
            <input type="text" name="specialty" value="<?= $current['specialty'] ?? '' ?>" placeholder="e.g., Clinical Nutrition" required>

            <label>Consultation Price (₱)</label>
            <input type="number" name="price" value="<?= $current['price'] ?? '' ?>" required>

            <label>Profile Photo</label>
            <input type="file" name="photo" accept="image/*">

            <button type="submit" name="save_profile" class="btn-save">Update Landing Page Profile</button>
            <p style="text-align:center;"><a href="dashboard.php" style="color:#64748b; text-decoration:none;">Cancel</a></p>
        </form>
    </div>
</body>
</html>