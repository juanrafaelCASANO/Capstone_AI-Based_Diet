  <?php
  session_start();
  require_once '../config.php';

  if (!isset($_SESSION['user_id'])) {
      header("Location: ../auth/login.php");
      exit;
  }

  $user_id = $_SESSION['user_id'];

  $user = $conn->query("SELECT fullname FROM users WHERE id=$user_id")->fetch_assoc();
  ?>
  <!DOCTYPE html>
  <html>
  <head>
  <title>AI-Based Diet & Nutritional Planner</title>
      
      <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
  <style>
  body{font-family:Arial;background:#f4f7ff;margin:0}
  .sidebar{
    width:220px;height:100vh;position:fixed;
    background:#1e3a8a;color:white;padding:20px
  }
  .sidebar a{display:block;color:white;text-decoration:none;margin:15px 0}
  .main{margin-left:240px;padding:30px}
  .card{background:white;padding:20px;border-radius:12px;margin-bottom:20px}
  button{padding:12px 18px;background:#2563eb;color:white;border:none;border-radius:8px}
  </style>
  </head>
  <body>

  <div class="sidebar">
    <h3>AI Diet Planner</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="generate_weekly.php">📅 Weekly Meal Plan</a>
    <a href="chat.php">💬 Nutritionist</a>
    <a href="../index.php">🚪 Logout</a>
  </div>

  <div class="main">
    <h2>Welcome, <?php echo htmlspecialchars($user['fullname']); ?> 👋</h2>

    <div class="card">
      <h3>Your AI Meal Plan</h3>
      <p>Generate a weekly Filipino-based diet plan tailored to your goals.</p>
      <a href="generate_weekly.php">
        <button>Generate Weekly Plan</button>
      </a>
    </div>

    <div class="card">
      <h3>Consult a Nutritionist</h3>
      <p>Chat with a licensed nutritionist to improve your plan.</p>
      <a href="chat.php">
        <button>Open Chat</button>
      </a>
    </div>
  </div>

  </body>
  </html>
