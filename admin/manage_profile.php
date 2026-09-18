<?php
session_start();
require_once '../config.php';

// Security Check: Only Admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$result = $conn->query("SELECT * FROM nutritionists_profile ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Featured Nutritionists | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 20px; }
        .container { max-width: 1000px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        
        /* Button Styles */
        .actions-wrapper { display: flex; gap: 10px; }
        .btn-add { background: #10b981; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add:hover { background: #059669; }
        
        .btn-back { background: #64748b; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: #475569; }

        .btn-edit { color: #2563eb; text-decoration: none; margin-right: 10px; font-weight: 600; }
        .btn-delete { color: #dc2626; text-decoration: none; font-weight: 600; }
        .avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Featured Nutritionists</h1>
            <div class="actions-wrapper">
                <a href="dashboard.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <a href="edit_featured.php" class="btn-add">
                    <i class="fa-solid fa-plus"></i> Add Profile
                </a>
            </div>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Specialty</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><img src="../<?= $row['photo'] ?>" class="avatar" onerror="this.src='../uploads/default_avatar.jpg'"></td>
                            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['specialty']) ?></td>
                            <td>₱<?= number_format($row['price']) ?></td>
                            <td>
                                <a href="edit_featured.php?id=<?= $row['id'] ?>" class="btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                <a href="delete_profile.php?id=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Remove this from landing page?')"><i class="fa-solid fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 30px; color: #64748b;">No featured profiles found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>