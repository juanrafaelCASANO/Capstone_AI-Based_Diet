<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Registration Type | AI Diet Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
            text-decoration: none;
        }

        .back-link {
            font-size: 15px;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #1d4ed8;
        }

        .main-container {
            flex: 1;
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .title-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .title-section h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .title-section p {
            color: #64748b;
            font-size: 16px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            width: 100%;
        }

        .role-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 35px 25px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .role-card:hover {
            border-color: #2563eb;
            transform: translateY(-6px);
            box-shadow: 0 12px 24px -1px rgba(37, 99, 235, 0.15);
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            transition: background 0.3s, color 0.3s;
        }

        .role-card:hover .icon-box {
            background: #2563eb;
            color: #ffffff;
        }

        .role-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .role-card p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .btn-select {
            margin-top: auto;
            width: 100%;
            padding: 12px 20px;
            background: #f1f5f9;
            color: #334155;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s, color 0.3s;
        }

        .role-card:hover .btn-select {
            background: #2563eb;
            color: #ffffff;
        }

        .footer-text {
            margin-top: 35px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        .footer-text a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 20px;
            }

            .title-section h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <a href="../index.php" class="logo">🥗 AI Diet Planner</a>
        <a href="../index.php" class="back-link">← Back to Home</a>
    </header>

    <main class="main-container">
        <div class="title-section">
            <h1>Create Your Account</h1>
            <p>Please select your account type to proceed with registration</p>
        </div>

        <div class="cards-grid">
            <!-- User Registration Card -->
            <a href="register_user.php" class="role-card">
                <div class="icon-box">
                    <i class="fa-solid fa-user"></i>
                </div>
                <h3>Standard User</h3>
                <p>Get personalized AI dietary plans, tracking tools, and nutrition recommendations tailored to your goals.</p>
                <div class="btn-select">Register as User</div>
            </a>

            <!-- Nutritionist Registration Card -->
            <a href="register_nutritionist.php" class="role-card">
                <div class="icon-box">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <h3>Nutritionist / Expert</h3>
                <p>Join as a verified professional to provide expert guidance, review dietary plans, and assist clients.</p>
                <div class="btn-select">Register as Expert</div>
            </a>
        </div>

        <div class="footer-text">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </main>

</body>
</html>