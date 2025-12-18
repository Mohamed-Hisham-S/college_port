<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole(['student']);

if ($_POST) {
    $user_id = $_SESSION['user_id'];
    $full_name = $_POST['full_name'];
    $enrollment_no = $_POST['enrollment_no'];
    
    try {
        $stmt = $pdo->prepare("UPDATE student_profiles SET full_name = ?, enrollment_no = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $enrollment_no, $user_id]);
        
        header("Location: student.php?success=profile_updated");
        exit();
    } catch(PDOException $e) {
        header("Location: student.php?error=update_failed");
        exit();
    }
}
?>