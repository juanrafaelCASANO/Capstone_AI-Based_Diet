<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$nutri_id = $_SESSION['user_id'];$message = "";

if (isset($_POST['add_meal'])) {$title = trim($_POST['title'] ?? '');$description = trim($_POST['description'] ?? '');$goal = $_POST['goal'] ?? 'Balanced';$calories = (int)($_POST['calories'] ?? 0);$photo_path = "";
    
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

    $id_stmt =$conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM meal_plans");
    $next_id =$id_stmt->fetchColumn();

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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--cream); color: var(--text); overflow: hidden; }
        .app { height: 100vh; display: flex; overflow: hidden; }
        
        /* SIDEBAR - Fixed */
        .sidebar {
            width: 260px; flex: 0 0 260px; height: 100vh; position: sticky; top: 0;
            background: linear-gradient(180deg, var(--navy), #0a2820); color: #fff; padding: 24px 18px; 
            display: flex; flex-direction: column; overflow: hidden; z-index: 20;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding: 6px 10px 28px; flex-shrink: 0; }
        .brand-icon { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: rgba(255,255,255,.12); font-size: 23px; }
        .brand strong { display: block; font-size: 17px; font-family: 'Plus Jakarta Sans', sans-serif; } 
        .brand span { display: block; color: #99f6e4; font-size: 12px; margin-top: 2px; font-family: 'Plus Jakarta Sans', sans-serif; }
        .nav-label { padding: 0 12px 8px; color: #80e0d0; text-transform: uppercase; font-size: 10px; font-weight: 800; letter-spacing: .11em; flex-shrink: 0; }
        .nav { display: grid; gap: 6px; flex-shrink: 0; }
        .nav a { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 13px 12px; border-radius: 12px; color: #e6fffa; font-size: 14px; font-weight: 650; font-family: 'Plus Jakarta Sans', sans-serif; }
        .nav a.active { background: rgba(255,255,255,.14); color: #fff; }
        .nav-icon { width: 24px; text-align: center; font-size: 17px; }

        .sidebar-bottom { margin-top: auto; border-top: 1px solid rgba(255,255,255,.1); padding-top: 16px; flex-shrink: 0; }
        .sidebar-bottom a.logout {
            display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 13px 12px;
            border-radius: 12px; color: #f8d7da !important; font-size: 14px; font-weight: 650;
            font-family: 'Plus Jakarta Sans', sans-serif; transition: background .2s, transform .2s;
        }
        .sidebar-bottom a.logout:hover { background: rgba(220,53,69,.2); transform: translateX(2px); }
        
        /* MAIN WORKSPACE */
        .main { flex: 1; padding: 36px 48px; height: 100vh; overflow-y: auto; }
        .page-title { font-size: 24px; font-weight: 800; margin-bottom: 24px; color: var(--text); }
        
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            align-items: start;
            padding-bottom: 40px;
        }

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

        .preview-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
        .preview-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; border-bottom: 1px solid #eef2f7; padding-bottom: 12px; }
        .preview-header h3 { font-size: 16px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .preview-badge-live { background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px; }

        .meal-preview-box { border: 1px dashed var(--border); border-radius: 14px; padding: 20px; background: #fafdfb; text-align: center; }
        .preview-img-wrapper { width: 100%; height: 200px; border-radius: 12px; background: #e2e8f0; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 1px solid #e2e8f0; }
        .preview-img-wrapper img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .preview-img-wrapper .placeholder-icon { font-size: 36px; color: #94a3b8; }
        
        .preview-title { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 8px; word-break: break-word; }
        .preview-goal { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; margin-bottom: 14px; }
        .preview-calories { font-size: 15px; font-weight: 800; color: #111827; margin-bottom: 14px; }
        .preview-calories span { font-weight: 600; color: #64748b; font-size: 12px; }
        .preview-desc { font-size: 13px; color: #64748b; line-height: 1.5; text-align: left; background: #ffffff; padding: 12px; border-radius: 10px; border: 1px solid #f1f5f9; min-height: 60px; word-break: break-word; }

        /* LOGOUT MODAL STYLES */
        .logout-modal-overlay {
          position: fixed; inset: 0; background: rgba(15, 58, 46, 0.5); backdrop-filter: blur(4px);
          display: flex; align-items: center; justify-content: center; z-index: 9999;
          opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .logout-modal-overlay.active { opacity: 1; visibility: visible; }
        .logout-modal-card {
          background: #ffffff; width: 90%; max-width: 380px; border-radius: 20px; padding: 28px 24px;
          text-align: center; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: scale(0.85);
          transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
        .logout-modal-icon {
          width: 56px; height: 56px; background: #fef2f2; color: #dc2626; font-size: 26px;
          border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px;
        }
        .logout-modal-card h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #112d25; }
        .logout-modal-card p { margin: 0 0 24px; font-size: 14px; color: #5c736c; line-height: 1.5; }
        .logout-modal-actions { display: flex; gap: 12px; }
        .btn-modal-cancel {
          flex: 1; height: 44px; background: #f4fbf7; color: #112d25; border: 1px solid #dbece5;
          border-radius: 12px; font-weight: 650; font-size: 14px; cursor: pointer; transition: background 0.2s;
        }
        .btn-modal-cancel:hover { background: #e6fffa; }
        .btn-modal-logout {
          flex: 1; height: 44px; background: #dc2626; color: #ffffff; border: none; border-radius: 12px;
          font-weight: 650; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;
          text-decoration: none; transition: background 0.2s, transform 0.2s;
        }
        .btn-modal-logout:hover { background: #b91c1c; transform: translateY(-1px); }

        @media (max-width: 900px) {
            body { overflow: auto; }
            .app { height: auto; overflow: auto; }
            .sidebar { height: auto; position: relative; }
            .main { height: auto; overflow: visible; }
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
    <div class="sidebar-bottom">
      <a class="logout" href="javascript:void(0);" onclick="showLogoutModal();"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <h1 class="page-title">Add Meal Plan</h1>
    
    <div class="grid-container">
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

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" class="logout-modal-overlay">
  <div class="logout-modal-card">
    <div class="logout-modal-icon">🚪</div>
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to log out of your session?</p>
    <div class="logout-modal-actions">
      <button class="btn-modal-cancel" onclick="closeLogoutModal();">Cancel</button>
      <a href="../auth/logout.php" class="btn-modal-logout">Yes, Logout</a>
    </div>
  </div>
</div>

<script>
    function showLogoutModal() {
      document.getElementById('logoutModal').classList.add('active');
    }

    function closeLogoutModal() {
      document.getElementById('logoutModal').classList.remove('active');
    }

    document.getElementById('logoutModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeLogoutModal();
      }
    });

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

    inputTitle.addEventListener('input', () => {
        previewTitle.textContent = inputTitle.value.trim() || 'Untitled Meal';
    });

    inputGoal.addEventListener('change', () => {
        previewGoal.innerHTML = `<i class="fa-solid fa-bullseye"></i> ${inputGoal.value}`;
    });

    inputCalories.addEventListener('input', () => {
        const val = inputCalories.value ? Number(inputCalories.value).toLocaleString() : '0';
        previewCalories.innerHTML = `${val} <span>kcal</span>`;
    });

    inputDescription.addEventListener('input', () => {
        previewDesc.textContent = inputDescription.value.trim() || 'Meal description will appear here as you type...';
    });

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