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
$userStmt->execute([$user_id]);
$user = $userStmt->fetch();

if (!$user) die("User profile not found.");

$goal = $user['goal'];
$diet_type = "%" . $user['diet_type'] . "%";
$allergies = array_map('trim', explode(',', strtolower($user['allergies'])));

/* --------------------------------------------------
2. CLEAR OLD WEEKLY PLAN
-------------------------------------------------- */
$clearStmt = $conn->prepare("DELETE FROM weekly_meal_plan WHERE user_id = ?");
$clearStmt->execute([$user_id]);

/* --------------------------------------------------
3. GENERATE AI-BASED WEEKLY PLAN WITH ALLERGY FILTER
-------------------------------------------------- */
$mealStmt = $conn->prepare("
    SELECT id, ingredients 
    FROM meals
    WHERE category = ?
    AND goal = ?
    AND diet_type LIKE ?
    ORDER BY RANDOM()
    LIMIT 50
");

// Idinagdag ang 'created_at' para hindi mag-not null violation error
$insertStmt = $conn->prepare("
    INSERT INTO weekly_meal_plan (id, user_id, day, meal_type, meal_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

foreach ($days as $day) {
    foreach ($meal_types as $type) {

        $mealStmt->execute([$type, $goal, $diet_type]);

        $chosenMeal = null;
        while ($meal = $mealStmt->fetch()) {
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
            // Kinukuha ang manual next id at current timestamp
            $idStmt = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 FROM weekly_meal_plan");
            $nextId = $idStmt->fetchColumn();
            $current_time = date('Y-m-d H:i:s');

            $insertStmt->execute([$nextId, $user_id, $day, $type, $chosenMeal['id'], $current_time]);
        }
    }
}

/* --------------------------------------------------
4. FETCH SNACKS FOR USER (OPTIONAL DISPLAY)
-------------------------------------------------- */
$snackStmt = $conn->prepare("
    SELECT m.name, m.calories, m.protein, m.carbs, m.fats
    FROM weekly_meal_plan w
    JOIN meals m ON w.meal_id = m.id
    WHERE w.user_id = ? AND w.meal_type = 'snack'
");
$snackStmt->execute([$user_id]);

$snacks = [];
while ($row = $snackStmt->fetch()) {
    $snacks[] = $row;
}

/* --------------------------------------------------
5. REDIRECT TO VIEW WEEKLY PLAN
-------------------------------------------------- */
header("Location: view_weekly.php");
exit;
?>