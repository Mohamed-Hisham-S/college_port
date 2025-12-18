<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireRole(['staff']);

if ($_POST) {
    $student_id = $_POST['student_id'];
    $subject = $_POST['subject'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    $marks_obtained = $_POST['marks_obtained'];
    $total_marks = $_POST['total_marks'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO academic_results (student_id, subject, semester, academic_year, marks_obtained, total_marks) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $subject, $semester, $academic_year, $marks_obtained, $total_marks]);
        
        header("Location: staff.php?success=result_posted");
        exit();
    } catch(PDOException $e) {
        header("Location: staff.php?error=result_post_failed");
        exit();
    }
}
?>