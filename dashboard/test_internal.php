<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['staff']);

echo "<h2>Testing Internal Results Submission</h2>";

// Test with first student
$students = getStudentsForStaff();
if (count($students) > 0) {
    $test_student = $students[0];
    echo "Testing with student: " . $test_student['full_name'] . " (ID: " . $test_student['id'] . ")<br>";
    
    $result = submitInternalResults(
        $test_student['id'], 
        1, 
        '2023-24', 
        6.5, 
        2.5, 
        7.0, 
        2.0, 
        2.5, 
        0.8
    );
    
    if ($result) {
        echo "<span style='color: green;'>SUCCESS: Test submission worked!</span>";
    } else {
        echo "<span style='color: red;'>FAILED: Test submission failed.</span>";
    }
} else {
    echo "No students found to test with.";
}

echo "<br><br>Check your PHP error logs for detailed debug information.";
?>