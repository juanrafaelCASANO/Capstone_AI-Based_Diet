    <?php
    $conn = new mysqli('localhost','root','','ai_diet_planner');
    if ($conn->connect_error) die('DB Error');

    $type = $_GET['type'] ?? 'meals';
    $goal = $_GET['goal'] ?? '';
    $specialty = $_GET['specialty'] ?? '';
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

        .navbar{ 
            background: #fff; 
            padding: 15px 50px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,.05); 
            position: sticky; 
            top:0; z-index: 1000; 
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

        .header { 
            padding:25px 60px; 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
        }

        .container{ 
            max-width:1200px; 
            margin:auto; 
            padding:20px 40px 
        }

        h1{ 
            font-size:40px; 
            font-weight:800 
        }

        .tabs{ 
            display:flex; 
            gap:20px; 
            margin:30px 0 
        }

        .tab{ 
            padding:14px 26px; 
            border-radius:14px; 
            font-weight:600; 
            background:#e0e7ff; 
            color:#2563eb; 
            text-decoration:none; 
        }

        .tab.active{ 
            background:#2563eb; 
            color:#fff 
        }

        .grid{ 
            display:grid; 
            grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); 
            gap:30px 
        }

        .card{
            background:#fff;
            padding:30px;
            border-radius:22px;
            box-shadow:0 20px 50px rgba(0,0,0,.08);
            transition: transform .3s ease, box-shadow .3s ease;
        }
        .card:hover{
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(37,99,235,.2);
            cursor:pointer;
        }
        
        .card h3{
            margin:10px 0 
        }

        .tag{ 
            background:#e0e7ff; 
            color:#2563eb; 
            padding:6px 14px; 
            border-radius:12px; 
            font-size:12px 
        }

        .btn{ 
            display:inline-block; 
            margin-top:15px; 
            background:#2563eb; 
            color:#fff; 
            padding:12px 22px; 
            border-radius:14px; 
            font-weight:600; 
            text-decoration:none; 
        }

        .avatar{ 
            width:120px; 
            height:120px; 
            border-radius:50%; 
            object-fit:cover; 
            border: 4px solid #e0e7ff; 
        }

        .price{ 
            color:#2563eb;
            font-weight:700 
        }

        .filter{ 
            margin-bottom:30px 
        }

        select{ 
            padding:12px;
            border-radius:10px;
            border:1px solid #cbd5e1 
        }

        .no-results{
            grid-column:1/-1;
            text-align:center;
            padding:50px;
            background:#fff;
            border-radius:20px;
            color:#64748b;
            font-weight:600;
        }
    </style>
    </head>

    <body>

    <div class="navbar">
    <div class="logo">🥗 <span>AI Diet Planner</span></div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact Us</a>
        <a href="auth/login.php">Sign In</a>
        <a href="auth/register.php" class="btn-primary">Get Started</a>
    </div>
</div>

    <div class="container">
    <h1>Browse</h1>
    <p style="color:#64748b">Explore meal plans and nutritionists before getting started.</p>

    <div class="tabs">
        <a href="browse.php?type=meals" class="tab <?= $type==='meals'?'active':'' ?>">🥗 Meal Plans</a>
        <a href="browse.php?type=nutritionists" class="tab <?= $type==='nutritionists'?'active':'' ?>">👩‍⚕️ Nutritionists</a>
    </div>

    <?php if($type === 'meals'): ?>

    <div class="filter">
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
    $sql = $goal ? "SELECT * FROM meal_plans WHERE goal = ?" : "SELECT * FROM meal_plans";
    $stmt = $conn->prepare($sql);
    if ($goal) $stmt->bind_param("s",$goal);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows>0):
    while($row=$res->fetch_assoc()):
    $img = $row['photo'] ?: 'uploads/default_meal.jpg';
    ?>
    <div class="card">
        <img src="<?= htmlspecialchars($img) ?>" style="width:100%;height:180px;object-fit:cover;border-radius:16px;margin-bottom:15px;">
        <span class="tag"><?= htmlspecialchars($row['goal']) ?></span>
        <h3><?= htmlspecialchars($row['title']) ?></h3>
        <p><?= htmlspecialchars(substr($row['description'],0,80)) ?>...</p>
        <strong><?= (int)$row['calories'] ?> kcal/day</strong><br>
        <a href="auth/register.php" class="btn">View Plan</a>
    </div>
    <?php endwhile; else: ?>
    <div class="no-results">No meal plans found.</div>
    <?php endif; ?>
    </div>

    <?php else: ?>



    <div class="grid">
    <?php
    // Ensure we fetch the profile_pic column
    if ($specialty) {
        $sql = "SELECT * FROM nutritionist WHERE certification=? AND status='approved'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s",$specialty);
    } else {
        $sql = "SELECT * FROM nutritionist WHERE status='approved'";
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows>0):
    while($row=$res->fetch_assoc()):
        // Check if nutritionist has a profile pic, otherwise use default
        $profile_img = !empty($row['profile_pic']) ? 'uploads/profile_pics/'.$row['profile_pic'] : 'uploads/default_avatar.jpg';
    ?>
    <div class="card" style="text-align:center">
        <img src="<?= htmlspecialchars($profile_img) ?>" class="avatar" alt="Profile Picture"><br>
        
        <h3><?= htmlspecialchars($row['fullname']) ?></h3>
        <p><?= htmlspecialchars($row['certification']) ?></p>
        <div style="color:#475569;font-weight:600">
            <?= (int)$row['experience'] ?> years experience
        </div>
        <a href="nutritionist_profile.php?id=<?= $row['id'] ?>" class="btn">View Profile</a>
    </div>
    <?php endwhile; else: ?>
    <div class="no-results">No nutritionists found.</div>
    <?php endif; ?>
    </div>

    <?php endif; ?>

    </div>
    </body>
    </html>