<?php /* about.php */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#071a12">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --bg:#f5f8f6;
            --surface:#fff;
            --surface-2:#eef7f2;
            --text:#102019;
            --muted:#65736d;
            --line:rgba(16,32,25,.09);
            --primary:#16a36a;
            --primary-dark:#08734a;
            --primary-soft:#e3f6ed;
            --dark:#071a12;
            --shadow:0 24px 70px rgba(7,26,18,.10);
            --shadow-sm:0 12px 35px rgba(7,26,18,.08);
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

        /* Ambient background */
        body::before{
            content:"";
            position:fixed;
            width:520px;
            height:520px;
            top:-240px;
            right:-190px;
            background:rgba(22,163,106,.10);
            filter:blur(20px);
            border-radius:50%;
            pointer-events:none;
            z-index:-1;
        }

        .page-shell{
            width:min(1440px,100%);
            margin:auto;
        }

        /* ================= NAVBAR ================= */

        .navbar{
            position:sticky;
            top:0;
            z-index:9999;
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
            background:rgba(255,255,255,.95);
            box-shadow:0 10px 35px rgba(7,26,18,.08);
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
            width:40px;
            height:40px;
            display:grid;
            place-items:center;
            border-radius:13px;
            background:linear-gradient(145deg,#20bd7a,#078452);
            box-shadow:0 9px 22px rgba(22,163,106,.24);
            font-size:21px;
        }

        .logo span:last-child{color:var(--dark)}

        .nav-links{
            display:flex;
            align-items:center;
            gap:5px;
        }

        .nav-links a{
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

        .btn-primary{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            min-height:46px;
            padding:0 20px;
            border-radius:14px;
            color:#fff!important;
            background:linear-gradient(135deg,#19ad70,#078151);
            box-shadow:0 12px 26px rgba(8,129,81,.20);
            font-weight:700;
            font-size:14px;
            transition:.25s ease;
        }

        .btn-primary:hover{
            transform:translateY(-3px);
            box-shadow:0 16px 32px rgba(8,129,81,.28);
        }

        .nav-links .btn-primary{
            margin-left:7px;
            min-height:43px;
            padding:0 17px;
        }

        .menu-toggle{
            display:none;
            width:45px;
            height:45px;
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
            width:19px;
            height:2px;
            border-radius:5px;
            background:var(--dark);
            transition:.25s ease;
        }

        .menu-toggle.active span:nth-child(1){
            transform:translateY(7px) rotate(45deg);
        }

        .menu-toggle.active span:nth-child(2){opacity:0}

        .menu-toggle.active span:nth-child(3){
            transform:translateY(-7px) rotate(-45deg);
        }

        /* ================= HERO ================= */

        .about-hero{
            position:relative;
            padding:clamp(58px,8vw,105px) clamp(20px,6vw,84px) 70px;
            overflow:hidden;
        }

        .about-hero::after{
            content:"";
            position:absolute;
            width:430px;
            height:430px;
            left:-250px;
            top:100px;
            border-radius:50%;
            background:rgba(22,163,106,.08);
            filter:blur(15px);
            z-index:-1;
        }

        .hero-grid{
            max-width:1200px;
            margin:auto;
            display:grid;
            grid-template-columns:1.05fr .95fr;
            gap:clamp(35px,6vw,80px);
            align-items:center;
        }

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
            width:7px;
            height:7px;
            border-radius:50%;
            background:var(--primary);
            box-shadow:0 0 0 5px rgba(22,163,106,.10);
        }

        .hero-copy h1{
            font-family:'Manrope',sans-serif;
            font-size:clamp(43px,5.1vw,70px);
            line-height:1.03;
            letter-spacing:-3px;
            color:var(--dark);
            margin-bottom:20px;
        }

        .hero-copy h1 .gradient{
            background:linear-gradient(120deg,#08734a,#1dbb78);
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
        }

        .hero-copy .lead{
            max-width:630px;
            color:var(--muted);
            font-size:clamp(16px,1.5vw,18px);
            line-height:1.75;
            margin-bottom:28px;
        }

        .hero-actions{
            display:flex;
            gap:11px;
            flex-wrap:wrap;
        }

        .hero-badge{
            display:inline-flex;
            align-items:center;
            gap:9px;
            color:var(--primary-dark);
            font-size:13px;
            font-weight:700;
            margin-top:20px;
        }

        .hero-badge span{
            width:23px;
            height:23px;
            border-radius:50%;
            display:grid;
            place-items:center;
            background:var(--primary-soft);
        }

        .hero-visual{
            position:relative;
            min-height:410px;
            display:grid;
            place-items:center;
        }

        .visual-orbit{
            position:absolute;
            width:min(430px,90%);
            aspect-ratio:1;
            border-radius:50%;
            border:1px solid rgba(22,163,106,.17);
        }

        .visual-orbit::before,
        .visual-orbit::after{
            content:"";
            position:absolute;
            border-radius:50%;
            border:1px dashed rgba(22,163,106,.14);
        }

        .visual-orbit::before{inset:9%}
        .visual-orbit::after{inset:20%}

        .visual-card{
            position:relative;
            width:min(380px,82%);
            aspect-ratio:1;
            border-radius:35px;
            display:grid;
            place-items:center;
            background:linear-gradient(145deg,#e4f7ed,#fff);
            border:1px solid rgba(255,255,255,.95);
            box-shadow:0 35px 85px rgba(7,26,18,.15);
            z-index:2;
            animation:float 6s ease-in-out infinite;
        }

        .visual-card::before{
            content:"";
            position:absolute;
            width:230px;
            height:230px;
            border-radius:50%;
            background:rgba(22,163,106,.12);
            filter:blur(5px);
        }

        .visual-icon{
            position:relative;
            z-index:2;
            width:135px;
            height:135px;
            display:grid;
            place-items:center;
            border-radius:38px;
            background:rgba(255,255,255,.82);
            box-shadow:0 18px 45px rgba(7,26,18,.12);
            font-size:72px;
        }

        .floating-card{
            position:absolute;
            z-index:4;
            display:flex;
            align-items:center;
            gap:10px;
            padding:12px 15px;
            background:rgba(255,255,255,.94);
            border:1px solid rgba(255,255,255,.95);
            border-radius:16px;
            box-shadow:var(--shadow-sm);
            color:var(--dark);
            font-size:12px;
            font-weight:800;
            backdrop-filter:blur(10px);
        }

        .floating-icon{
            width:34px;
            height:34px;
            display:grid;
            place-items:center;
            border-radius:11px;
            background:var(--primary-soft);
            font-size:17px;
        }

        .float-one{top:9%;left:0}
        .float-two{right:-2%;bottom:16%}
        .float-three{left:12%;bottom:3%}

        @keyframes float{
            50%{transform:translateY(-10px)}
        }

        /* ================= CONTENT ================= */

        .container{
            width:min(1200px,100%);
            margin:0 auto;
            padding:0 clamp(20px,6vw,84px) 90px;
        }

        .content-section{
            margin-top:28px;
            padding:clamp(48px,6vw,72px);
            border-radius:32px;
            background:#fff;
            border:1px solid var(--line);
            box-shadow:0 16px 50px rgba(7,26,18,.06);
            position:relative;
            overflow:hidden;
        }

        .content-section::before{
            content:"";
            position:absolute;
            width:180px;
            height:180px;
            border-radius:50%;
            right:-80px;
            top:-80px;
            background:var(--primary-soft);
        }

        .section-heading{
            position:relative;
            z-index:1;
            text-align:center;
            max-width:800px;
            margin:0 auto 35px;
        }

        .section-kicker{
            color:var(--primary-dark);
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:1.2px;
            font-weight:800;
            margin-bottom:9px;
        }

        .section-heading h2{
            font-family:'Manrope',sans-serif;
            font-size:clamp(29px,3vw,40px);
            letter-spacing:-1.4px;
            line-height:1.1;
            margin-bottom:13px;
        }

        .section-heading p{
            color:var(--muted);
            font-size:15px;
            line-height:1.75;
        }

        .mission-content{
            position:relative;
            z-index:1;
            max-width:850px;
            margin:auto;
            text-align:center;
        }

        .mission-icon{
            width:70px;
            height:70px;
            margin:0 auto 20px;
            display:grid;
            place-items:center;
            border-radius:22px;
            background:var(--primary-soft);
            font-size:32px;
            box-shadow:0 10px 25px rgba(22,163,106,.08);
        }

        .mission-content p{
            color:var(--muted);
            font-size:16px;
            line-height:1.8;
        }

        /* ================= FEATURES ================= */

        .feature-grid{
            position:relative;
            z-index:1;
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:20px;
        }

        .feature-card{
            padding:30px 25px;
            border-radius:24px;
            background:linear-gradient(180deg,#fff,#f7fbf8);
            border:1px solid var(--line);
            box-shadow:0 10px 30px rgba(7,26,18,.05);
            text-align:left;
            transition:.3s ease;
            cursor:default;
        }

        .feature-card:hover{
            transform:translateY(-8px);
            border-color:rgba(22,163,106,.22);
            box-shadow:0 22px 45px rgba(7,26,18,.10);
        }

        .feature-icon{
            width:58px;
            height:58px;
            display:grid;
            place-items:center;
            border-radius:18px;
            background:var(--primary-soft);
            font-size:28px;
            margin-bottom:21px;
            transition:.3s ease;
        }

        .feature-card:hover .feature-icon{
            transform:scale(1.08) rotate(-3deg);
        }

        .feature-card h3{
            font-family:'Manrope',sans-serif;
            font-size:19px;
            margin-bottom:10px;
            color:var(--dark);
        }

        .feature-card p{
            color:var(--muted);
            font-size:14px;
            line-height:1.7;
        }

        /* ================= VISION ================= */

        .vision-section{
            background:
                radial-gradient(circle at 15% 20%,rgba(22,163,106,.08),transparent 30%),
                linear-gradient(180deg,#f1f8f4,#eaf4ef);
            border-color:rgba(22,163,106,.08);
        }

        .vision-section::before{display:none}

        /* ================= REVEAL ================= */

        .reveal{
            opacity:0;
            transform:translateY(25px);
            transition:opacity .7s ease,transform .7s ease;
        }

        .reveal.visible{
            opacity:1;
            transform:none;
        }

        /* ================= RESPONSIVE ================= */

        @media(max-width:980px){
            .navbar{height:70px}

            .nav-links{
                position:fixed;
                left:16px;
                right:16px;
                top:78px;
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

            .nav-links a{
                width:100%;
                padding:13px 15px;
            }

            .nav-links .btn-primary{
                margin:4px 0 0;
            }

            .menu-toggle{display:flex}

            .hero-grid{
                grid-template-columns:1fr;
                text-align:center;
            }

            .hero-copy{
                display:flex;
                flex-direction:column;
                align-items:center;
            }

            .hero-copy .lead{max-width:720px}

            .hero-visual{min-height:380px}

            .feature-grid{
                grid-template-columns:1fr;
            }

            .feature-card{
                display:grid;
                grid-template-columns:auto 1fr;
                column-gap:18px;
                align-items:start;
            }

            .feature-icon{
                grid-row:span 2;
                margin-bottom:0;
            }
        }

        @media(max-width:640px){
            .logo{font-size:16px}

            .logo-mark{
                width:36px;
                height:36px;
                border-radius:11px;
                font-size:19px;
            }

            .nav-links{top:76px}

            .about-hero{
                padding:48px 18px 40px;
            }

            .hero-copy h1{
                font-size:43px;
                letter-spacing:-2.4px;
            }

            .hero-copy .lead{
                font-size:15px;
                line-height:1.65;
            }

            .hero-actions{
                width:100%;
                flex-direction:column;
            }

            .hero-actions a{width:100%}

            .hero-visual{
                min-height:330px;
            }

            .visual-card{
                width:80%;
                border-radius:27px;
            }

            .visual-icon{
                width:105px;
                height:105px;
                border-radius:30px;
                font-size:55px;
            }

            .floating-card{
                display:none;
            }

            .container{
                padding:0 18px 65px;
            }

            .content-section{
                margin-top:18px;
                padding:40px 22px;
                border-radius:25px;
            }

            .section-heading{
                margin-bottom:30px;
            }

            .section-heading h2{
                font-size:29px;
            }

            .mission-content p{
                font-size:15px;
                line-height:1.7;
            }

            .feature-card{
                display:block;
                padding:25px 22px;
            }

            .feature-icon{
                margin-bottom:18px;
            }
        }

        @media(prefers-reduced-motion:reduce){
            html{scroll-behavior:auto}
            *,*::before,*::after{
                animation-duration:.01ms!important;
                animation-iteration-count:1!important;
                transition-duration:.01ms!important;
            }
        }
    </style>
</head>

<body>
<div class="page-shell">

    <!-- NAVIGATION -->
    <header class="navbar" id="navbar">
        <a href="index.php" class="logo" aria-label="AI Diet Planner home">
            <span class="logo-mark">🥗</span>
            <span>AI Diet Planner</span>
        </a>

        <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="browse.php">Browse</a>
            <a href="contact.php">Contact Us</a>
            <a href="auth/login.php">Sign In</a>
            <a href="auth/login.php" class="btn-primary">Get Started <span>→</span></a>
</nav>
    </header>

    <main>

        <!-- HERO -->
        <section class="about-hero">
            <div class="hero-grid">

                <div class="hero-copy reveal">
                    <div class="eyebrow">
                        <span class="eyebrow-dot"></span>
                        About our platform
                    </div>

                    <h1>
                        Nutrition powered by
                        <span class="gradient">intelligence.</span>
                    </h1>

                    <p class="lead">
                        A smart nutrition platform that connects users with personalized
                        AI-generated meal plans and certified nutritionists to support
                        healthier lifestyles through technology.
                    </p>

                    <div class="hero-actions">
                        <a href="auth/login.php" class="btn-primary">
                            Get Started <span>→</span>
                        </a>

                        <a href="browse.php" class="btn-primary" style="background:#fff;color:var(--dark)!important;border:1px solid var(--line);box-shadow:none;">
                            Explore Platform <span>↗</span>
                        </a>
                    </div>

                    <div class="hero-badge">
                        <span>✓</span>
                        Personalized • Intelligent • Accessible
                    </div>
                </div>

                <div class="hero-visual reveal">
                    <div class="visual-orbit"></div>

                    <div class="floating-card float-one">
                        <span class="floating-icon">🤖</span>
                        <span>AI-powered<br>planning</span>
                    </div>

                    <div class="floating-card float-two">
                        <span class="floating-icon">🥗</span>
                        <span>Personalized<br>nutrition</span>
                    </div>

                    <div class="floating-card float-three">
                        <span class="floating-icon">✓</span>
                        <span>Expert support</span>
                    </div>

                    <div class="visual-card">
                        <div class="visual-icon">🥗</div>
                    </div>
                </div>

            </div>
        </section>

        <div class="container">

            <!-- MISSION -->
            <section class="content-section reveal">
                <div class="section-heading">
                    <div class="section-kicker">What drives us</div>
                    <h2>Our Mission</h2>
                </div>

                <div class="mission-content">
                    <div class="mission-icon">🎯</div>

                    <p>
                        To empower individuals to achieve better health by providing
                        personalized, accessible, and data-driven nutrition solutions
                        through artificial intelligence and professional dietary guidance.
                    </p>
                </div>
            </section>

            <!-- CORE FEATURES -->
            <section class="content-section reveal">
                <div class="section-heading">
                    <div class="section-kicker">Built around your needs</div>
                    <h2>Core Features</h2>
                    <p>Technology and professional guidance working together to support healthier decisions.</p>
                </div>

                <div class="feature-grid">

                    <article class="feature-card">
                        <div class="feature-icon">🍽</div>
                        <div>
                            <h3>Personalized Meal Plans</h3>
                            <p>
                                AI-generated meal suggestions based on calorie requirements,
                                user goals, and nutritional balance.
                            </p>
                        </div>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">💬</div>
                        <div>
                            <h3>Nutritionist Consultation</h3>
                            <p>
                                Users can communicate with nutritionists via a real-time chat
                                system for professional guidance.
                            </p>
                        </div>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">🔐</div>
                        <div>
                            <h3>Secure Role-Based Access</h3>
                            <p>
                                Role-based authentication ensures privacy, data protection,
                                and controlled system access.
                            </p>
                        </div>
                    </article>

                </div>
            </section>

            <!-- VISION -->
            <section class="content-section vision-section reveal">
                <div class="section-heading">
                    <div class="section-kicker">Where we're going</div>
                    <h2>Our Vision</h2>
                </div>

                <div class="mission-content">
                    <div class="mission-icon">🌍</div>

                    <p>
                        To become a trusted digital nutrition companion that bridges
                        technology and healthcare, making healthy living accessible
                        anytime and anywhere.
                    </p>
                </div>
            </section>

        </div>
    </main>
</div>

<script>
(() => {
    const navbar = document.getElementById('navbar');
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');

    // Mobile navigation
    function closeMenu(){
        navLinks.classList.remove('open');
        menuToggle.classList.remove('active');
        menuToggle.setAttribute('aria-expanded','false');
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
        if(window.innerWidth > 980) closeMenu();
    });

    // Sticky navbar effect
    function updateNavbar(){
        navbar.classList.toggle('scrolled', window.scrollY > 10);
    }

    updateNavbar();
    window.addEventListener('scroll', updateNavbar, {passive:true});

    // Scroll reveal
    const revealItems = document.querySelectorAll('.reveal');

    if('IntersectionObserver' in window){
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if(entry.isIntersecting){
                    entry.target.classList.add('visible');
                    obs.unobserve(entry.target);
                }
            });
        }, {threshold:0.12});

        revealItems.forEach(item => observer.observe(item));
    }else{
        revealItems.forEach(item => item.classList.add('visible'));
    }
})();
</script>

</body>
</html>
