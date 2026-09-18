<?php
session_start();
require_once 'config.php';

$id = $_GET['id'] ?? 0;

// ADDED 'profile_pic' to your SELECT query
$stmt = $conn->prepare("
    SELECT id, fullname, profile_pic, email, phone, certification, experience, document
    FROM nutritionist
    WHERE id = ? AND status = 'approved'
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Nutritionist not found.");
}

$n = $result->fetch_assoc();

// LOGIC FOR REVIEWS: Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $u_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    
    $ins = $conn->prepare("INSERT INTO nutritionist_reviews (nutritionist_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
    $ins->bind_param("iiis", $id, $u_id, $rating, $comment);
    $ins->execute();
    header("Location: nutritionist_profile.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

<style>
/* YOUR ORIGINAL STYLES */
body{ margin:0; font-family:'Inter',sans-serif; background:#f4f7ff; color:#0f172a; }
.header{ padding:25px 60px; display:flex; justify-content:space-between; align-items:center; }
.logo{ font-size:22px; font-weight:800; color:#2563eb; }
.back{ text-decoration:none; color:#2563eb; font-weight:600; }
.container{ max-width:1000px; margin:40px auto; padding:0 30px; }
.profile-card{ background:#fff; border-radius:28px; padding:40px; box-shadow:0 30px 70px rgba(0,0,0,.1); }
.profile-header{ display:flex; gap:30px; align-items:center; }
.avatar{ width:130px; height:130px; border-radius:50%; object-fit:cover; border:6px solid #e0e7ff; }
.name{ font-size:34px; font-weight:800; }
.specialty{ color:#2563eb; font-weight:600; margin:5px 0; }
.badge{ display:inline-block; margin-top:8px; padding:6px 14px; background:#e0e7ff; color:#2563eb; border-radius:20px; font-size:13px; font-weight:600; }
.section{ margin-top:40px; }
.section h3{ margin-bottom:15px; font-size:22px; }
.info-grid{ display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.info-box{ background:#f8fafc; padding:20px; border-radius:16px; }
.info-box span{ color:#64748b; font-size:14px; }
.info-box p{ margin-top:6px; font-weight:600; }
.btn-primary{ display:inline-block; margin-top:30px; background:#2563eb; color:#fff; padding:16px 28px; border-radius:18px; font-weight:700; text-decoration:none; border:none; cursor:pointer; }
.btn-outline{ display:inline-block; margin-left:15px; border:2px solid #2563eb; color:#2563eb; padding:14px 26px; border-radius:18px; font-weight:700; text-decoration:none; }

/* NEW REVIEW STYLES */
.review-item { background: #f8fafc; padding: 15px; border-radius: 12px; margin-bottom: 10px; }
.stars { color: #f59e0b; font-weight: bold; }
input, textarea, select { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: 1px solid #ddd; font-family: inherit; }

@media(max-width:700px){ .profile-header{flex-direction:column;text-align:center} .info-grid{grid-template-columns:1fr} }
</style>
</head>

<body>

<div class="header">
    <div class="logo">AI Diet Planner</div>
    <a href="browse.php?type=nutritionists" class="back">← Back to Browse</a>
</div>

<div class="container">
<div class="profile-card">

    <div class="profile-header">
        <?php 
            $img_path = !empty($n['profile_pic']) ? "uploads/profile_pics/".$n['profile_pic'] : "uploads/default_avatar.jpg";
        ?>
        <img src="<?= $img_path ?>" class="avatar">
        <div>
            <div class="name"><?= htmlspecialchars($n['fullname']) ?></div>
            <div class="specialty"><?= htmlspecialchars($n['certification']) ?></div>
            <div class="badge"><?= (int)$n['experience'] ?> years experience</div>
        </div>
    </div>

    <div class="section">
        <h3>About Nutritionist</h3>
        <p style="color:#475569;line-height:1.7">
            Professional nutritionist specializing in <?= htmlspecialchars($n['certification']) ?> with <?= (int)$n['experience'] ?> years experience.
        </p>
    </div>

    <div class="section">
        <h3>Client Reviews</h3>
        <?php
        $rev_sql = "SELECT r.*, u.fullname FROM nutritionist_reviews r JOIN users u ON r.user_id = u.id WHERE r.nutritionist_id = $id ORDER BY r.created_at DESC";
        $reviews = $conn->query($rev_sql);
        if($reviews->num_rows > 0):
            while($rev = $reviews->fetch_assoc()): ?>
                <div class="review-item">
                    <strong><?= htmlspecialchars($rev['fullname']) ?></strong> 
                    <span class="stars"><?= str_repeat('⭐', $rev['rating']) ?></span>
                    <p style="margin: 5px 0 0; color: #475569;"><?= htmlspecialchars($rev['review_text']) ?></p>
                </div>
            <?php endwhile;
        else: echo "<p style='color:#64748b'>No reviews yet.</p>"; endif; ?>
    </div>

    <?php if(isset($_SESSION['user_id'])): ?>
    <div class="section" style="background: #f1f5f9; padding: 20px; border-radius: 20px;">
        <h3>Leave a Review</h3>
        <form method="POST">
            <label>Rating:</label>
            <select name="rating">
                <option value="5">⭐⭐⭐⭐⭐ (Excellent)</option>
                <option value="4">⭐⭐⭐⭐ (Good)</option>
                <option value="3">⭐⭐⭐ (Average)</option>
                <option value="2">⭐⭐ (Poor)</option>
                <option value="1">⭐ (Terrible)</option>
            </select>
            <textarea name="comment" rows="3" placeholder="Write your feedback here..." required></textarea>
            <button type="submit" class="btn-primary" style="margin-top:10px;">Post Review</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="section">
        <h3>Contact Information</h3>
        <div class="info-grid">
            <div class="info-box">
                <span>Email</span>
                <p><?= htmlspecialchars($n['email']) ?></p>
            </div>
            <div class="info-box">
                <span>Phone</span>
                <p><?= htmlspecialchars($n['phone']) ?: 'Not provided' ?></p>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Credentials</h3>
        <?php if ($n['document']): ?>
            <a href="uploads/nutritionists/<?= htmlspecialchars($n['document']) ?>" target="_blank" class="btn-outline">View Certificate</a>
        <?php endif; ?>
    </div>

    <div class="section">
        <a href="auth/login.php" class="btn-primary">Chat with Nutritionist</a>
    </div>

</div>
</div>

</body>
</html>