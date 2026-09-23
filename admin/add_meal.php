<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

if (isset($_POST['add_meal'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $goal = $_POST['goal'];
    $calories = $_POST['calories'];
    
    $photo_path = "";
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "../uploads/meals/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_name = time() . "_" . basename($_FILES["photo"]["name"]);
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $photo_path = "uploads/meals/" . $file_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO meal_plans (title, description, goal, calories, photo) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$title, $description, $goal, $calories, $photo_path])) {
        header("Location: meals.php?msg=added");
        exit();
    } else {
        $message = "Error occurred while adding meal.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Meal | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 40px; }
        .form-card { background: #fff; max-width: 500px; margin: auto; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .btn { background: #2563eb; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Add New Meal Plan</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Meal Title</label>
            <input type="text" name="title" required placeholder="e.g. Keto Breakfast">
            <label>Goal</label>
            <select name="goal">
                <option value="Weight Loss">Weight Loss</option>
                <option value="Muscle Building">Muscle Building</option>
                <option value="Balanced">Balanced</option>
            </select>
            <label>Calories</label>
            <input type="number" name="calories" required>
            <label>Description</label>
            <textarea name="description" rows="4"></textarea>
            <label>Meal Photo</label>
            <input type="file" name="photo" accept="image/*">
            <button type="submit" name="add_meal" class="btn">Save Meal Plan</button>
            <a href="meals.php" class="btn-back">Cancel</a>
        </form>
    </div>
</body>
</html>