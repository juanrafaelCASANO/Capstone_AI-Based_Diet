<?php
session_start();
require_once '../config.php';

// Security Check
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    exit("Unauthorized Access");
}

if(isset($_GET['id'])){
    $id = (int)$_GET['id'];
    
    // Use Prepared Statement for safety
    $stmt = $conn->prepare("DELETE FROM nutritionist WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()){
        header("Location: manage_account.php?msg=deleted");
    } else {
        echo "Error deleting record.";
    }
} else {
    header("Location: manage_account.php");
}
exit();
?>