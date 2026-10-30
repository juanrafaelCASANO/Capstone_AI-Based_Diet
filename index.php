<?php 
require_once 'config.php'; 
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:;");

// Fetch available nutritionists using PDO
$sql = "SELECT id, fullname, certification, experience, profile_pic FROM nutritionist WHERE status = 'active' OR status = 'approved' OR status IS NULL LIMIT 10";
$stmt = $conn->query($sql);
$nutritionists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get initials (e.g. "Juan Dela Cruz" -> "JC")
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --error-bg: #fff1f2;
            --error-text: #9f1239;
            --error-border: #fecdd3;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{
            font-family:'DM Sans',sans-serif;
            background:var(--bg);
            color:var(--text);
            overflow-x:hidden;
            line-height:1.5;
        }
        body.menu-open{overflow:hidden}
        a{text-decoration:none;color:inherit}
        button{font:inherit}
        img{max-width:100%;display:block}

        body::before{
            content:"";
            position:fixed;
            width:520px;height:520px;
            top:-230px;right:-180px;
            background:rgba(22,163,106,.10);
            filter:blur(20px);
            border-radius:50%;
            pointer-events:none;
            z-index:-1;
        }

        .page-shell {
            width: min(1440px, 100%);
            margin: auto;
        }

        .navbar{
            position:sticky;
            top:0;
            z-index:1000;
            min-height:76px;
            padding: 14px clamp(20px, 5vw, 72px);
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:24px;
            background:rgba(255,255,255,.78);
            border-bottom:1px solid rgba(7,26,18,.06);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            transition:box-shadow .3s ease,background .3s ease;
        }
        .navbar.scrolled{
            box-shadow:0 10px 35px rgba(7,26,18,.08);
            background:rgba(255,255,255,.94);
        }

        .logo{
            display:inline-flex;
            align-items:center;
            gap:11px;
            font-family:'Manrope',sans-serif;
            font-size:19px;
            font-weight:800;
            letter-spacing:-.4px;
            white-space:nowrap;
        }
        .logo-mark{
            width:40px;height:40px;
            display:grid;place-items:center;
            border-radius:13px;
            background:linear-gradient(145deg,#20bd7a,#078452);
            box-shadow:0 9px 22px rgba(22,163,106,.24);
            font-size:21px;
        }
        .logo span{color:var(--dark)}

        .nav-links{
            display:flex;
            align-items:center;
            gap:5px;
        }
        .nav-links a{
            position:relative;
            padding:10px 14px;
            border-radius:12px;
            color:#425149;
            font-weight:600;
            font-size:14px;
            transition:.25s ease;
        }
        .nav-links a:not(.btn-primary):hover{
            color:var(--primary-dark);
            background:var(--primary-soft);
            transform:translateY(-1px);
        }

        .btn-primary,.btn-outline{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            min-height:48px;
            padding:0 20px;
            border-radius:14px;
            font-weight:700;
            font-size:14px;
            cursor:pointer;
            transition:transform .25s ease,box-shadow .25s ease,background .25s ease;
        }
        .btn-primary{
            color:#fff!important;
            background:linear-gradient(135deg,#19ad70,#078151);
            box-shadow:0 12px 26px rgba(8,129,81,.20);
        }
        .btn-primary:hover{
            transform:translateY(-3px);
            box-shadow:0 16px 32px rgba(8,129,81,.28);
        }
        .nav-links .btn-primary{margin-left:7px;padding:0 17px;min-height:43px}

        .menu-toggle{
            display:none;
            width:45px;height:45px;
            border:1px solid var(--line);
            border-radius:13px;
            background:#fff;
            cursor:pointer;
            align-items:center;
            justify-content:center;
            flex-direction:column;
            gap:5px;
        }
        .menu-toggle span{
            width:19px;height:2px;
            border-radius:5px;
            background:var(--dark);
            transition:.25s ease;
        }
        .menu-toggle.active span:nth-child(1){transform:translateY(7px) rotate(45deg)}
        .menu-toggle.active span:nth-child(2){opacity:0}
        .menu-toggle.active span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

        /* HERO */
        .hero{
            min-height:calc(100vh - 78px);
            padding:20px clamp(20px,6vw,84px) clamp(48px,7vw,94px);
            display:grid;
            grid-template-columns:minmax(0,1.03fr) minmax(380px,.97fr);
            gap:clamp(35px,6vw,90px);
            align-items:center;
            position:relative;
            overflow:hidden;
        }
        .hero-text{position:relative;z-index:2}
        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 12px;
            border-radius:999px;
            background:var(--primary-soft);
            color:var(--primary-dark);
            font-size:12px;
            font-weight:800;
            letter-spacing:.8px;
            text-transform:uppercase;
            margin-bottom:20px;
        }
        .eyebrow-dot{
            width:7px;height:7px;border-radius:50%;
            background:var(--primary);
            box-shadow:0 0 0 5px rgba(22,163,106,.10);
        }
        .hero h1{
            font-family:'Manrope',sans-serif;
            font-size:clamp(44px,5.3vw,76px);
            line-height:1.02;
            letter-spacing:-3.5px;
            max-width:720px;
            margin-bottom:24px;
            color:var(--dark);
        }
        .hero h1 .gradient{
            background:linear-gradient(120deg,#08734a,#1dbb78);
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
        }
        .hero p{
            max-width:610px;
            color:var(--muted);
            font-size:clamp(16px,1.5vw,19px);
            line-height:1.75;
            margin-bottom:32px;
        }
        .hero-buttons{display:flex;gap:12px;flex-wrap:wrap}
        .btn-outline{
            color:var(--dark);
            border:1px solid rgba(7,26,18,.14);
            background:rgba(255,255,255,.65);
        }
        .btn-outline:hover{
            border-color:rgba(22,163,106,.4);
            background:var(--primary-soft);
            color:var(--primary-dark);
            transform:translateY(-3px);
        }

        /* HERO VISUAL WITH ORBIT AND SIGN IN CARD */
        .hero-visual{position:relative;min-height:510px;display:grid;place-items:center}
        .hero-orbit{
            position:absolute;
            width:min(530px,90%);
            aspect-ratio:1;
            border-radius:50%;
            border:1px solid rgba(22,163,106,.16);
        }
        .hero-orbit::before,.hero-orbit::after{
            content:"";
            position:absolute;
            border-radius:50%;
            border:1px dashed rgba(22,163,106,.14);
        }
        .hero-orbit::before{inset:9%}
        .hero-orbit::after{inset:19%}

        .hero-signin-card {
            position: relative;
            width: min(440px, 88%);
            padding: 38px 28px;
            border-radius: 34px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.95);
            box-shadow: 0 35px 90px rgba(7,26,18,.16);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            z-index: 2;
            text-align: left;
        }
        .card-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 14px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 22px;
            border-radius: 14px;
            display: grid;
            place-items: center;
        }
        .hero-signin-card h3 {
            font-family: 'Manrope', sans-serif;
            font-size: 24px;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            color: var(--dark);
            text-align: center;
        }
        .hero-signin-card p.card-subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 22px;
            text-align: center;
        }
        .form-group {
            text-align: left;
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: var(--dark-2);
            margin-bottom: 5px;
        }
        .password-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .password-header label {
            margin-bottom: 0;
        }
        .forgot-link {
            font-size: 12px;
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.25s ease;
        }
        .forgot-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        .input-box { position: relative; }
        .input-box i { 
            position: absolute; 
            top: 50%; 
            left: 15px; 
            transform: translateY(-50%); 
            color: #9ba7a2; 
            font-size: 14px;
        }
        .input-box input { 
            width: 100%; 
            padding: 12px 14px 12px 42px; 
            border-radius: 12px; 
            border: 1px solid var(--line); 
            background: var(--surface-2); 
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            transition: all 0.25s ease;
        }
        .input-box input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            text-align: left;
            margin-bottom: 18px;
        }
        .terms-group input[type="checkbox"] {
            margin-top: 2px;
            width: 15px;
            height: 15px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        .terms-group label {
            font-size: 11px;
            color: var(--muted);
            cursor: pointer;
            line-height: 1.4;
        }
        .hero-signin-card .btn-primary {
            width: 100%;
            min-height: 46px;
            border: none;
        }
        .signup-link {
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }
        .signup-link a {
            color: var(--primary-dark);
            font-weight: 700;
        }
        .signup-link a:hover { text-decoration: underline; }

        @keyframes float{50%{transform:translateY(-10px)}}
        .hero-signin-card{animation:float 6s ease-in-out infinite}

        /* SECTION BASE */
        .section{padding:clamp(60px,8vw,100px) clamp(20px,6vw,84px)}
        .section-head{
            display:flex;
            justify-content:space-between;
            align-items:end;
            gap:25px;
            margin-bottom:30px;
        }
        .section-kicker{
            color:var(--primary-dark);
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:1.2px;
            font-weight:800;
            margin-bottom:8px;
        }
        .section h2{
            font-family:'Manrope',sans-serif;
            font-size:clamp(28px,3vw,42px);
            letter-spacing:-1.5px;
            line-height:1.1;
        }
        .section-subtitle{color:var(--muted);margin-top:9px;font-size:15px}
        .view-all-btn{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:var(--primary-dark);
            font-weight:800;
            padding:11px 14px;
            border-radius:12px;
            transition:.25s ease;
            white-space:nowrap;
        }
        .view-all-btn:hover{background:var(--primary-soft);transform:translateX(4px)}

        /* NUTRITIONISTS - CENTERED CARD DESIGN */
        .features{
            background:var(--surface);
            border-top:1px solid var(--line);
            border-bottom:1px solid var(--line);
        }
        .scroll-wrapper{position:relative}
        .scroll-container{
            display:flex;
            gap:20px;
            overflow-x:auto;
            scroll-snap-type:x mandatory;
            scroll-behavior:smooth;
            padding:12px 5px 24px;
            scrollbar-width:none;
            cursor:grab;
        }
        .scroll-container:active{cursor:grabbing}
        .scroll-container::-webkit-scrollbar{display:none}
        .feature-box{
            flex:0 0 295px;
            scroll-snap-align:start;
            position:relative;
            padding:26px;
            border-radius:24px;
            background:linear-gradient(180deg,#fff,#f8fbf9);
            border:1px solid var(--line);
            box-shadow:0 9px 28px rgba(7,26,18,.045);
            transition:.3s ease;
            overflow:hidden;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .feature-box::after{
            content:"";
            position:absolute;
            width:100px;height:100px;
            right:-45px;top:-45px;
            border-radius:50%;
            background:var(--primary-soft);
        }
        .feature-box:hover{
            transform:translateY(-8px);
            box-shadow:0 22px 48px rgba(7,26,18,.11);
            border-color:rgba(22,163,106,.22);
        }
        .expert-img, .expert-avatar {
            width: 82px; height: 82px;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 10px 25px rgba(7,26,18,.12);
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }
        .expert-img {
            object-fit: cover;
        }
        .expert-avatar {
            background: var(--primary-soft);
            color: var(--primary-dark);
            display: grid;
            place-items: center;
            font-size: 22px;
            font-weight: 800;
        }
        .expert-name{
            font-family:'Manrope',sans-serif;
            font-size:19px;
            font-weight:800;
            margin-bottom:5px;
            color:var(--dark);
        }
        .expert-specialty{
            min-height:43px;
            color:var(--muted);
            font-size:13px;
            line-height:1.55;
            margin-bottom:16px;
        }
        .expert-meta{
            display:inline-flex;
            padding:7px 11px;
            border-radius:999px;
            color:var(--primary-dark);
            background:var(--primary-soft);
            font-size:11px;
            font-weight:800;
            margin-bottom:20px;
        }
        .feature-box .btn-primary{width:100%;min-height:44px}
        .nav-btn{
            position:absolute;
            top:45%;
            transform:translateY(-50%);
            z-index:5;
            width:43px;height:43px;
            border-radius:50%;
            border:1px solid rgba(7,26,18,.10);
            background:rgba(255,255,255,.94);
            color:var(--primary-dark);
            display:grid;
            place-items:center;
            cursor:pointer;
            box-shadow:0 12px 28px rgba(7,26,18,.12);
            transition:.25s ease;
        }
        .nav-btn:hover{background:var(--primary);color:#fff;transform:translateY(-50%) scale(1.06)}
        .prev-btn{left:-14px}
        .next-btn{right:-14px}
        .nav-btn:disabled{opacity:.35;pointer-events:none}
        .empty-state{padding:25px;color:var(--muted)}

        /* HOW IT WORKS */
        .how-it-works{
            background:
                radial-gradient(circle at 10% 10%,rgba(22,163,106,.08),transparent 28%),
                linear-gradient(180deg,#f5f8f6,#eef5f1);
        }
        .section-header{text-align:center;max-width:720px;margin:0 auto 52px}
        .section-header .section-kicker{margin-bottom:10px}
        .section-header h2{margin-bottom:11px}
        .section-header p{color:var(--muted);font-size:16px}
        .steps-container{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:22px;
            max-width:1180px;
            margin:auto;
        }
        .step-card{
            position:relative;
            padding:38px 30px 32px;
            min-height:280px;
            border-radius:25px;
            background:rgba(255,255,255,.72);
            border:1px solid rgba(255,255,255,.95);
            box-shadow:0 14px 38px rgba(7,26,18,.06);
            transition:.3s ease;
            overflow:hidden;
        }
        .step-card:hover{
            transform:translateY(-8px);
            box-shadow:0 25px 50px rgba(7,26,18,.11);
        }
        .step-number{
            position:absolute;
            top:20px;right:20px;
            width:35px;height:35px;
            border-radius:12px;
            display:grid;place-items:center;
            background:var(--dark);
            color:#fff;
            font-size:12px;
            font-weight:800;
        }
        .step-icon{
            width:58px;height:58px;
            display:grid;place-items:center;
            border-radius:18px;
            background:var(--primary-soft);
            font-size:28px;
            margin-bottom:24px;
        }
        .step-card h3{
            font-family:'Manrope',sans-serif;
            font-size:20px;
            margin-bottom:11px;
        }
        .step-card p{color:var(--muted);font-size:14px;line-height:1.7}

        /* Reveal animation */
        .reveal{
            opacity:0;
            transform:translateY(24px);
            transition:opacity .7s ease,transform .7s ease;
        }
        .reveal.visible{opacity:1;transform:none}

        /* MOBILE */
        @media(max-width:980px){
            .navbar{height:70px}
            .nav-links{
                position:fixed;
                left:16px;right:16px;top:78px;
                padding:12px;
                display:flex;
                flex-direction:column;
                align-items:stretch;
                gap:4px;
                background:rgba(255,255,255,.97);
                border:1px solid var(--line);
                border-radius:20px;
                box-shadow:0 25px 60px rgba(7,26,18,.15);
                backdrop-filter:blur(18px);
                opacity:0;
                visibility:hidden;
                transform:translateY(-12px);
                transition:.25s ease;
            }
            .nav-links.open{
                opacity:1;
                visibility:visible;
                transform:none;
            }
            .nav-links a{width:100%;padding:13px 15px}
            .nav-links .btn-primary{margin:4px 0 0}
            .menu-toggle{display:flex}
            .hero{
                grid-template-columns:1fr;
                text-align:center;
                padding-top:55px;
            }
            .hero-text{display:flex;flex-direction:column;align-items:center}
            .hero p{max-width:680px}
            .hero-buttons{width:100%;flex-direction:column;align-items:center}
            .hero-buttons a{width:100%;max-width:380px}
            .steps-container{grid-template-columns:1fr}
            .step-card{min-height:auto}
        }

        @media(max-width:640px){
            .logo{font-size:16px}
            .logo-mark{width:36px;height:36px;border-radius:11px;font-size:19px}
            .hero{padding:42px 18px 55px;min-height:auto}
            .hero h1{font-size:43px;letter-spacing:-2.4px}
            .hero p{font-size:15px;line-height:1.65}
            .section{padding:58px 18px}
            .section-head{align-items:flex-start;margin-bottom:20px}
            .section h2{font-size:29px}
            .view-all-btn{padding:8px 5px;font-size:13px}
            .feature-box{flex-basis:calc(100vw - 42px);padding:23px}
            .nav-btn{display:none}
            .scroll-container{padding-bottom:17px}
            .section-header{margin-bottom:42px}
            .section-header h2{font-size:30px}
        }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <a href="index.php" class="logo" aria-label="AI Diet Planner home">
            <span class="logo-mark">🥗</span>
            <span>AI Diet Planner</span>
        </a>

        <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-links" id="navLinks">
            <a href="browse.php">Browse</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact Us</a>
            <a href="auth/login.php">Sign In</a>
            <a href="auth/register.php" class="btn-primary">Get Started <span>→</span></a>
        </nav>
    </header>

    <div class="page-shell"> 
        <main>
            <section class="hero">
                <div class="hero-text reveal">
                    <div class="eyebrow"><span class="eyebrow-dot"></span> AI-powered nutrition</div>
                    <h1>Plan your diet.<br><span class="gradient">Live better.</span></h1>
                    <p>Get personalized meal plans, nutrition guidance, and expert consultations powered by Artificial Intelligence — designed around your goals and lifestyle.</p>
                    <div class="hero-buttons">
                        <a href="auth/login.php" class="btn-primary">Get My Meal Plan <span>→</span></a>
                        <a href="auth/login.php" class="btn-outline">Consult a Nutritionist <span>↗</span></a>
                    </div>
                </div>

                <div class="hero-visual reveal">
                    <div class="hero-orbit"></div>

                    <!-- SIGN IN CARD IN THE CENTER (POSTS DIRECTLY TO auth/login.php FOR 2FA SUPPORT) -->
                    <div class="hero-signin-card">
                        <div class="card-icon"><i class="fa-solid fa-seedling"></i></div>
                        <h3>Welcome Back</h3>
                        <p class="card-subtitle">Sign in to your AI Diet Planner account</p>

                        <form method="POST" action="auth/login.php">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <div class="input-box">
                                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                                    <i class="fa-regular fa-envelope"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="password-header">
                                    <label for="password">Password</label>
                                    <a href="auth/forgot_password.php" class="forgot-link">Forgot Password?</a>
                                </div>
                                <div class="input-box">
                                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                            </div>

                            <div class="terms-group">
                                <input type="checkbox" id="terms" name="terms_accepted" required>
                                <label for="terms">I agree to the Terms of Service and Privacy Policy.</label>
                            </div>

                            <button type="submit" name="login" class="btn-primary">Sign In</button>
                        </form>

                        <div class="signup-link">
                            Don't have an account yet? <a href="auth/register.php">Sign Up</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section features" id="features">
                <div class="section-head reveal">
                    <div>
                        <div class="section-kicker">Professional support</div>
                        <h2>Featured Nutritionists</h2>
                        <p class="section-subtitle">Connect with qualified experts for personalized guidance.</p>
                    </div>
                    <a href="browse.php?type=nutritionists" class="view-all-btn">View All <span>→</span></a>
                </div>

                <div class="scroll-wrapper reveal">
                    <button class="nav-btn prev-btn" id="prevBtn" aria-label="Previous nutritionists">‹</button>
                    <button class="nav-btn next-btn" id="nextBtn" aria-label="Next nutritionists">›</button>

                    <div class="scroll-container" id="scrollContainer">
                        <?php if (!empty($nutritionists)): ?>
                            <?php foreach($nutritionists as $row): 
                                $filename = trim($row['profile_pic'] ?? '');
                                $has_valid_image = false;
                                $img = "";

                                if (!empty($filename) && $filename !== 'default_avatar.png') {
                                    if (strpos($filename, 'http') === 0 || strpos($filename, '/') === 0) {
                                        $img = $filename;
                                        $has_valid_image = true;
                                    } else {
                                        if (file_exists("uploads/profile_pics/" . $filename)) {
                                            $img = "uploads/profile_pics/" . $filename;
                                            $has_valid_image = true;
                                        } else if (file_exists($filename)) {
                                            $img = $filename;
                                            $has_valid_image = true;
                                        }
                                    }
                                }
                            ?>
                                <article class="feature-box">
                                    <?php if ($has_valid_image): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" class="expert-img" alt="<?= htmlspecialchars($row['fullname']) ?>">
                                    <?php else: ?>
                                        <div class="expert-avatar">
                                            <?= getInitials($row['fullname']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="expert-name"><?= htmlspecialchars($row['fullname']) ?></div>
                                    <div class="expert-specialty"><?= htmlspecialchars($row['certification']) ?></div>
                                    <div>
                                        <span class="expert-meta"><?= (int)$row['experience'] ?> Years Experience</span>
                                    </div>
                                    <a href="auth/login.php" class="btn-primary">Consult Expert <span>→</span></a>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">No experts are currently available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="section how-it-works">
                <div class="section-header reveal">
                    <div class="section-kicker">Simple process</div>
                    <h2>How It Works</h2>
                    <p>Get started on your health journey in three simple steps.</p>
                </div>

                <div class="steps-container">
                    <article class="step-card reveal">
                        <div class="step-number">01</div>
                        <div class="step-icon">📝</div>
                        <h3>Create Profile</h3>
                        <p>Tell us about your goals, allergies, and food preferences so our AI can understand your needs.</p>
                    </article>

                    <article class="step-card reveal">
                        <div class="step-number">02</div>
                        <div class="step-icon">🤖</div>
                        <h3>AI Generation</h3>
                        <p>Our AI analyzes your data to create a custom meal plan optimized for your specific requirements.</p>
                    </article>

                    <article class="step-card reveal">
                        <div class="step-number">03</div>
                        <div class="step-icon">🥗</div>
                        <h3>Expert Guidance</h3>
                        <p>Connect with certified nutritionists to fine-tune your plan and stay on track with professional support.</p>
                    </article>
                </div>
            </section>
        </main>
    </div>

<script>
(() => {
    const navbar = document.getElementById('navbar');
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');
    const container = document.getElementById('scrollContainer');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');

    // Mobile navigation
    function closeMenu() {
        navLinks.classList.remove('open');
        menuToggle.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
    }

    menuToggle.addEventListener('click', () => {
        const isOpen = navLinks.classList.toggle('open');
        menuToggle.classList.toggle('active', isOpen);
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('menu-open', isOpen);
    });

    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 980) closeMenu();
    });

    // Sticky navbar state
    const updateNavbar = () => {
        navbar.classList.toggle('scrolled', window.scrollY > 10);
    };
    updateNavbar();
    window.addEventListener('scroll', updateNavbar, {passive:true});

    // Nutritionist carousel
    const getScrollAmount = () => {
        const card = container.querySelector('.feature-box');
        if (!card) return 320;
        return card.getBoundingClientRect().width + 20;
    };

    nextBtn.addEventListener('click', () => {
        container.scrollBy({left:getScrollAmount(), behavior:'smooth'});
    });

    prevBtn.addEventListener('click', () => {
        container.scrollBy({left:-getScrollAmount(), behavior:'smooth'});
    });

    function updateArrows() {
        const maxScroll = container.scrollWidth - container.clientWidth - 2;
        prevBtn.disabled = container.scrollLeft <= 2;
        nextBtn.disabled = container.scrollLeft >= maxScroll;
    }

    container.addEventListener('scroll', updateArrows, {passive:true});
    window.addEventListener('resize', updateArrows);
    updateArrows();

    // Mouse-wheel support inside carousel
    container.addEventListener('wheel', (event) => {
        if (Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
            container.scrollLeft += event.deltaY;
        }
    }, {passive:true});

    // Drag-to-scroll on desktop
    let isDragging = false;
    let startX = 0;
    let startScroll = 0;

    container.addEventListener('pointerdown', event => {
        if (event.target.closest('a') || event.target.closest('button')) return; 

        isDragging = true;
        startX = event.clientX;
        startScroll = container.scrollLeft;
        container.setPointerCapture(event.pointerId);
    });

    container.addEventListener('pointermove', event => {
        if (!isDragging) return;
        container.scrollLeft = startScroll - (event.clientX - startX);
    });

    container.addEventListener('pointerup', () => isDragging = false);
    container.addEventListener('pointercancel', () => isDragging = false);

    // Scroll reveal
    const revealItems = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    obs.unobserve(entry.target);
                }
            });
        }, {threshold:0.12});

        revealItems.forEach(item => observer.observe(item));
    } else {
        revealItems.forEach(item => item.classList.add('visible'));
    }
})();
</script>
</body>
</html>