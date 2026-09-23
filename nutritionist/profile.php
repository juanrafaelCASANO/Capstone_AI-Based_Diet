<?php
session_start();
require_once '../config.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$nutritionist_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM nutritionist WHERE id = ?");
$stmt->execute([$nutritionist_id]);
$profile = $stmt->fetch();

if (!$profile) {
    die("Profile not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <style>
        body { font-family: Arial; background: #f4f7ff; }
        .profile-card { background: white; padding: 25px; width: 500px; margin: 40px auto; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-card img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; }
        .profile-card h2 { margin-bottom: 10px; }
        .profile-card p { margin: 6px 0; }
        .status { font-weight: bold; color: <?= ($profile['status'] ?? '') === 'approved' ? 'green' : 'orange'; ?>; }
    </style>
</head>
<body>
<div class="profile-card">
    <center>
        <img src="../uploads/profile_pics/<?= htmlspecialchars($profile['profile_pic'] ?? 'default_profile.png'); ?>">
        <h2><?= htmlspecialchars($profile['fullname']); ?></h2>
        <p class="status"><?= ucfirst($profile['status'] ?? 'pending'); ?></p>
    </center>
    <p><strong>Email:</strong> <?= htmlspecialchars($profile['email']); ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($profile['phone'] ?? 'N/A'); ?></p>
    <p><strong>Experience:</strong> <?= htmlspecialchars($profile['experience'] ?? 'N/A'); ?></p>
    <p><strong>Certification:</strong> <?= htmlspecialchars($profile['certification'] ?? 'N/A'); ?></p>
    <p><strong>Joined:</strong> <?= date("F d, Y", strtotime($profile['created_at'])); ?></p>
    <br>
    <a href="edit_profile.php">✏️ Edit Profile</a>
</div>
</body>
</html>