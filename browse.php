<?php
require_once 'config.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'meals';
$goal = isset($_GET['goal']) ? $_GET['goal'] : '';
$specialty = isset($_GET['specialty']) ? $_GET['specialty'] : '';

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#16a36a;
            --primary-dark:#08734a;
            --primary-soft:#e9f8f0;
            --primary-pale:#f3fbf7;
            --ink:#071a12;
            --muted:#607268;
            --line:rgba(7,26,18,.09);
            --white:#ffffff;
            --shadow:0 20px 55px rgba(7,50,30,.09);
            --shadow-hover:0 28px 70px rgba(7,50,30,.15);
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{
            font-family:"Inter",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background:
                radial-gradient(circle at 10% 15%, rgba(22,163,106,.07), transparent 26rem),
                radial-gradient(circle at 90% 20%, rgba(8,115,74,.06), transparent 24rem),
                #f7fbf9;
            color:var(--ink);
            overflow-x:hidden;
        }
        body::before{
            content:"";
            position:fixed;
            inset:0;
            pointer-events:none;
            background-image:linear-gradient(rgba(7,26,18,.025) 1px,transparent 1px),
                             linear-gradient(90deg,rgba(7,26,18,.025) 1px,transparent 1px);
            background-size:42px 42px;
            mask-image:linear-gradient(to bottom,black,transparent 72%);
            z-index:-1;
        }
        a{text-decoration:none;color:inherit}
        button,input,select{font:inherit}

        .navbar{
            position:sticky;
            top:0;
            z-index:1000;
            min-height:76px;
            padding:14px clamp(20px,5vw,72px);
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
            background:rgba(255,255,255,.94);
            box-shadow:0 10px 35px rgba(7,26,18,.08);
        }
        .logo{
            display:flex;
            align-items:center;
            gap:10px;
            color:var(--primary-dark);
            font-weight:800;
            font-size:20px;
            letter-spacing:-.4px;
            white-space:nowrap;
        }
        .logo:first-letter{font-size:25px}
        .nav-links{
            display:flex;
            align-items:center;
            gap:5px;
        }
        .nav-links a{
            position:relative;
            margin:0;
            padding:10px 14px;
            border-radius:12px;
            color:#365147;
            font-size:14px;
            font-weight:600;
            transition:.25s ease;
        }
        .nav-links a:not(.btn-primary):hover{
            color:var(--primary-dark);
            background:var(--primary-soft);
            transform:translateY(-1px);
        }
        
        .btn-primary,.btn{
            border:0;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            color:#fff !important;
            font-weight:700;
            box-shadow:0 10px 25px rgba(22,163,106,.18);
            transition:transform .25s ease,box-shadow .25s ease,filter .25s ease;
        }
        .btn-primary{
            padding:11px 18px !important;
            border-radius:13px !important;
            margin-left:4px !important;
        }
        .btn-primary:hover,.btn:hover{
            transform:translateY(-3px);
            box-shadow:0 16px 34px rgba(22,163,106,.27);
            filter:saturate(1.08);
        }
        .mobile-toggle{
            display:none;
            width:44px;
            height:44px;
            border:1px solid var(--line);
            border-radius:12px;
            background:#fff;
            cursor:pointer;
            align-items:center;
            justify-content:center;
            flex-direction:column;
            gap:5px;
        }
        .mobile-toggle span{
            width:20px;height:2px;border-radius:2px;background:var(--ink);
            transition:.25s ease;
        }
        .mobile-toggle.active span:nth-child(1){transform:translateY(7px) rotate(45deg)}
        .mobile-toggle.active span:nth-child(2){opacity:0}
        .mobile-toggle.active span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

        .container{
            width:min(1180px,calc(100% - 40px));
            margin:0 auto;
            padding:54px 0 90px;
        }
        
        .tabs{
            display:flex;
            gap:10px;
            margin:32px 0 24px;
            padding:6px;
            width:max-content;
            max-width:100%;
            border:1px solid var(--line);
            border-radius:17px;
            background:rgb(255, 255, 255);
            box-shadow:0 10px 30px rgba(7,26,18,.05);
        }
        .tab{
            padding:12px 19px;
            border-radius:12px;
            color:#456057;
            font-size:14px;
            font-weight:700;
            transition:.25s ease;
            white-space:nowrap;
        }
        .tab.active{
            color:#fff;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            box-shadow:0 8px 20px rgba(22,163,106,.2);
        }
        .tab:not(.active):hover{
            color:var(--primary-dark);
            background:var(--primary-soft);
            transform:translateY(-2px);
        }

        .filter-panel{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:24px;
            padding:16px 18px;
            border:1px solid var(--line);
            border-radius:18px;
            background:rgba(255,255,255,.78);
            box-shadow:0 10px 30px rgba(7,26,18,.04);
        }
        .filter-panel::before{
            content:"Filter meal plans by goal";
            color:#52675e;
            font-size:13px;
            font-weight:700;
        }
        select{
            min-width:190px;
            appearance:none;
            padding:12px 40px 12px 14px;
            border:1px solid #d7e3dc;
            border-radius:12px;
            outline:none;
            color:#29483b;
            background:#fff;
            cursor:pointer;
            font-size:14px;
            font-weight:600;
            box-shadow:0 5px 15px rgba(7,26,18,.04);
            transition:.25s ease;
        }
        select:focus,select:hover{
            border-color:rgba(22,163,106,.5);
            box-shadow:0 0 0 4px rgba(22,163,106,.08);
        }

        .grid{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:24px;
            align-items:stretch;
        }
        .card{
            position:relative;
            overflow:hidden;
            padding:20px;
            border:1px solid var(--line);
            border-radius:24px;
            background:rgba(255,255,255,.92);
            box-shadow:var(--shadow);
            transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease;
            display:flex;
            flex-direction:column;
            height:100%;
        }
        .card::before{
            content:"";
            position:absolute;
            width:120px;height:120px;
            right:-60px;top:-60px;
            border-radius:50%;
            background:rgba(22,163,106,.07);
            transition:.35s ease;
        }
        .card:hover{
            transform:translateY(-8px);
            box-shadow:var(--shadow-hover);
            border-color:rgba(22,163,106,.18);
        }
        .card:hover::before{transform:scale(1.35)}
        .meal-image{
            width:100%;
            height:190px;
            object-fit:cover;
            display:block;
            border-radius:17px;
            margin-bottom:16px;
            background:var(--primary-pale);
            transition:transform .45s ease;
        }
        .meal-card:hover .meal-image{transform:scale(1.025)}
        .tag{
            display:inline-flex;
            padding:6px 10px;
            border-radius:999px;
            background:var(--primary-soft);
            color:var(--primary-dark);
            font-size:11px;
            font-weight:800;
            letter-spacing:.2px;
            width:max-content;
        }
        .card h3{
            margin:12px 0 8px;
            color:var(--ink);
            font-size:19px;
            line-height:1.25;
        }
        .card p{
            min-height:42px;
            color:var(--muted);
            font-size:13px;
            line-height:1.65;
            flex-grow:1;
        }
        .calories{
            display:flex;
            align-items:center;
            gap:6px;
            margin-top:14px;
            color:#29483b;
            font-size:13px;
            font-weight:800;
        }
        .btn{
            width:100%;
            margin-top:auto;
            padding:12px 18px;
            border-radius:13px;
            font-size:13px;
        }

        .nutritionist-card{
            text-align:center;
            padding:28px 22px 22px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .avatar, .avatar-initials{
            width:120px;height:120px;
            display:block;
            margin:0 auto 16px;
            border-radius:50%;
            border:5px solid #e7f7ee;
            box-shadow:0 12px 28px rgba(7,26,18,.10);
            transition:.35s ease;
            flex-shrink: 0;
        }
        .avatar{object-fit:cover;}
        .avatar-initials{
            background:var(--primary-soft);
            color:var(--primary-dark);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:38px;
            font-weight:800;
        }
        .nutritionist-card:hover .avatar, .nutritionist-card:hover .avatar-initials{
            transform:scale(1.06);
            border-color:#bdebd1;
        }
        .nutritionist-card h3{margin-top:0}
        .nutritionist-card p{
            min-height:auto;
            margin-bottom:10px;
            color:var(--primary-dark);
            font-weight:700;
            flex-grow:0;
        }
        .experience{
            color:#607268;
            font-size:13px;
            font-weight:700;
            margin-bottom:17px;
        }

        .no-results{
            grid-column:1/-1;
            text-align:center;
            padding:65px 25px;
            border:1px dashed #c9d9d1;
            border-radius:22px;
            background:rgba(255,255,255,.7);
            color:#687c73;
            font-weight:700;
        }

        .reveal{
            opacity:0;
            transform:translateY(18px);
            transition:opacity .65s ease,transform .65s ease;
        }
        .reveal.visible{opacity:1;transform:translateY(0)}

        @media (max-width:980px){
            .navbar{padding:13px 24px}
            .mobile-toggle{display:flex}
            .nav-links{
                position:absolute;
                top:calc(100% + 8px);
                left:20px;right:20px;
                display:flex;
                flex-direction:column;
                align-items:stretch;
                gap:5px;
                padding:12px;
                border:1px solid var(--line);
                border-radius:18px;
                background:rgba(255,255,255,.96);
                backdrop-filter:blur(18px);
                box-shadow:0 20px 50px rgba(7,26,18,.12);
                opacity:0;
                visibility:hidden;
                transform:translateY(-8px);
                transition:.25s ease;
            }
            .nav-links.open{
                opacity:1;
                visibility:visible;
                transform:translateY(0);
            }
            .nav-links a{width:100%;text-align:left}
            .btn-primary{margin-left:0 !important}
            .container{padding-top:35px}
            .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        }

        @media (max-width:680px){
            .navbar{min-height:68px;padding:11px 17px}
            .logo{font-size:17px}
            .container{width:min(100% - 28px,560px);padding:26px 0 60px}
            .tabs{
                width:100%;
                overflow-x:auto;
                scrollbar-width:none;
                margin:24px 0 18px;
            }
            .tabs::-webkit-scrollbar{display:none}
            .tab{flex:1;text-align:center;padding:11px 13px;font-size:12px}
            .filter-panel{
                align-items:flex-start;
                flex-direction:column;
                gap:11px;
            }
            select{width:100%}
            .grid{grid-template-columns:1fr;gap:18px}
            .meal-image{height:200px}
            .card{border-radius:21px}
        }
    </style>
</head>
<body>
    <header class="navbar">
        <a class="logo" href="index.php">🥗 <span>AI Diet Planner</span></a>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact Us</a>
            <a href="auth/login.php">Sign In</a>
            <a href="auth/register.php" class="btn-primary">Get Started</a>
        </nav>
    </header>
    
    <div class="container">
        <div class="tabs">
            <a href="browse.php?type=meals" class="tab <?= $type==='meals'?'active':'' ?>">🥗 Meal Plans</a>
            <a href="browse.php?type=nutritionists" class="tab <?= $type==='nutritionists'?'active':'' ?>">👩‍⚕️ Nutritionists</a>
        </div>

        <?php if($type === 'meals'): ?>

        <div class="filter-panel reveal">
            <form method="GET">
                <input type="hidden" name="type" value="meals">
                <select name="goal" onchange="this.form.submit()">
                    <option value="">All Goals</option>
                    <option value="Weight Loss" <?= $goal=='Weight Loss'?'selected':'' ?>>Weight Loss</option>
                    <option value="Muscle Building" <?= $goal=='Muscle Building'?'selected':'' ?>>Muscle Building</option>
                    <option value="Balanced" <?= $goal=='Balanced'?'selected':'' ?>>Balanced</option>
                </select>
            </form>
        </div>

        <div class="grid">
        <?php
        if ($goal) {
            $sql = "SELECT * FROM meal_plans WHERE goal = ? ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$goal]);
        } else {
            $sql = "SELECT * FROM meal_plans ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }

        $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(!empty($meals)):
            foreach($meals as $row):
                $img = (!empty($row['photo']) && file_exists($row['photo'])) ? $row['photo'] : 'uploads/default_meal.jpg';
        ?>
            <article class="card meal-card reveal">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="meal-image">
                <span class="tag"><?= htmlspecialchars($row['goal']) ?></span>
                <h3><?= htmlspecialchars($row['title']) ?></h3>
                <p><?= htmlspecialchars(substr($row['description'] ?? '', 0, 80)) ?>...</p>
                <div class="calories"><span>🔥</span> <?= (int)$row['calories'] ?> kcal/day</div>
                <a href="auth/login.php" class="btn">View Plan</a>
            </article>
        <?php 
            endforeach; 
        else: 
        ?>
            <div class="no-results">No meal plans found.</div>
        <?php endif; ?>
        </div>

        <?php else: ?>

        <div class="grid">
        <?php
        // Fixed SQL query to support active, approved, or null status nutritionists
        if ($specialty) {
            $sql = "SELECT * FROM nutritionist WHERE certification=? AND (status='approved' OR status='active' OR status IS NULL) ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$specialty]);
        } else {
            $sql = "SELECT * FROM nutritionist WHERE status='approved' OR status='active' OR status IS NULL ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }
        
        $nutritionists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(!empty($nutritionists)):
            foreach($nutritionists as $row):
                $filename = trim($row['profile_pic'] ?? '');
                $has_valid_image = false;
                $profile_img = "";

                if (!empty($filename) && $filename !== 'default_avatar.png') {
                    if (strpos($filename, 'http') === 0 || strpos($filename, '/') === 0) {
                        $profile_img = $filename;
                        $has_valid_image = true;
                    } else {
                        if (file_exists("uploads/profile_pics/" . $filename)) {
                            $profile_img = "uploads/profile_pics/" . $filename;
                            $has_valid_image = true;
                        } else if (file_exists($filename)) {
                            $profile_img = $filename;
                            $has_valid_image = true;
                        }
                    }
                }
        ?>
            <article class="card nutritionist-card reveal">
                <?php if ($has_valid_image): ?>
                    <img src="<?= htmlspecialchars($profile_img) ?>" class="avatar" alt="<?= htmlspecialchars($row['fullname']) ?>">
                <?php else: ?>
                    <div class="avatar-initials">
                        <?= getInitials($row['fullname']) ?>
                    </div>
                <?php endif; ?>

                <h3><?= htmlspecialchars($row['fullname']) ?></h3>
                <p><?= htmlspecialchars($row['certification']) ?></p>
                <div class="experience">
                    <?= (int)$row['experience'] ?> years experience
                </div>
                <a href="auth/login.php" class="btn">Consult Expert <span>→</span></a>
            </article>
        <?php 
            endforeach; 
        else: 
        ?>
            <div class="no-results">No nutritionists found.</div>
        <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('.navbar');

    // Responsive mobile navigation
    const navLinks = document.querySelector('.nav-links');
    if (navLinks) {
        const toggle = document.createElement('button');
        toggle.className = 'mobile-toggle';
        toggle.type = 'button';
        toggle.setAttribute('aria-label', 'Toggle navigation');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<span></span><span></span><span></span>';
        navbar.appendChild(toggle);

        toggle.addEventListener('click', () => {
            const open = navLinks.classList.toggle('open');
            toggle.classList.toggle('active', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('open');
                toggle.classList.remove('active');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', e => {
            if (!navbar.contains(e.target)) {
                navLinks.classList.remove('open');
                toggle.classList.remove('active');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Premium navbar state on scroll
    const updateNavbar = () => {
        if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 12);
    };
    updateNavbar();
    window.addEventListener('scroll', updateNavbar, { passive: true });

    // Scroll reveal
    const revealItems = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });
        revealItems.forEach(item => observer.observe(item));
    } else {
        revealItems.forEach(item => item.classList.add('visible'));
    }

    // Subtle stagger for cards
    document.querySelectorAll('.card.reveal').forEach((card, index) => {
        card.style.transitionDelay = `${Math.min(index * 55, 330)}ms`;
    });
});
</script>
</body>
</html>