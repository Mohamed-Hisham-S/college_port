<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireRole(['staff']);

if ($_POST) {
    $assigned_to = $_POST['assigned_to'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $assigned_by = $_SESSION['user_id'];
    
    try {
        // Verify the assigned_to user is actually a student
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$assigned_to]);
        $user = $stmt->fetch();
        
        if (!$user || $user['role'] !== 'student') {
            header("Location: staff.php?error=can_only_assign_to_students");
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_by, assigned_to, due_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $assigned_by, $assigned_to, $due_date]);
        
        header("Location: staff.php?success=task_assigned_to_student");
        exit();
    } catch(PDOException $e) {
        header("Location: staff.php?error=task_assignment_failed");
        exit();
    }
}
?>