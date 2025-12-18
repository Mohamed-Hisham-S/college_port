<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole(['admin']);

if ($_POST) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['user_type'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);
        
        header("Location: admin.php?success=user_created");
        exit();
    } catch(PDOException $e) {
        header("Location: admin.php?error=user_creation_failed");
        exit();
    }
}
?>