    <?php
    session_start();
    require_once '../config.php';

    if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') exit();

    // --- CORRECTED LOGIC (Removed 'role') ---
    if(isset($_POST['add_expert'])){
        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $experience = $_POST['experience'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Removed 'role' from the query and the bind_param
        $stmt = $conn->prepare("INSERT INTO nutritionist (fullname, email, password, experience) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $fullname, $email, $password, $experience);
        
        if($stmt->execute()){
            header("Location: manage_account.php?msg=success");
            exit();
        }
    }

    // --- LOGIC: HANDLE DELETE ---
    if(isset($_GET['delete_id'])){
        $id = (int)$_GET['delete_id'];
        $conn->query("DELETE FROM nutritionist WHERE id = $id");
        header("Location: manage_account.php?msg=deleted");
        exit();
    }

    $result = $conn->query("SELECT id, fullname, email, experience FROM nutritionist ORDER BY id DESC");
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Manage Experts | Admin</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background: #f4f7ff; padding: 20px; }
            .container { max-width: 1100px; margin: auto; }
            .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
            .card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
            
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; }
            
            .btn-add { background: #2563eb; color: #fff; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; cursor: pointer; border:none; display:flex; align-items:center; gap:8px; }
            
            /* MODAL STYLES */
            .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
            .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; position: relative; }
            .close-btn { position: absolute; right: 20px; top: 20px; cursor: pointer; font-size: 20px; color: #64748b; }
            
            input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
            label { font-size: 13px; font-weight: 600; color: #475569; }
        </style>
    </head>
    <body>

    <div class="container">
        <div class="top-bar">
            <a href="dashboard.php" style="text-decoration:none; color:#2563eb; font-weight:600;">← Back to Dashboard</a>
            <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add New Expert</button>
        </div>

        <div class="card">
            <h3>Account Database (nutritionists)</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Experience</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= $row['experience'] ?> Yrs</td>
                        <td>
                            <a href="edit_expert.php?id=<?= $row['id'] ?>" style="color:#2563eb; text-decoration:none; margin-right:10px;">Edit</a>
                            <a href="manage_account.php?delete_id=<?= $row['id'] ?>" style="color:#dc2626; text-decoration:none;" onclick="return confirm('Delete this account?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 style="margin-top:0;">Add New Expert</h2>
            <form method="POST">
                <label>Full Name</label>
                <input type="text" name="fullname" required placeholder="Dr. Jane Doe">
                
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="jane@example.com">
                
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
                
                <label>Years of Experience</label>
                <input type="number" name="experience" required placeholder="e.g. 5">
                
                <button type="submit" name="add_expert" class="btn-add" style="width:100%; justify-content:center; margin-top:10px;">Create Account</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('addModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('addModal').style.display = 'none'; }
        
        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            let modal = document.getElementById('addModal');
            if (event.target == modal) { closeModal(); }
        }
    </script>

    </body>
    </html>