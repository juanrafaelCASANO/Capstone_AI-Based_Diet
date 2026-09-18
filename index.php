<?php 
require_once 'config.php'; 

// Fetch approved nutritionists
$sql = "SELECT id, fullname, certification, experience, profile_pic FROM nutritionist WHERE status = 'approved' LIMIT 10";
$result = $conn->query($sql);
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
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f4f7ff; 
            color: #0f172a; 
            overflow-x: hidden; 
        }
        a { 
            text-decoration: none; 
        }
        .navbar { 
            background: #fff; 
            padding: 15px 50px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,.05); 
            position: sticky; 
            top:0; 
            z-index: 1000; 
        }
        .logo { 
            display: flex; 
            align-items: center; 
            font-weight: 800;
            font-size: 22px; 
            color: #2563eb; 
        }
        .logo span { 
            margin-left: 10px; 
        }
        .nav-links a { 
            margin-left: 25px; 
            font-weight: 500; 
            color: #334155; 
        }
        .btn-primary { 
            background: #2563eb; 
            color: #fff; 
            padding: 10px 20px; 
            border-radius: 10px; 
            font-weight: 600; 
            display: inline-block; 
            border: none; 
            cursor: pointer; 
        }

        /* HERO SECTION */
        .hero { 
            padding: 80px 60px; 
            display: grid; 
            grid-template-columns: 1.2fr 1fr; 
            gap: 60px; 
            align-items: center; 
        }
        .hero h1 { 
            font-size: 56px; 
            line-height: 1.1; 
            margin-bottom: 20px; 
        }
        .hero p { 
            font-size: 18px; 
            color: #475569; 
            margin-bottom: 35px; 
        }
        .btn-outline { 
            border: 2px solid #2563eb; 
            padding: 12px 22px; 
            border-radius: 10px; 
            font-weight: 600; 
            color: #2563eb; 
        }
        .hero-card { 
            background: #fff; 
            border-radius: 30px; 
            padding: 40px; 
            display: flex; 
            justify-content: center; 
            box-shadow: 0 30px 60px rgba(37,99,235,.15);
         }
        .hero-card img { 
            max-width: 260px; 
        }

        /* SCROLLING SECTION (FEATURES) */
        .features { 
            background: #fff; 
            padding: 80px 60px; 
            position: relative; 
        }
        .features h2 { 
            font-size: 32px; 
            font-weight: 800; 
        }
        
        /* SCROLL LOGIC */
        .scroll-wrapper { 
            position: relative; 
            display: flex; 
            align-items: center; 
            margin-top: 30px; 
        }
        .scroll-container { 
            display: flex; 
            overflow-x: auto; 
            gap: 25px; 
            padding: 20px 10px 40px 10px; 
            scroll-behavior: smooth; 
            width: 100%; 
        }
        .scroll-container::-webkit-scrollbar { 
            display: none; 
        }
        .nav-btn { 
            position: absolute; 
            top: 50%; 
            transform: translateY(-50%); 
            width: 45px; 
            height: 45px; 
            background: #fff; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            cursor: pointer; 
            z-index: 10; 
            border: 1px solid #e2e8f0; color: #2563eb; 
            font-size: 20px; 
            font-weight: bold; 
            transition: all 0.3s ease; 
        }
        .nav-btn:hover { 
            background: #2563eb; 
            color: #fff; 
        }
        .prev-btn { 
            left: -20px; 
        }
        .next-btn { 
            right: -20px; 
        }
        
        .feature-box { 
            flex: 0 0 300px; 
            background: #fff; 
            padding: 35px 25px; 
            border-radius: 24px; 
            text-align: center; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            transition: transform 0.3s ease; 
            border: 1px solid #f1f5f9; 
        }
        .feature-box:hover { 
            transform: translateY(-10px); 
            box-shadow: 0 20px 40px rgba(37,99,235,0.1); 
        }
        .expert-img { 
            width: 100px; 
            height: 100px; 
            margin-bottom: 20px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 4px solid #e0e7ff; 
        }
        .expert-name { 
            font-size: 20px; 
            font-weight: 700; 
            margin-bottom: 5px; 
            color: #1e293b; 
        }
        .expert-specialty { 
            color: #64748b; 
            font-size: 14px; 
            margin-bottom: 15px; 
            min-height: 40px; 
        }
        .expert-meta { 
            background: #eff6ff; color: #2563eb; 
            padding: 6px 12px; 
            border-radius: 12px; 
            font-size: 13px; 
            font-weight: 600; 
        }
        
        .hover-zone { 
            position: absolute; 
            top: 0; 
            bottom: 0; 
            width: 100px; 
            z-index: 5; 
            cursor: pointer; 
        }
        .hover-right { 
            right: 0; 
        }
        .hover-left { 
            left: 0; 
        }

        /* HOW IT WORKS SECTION (Adjusted for consistency) */
        .how-it-works { 
            padding: 80px 60px; 
            background: #fff; 
            text-align: center; 
            border-top: 1px solid #f1f5f9; 
        }
        .section-header { 
            margin-bottom: 50px; 
        }
        .section-header span { 
            color: #2563eb; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-size: 14px; 
        }
        .section-header h2 { 
            font-size: 32px; 
            font-weight: 800; 
            margin: 10px 0; 
        }
        .section-header p { 
            color: #64748b; 
            font-size: 18px; 
        }
        
        .steps-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 30px; max-width: 1200px; 
            margin: 0 auto; 
        }
        .step-card { 
            background: #f8fafc; 
            padding: 45px 30px; 
            border-radius: 24px; 
            position: relative; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.02); 
            transition: transform 0.3s ease; 
            border: 1px solid #f1f5f9; 
        }
        .step-card:hover { 
            transform: translateY(-5px); 
        }
        .step-number { 
            position: absolute; 
            top: -20px; 
            left: 50%; 
            transform: translateX(-50%); 
            background: #2563eb; 
            color: #fff; 
            width: 35px; 
            height: 35px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 800; 
            font-size: 14px; 
            box-shadow: 0 4px 10px rgba(37,99,235,0.3); 
        }
        .step-icon { 
            font-size: 45px; 
            margin-bottom: 20px; 
        }
        .step-card h3 { 
            font-size: 22px; 
            margin-bottom: 12px; 
            color: #1e293b; 
        }
        .step-card p { 
            color: #64748b; 
            line-height: 1.6; 
            font-size: 15px; 
        }

        @media(max-width: 900px) {
            .hero { 
                grid-template-columns: 1fr; 
                text-align: center; 
                padding: 40px 20px; 
            }
            .features, .how-it-works { 
                padding: 40px 20px; 
            }
            .nav-btn { 
                display: none; 
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">🥗 <span>AI Diet Planner</span></div>
    <div class="nav-links">
        <a href="browse.php">Browse</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact Us</a>
        <a href="auth/login.php">Sign In</a>
        <a href="auth/register.php" class="btn-primary">Get Started</a>
    </div>
</div>

<section class="hero">
    <div>
        <h1>Plan Your Diet with AI</h1>
        <p>Get personalized meal plans, nutrition guidance, and expert consultations powered by Artificial Intelligence.</p>
        <div class="hero-buttons">
            <a href="auth/login.php" class="btn-primary">Get My Meal Plan</a>
            <a href="auth/login.php" class="btn-outline">Consult a Nutritionist</a>
        </div>
    </div>
    <div class="hero-card">
        <img src="https://cdn-icons-png.flaticon.com/512/706/706164.png" alt="AI Nutrition">
    </div>
</section>

<section class="features" id="features">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Featured Nutritionists</h2>
        <a href="browse.php?type=nutritionists" style="color:#2563eb; font-weight:700;">View All →</a>
    </div>

    <div class="scroll-wrapper">
        <div class="nav-btn prev-btn" id="prevBtn">❮</div>
        <div class="nav-btn next-btn" id="nextBtn">❯</div>
        <div class="hover-zone hover-left" id="hoverLeft"></div>
        <div class="hover-zone hover-right" id="hoverRight"></div>
        <div class="scroll-container" id="scrollContainer">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $img = !empty($row['profile_pic']) ? "uploads/profile_pics/".$row['profile_pic'] : "uploads/default_avatar.jpg";
                ?>
                    <div class="feature-box">
                        <img src="<?= $img ?>" class="expert-img">
                        <div class="expert-name"><?= htmlspecialchars($row['fullname']) ?></div>
                        <div class="expert-specialty"><?= htmlspecialchars($row['certification']) ?></div>
                        <div style="margin-bottom: 20px;">
                            <span class="expert-meta"><?= (int)$row['experience'] ?> Years Experience</span>
                        </div>
                        <a href="nutritionist_profile.php?id=<?= $row['id'] ?>" class="btn-primary" style="width:100%; text-align:center;">View Profile</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #64748b;">No experts are currently available.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="how-it-works">
    <div class="section-header">
        <span>Process</span>
        <h2>How It Works</h2>
        <p>Get started on your health journey in three simple steps.</p>
    </div>

    <div class="steps-container">
        <div class="step-card">
            <div class="step-number">1</div>
            <div class="step-icon">📝</div>
            <h3>Create Profile</h3>
            <p>Tell us about your goals, allergies, and food preferences so our AI can understand your needs.</p>
        </div>
        <div class="step-card">
            <div class="step-number">2</div>
            <div class="step-icon">🤖</div>
            <h3>AI Generation</h3>
            <p>Our AI analyzes your data to create a custom meal plan optimized for your specific requirements.</p>
        </div>
        <div class="step-card">
            <div class="step-number">3</div>
            <div class="step-icon">🥗</div>
            <h3>Expert Guidance</h3>
            <p>Connect with certified nutritionists to fine-tune your plan and stay on track with professional support.</p>
        </div>
    </div>
</section>

<script>
    const container = document.getElementById('scrollContainer');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const hoverRight = document.getElementById('hoverRight');
    const hoverLeft = document.getElementById('hoverLeft');

    let scrollInterval;

    nextBtn.addEventListener('click', () => {
        container.scrollBy({ left: 325, behavior: 'smooth' });
    });

    prevBtn.addEventListener('click', () => {
        container.scrollBy({ left: -325, behavior: 'smooth' });
    });

    function startScrolling(direction) {
        scrollInterval = setInterval(() => {
            container.scrollLeft += direction;
        }, 10);
    }

    function stopScrolling() {
        clearInterval(scrollInterval);
    }

    hoverRight.addEventListener('mouseenter', () => startScrolling(5));
    hoverRight.addEventListener('mouseleave', stopScrolling);
    hoverLeft.addEventListener('mouseenter', () => startScrolling(-5));
    hoverLeft.addEventListener('mouseleave', stopScrolling);
</script>

</body>
</html>