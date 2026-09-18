    <?php /* get-started.php */ ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>AI-Based Diet & Nutritional Planner</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body {
        font-family:'Inter',sans-serif;
        background:#f4f7ff;
        color:#0f172a;
    }
    a { text-decoration:none; }

    /* HEADER */
    .header {
        padding:25px 60px;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    .logo {
        font-size:22px;
        font-weight:800;
        color:#2563eb;
    }
    .back {
        font-size:16px;
        color:#2563eb;
    }

    /* HERO */
    .hero {
        text-align:center;
        padding:30px 20px 40px;
    }
    .hero h1 {
        font-size:42px;
        font-weight:800;
        margin-bottom:10px;
    }
    .hero p {
        font-size:18px;
        color:#64748b;
    }

    /* CARDS */
    .cards {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
        gap:30px;
        padding:50px;
        max-width:1200px;
        margin:auto;
    }
    .card {
        background:#fff;
        padding:40px 30px;
        border-radius:24px;
        box-shadow:0 30px 60px rgba(0,0,0,.08);
        transition:.3s;
        text-align:center;
    }
    .card:hover {
        transform:translateY(-8px);
    }
    .icon {
        font-size:48px;
        color:#2563eb;
        margin-bottom:20px;
    }
    .card h3 {
        font-size:22px;
        margin-bottom:10px;
    }
    .card p {
        font-size:15px;
        color:#64748b;
        margin-bottom:25px;
    }
    .card ul {
        list-style:none;
        text-align:left;
        margin-bottom:30px;
    }
    .card ul li {
        margin-bottom:10px;
        font-size:14px;
    }
    .card ul li i {
        color:#22c55e;
        margin-right:10px;
    }

    /* BUTTON */
    .btn {
        display:inline-block;
        background:#2563eb;
        color:#fff;
        padding:14px 26px;
        border-radius:14px;
        font-weight:700;
    }

    /* FOOTER NOTE */
    .note {
        text-align:center;
        padding-bottom:50px;
        color:#94a3b8;
        font-size:14px;
    }
    /* MODAL */
    #modalOverlay {
        position:fixed;
        top:0; left:0;
        width:100%;
        height:100%;
        background:rgba(0,0,0,.45);
        display:flex;
        justify-content:center;
        align-items:center;
        z-index:999;
    }

    .modal {
        background:#fff;
        padding:40px;
        border-radius:24px;
        width:90%;
        max-width:420px;
        text-align:center;
        box-shadow:0 40px 80px rgba(0,0,0,.2);
        animation:pop .25s ease;
    }

    .modal h2 {
        margin-bottom:10px;
    }

    .modal p {
        color:#64748b;
        margin-bottom:25px;
    }

    .modal-actions {
        display:flex;
        gap:15px;
        justify-content:center;
    }

    .cancel {
        background:#e5e7eb;
        color:#0f172a;
    }

    @keyframes pop {
        from { transform:scale(.9); opacity:0; }
        to { transform:scale(1); opacity:1; }
    }

    </style>
    </head>

    <body>

    <!-- HEADER -->
    <div class="header">
        <div class="logo">AI Diet Planner</div>
        <a href="../index.php" class="back">← Back</a>
    </div>

    <!-- HERO -->
    <section class="hero">
        <h1>Get Started</h1>
        <p>Choose how you want to use the AI-Based Diet & Nutritional Planner</p>
    </section>

    <!-- CARDS -->
    <section class="cards">

        <!-- USER -->
        <div class="card">
            <div class="icon"><i class="fa-solid fa-user"></i></div>
            <h3>I'm a User</h3>
            <p>Get personalized diet plans powered by AI and nutrition experts.</p>

            <ul>
                <li><i class="fa-solid fa-check"></i> AI-generated meal plans</li>
                <li><i class="fa-solid fa-check"></i> Calorie & macro tracking</li>
                <li><i class="fa-solid fa-check"></i> Chat with nutritionists</li>
            </ul>

            <button class="btn" onclick="openModal('user')">Get Started</button>

        </div>

        <!-- NUTRITIONIST -->
        <div class="card">
            <div class="icon"><i class="fa-solid fa-user-doctor"></i></div>
            <h3>I'm a Nutritionist</h3>
            <p>Guide users with expert advice and AI-assisted planning.</p>

            <ul>
                <li><i class="fa-solid fa-check"></i> Manage client meal plans</li>
                <li><i class="fa-solid fa-check"></i> Real-time chat support</li>
                <li><i class="fa-solid fa-check"></i> Nutrition analytics</li>
            </ul>

            <button class="btn" onclick="openModal('nutritionist')">Join as Nutritionist</button>
        </div>

        

    </section>

    <div class="note">
        Start your healthier journey today with AI-powered nutrition 🌱
    </div>
    <!-- MODAL OVERLAY -->
    <div id="modalOverlay" style="display:none;">
        <div class="modal">
            <h2 id="modalTitle"></h2>
            <p id="modalDesc"></p>

            <div class="modal-actions">
                <button class="btn" onclick="continueFlow()">Continue</button>
                <button class="btn cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
    <script>
    let selectedRole = '';

    function openModal(role) {
        selectedRole = role;

        const title = {
            user: "Continue as User",
            nutritionist: "Continue as Nutritionist",
            admin: "Admin Access"
        };

        const desc = {
            user: "You’ll receive AI-powered meal plans and nutrition guidance.",
            nutritionist: "You’ll manage clients and provide expert diet plans.",
            admin: "You’ll manage users, meals, and system data."
        };

        document.getElementById("modalTitle").innerText = title[role];
        document.getElementById("modalDesc").innerText = desc[role];
        document.getElementById("modalOverlay").style.display = "flex";
    }

    function closeModal() {
        document.getElementById("modalOverlay").style.display = "none";
    }

    function continueFlow() {
    if (selectedRole === 'nutritionist') {
        // Nutritionist goes to verification/registration page
        window.location.href = "register_nutritionist.php";
    } else if (selectedRole === 'user') {
        // User goes to login page
        window.location.href = "login.php";
    }
}


    </script>

    </body>
    </html>
