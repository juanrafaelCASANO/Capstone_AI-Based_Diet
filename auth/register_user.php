<?php
// register.php
session_start();
require_once '../config.php'; // Database connection

    $errors = [];
    $success = "";

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
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Email already registered. Please sign in.";
            }
            $stmt->close();
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

        $stmt->bind_param(
        "sssssssssssss",
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
    );


            if ($stmt->execute()) {
                $success = "Registration successful! You can now <a href='login.php'>Sign In</a>.";
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
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
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

        <!-- Google Identity Services -->
        <script src="https://accounts.google.com/gsi/client" async defer></script>

<style>
    * { 
        box-sizing:border-box; 
        margin:0; 
        padding:0; 
    }
    body { 
        font-family:'Inter',sans-serif; 
        background:#f4f7ff; 
        color:#0f172a; 
    }
    a { 
        text-decoration:none; 
        color:#2563eb; 
    }
    .header {
        padding:25px 60px;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    .logo {
        font-size:22px;
        font-weight:800;
        color:#2563eb;
    }
    .back {
        font-size:16px;
        color:#2563eb;
    }
    .container { 
        max-width:500px; 
        margin:auto; 
        background:white; 
        padding:30px; 
        border-radius:12px; 
        box-shadow:0 4px 12px rgba(0,0,0,.05); 
    }
    h2 { 
        margin-bottom:20px; 
        color:#2563eb; 
        text-align:center; 
    }
    input, select, textarea {
        width:100%; 
        padding:12px; 
        margin:8px 0;
        border-radius:8px; 
        border:1px solid #ccc;
    }
    textarea { 
        resize:none; 
    }
    button { 
        width:100%; 
        background:#2563eb; 
        color:white; 
        padding:14px; 
        border:none; 
        border-radius:8px; 
        font-weight:600; 
        cursor:pointer; 
        margin-top:10px; 
    }
    .error { 
        background:#fee2e2; 
        padding:10px; 
        margin-bottom:15px; 
        border-left:4px solid #dc2626; 
    }
    .success { 
        background:#d1fae5; 
        padding:10px; 
        margin-bottom:15px; 
        border-left:4px solid #22c55e; 
    }
    .top-bar { 
        padding-bottom:20px; 
        }
</style>
</head>
<body>

<div class="header">
        <div class="logo">AI Diet Planner</div>
        <a href="../index.php" class="back">← Back</a>
    </div>


<div class="container">
    <h2>Create Your Profile</h2>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err) echo "• $err<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <input type="text" name="phone" placeholder="Phone Number (11 digits)" required>

        <input type="number" name="age" placeholder="Age" required>

        <select name="gender" required>
            <option value="">Gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>

        <input type="number" name="height" placeholder="Height (cm)" required>
        <input type="number" name="weight" placeholder="Weight (kg)" required>

        <select name="goal" required>
            <option value="">Goal</option>
            <option value="lose">Lose Weight</option>
            <option value="maintain">Maintain</option>
            <option value="gain">Gain Muscle</option>
        </select>

        <select name="activity" required>
            <option value="">Activity Level</option>
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="high">High</option>
        </select>

        <input type="text" name="diet_type" placeholder="Diet Type (e.g. Filipino, Vegetarian)">
        <textarea name="allergies" placeholder="Food Allergies (optional)"></textarea>

        <button type="submit">Create Account</button>
    </form>

    <p style="text-align:center; margin-top:15px;">
        Already have an account? <a href="login.php">Sign In</a>
    </p>
</div>

</body>
</html>
