<?php
session_start();
require_once '../config.php';

// Security Check: Only Admin
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

// Corrected Query: 
// 1. Table 'nutritionists' handles accounts (fullname, email)
// 2. Table 'nutritionists_profile' handles display info (specialty, price)
$sql = "SELECT n.id, n.fullname, n.email, p.specialty, p.price 
        FROM nutritionist n 
        LEFT JOIN nutritionists_profile p ON n.id = p.id 
        ORDER BY n.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Experts | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7ff; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #64748b; font-size: 13px; text-transform: uppercase; letter-spacing: 0.025em; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .btn-back { text-decoration: none; color: #2563eb; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .status-tag { background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .price-text { color: #2563eb; font-weight: 700; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fa-solid fa-user-doctor" style="color: #2563eb;"></i> Registered Experts</h1>
        <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Specialty</th>
                    <th>Session Rate</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <?php if(!empty($row['specialty'])): ?>
                                <span style="color: #475569;"><?= htmlspecialchars($row['specialty']) ?></span>
                            <?php else: ?>
                                <span style="color: #cbd5e1; font-style: italic;">Profile not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="price-text">
                                <?= $row['price'] ? '₱' . number_format($row['price']) : 'N/A' ?>
                            </span>
                        </td>
                        <td><span class="status-tag">Active</span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 40px; color: #64748b;">
                            <i class="fa-solid fa-inbox" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                            No nutritionists found in database.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>