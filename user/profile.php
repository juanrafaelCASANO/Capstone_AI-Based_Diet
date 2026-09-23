<?php

session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$message = "";
$error = "";


/* =========================================================
   FETCH USER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        fullname,
        email,
        phone,
        role,
        created_at,
        age,
        gender,
        height,
        weight,
        goal,
        activity,
        diet_type,
        allergies
    FROM users
    WHERE id = ?
");

// PDO: Execute with array instead of bind_param
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}


/* =========================================================
   UPDATE PROFILE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_profile'])) {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $goal = !empty($_POST['goal']) ? $_POST['goal'] : null;
    $activity = !empty($_POST['activity']) ? $_POST['activity'] : null;
    $diet_type = trim($_POST['diet_type'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');

    /* =====================================================
       VALIDATION
    ===================================================== */
    if ($fullname === '') {
        $error = "Full name is required.";
    } elseif ($email === '') {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($phone === '') {
        $error = "Phone number is required.";
    } elseif ($age !== null && ($age < 1 || $age > 120)) {
        $error = "Please enter a valid age.";
    } elseif ($height !== null && ($height <= 0 || $height > 300)) {
        $error = "Please enter a valid height.";
    } elseif ($weight !== null && ($weight <= 0 || $weight > 500)) {
        $error = "Please enter a valid weight.";
    }

    /* =====================================================
       CHECK DUPLICATE EMAIL
    ===================================================== */
    if ($error === '') {
        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
        ");

        // PDO execute
        $check->execute([$email, $user_id]);

        // PDO rowCount instead of num_rows
        if ($check->rowCount() > 0) {
            $error = "That email is already registered to another account.";
        }
    }

    /* =====================================================
       UPDATE DATABASE
    ===================================================== */
    if ($error === '') {
        $update = $conn->prepare("
            UPDATE users SET
                fullname = ?,
                email = ?,
                phone = ?,
                age = ?,
                gender = ?,
                height = ?,
                weight = ?,
                goal = ?,
                activity = ?,
                diet_type = ?,
                allergies = ?
            WHERE id = ?
        ");

        // PDO execute with array of parameters
        $updateSuccess = $update->execute([
            $fullname,
            $email,
            $phone,
            $age,
            $gender,
            $height,
            $weight,
            $goal,
            $activity,
            $diet_type,
            $allergies,
            $user_id
        ]);

        if ($updateSuccess) {
            $message = "Your profile has been updated successfully!";

            /* Fetch updated data */
            $stmt = $conn->prepare("
                SELECT
                    id,
                    fullname,
                    email,
                    phone,
                    role,
                    created_at,
                    age,
                    gender,
                    height,
                    weight,
                    goal,
                    activity,
                    diet_type,
                    allergies
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $error = "Something went wrong while updating your profile.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | AI Diet Planner</title>

<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f7ff; color: #111827; }

/* SIDEBAR */
.sidebar { width: 230px; height: 100vh; position: fixed; left: 0; top: 0; background: #1e3a8a; color: white; padding: 25px 15px; }
.sidebar h2 { margin: 0 10px 35px; font-size: 22px; }
.sidebar-title { font-size: 11px; opacity: .6; margin: 0 10px 10px; text-transform: uppercase; }
.sidebar a { display: flex; align-items: center; gap: 10px; color: white; text-decoration: none; margin: 5px 0; padding: 13px 15px; border-radius: 10px; transition: .2s; }
.sidebar a:hover { background: rgba(255,255,255,.15); transform: translateX(3px); }
.sidebar a.active { background: white; color: #1e3a8a; font-weight: bold; }
.sidebar-icon { font-size: 19px; }
.logout { position: absolute; bottom: 25px; left: 15px; right: 15px; }

/* MAIN */
.main { margin-left: 230px; padding: 35px; min-height: 100vh; }
.header { margin-bottom: 25px; }
.header h1 { margin: 0; font-size: 30px; }
.header p { color: #64748b; margin-top: 8px; }

/* PROFILE */
.profile-layout { display: grid; grid-template-columns: 280px 1fr; gap: 25px; max-width: 1100px; }
.profile-card { background: white; border-radius: 16px; padding: 30px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,.05); height: fit-content; }
.avatar { width: 100px; height: 100px; margin: 0 auto 20px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #60a5fa); display: flex; align-items: center; justify-content: center; color: white; font-size: 38px; font-weight: bold; }
.profile-card h2 { margin-bottom: 7px; font-size: 20px; }
.profile-card p { color: #64748b; font-size: 13px; word-break: break-word; }
.status { display: inline-block; margin-top: 15px; padding: 7px 13px; border-radius: 20px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: bold; }

/* FORM CARD */
.form-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,.05); }
.form-card h2 { margin-top: 0; margin-bottom: 5px; }
.form-description { color: #64748b; margin-bottom: 25px; font-size: 14px; }
.section { margin-bottom: 30px; }
.section-title { display: flex; align-items: center; gap: 8px; font-size: 17px; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }

/* FORM GRID */
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
.form-group { display: flex; flex-direction: column; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 13px; font-weight: bold; margin-bottom: 7px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 13px; border: 1px solid #d1d5db; border-radius: 9px; font-size: 14px; outline: none; font-family: inherit; transition: .2s; }
.form-group textarea { resize: vertical; min-height: 90px; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.form-actions { display: flex; justify-content: flex-end; padding-top: 10px; }
.save-btn { border: none; background: #2563eb; color: white; padding: 13px 24px; border-radius: 9px; font-weight: bold; cursor: pointer; transition: .2s; }
.save-btn:hover { background: #1d4ed8; transform: translateY(-2px); }

/* ALERTS */
.alert { padding: 13px 15px; border-radius: 9px; margin-bottom: 20px; font-size: 14px; }
.success { background: #dcfce7; color: #166534; }
.error { background: #fee2e2; color: #991b1b; }

/* ACCOUNT INFO */
.account-info { margin-top: 25px; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,.05); max-width: 1100px; }
.account-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
.account-box { padding: 17px; border-radius: 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
.account-box span { display: block; font-size: 12px; color: #64748b; margin-bottom: 6px; }
.account-box strong { font-size: 14px; }

/* RESPONSIVE */
@media(max-width: 850px) { .profile-layout { grid-template-columns: 1fr; } .account-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 650px) { .sidebar { width: 190px; } .main { margin-left: 190px; padding: 20px; } .form-grid { grid-template-columns: 1fr; } .form-group.full { grid-column: auto; } .account-grid { grid-template-columns: 1fr; } }
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🥗 AI Diet Planner</h2>
    <div class="sidebar-title">Main Menu</div>
    <a href="dashboard.php">
        <span class="sidebar-icon">🏠</span> Dashboard
    </a>
    <a href="generate_weekly.php">
        <span class="sidebar-icon">📅</span> Weekly Meal Plan
    </a>
    <a href="chat.php">
        <span class="sidebar-icon">💬</span> Nutritionist
    </a>
    <a href="profile.php" class="active">
        <span class="sidebar-icon">👤</span> Profile
    </a>
    <div class="logout">
        <a href="../index.php" onclick="return confirm('Are you sure you want to logout?');">
            <span class="sidebar-icon">🚪</span> Logout
        </a>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="header">
        <h1>My Profile 👤</h1>
        <p>Manage your personal information and nutrition preferences.</p>
    </div>

    <!-- ALERT -->
    <?php if ($message): ?>
        <div class="alert success">
            ✅ <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">
        <!-- LEFT PROFILE CARD -->
        <div class="profile-card">
            <div class="avatar">
                <?php
                $firstLetter = strtoupper(substr($user['fullname'] ?? 'U', 0, 1));
                echo htmlspecialchars($firstLetter);
                ?>
            </div>
            <h2><?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></h2>
            <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
            <span class="status">● Active Account</span>
        </div>

        <!-- EDIT FORM -->
        <div class="form-card">
            <h2>Edit Personal Information</h2>
            <p class="form-description">Update your information below. Changes will be saved to your account.</p>

            <form method="POST">
                <!-- PERSONAL INFORMATION -->
                <div class="section">
                    <div class="section-title">👤 Personal Information</div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Full Name</label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" maxlength="15" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" min="1" max="120" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php if (($user['gender'] ?? '') === 'male') echo 'selected'; ?>>Male</option>
                                <option value="female" <?php if (($user['gender'] ?? '') === 'female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- BODY INFORMATION -->
                <div class="section">
                    <div class="section-title">⚖️ Body Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" name="height" step="0.01" min="1" max="300" placeholder="Example: 170" value="<?php echo htmlspecialchars($user['height'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight" step="0.01" min="1" max="500" placeholder="Example: 70" value="<?php echo htmlspecialchars($user['weight'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- NUTRITION INFORMATION -->
                <div class="section">
                    <div class="section-title">🥗 Nutrition Preferences</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nutrition Goal</label>
                            <select name="goal">
                                <option value="">Select Goal</option>
                                <option value="lose" <?php if (($user['goal'] ?? '') === 'lose') echo 'selected'; ?>>Lose Weight</option>
                                <option value="maintain" <?php if (($user['goal'] ?? '') === 'maintain') echo 'selected'; ?>>Maintain Weight</option>
                                <option value="gain" <?php if (($user['goal'] ?? '') === 'gain') echo 'selected'; ?>>Gain Weight</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Activity Level</label>
                            <select name="activity">
                                <option value="">Select Activity</option>
                                <option value="low" <?php if (($user['activity'] ?? '') === 'low') echo 'selected'; ?>>Low</option>
                                <option value="moderate" <?php if (($user['activity'] ?? '') === 'moderate') echo 'selected'; ?>>Moderate</option>
                                <option value="high" <?php if (($user['activity'] ?? '') === 'high') echo 'selected'; ?>>High</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Diet Type</label>
                            <input type="text" name="diet_type" maxlength="50" placeholder="Example: Filipino, Vegetarian, Low Carb" value="<?php echo htmlspecialchars($user['diet_type'] ?? ''); ?>">
                        </div>
                        <div class="form-group full">
                            <label>Food Allergies</label>
                            <textarea name="allergies" placeholder="Example: Peanuts, shrimp, dairy. Type 'None' if you have no allergies."><?php echo htmlspecialchars($user['allergies'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- SAVE -->
                <div class="form-actions">
                    <button type="submit" name="update_profile" class="save-btn">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ACCOUNT INFORMATION -->
    <div class="account-info">
        <h2>📋 Account Information</h2>
        <div class="account-grid">
            <div class="account-box">
                <span>User ID</span>
                <strong>#<?php echo htmlspecialchars($user['id']); ?></strong>
            </div>
            <div class="account-box">
                <span>Account Role</span>
                <strong><?php echo htmlspecialchars(ucfirst($user['role'])); ?></strong>
            </div>
            <div class="account-box">
                <span>Account Created</span>
                <strong><?php echo htmlspecialchars($user['created_at']); ?></strong>
            </div>
        </div>
    </div>
</div>

</body>
</html>