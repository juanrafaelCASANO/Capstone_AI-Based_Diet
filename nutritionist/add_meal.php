<?php
session_start();
require_once '../config.php';

// Check logged-in user
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$nutri_id = $_SESSION['user_id'];$message = "";

// Handle meal creation
if (isset($_POST['add_meal'])) {$title = trim($_POST['title'] ?? '');$description = trim($_POST['description'] ?? '');$goal = $_POST['goal'] ?? 'Balanced';$calories = (int)($_POST['calories'] ?? 0);          // Handle File Upload$photo_path = "";
    if (!empty($_FILES['photo']['name'])) {$target_dir = "../uploads/meals/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . "_" . basename($_FILES["photo"]["name"]);
        $target_file = $target_dir .$file_name;
        
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $photo_path = "uploads/meals/" . $file_name;
        }
    }

    // Fetch the next ID value manually for PostgreSQL
    $id_stmt =$conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM meal_plans");
    $next_id =$id_stmt->fetchColumn();

    // Insert into meal_plans
    $stmt =$conn->prepare("INSERT INTO meal_plans (id, title, description, goal, calories, photo) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$next_id,$title, $description,$goal, $calories,$photo_path])) {
        header("Location: dashboard.php?msg=added");
        exit();
    } else {
        $message = "Error occurred while adding meal.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Meal Plan | Nutritionist Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0f3a2e;
            --teal: #0d9488;
            --cream: #f4fbf7;
            --card: #ffffff;
            --text: #112d25;
            --border: #dbece5;
            --radius: 18px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--text); }
        .app { min-height: 100vh; display: flex; }
        
        /* SIDEBAR */
        .sidebar {
            width: 260px; flex: 0 0 260px; min-height: 100vh; position: sticky; top: 0;
            background: linear-gradient(180deg, var(--navy), #0a2820); color: #fff; padding: 24px 18px; display: flex; flex-direction: column;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding: 6px 10px 28px; }
        .brand-icon { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: rgba(255,255,255,.12); font-size: 23px; }
        .brand strong { display: block; font-size: 17px; } .brand span { display: block; color: #99f6e4; font-size: 12px; }
        .nav-label { padding: 0 12px 8px; color: #80e0d0; text-transform: uppercase; font-size: 10px; font-weight: 800; }
        .nav { display: grid; gap: 6px; }
        .nav a { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 13px 12px; border-radius: 12px; color: #e6fffa; font-size: 14px; font-weight: 600; }
        .nav a.active { background: rgba(255,255,255,.14); color: #fff; }
        
        /* MAIN WORKSPACE */
        .main { flex: 1; padding: 36px 48px; }
        .page-title { font-size: 24px; font-weight: 800; margin-bottom: 24px; color: var(--text); }
        
        /* TWO COLUMN GRID */
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            align-items: start;
        }

        /* FORM CARD */
        .form-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
        .form-card h2 { margin-bottom: 20px; font-size: 20px; font-weight: 700; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 11px 14px; border-radius: 10px; border: 1px solid var(--border); font-size: 14px; font-family: inherit; outline: none; transition: .2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--teal); box-shadow: 0 0 0 3px rgba(13,148,136,0.12);
        }
        .btn { background: var(--teal); color: #fff; border: none; padding: 12px; width: 100%; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 15px; margin-top: 10px; transition: .2s; }
        .btn:hover { background: #0f766e; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 14px; }
        .alert-error { color: #dc2626; background: #fef2f2; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }

        /* PREVIEW CARD STYLING */
        .preview-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); sticky; top: 36px; }
        .preview-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; border-bottom: 1px solid #eef2f7; padding-bottom: 12px; }
        .preview-header h3 { font-size: 16px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .preview-badge-live { background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px; }

        /* PREVIEW DISPLAY BOX */
        .meal-preview-box { border: 1px dashed var(--border); border-radius: 14px; padding: 20px; background: #fafdfb; text-align: center; }
        .preview-img-wrapper { width: 100%; height: 200px; border-radius: 12px; background: #e2e8f0; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid #e2e8f0; }
        .preview-img-wrapper img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .preview-img-wrapper .placeholder-icon { font-size: 36px; color: #94a3b8; }
        
        .preview-title { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 8px; word-break: break-word; }
        .preview-goal { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; margin-bottom: 14px; }
        .preview-calories { font-size: 15px; font-weight: 800; color: #111827; margin-bottom: 14px; }
        .preview-calories span { font-weight: 600; color: #64748b; font-size: 12px; }
        .preview-desc { font-size: 13px; color: #64748b; line-height: 1.5; text-align: left; background: #ffffff; padding: 12px; border-radius: 10px; border: 1px solid #f1f5f9; min-height: 60px; word-break: break-word; }

        @media (max-width: 900px) {
            .grid-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div class="brand"><div class="brand-icon">🩺</div><div><strong>NutriPanel</strong><span>Nutritionist portal</span></div></div>
    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a class="active" href="add_meal.php"><span class="nav-icon">🍲</span>Add Meal</a>
      <a href="inbox.php"><span class="nav-icon">📥</span>Inbox</a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>
  </aside>

  <main class="main">
    <h1 class="page-title">Add Meal Plan</h1>
    
    <div class="grid-container">
        <!-- FORM SECTION -->
        <div class="form-card">
            <h2>Meal Details</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert-error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Meal Title</label>
                    <input type="text" id="inputTitle" name="title" required placeholder="e.g. Keto Breakfast">
                </div>
                
                <div class="form-group">
                    <label>Goal</label>
                    <select id="inputGoal" name="goal">
                        <option value="Weight Loss">Weight Loss</option>
                        <option value="Muscle Building">Muscle Building</option>
                        <option value="Balanced" selected>Balanced</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Calories (kcal)</label>
                    <input type="number" id="inputCalories" name="calories" required placeholder="e.g. 450">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="inputDescription" name="description" rows="4" placeholder="Brief description of ingredients or preparation..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Meal Photo</label>
                    <input type="file" id="inputPhoto" name="photo" accept="image/*">
                </div>
                
                <button type="submit" name="add_meal" class="btn">Save Meal Plan</button>
                <a href="dashboard.php" class="btn-back">Cancel</a>
            </form>
        </div>

        <!-- PREVIEW SECTION -->
        <div class="preview-card">
            <div class="preview-header">
                <h3>Card Preview</h3>
                <span class="preview-badge-live"><i class="fa-solid fa-eye"></i> Live View</span>
            </div>

            <div class="meal-preview-box">
                <div class="preview-img-wrapper">
                    <i class="fa-solid fa-utensils placeholder-icon" id="previewPlaceholder"></i>
                    <img id="previewImg" src="" alt="Meal Photo Preview">
                </div>

                <div class="preview-title" id="previewTitle">Untitled Meal</div>

                <div class="preview-goal" id="previewGoal">
                    <i class="fa-solid fa-bullseye"></i> Balanced
                </div>

                <div class="preview-calories" id="previewCalories">
                    0 <span>kcal</span>
                </div>

                <div class="preview-desc" id="previewDesc">
                    Meal description will appear here as you type...
                </div>
            </div>
        </div>
    </div>
  </main>
</div>

<script>
    // Elements
    const inputTitle = document.getElementById('inputTitle');
    const inputGoal = document.getElementById('inputGoal');
    const inputCalories = document.getElementById('inputCalories');
    const inputDescription = document.getElementById('inputDescription');
    const inputPhoto = document.getElementById('inputPhoto');

    const previewTitle = document.getElementById('previewTitle');
    const previewGoal = document.getElementById('previewGoal');
    const previewCalories = document.getElementById('previewCalories');
    const previewDesc = document.getElementById('previewDesc');
    const previewImg = document.getElementById('previewImg');
    const previewPlaceholder = document.getElementById('previewPlaceholder');

    // Title Sync
    inputTitle.addEventListener('input', () => {
        previewTitle.textContent = inputTitle.value.trim() || 'Untitled Meal';
    });

    // Goal Sync
    inputGoal.addEventListener('change', () => {
        previewGoal.innerHTML = `<i class="fa-solid fa-bullseye"></i> ${inputGoal.value}`;
    });

    // Calories Sync
    inputCalories.addEventListener('input', () => {
        const val = inputCalories.value ? Number(inputCalories.value).toLocaleString() : '0';
        previewCalories.innerHTML = `${val} <span>kcal</span>`;
    });

    // Description Sync
    inputDescription.addEventListener('input', () => {
        previewDesc.textContent = inputDescription.value.trim() || 'Meal description will appear here as you type...';
    });

    // Image Upload Sync
    inputPhoto.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
                previewPlaceholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.src = '';
            previewImg.style.display = 'none';
            previewPlaceholder.style.display = 'block';
        }
    });
</script>

</body>
</html>