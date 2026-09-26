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
    <title>AI-Based Diet & Nutritional Planner</title>
        
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        
        body {
            font-family:'Inter',sans-serif;
            background:#f4f7ff;
            color:#0f172a;
            overflow-x: hidden;
        }

        /* --- FIXED NAVBAR --- */
        .navbar { 
            background: #fff; 
            padding: 15px 50px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,.05); 
            position: fixed; 
            top: 0; 
            left: 0;
            right: 0;
            width: 100%;
            z-index: 9999; 
        }

        .logo { 
            display: flex; 
            align-items: center; 
            font-weight: 800; 
            font-size: 22px; 
            color: #2563eb; 
        }
        
        .logo span { margin-left: 10px; }

        /* Hover at pop-up effect sa mga nav links */
        .nav-links a { 
            margin-left: 10px; 
            padding: 8px 15px; 
            border-radius: 8px; 
            font-weight: 500; 
            color: #334155; 
            text-decoration: none;
            transition: all 0.3s ease; 
            display: inline-block; 
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
            transition: all 0.3s ease; 
        }

        /* Hover effects para sa Navigation */
        .nav-links a:not(.btn-primary):hover {
            background-color: #eff6ff; 
            color: #2563eb; 
            transform: translateY(-3px); 
            box-shadow: 0 4px 10px rgba(37,99,235,0.1); 
        }

        .btn-primary:hover {
            background-color: #1d4ed8; 
            transform: translateY(-3px); 
            box-shadow: 0 6px 15px rgba(37,99,235,0.3); 
        }

        /* --- CONTENT AREA --- */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            /* Padding to prevent content from hiding under the navbar */
            padding: 120px 20px 60px 20px; 
        }

        .contact-card {
            background:#fff;
            border-radius:24px;
            padding:50px;
            box-shadow:0 20px 40px rgba(37,99,235,.1);
        }

        .contact-card h1 {
            font-size:36px;
            margin-bottom:10px;
            color: #1e293b;
        }

        .contact-card p {
            color:#64748b;
            margin-bottom:30px;
            font-size: 16px;
        }

        /* Alert Styling */
        .alert-success {
            padding: 15px;
            background-color: #dcfce7;
            color: #166534;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #bbf7d0;
            font-weight: 500;
        }

        /* FORM STYLES */
        label {
            font-weight:600;
            display:block;
            margin-top:20px;
            color: #475569;
            font-size: 14px;
        }
        input, textarea {
            width:100%;
            padding:12px 15px;
            margin-top:8px;
            border-radius:10px;
            border:1px solid #e2e8f0;
            font-family:'Inter',sans-serif;
            background: #f8fafc;
            transition: border 0.3s ease;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            background: #fff;
        }
        
        /* Submit Button with Pop-up Effect */
        button {
            margin-top:30px;
            background:#2563eb;
            color:#fff;
            padding:14px 28px;
            border:none;
            border-radius:12px;
            font-weight:600;
            cursor:pointer;
            width: 100%;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        button:hover { 
            background-color: #1d4ed8; 
            transform: translateY(-3px); 
            box-shadow: 0 6px 15px rgba(37,99,235,0.3); 
        }

        @media(max-width:768px){
            .navbar { padding: 15px 20px; }
            .nav-links a:not(.btn-primary) { display: none; }
            .contact-card { padding:30px; }
            .container { padding-top: 100px; }
        }
    </style>
    </head>

    <body>

    <div class="navbar">
        <div class="logo">🥗 <span>AI Diet Planner</span></div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="browse.php">Browse</a>
            <a href="about.php">About</a>
            <a href="auth/login.php">Sign In</a>
            <a href="auth/register.php" class="btn-primary">Get Started</a>
        </div>
    </div>

    <div class="container">
        <div class="contact-card">
            <h1>Contact Us</h1>

            <?php if($success_status): ?>
                <div class="alert-success">
                    ✅ Your message has been sent! Our team will get back to you soon.
                </div>
            <?php endif; ?>

            <p>
                Have questions, feedback, or need assistance? 
                Our team is here to help you with your nutrition journey.
            </p>

            <form method="POST" action="contact.php">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter your name" required>

                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>

                <label>Message</label>
                <textarea rows="5" name="message" placeholder="How can we help you?" required></textarea>

                <button type="submit">Send Message</button>
            </form>
        </div>
    </div>

    </body>
    </html>