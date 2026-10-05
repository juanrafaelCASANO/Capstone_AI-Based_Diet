<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Registration Type - AI Diet Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="theme-color" content="#071a12">
    
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root{
            --bg:#f5f8f6;
            --surface:#ffffff;
            --surface-2:#f0f6f2;
            --text:#102019;
            --muted:#65736d;
            --line:rgba(16,32,25,.09);
            --primary:#16a36a;
            --primary-dark:#08734a;
            --primary-soft:#e3f6ed;
            --dark:#071a12;
            --dark-2:#0d281d;
            --shadow:0 24px 70px rgba(7,26,18,.10);
            --shadow-sm:0 12px 35px rgba(7,26,18,.08);
            --radius:28px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            line-height: 1.5;
        }

        a { text-decoration: none; color: inherit; }

        /* Ambient background */
        body::before {
            content: "";
            position: fixed;
            width: 520px; height: 520px;
            top: -230px; right: -180px;
            background: rgba(22,163,106,.10);
            filter: blur(20px);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
        }
        
        body::after {
            content: "";
            position: fixed;
            width: 400px; height: 400px;
            bottom: -150px; left: -150px;
            background: rgba(22,163,106,.07);
            filter: blur(20px);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
        }

        /* HEADER (Simplified Navbar) */
        .header {
            padding: 24px clamp(20px, 5vw, 60px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            font-family: 'Manrope', sans-serif;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -.4px;
        }
        
        .logo-mark {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            border-radius: 13px;
            background: linear-gradient(145deg,#20bd7a,#078452);
            box-shadow: 0 9px 22px rgba(22,163,106,.24);
            font-size: 21px;
        }
        
        .logo span { color: var(--dark); }

        .back-link {
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            transition: .25s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-4px);
        }

        /* MAIN CONTAINER */
        .wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px clamp(15px, 4vw, 40px) 40px;
        }

        .registration-container {
            width: 100%;
            max-width: 900px;
            animation: slideUp 0.6s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .title-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .title-section h1 {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(28px, 4vw, 36px);
            letter-spacing: -1px;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .title-section p {
            color: var(--muted);
            font-size: 16px;
        }

        /* CARDS GRID */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            width: 100%;
            margin-bottom: 35px;
        }

        .role-card {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255,255,255,.9);
            border-radius: var(--radius);
            padding: 40px 30px;
            text-align: center;
            backdrop-filter: blur(15px);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .role-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: var(--radius);
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
            pointer-events: none;
        }

        .role-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow);
        }
        
        .role-card:hover::before {
            border-color: rgba(22, 163, 106, 0.3);
        }

        .icon-box {
            width: 75px;
            height: 75px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-radius: 20px;
            display: grid;
            place-items: center;
            font-size: 32px;
            margin-bottom: 24px;
            transition: transform 0.3s ease, background 0.3s ease;
        }

        .role-card:hover .icon-box {
            transform: scale(1.05);
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(22, 163, 106, 0.25);
        }

        .role-card h3 {
            font-family: 'Manrope', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 12px;
        }

        .role-card p {
            font-size: 14.5px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 30px;
            flex-grow: 1;
        }

        .btn-select {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 48px;
            padding: 0 20px;
            background: var(--surface-2);
            color: var(--dark-2);
            border: 1px solid var(--line);
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        .role-card:hover .btn-select {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-color: rgba(22, 163, 106, 0.2);
        }

        /* FOOTER LINK */
        .footer-text {
            text-align: center;
            color: var(--muted);
            font-size: 15px;
        }

        .footer-text a {
            color: var(--primary-dark);
            font-weight: 700;
            transition: .25s ease;
        }

        .footer-text a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .role-card {
                padding: 30px 20px;
                border-radius: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .header { padding: 20px 15px; }
            .logo { font-size: 16px; }
            .logo-mark { width: 36px; height: 36px; font-size: 19px; border-radius: 11px; }
            .title-section h1 { font-size: 26px; }
            .wrapper { padding: 10px 15px 30px; }
        }
    </style>
</head>
<body>

    <header class="header">
        <a href="../index.php" class="logo" aria-label="AI Diet Planner home">
            <span class="logo-mark">🥗</span>
            <span>AI Diet Planner</span>
        </a>
        <a href="../index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </header>

    <main class="wrapper">
        <div class="registration-container">
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
                    <p>Get personalized AI dietary plans, tracking tools, and nutrition recommendations tailored to your unique goals.</p>
                    <div class="btn-select">Register as User <span>→</span></div>
                </a>

                <!-- Nutritionist Registration Card -->
                <a href="register_nutritionist.php" class="role-card">
                    <div class="icon-box">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <h3>Nutritionist / Expert</h3>
                    <p>Join as a verified professional to provide expert guidance, review dietary plans, and assist clients on their journey.</p>
                    <div class="btn-select">Register as Expert <span>→</span></div>
                </a>
            </div>

            <div class="footer-text">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </main>

</body>
</html>