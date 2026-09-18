<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_GET['id'])){
    header("Location: meals.php");
    exit();
}

$id = intval($_GET['id']);
$res = $conn->query("SELECT * FROM meal_plans WHERE id = $id");
$meal = $res->fetch_assoc();

if (isset($_POST['update_meal'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $goal = $_POST['goal'];
    $calories = $_POST['calories'];
    
    // Keep old photo by default
    $photo_path = $meal['photo'];

    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "../uploads/meals/";
        $file_name = time() . "_" . basename($_FILES["photo"]["name"]);
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $photo_path = "uploads/meals/" . $file_name;
        }
    }

    $stmt = $conn->prepare("UPDATE meal_plans SET title=?, description=?, goal=?, calories=?, photo=? WHERE id=?");
    $stmt->bind_param("ssdisi", $title, $description, $goal, $calories, $photo_path, $id);
    
    if ($stmt->execute()) {
        header("Location: meals.php?msg=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Meal | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 40px; }
        .form-card { background: #fff; max-width: 500px; margin: auto; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .btn { background: #2563eb; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 14px; }
        .current-img { width: 100px; height: 60px; object-fit: cover; border-radius: 5px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Edit Meal Plan</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Meal Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($meal['title']) ?>" required>
            
            <label>Goal</label>
            <select name="goal">
                <option value="Weight Loss" <?= $meal['goal'] == 'Weight Loss' ? 'selected' : '' ?>>Weight Loss</option>
                <option value="Muscle Building" <?= $meal['goal'] == 'Muscle Building' ? 'selected' : '' ?>>Muscle Building</option>
                <option value="Balanced" <?= $meal['goal'] == 'Balanced' ? 'selected' : '' ?>>Balanced</option>
            </select>

            <label>Calories</label>
            <input type="number" name="calories" value="<?= $meal['calories'] ?>" required>

            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($meal['description']) ?></textarea>

            <label>Current Photo</label><br>
            <img src="../<?= $meal['photo'] ?>" class="current-img"><br>
            <input type="file" name="photo" accept="image/*">

            <button type="submit" name="update_meal" class="btn">Update Meal Plan</button>
            <a href="meals.php" class="btn-back">Cancel</a>
        </form>
    </div>
</body>
</html>