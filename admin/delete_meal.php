<?php
session_start();
require_once '../config.php';

if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && isset($_GET['id'])){
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ?");
    $stmt->execute([$id]); // Pass $id directly into execute
}
header("Location: meals.php");
exit();