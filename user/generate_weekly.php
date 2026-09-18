    <?php
    session_start();
    require_once '../config.php';

    if (!isset($_SESSION['user_id'])) {
        exit;
    }

    $user_id = $_SESSION['user_id'];

    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $meal_types = ['breakfast','lunch','dinner','snack'];

    /* --------------------------------------------------
    1. GET USER PROFILE (AI INPUT)
    -------------------------------------------------- */
    $userStmt = $conn->prepare("
        SELECT goal, diet_type, allergies 
        FROM users 
        WHERE id = ?
    ");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) die("User profile not found.");

    $goal = $user['goal'];
    $diet_type = "%" . $user['diet_type'] . "%";
    $allergies = array_map('trim', explode(',', strtolower($user['allergies'])));

    /* --------------------------------------------------
    2. CLEAR OLD WEEKLY PLAN
    -------------------------------------------------- */
    $clearStmt = $conn->prepare("DELETE FROM weekly_meal_plan WHERE user_id = ?");
    $clearStmt->bind_param("i", $user_id);
    $clearStmt->execute();
    $clearStmt->close();

    /* --------------------------------------------------
    3. GENERATE AI-BASED WEEKLY PLAN WITH ALLERGY FILTER
    -------------------------------------------------- */
    $mealStmt = $conn->prepare("
        SELECT id, ingredients 
        FROM meals
        WHERE category = ?
        AND goal = ?
        AND diet_type LIKE ?
        ORDER BY RAND()
        LIMIT 50
    ");

    $insertStmt = $conn->prepare("
        INSERT INTO weekly_meal_plan (user_id, day, meal_type, meal_id)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($days as $day) {
        foreach ($meal_types as $type) {

            // Fetch multiple meals to filter allergens
            $mealStmt->bind_param("sss", $type, $goal, $diet_type);
            $mealStmt->execute();
            $result = $mealStmt->get_result();

            $chosenMeal = null;
            while ($meal = $result->fetch_assoc()) {
                $ingredients = array_map('trim', explode(',', strtolower($meal['ingredients'])));
                $hasAllergy = false;
                foreach ($allergies as $allergy) {
                    if ($allergy && in_array($allergy, $ingredients)) {
                        $hasAllergy = true;
                        break;
                    }
                }

                if (!$hasAllergy) {
                    $chosenMeal = $meal;
                    break;
                }
            }

            if ($chosenMeal) {
                $insertStmt->bind_param("issi", $user_id, $day, $type, $chosenMeal['id']);
                $insertStmt->execute();
            }
        }
    }

    $mealStmt->close();
    $insertStmt->close();

    /* --------------------------------------------------
    4. FETCH SNACKS FOR USER (OPTIONAL DISPLAY)
    -------------------------------------------------- */
    $snackStmt = $conn->prepare("
        SELECT m.name, m.calories, m.protein, m.carbs, m.fats
        FROM weekly_meal_plan w
        JOIN meals m ON w.meal_id = m.id
        WHERE w.user_id = ? AND w.meal_type = 'snack'
    ");
    $snackStmt->bind_param("i", $user_id);
    $snackStmt->execute();
    $snackResult = $snackStmt->get_result();

    $snacks = [];
    while ($row = $snackResult->fetch_assoc()) {
        $snacks[] = $row;
    }
    $snackStmt->close();

    /* --------------------------------------------------
    5. REDIRECT TO VIEW WEEKLY PLAN
    -------------------------------------------------- */
    header("Location: view_weekly.php");
    exit;
