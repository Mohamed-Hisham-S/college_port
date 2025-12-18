<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Initialize Filter Variables
$filter_dept = $_GET['filter_dept'] ?? '';
$filter_course = $_GET['filter_course'] ?? '';
$filter_year = $_GET['filter_year'] ?? '';

// Handle Form Submission (Publish Result)
if (isset($_POST['publish_result'])) {
    $student_id = $_POST['student_id']; // This is user_id
    $subject = $_POST['subject'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    $internal_marks = floatval($_POST['internal_marks']);
    $external_marks = floatval($_POST['external_marks']);
    
    // Preserve filters
    $filter_dept = $_POST['filter_dept'] ?? '';
    $filter_course = $_POST['filter_course'] ?? '';
    $filter_year = $_POST['filter_year'] ?? '';
    
    if ($external_marks < 0 || $external_marks > 75) {
        $error = "External marks must be between 0 and 75.";
        $selected_student_id = $student_id;
    } else {
        // Need profile ID for publishing, assume publishFinalResult takes Profile ID or handles it.
        // Looking at functions.php, publishFinalResult uses student_id which usually refers to Profile ID in tables
        // But the form sends user_id as student_id. Let's convert if needed.
        // functions.php: publishFinalResult inserts into academic_results(student_id...)
        // academic_results.student_id refers to student_profiles.id
        // So we must convert user_id to profile_id.
        $stu_profile = getStudentByUserId($student_id);
        
        if ($stu_profile && publishFinalResult($stu_profile['id'], $subject, $semester, $academic_year, $internal_marks, $external_marks)) {
            $success = "Result published successfully for $subject!";
            $selected_student_id = $student_id;
        } else {
            $error = "Failed to publish result.";
        }
    }
}

// Handle Student Selection
$selected_student_id = $_GET['student_id'] ?? ($selected_student_id ?? null);
$internal_records = [];
$profile_data = null;

// Fetch Students based on Filters
$students_list = [];
if ($filter_dept && $filter_course && $filter_year) {
    $students_list = getStudentsByClass($filter_dept, $filter_course, $filter_year);
    
    // --- NEW: FETCH STATUS COUNTS ---
    if (!empty($students_list)) {
        // Get all Profile IDs
        $profile_ids = array_column($students_list, 'id');
        $in_query = implode(',', array_fill(0, count($profile_ids), '?'));
        
        // 1. Count Internals (Total Subjects Available)
        $stmt = $pdo->prepare("SELECT student_id, COUNT(*) as cnt FROM internal_results WHERE student_id IN ($in_query) GROUP BY student_id");
        $stmt->execute($profile_ids);
        $internal_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [student_id => count]
        
        // 2. Count Academic Results (Already Published)
        $stmt = $pdo->prepare("SELECT student_id, COUNT(*) as cnt FROM academic_results WHERE student_id IN ($in_query) GROUP BY student_id");
        $stmt->execute($profile_ids);
        $published_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [student_id => count]
    }
}

// Fetch Selected Student Data
if ($selected_student_id) {
    $profile_data = getStudentByUserId($selected_student_id);
    if ($profile_data) {
        $internal_records = getStudentInternalDetails($profile_data['id']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Results - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .calc-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .calc-table th, .calc-table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .calc-table th { background-color: var(--light); }
        .input-mark { width: 80px; padding: 5px; }
        .published-badge { background: #e8f5e9; color: #2e7d32; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .calc-row { background: #fff; transition: background 0.3s; }
        .calc-row:hover { background: #f9f9f9; }
        .filter-card { background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbdefb; }
        .student-select-table tr.active-student { background-color: #e3f2fd; border-left: 4px solid var(--primary); }
        .student-select-table tr:hover { background-color: #f1f1f1; }
        
        .status-pill { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 600; min-width: 100px; text-align: center; }
        .status-done { background: #c8e6c9; color: #256029; }
        .status-partial { background: #feedaf; color: #8a5300; }
        .status-none { background: #f1f1f1; color: #666; }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Admin Panel</h2></div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin.php">Dashboard</a></li>
                    <li><a href="admin.php#manage-students">Manage Students</a></li>
                    <li><a href="publish_results.php" class="active">Publish Results</a></li>
                    <li><a href="admin.php?logout=true">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Publish Academic Results</h1>
                    <p>Calculate Final Marks (Internal + External)</p>
                </div>
                <div class="header-right">
                    <div class="user-info"><span>Admin</span></div>
                </div>
            </header>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <section class="dashboard-section" style="padding-bottom: 10px;">
                <h2>1. Filter Students (Department & Class)</h2>
                <div class="filter-card">
                    <form method="GET" action="publish_results.php">
                        <div class="form-row" style="align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="dept">Department</label>
                                <select name="filter_dept" id="dept" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science" <?= $filter_dept == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                    <option value="Electronics" <?= $filter_dept == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                    <option value="Mechanical" <?= $filter_dept == 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                    <option value="Civil" <?= $filter_dept == 'Civil' ? 'selected' : '' ?>>Civil</option>
                                    <option value="Electrical" <?= $filter_dept == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                    <option value="Information Technology" <?= $filter_dept == 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="course">Course</label>
                                <select name="filter_course" id="course" required>
                                    <option value="">Select Course</option>
                                    <option value="B.Sc" <?= $filter_course == 'B.Sc' ? 'selected' : '' ?>>B.Sc</option>
                                    <option value="B.Tech" <?= $filter_course == 'B.Tech' ? 'selected' : '' ?>>B.Tech</option>
                                    <option value="B.Com" <?= $filter_course == 'B.Com' ? 'selected' : '' ?>>B.Com</option>
                                    <option value="B.A" <?= $filter_course == 'B.A' ? 'selected' : '' ?>>B.A</option>
                                    <option value="M.Sc" <?= $filter_course == 'M.Sc' ? 'selected' : '' ?>>M.Sc</option>
                                    <option value="M.Tech" <?= $filter_course == 'M.Tech' ? 'selected' : '' ?>>M.Tech</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="year">Year</label>
                                <select name="filter_year" id="year" required>
                                    <option value="">Select Year</option>
                                    <option value="1" <?= $filter_year == '1' ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2" <?= $filter_year == '2' ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3" <?= $filter_year == '3' ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4" <?= $filter_year == '4' ? 'selected' : '' ?>>4th Year</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Filter List</button>
                            <?php if($filter_dept): ?>
                                <a href="publish_results.php" class="btn btn-outline">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </section>

            <?php if (!empty($students_list)): ?>
            <section class="dashboard-section">
                <h2>2. Select Student to Enter Marks</h2>
                <div class="results-table">
                    <table class="student-select-table">
                        <thead>
                            <tr>
                                <th>Enrollment No</th>
                                <th>Full Name</th>
                                <th>Publish Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_list as $stu): ?>
                            <?php
                                $pid = $stu['id'];
                                $total_internal = $internal_counts[$pid] ?? 0;
                                $total_published = $published_counts[$pid] ?? 0;
                                
                                $status_class = 'status-none';
                                $status_text = 'No Data';
                                
                                if ($total_internal > 0) {
                                    if ($total_published >= $total_internal) {
                                        $status_class = 'status-done';
                                        $status_text = "All Done ($total_published/$total_internal)";
                                    } elseif ($total_published > 0) {
                                        $status_class = 'status-partial';
                                        $status_text = "Progress ($total_published/$total_internal)";
                                    } else {
                                        $status_class = 'status-partial'; // Use partial color for pending
                                        $status_text = "Pending (0/$total_internal)";
                                    }
                                }
                            ?>
                            <tr class="<?= $selected_student_id == $stu['user_id'] ? 'active-student' : '' ?>">
                                <td><?= htmlspecialchars($stu['enrollment_no']) ?></td>
                                <td><strong><?= htmlspecialchars($stu['full_name']) ?></strong></td>
                                <td>
                                    <span class="status-pill <?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="publish_results.php?student_id=<?= $stu['user_id'] ?>&filter_dept=<?= urlencode($filter_dept) ?>&filter_course=<?= urlencode($filter_course) ?>&filter_year=<?= urlencode($filter_year) ?>#enter-marks" 
                                       class="btn btn-sm <?= $selected_student_id == $stu['user_id'] ? 'btn-primary' : 'btn-outline' ?>">
                                        <?= $selected_student_id == $stu['user_id'] ? 'Selected' : 'Select / Enter' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php elseif ($filter_dept): ?>
                <section class="dashboard-section">
                    <p class="alert alert-warning">No students found for the selected filter.</p>
                </section>
            <?php endif; ?>

            <?php if ($selected_student_id && $profile_data): ?>
            <section id="enter-marks" class="dashboard-section">
                <h2>3. Enter External Marks for: <span style="color: var(--primary);"><?= htmlspecialchars($profile_data['full_name']) ?></span></h2>
                <p>Below are the subjects where Internal Marks (out of 25) have been submitted by staff.</p>
                
                <?php if (empty($internal_records)): ?>
                    <p class="alert alert-error">No internal marks found for this student. Ask staff to submit internal results first.</p>
                <?php else: ?>
                    <div class="results-table">
                        <table class="calc-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Semester</th>
                                    <th>Internal (25)</th>
                                    <th>External (75)</th>
                                    <th>Total (100)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($internal_records as $record): ?>
                                <tr class="calc-row">
                                    <form method="POST" action="publish_results.php">
                                        <input type="hidden" name="filter_dept" value="<?= htmlspecialchars($filter_dept) ?>">
                                        <input type="hidden" name="filter_course" value="<?= htmlspecialchars($filter_course) ?>">
                                        <input type="hidden" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                                        
                                        <input type="hidden" name="student_id" value="<?= $selected_student_id ?>"> 
                                        <input type="hidden" name="subject" value="<?= htmlspecialchars($record['subject']) ?>">
                                        <input type="hidden" name="semester" value="<?= htmlspecialchars($record['semester']) ?>">
                                        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($record['academic_year']) ?>">
                                        <input type="hidden" name="internal_marks" value="<?= $record['total_marks'] ?>" id="internal_<?= $record['id'] ?>">
                                        
                                        <td>
                                            <?= htmlspecialchars($record['subject']) ?>
                                            <?php if($record['academic_result_id']): ?>
                                                <br><span class="published-badge">Published</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($record['semester']) ?></td>
                                        <td style="font-weight: bold;">
                                            <?= htmlspecialchars($record['total_marks']) ?>
                                        </td>
                                        <td>
                                            <input type="number" name="external_marks" class="input-mark" 
                                                   id="external_<?= $record['id'] ?>" 
                                                   min="0" max="75" step="0.1" required 
                                                   placeholder="0-75"
                                                   oninput="calculateTotal(<?= $record['id'] ?>)">
                                        </td>
                                        <td>
                                            <span id="total_<?= $record['id'] ?>" style="font-weight: bold; font-size: 1.1em;">-</span>
                                        </td>
                                        <td>
                                            <button type="submit" name="publish_result" class="btn btn-sm btn-primary">Publish</button>
                                        </td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function calculateTotal(id) {
            const internal = parseFloat(document.getElementById('internal_' + id).value) || 0;
            const external = parseFloat(document.getElementById('external_' + id).value);
            
            const totalSpan = document.getElementById('total_' + id);
            
            if (!isNaN(external)) {
                if (external > 75) {
                    totalSpan.style.color = 'red';
                    totalSpan.innerText = 'Invalid (>75)';
                } else {
                    const total = internal + external;
                    totalSpan.style.color = total >= 40 ? 'green' : 'red';
                    totalSpan.innerText = total.toFixed(2);
                }
            } else {
                totalSpan.innerText = '-';
            }
        }
    </script>
</body>
</html>