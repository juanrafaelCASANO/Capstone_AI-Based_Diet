<?php
// Register_user.php
session_start();
require_once '../config.php'; // Database connection

$errors = [];
$success = "";

$fullname = "";
$email = "";
$phone = "";
$age = "";
$gender = "";
$height = "";
$weight = "";
$goal = "";
$activity = "";
$diet_type = "";
$allergies = "";

// When form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // BASIC INFO
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);

    // PROFILE INFO (AI INPUT)
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $height = floatval($_POST['height']);
    $weight = floatval($_POST['weight']);
    $goal = $_POST['goal'];
    $activity = $_POST['activity'];
    $diet_type = trim($_POST['diet_type']);
    $allergies = trim($_POST['allergies']);

    // Validation
    if (empty($fullname)) $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Phone number must be exactly 11 digits.";
    }

    if ($age <= 0) $errors[] = "Valid age is required.";
    if ($height <= 0) $errors[] = "Valid height is required.";
    if ($weight <= 0) $errors[] = "Valid weight is required.";

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Email already registered. Please sign in.";
        }
    }

    // Insert into DB
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';

        $stmt = $conn->prepare("
            INSERT INTO users
            (fullname, email, phone, password, role, age, gender, height, weight, goal, activity, diet_type, allergies)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $fullname,
                $email,
                $phone,
                $hashed_password,
                $role,
                $age,
                $gender,
                $height,
                $weight,
                $goal,
                $activity,
                $diet_type,
                $allergies
            ]);

            $success = "Registration successful! You can now <a href='login.php'>Sign In</a>.";
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration | AI-Based Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #f4f7ff; 
            color: #0f172a; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { 
            text-decoration: none; 
            color: #2563eb; 
        }

        .header {
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 22px;
            font-weight: 800;
            color: #2563eb;
        }

        .back {
            font-size: 15px;
            font-weight: 600;
            color: #2563eb;
            transition: color 0.2s;
        }

        .back:hover {
            color: #1d4ed8;
        }

        .container { 
            max-width: 680px; 
            width: 100%;
            margin: 10px auto 40px auto; 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); 
        }

        .header-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .header-title h2 { 
            font-size: 26px;
            font-weight: 800; 
            color: #1e293b;
            margin-bottom: 6px;
        }

        .header-title p {
            color: #64748b;
            font-size: 14px;
        }

        .section-divider {
            grid-column: span 2;
            font-size: 14px;
            font-weight: 700;
            color: #2563eb;
            border-bottom: 2px solid #eff6ff;
            padding-bottom: 6px;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label { 
            font-weight: 600; 
            font-size: 13px; 
            color: #334155;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select,
        textarea { 
            width: 100%; 
            padding: 12px 14px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #0f172a;
            background-color: #ffffff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        textarea { 
            resize: vertical; 
            min-height: 80px;
        }

        /* Password Toggle Styling */
        .password-box {
            position: relative;
            width: 100%;
        }

        .password-box input {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            user-select: none;
        }

        .toggle-password:hover {
            color: #2563eb;
        }

        button[type="submit"] { 
            width: 100%; 
            background: #2563eb; 
            color: white; 
            padding: 14px; 
            border: none; 
            border-radius: 8px; 
            font-size: 15px;
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 10px; 
            transition: background 0.2s;
        }

        button[type="submit"]:hover {
            background: #1d4ed8;
        }

        .error { 
            background: #fee2e2; 
            color: #991b1b;
            padding: 12px 16px; 
            border-radius: 8px;
            margin-bottom: 20px; 
            border-left: 4px solid #dc2626; 
            font-size: 14px;
            line-height: 1.5;
        }

        .success { 
            background: #d1fae5; 
            color: #065f46;
            padding: 12px 16px; 
            border-radius: 8px;
            margin-bottom: 20px; 
            border-left: 4px solid #22c55e; 
            font-size: 14px;
        }

        .footer-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #64748b;
        }

        .footer-link a {
            font-weight: 600;
        }

        @media (max-width: 640px) {
            .header {
                padding: 20px;
            }

            .container {
                padding: 25px 20px;
                margin: 0 auto 20px auto;
                border-radius: 0;
                box-shadow: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width,
            .section-divider {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="../index.php" class="logo">🥗 AI Diet Planner</a>
    <a href="register.php" class="back">← Switch Account Type</a>
</div>

<div class="container">
    <div class="header-title">
        <h2>Create Your Profile</h2>
        <p>Set up your account and personal metrics for tailored AI meal planning</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err) echo "• " . htmlspecialchars($err) . "<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            
            <div class="section-divider">Account Credentials</div>

            <div class="form-group full-width">
                <label>Full Name</label>
                <input type="text" name="fullname" placeholder="John Doe" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label>Phone Number (11 digits)</label>
                <input type="text" name="phone" placeholder="09123456789" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-box">
                    <input type="password" name="password" id="password" placeholder="At least 6 characters" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="password-box">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility('confirm_password', this)"></i>
                </div>
            </div>

            <div class="section-divider">Physical & Health Profile</div>

            <div class="form-group">
                <label>Age</label>
                <input type="number" name="age" placeholder="e.g. 25" required>
            </div>

            <div class="form-group">
                <label>Gender</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>

            <div class="form-group">
                <label>Height (cm)</label>
                <input type="number" step="0.1" name="height" placeholder="e.g. 170" required>
            </div>

            <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" step="0.1" name="weight" placeholder="e.g. 65" required>
            </div>

            <div class="form-group">
                <label>Primary Fitness Goal</label>
                <select name="goal" required>
                    <option value="">Select Goal</option>
                    <option value="lose">Lose Weight</option>
                    <option value="maintain">Maintain Weight</option>
                    <option value="gain">Gain Muscle</option>
                </select>
            </div>

            <div class="form-group">
                <label>Daily Activity Level</label>
                <select name="activity" required>
                    <option value="">Select Activity</option>
                    <option value="low">Low (Sedentary)</option>
                    <option value="moderate">Moderate (Exercise 3-5 days/wk)</option>
                    <option value="high">High (Heavy exercise daily)</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label>Dietary Preference</label>
                <input type="text" name="diet_type" placeholder="e.g. Filipino, Vegetarian, Keto (Optional)">
            </div>

            <div class="form-group full-width">
                <label>Food Allergies or Restrictions</label>
                <textarea name="allergies" placeholder="List any food allergies (e.g. Peanuts, Shellfish, Lactose)..."></textarea>
            </div>

            <div class="form-group full-width">
                <button type="submit">Create Account</button>
            </div>

        </div>
    </form>

    <div class="footer-link">
        Already have an account? <a href="login.php">Sign In</a>
    </div>
</div>

<script>
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