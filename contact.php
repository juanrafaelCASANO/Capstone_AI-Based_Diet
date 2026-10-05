<?php 
require_once 'config.php'; 

$success_status = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize user input to prevent SQL injection
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // Insert into database
    $sql = "INSERT INTO contact_messages (name, email, message) VALUES ('$name', '$email', '$message')";
    
    if ($conn->query($sql)) {
        $success_status = true;
    } else {
        echo "<script>alert('Error sending message: " . $conn->error . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - AI-Based Diet & Nutritional Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#071a12">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

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

        /* Ambient background */
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

        .page-shell{width:min(1440px,100%);margin:auto}

        /* NAVBAR */
        .navbar{
            position:sticky;
            top:0;
            z-index:1000;
            height:78px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 clamp(20px,5vw,72px);
            background:rgba(255,255,255,.84);
            border-bottom:1px solid rgba(16,32,25,.07);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            transition:.3s ease;
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
            border:none;
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

        /* CONTACT SECTION */
        .contact-hero {
            min-height: calc(100vh - 78px);
            padding: clamp(40px, 6vw, 80px) clamp(20px, 6vw, 84px);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .contact-hero::after {
            content:"";
            position:absolute;
            width:380px;height:380px;
            left:5%;bottom:-100px;
            background:rgba(22,163,106,.08);
            filter:blur(10px);
            border-radius:50%;
            z-index:-1;
        }

        .contact-card {
            width: 100%;
            max-width: 650px;
            position: relative;
            padding: clamp(30px, 5vw, 50px);
            border-radius: var(--radius);
            background: rgba(255,255,255,.85);
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: 0 35px 90px rgba(7,26,18,.08);
            backdrop-filter: blur(15px);
            z-index: 2;
        }

        .contact-card h1 {
            font-family: 'Manrope', sans-serif;
            font-size: clamp(32px, 4vw, 44px);
            letter-spacing: -1.5px;
            color: var(--dark);
            margin-bottom: 12px;
        }

        .contact-card p.subtitle {
            color: var(--muted);
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        /* Forms */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-2);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 15px 18px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: var(--surface-2);
            font-family: inherit;
            font-size: 15px;
            color: var(--text);
            transition: all 0.25s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }
        
        .form-control::placeholder {
            color: #9ba7a2;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 140px;
        }

        .submit-btn {
            width: 100%;
            margin-top: 10px;
            font-size: 15px;
            min-height: 52px;
        }

        .alert-success {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            border: 1px solid rgba(22, 163, 106, 0.2);
            border-radius: 14px;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 14px;
            animation: slideDown 0.4s ease-out forwards;
        }
        
        .alert-icon {
            width: 28px;
            height: 28px;
            background: var(--primary);
            color: #fff;
            display: grid;
            place-items: center;
            border-radius: 50%;
            font-size: 12px;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

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
        }

        @media(max-width:640px){
            .logo{font-size:16px}
            .logo-mark{width:36px;height:36px;border-radius:11px;font-size:19px}
            .nav-links{top:76px}
            .contact-hero { padding: 40px 18px; }
            .contact-card { padding: 30px 20px; border-radius: 24px; }
        }
    </style>
</head>

<body>
<div class="page-shell">

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
            <a href="contact.php" style="color: var(--primary-dark); background: var(--primary-soft);">Contact Us</a>
            <a href="auth/login.php">Sign In</a>
            <a href="auth/register.php" class="btn-primary">Get Started <span>→</span></a>
        </nav>
    </header>

    <main>
        <section class="contact-hero">
            <div class="contact-card reveal">
                <h1>Contact Us</h1>
                <p class="subtitle">Have questions, feedback, or need assistance? Our team is here to help you with your nutrition journey.</p>

                <?php if($success_status): ?>
                    <div class="alert-success">
                        <div class="alert-icon">✓</div>
                        <div>Your message has been sent! Our team will get back to you soon.</div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="Enter your full name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" placeholder="How can we help you?" required></textarea>
                    </div>

                    <button type="submit" class="btn-primary submit-btn">Send Message <span>→</span></button>
                </form>
            </div>
        </section>
    </main>
</div>

<script>
(() => {
    const navbar = document.getElementById('navbar');
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');

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