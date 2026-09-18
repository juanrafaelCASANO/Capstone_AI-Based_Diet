<?php /* about.php */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f4f7ff; 
            color: #0f172a; 
            overflow-x: hidden; 
        }

        /* --- FIXED NAVBAR (Matches Index) --- */
        .navbar { 
            background: #fff; 
            padding: 15px 50px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,.05); 
            position: fixed; 
            top: 0; 
            left: 0;
            right: 0;
            width: 100%;
            z-index: 9999; 
        }

        .logo { 
            display: flex; 
            align-items: center; 
            font-weight: 800; 
            font-size: 22px; 
            color: #2563eb; 
        }
        
        .logo span { margin-left: 10px; }

        .nav-links a { 
            margin-left: 25px; 
            font-weight: 500; 
            color: #334155; 
            text-decoration: none;
        }

        .btn-primary { 
            background: #2563eb; 
            color: #fff; 
            padding: 10px 20px; 
            border-radius: 10px; 
            font-weight: 600; 
            display: inline-block; 
            border: none; 
        }

        /* --- CONTAINER ADJUSTMENT --- */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            /* Extra padding at top so content starts below the fixed navbar */
            padding: 120px 20px 60px 20px; 
        }

        /* --- CONTENT STYLES --- */
        .about-card {
            background: #eef2ff;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
        }
        .about-card img { width: 90px; margin-bottom: 20px; }
        .about-card h1 { font-size: 40px; font-weight: 800; margin-bottom: 10px; }
        .about-card h2 { font-size: 20px; color: #2563eb; margin-bottom: 20px; }
        .about-card p {
            font-size: 17px;
            color: #475569;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .section { margin-top: 80px; text-align: center; }
        .section-icon { font-size: 42px; margin-bottom: 15px; }
        .section h3 { font-size: 28px; margin-bottom: 20px; color: #1e293b; }
        .section p {
            font-size: 17px;
            color: #475569;
            max-width: 800px;
            margin: auto;
            line-height: 1.7;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .feature-card {
            background: #fff;
            padding: 35px 25px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,.05);
            text-align: center;
        }
        .feature-card h4 { margin-top: 15px; font-size: 18px; color: #1e293b; }
        .feature-card p { margin-top: 10px; color: #64748b; font-size: 15px; line-height: 1.6; }

        @media(max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .nav-links a:not(.btn-primary) { display: none; } /* Simplified for mobile */
            .about-card h1 { font-size: 32px; }
            .container { padding-top: 100px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">🥗 <span>AI Diet Planner</span></div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="browse.php">Browse</a>
        <a href="contact.php">Contact Us</a>
        <a href="auth/login.php">Sign In</a>
        <a href="auth/register.php" class="btn-primary">Get Started</a>
    </div>
</div>

<div class="container">

    <div class="about-card">
        <img src="https://cdn-icons-png.flaticon.com/512/706/706164.png" alt="AI Diet Planner">
        <h1>AI Diet Planner</h1>
        <h2>Nutrition Powered by Intelligence</h2>
        <p>
            A smart nutrition platform that connects users with personalized
            AI-generated meal plans and certified nutritionists to support
            healthier lifestyles through technology.
        </p>
    </div>

    <div class="section">
        <div class="section-icon">🎯</div>
        <h3>Our Mission</h3>
        <p>
            To empower individuals to achieve better health by providing
            personalized, accessible, and data-driven nutrition solutions
            through artificial intelligence and professional dietary guidance.
        </p>
    </div>

    <div class="section">
        <div class="section-icon">⚙️</div>
        <h3>Core Features</h3>

        <div class="features">
            <div class="feature-card">
                <div style="font-size: 30px;">🍽</div>
                <h4>Personalized Meal Plans</h4>
                <p>AI-generated meal suggestions based on calorie requirements, user goals, and nutritional balance.</p>
            </div>

            <div class="feature-card">
                <div style="font-size: 30px;">💬</div>
                <h4>Nutritionist Consultation</h4>
                <p>Users can communicate with nutritionists via a real-time chat system for professional guidance.</p>
            </div>

            <div class="feature-card">
                <div style="font-size: 30px;">🔐</div>
                <h4>Secure Role-Based Access</h4>
                <p>Role-based authentication ensures privacy, data protection, and controlled system access.</p>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-icon">🌍</div>
        <h3>Our Vision</h3>
        <p>
            To become a trusted digital nutrition companion that bridges
            technology and healthcare, making healthy living accessible
            anytime and anywhere.
        </p>
    </div>

</div>

</body>
</html>