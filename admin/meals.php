<?php
session_start();
require_once '../config.php';

// Security Check: Only allow logged-in admins
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all meals from your 'meal_plan' table
// Note: Changed 'meal_plans' to 'meal_plan' to match your database screenshot
$sql = "SELECT * FROM meal_plans ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Meals | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .meal-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .btn { padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; display: inline-block; }
        .btn-back { color: #2563eb; display: flex; align-items: center; gap: 5px; text-decoration: none; font-weight: 600; }
        .btn-edit { background: #e0e7ff; color: #4338ca; margin-right: 5px; }
        .btn-edit:hover { background: #c7d2fe; }
        .btn-delete { background: #fee2e2; color: #b91c1c; }
        .btn-delete:hover { background: #fecaca; }
        .goal-tag { background: #eff6ff; color: #1e40af; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        /* Style for the container between header and table */
        .btnadd_meal { display: flex; justify-content: flex-end; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-flex">
        <h1><i class="fa-solid fa-utensils"></i> Manage Meal Plans</h1>
        <div>
            <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="btnadd_meal">
        <a href="add_meal.php" class="btn" style="background:#22c55e; color:white;">+ Add New Meal</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Meal Title</th>
                    <th>Goal</th>
                    <th>Calories</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if(!empty($row['photo'])): ?>
                                <img src="../<?= htmlspecialchars($row['photo']) ?>" class="meal-img" alt="meal">
                            <?php else: ?>
                                <div class="meal-img" style="background:#e2e8f0; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-image text-slate-400"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                        <td><span class="goal-tag"><?= htmlspecialchars($row['goal']) ?></span></td>
                        <td><strong><?= number_format($row['calories']) ?></strong> kcal</td>
                        <td style="max-width: 250px; color: #64748b; font-size: 13px;">
                            <?= htmlspecialchars(substr($row['description'], 0, 60)) ?>...
                        </td>
                        <td>
                            <div style="display: flex;">
                                <a href="edit_meal.php?id=<?= $row['id'] ?>" class="btn btn-edit">Edit</a>
                                <a href="delete_meal.php?id=<?= $row['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this meal plan?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">No meal plans found in the database.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>