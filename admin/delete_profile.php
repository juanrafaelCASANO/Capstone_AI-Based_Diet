<?php
session_start();
require_once '../config.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') exit();

if(isset($_GET['id'])){
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM nutritionists_profile WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: manage_profiles.php");
exit();