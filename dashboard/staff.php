<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['staff']);

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    logout();
    header('Location: ../index.php');
    exit();
}

// Handle Attendance Submission
if (isset($_POST['submit_attendance'])) {
    $alloc_id = $_POST['alloc_id'];
    $date = $_POST['date'];
    // absentees is an array of user_ids who are ABSENT
    $absentees = $_POST['absentees'] ?? []; 
    
    if (submitAttendance($alloc_id, $date, $absentees, $_SESSION['user_id'])) {
        $success = "Attendance saved for " . date('M d, Y', strtotime($date));
        
        // AUTO-ALERT: Check for low attendance and warn students
        // (Optional: You can add the messaging logic here)
    } else {
        $error = "Failed to save attendance.";
    }
}

// Get staff profile
$profile = getStaffProfile($_SESSION['user_id']);

// Handle profile update
if (isset($_POST['update_staff_profile'])) {
    // Only full_name is editable by the staff member
    $data = [
        'full_name' => $_POST['full_name']
    ];
    
    if (updateStaffProfile($_SESSION['user_id'], $data)) {
        $success = "Profile updated successfully!";
        // Refresh profile data
        $profile = getStaffProfile($_SESSION['user_id']);
    } else {
        $error = "Failed to update profile.";
    }
}

$students = getStudentsForStaff($_SESSION['user_id']);
$messages = getMessages($_SESSION['user_id']);


$my_allocations = getStaffAllocations($_SESSION['user_id']);
$first_alloc_id = count($my_allocations) > 0 ? $my_allocations[0]['id'] : null;
$selected_alloc_id = $_GET['alloc_id'] ?? ($_POST['alloc_id'] ?? $first_alloc_id);
// Initialize variables
$current_students = [];
$current_subject = ""; 
$current_semester = ""; 
$current_academic_year = "";
$current_class_info = "";

// --- MISSING CODE RESTORED START ---
if ($selected_alloc_id) {
    foreach ($my_allocations as $alloc) {
        if ($alloc['id'] == $selected_alloc_id) {
            $current_subject = $alloc['subject_name'];
            $current_semester = $alloc['semester'] ?? '';
            $current_academic_year = $alloc['academic_year'] ?? '';
            $current_class_info = ordinal($alloc['current_year']) . " Year " . $alloc['course'] . " (" . $alloc['department'] . ")";
            
            // Fetch students for THIS specific class
            $current_students = getStudentsByClass($alloc['department'], $alloc['course'], $alloc['current_year']);
            break;
        }
    }
}
// --- MISSING CODE RESTORED END ---

// Fetch all existing results for this staff to pre-fill the table
$all_internal_results = getInternalResults($_SESSION['user_id']);
$class_marks_map = [];

// Create a lookup array: [student_id => result_row]
if ($selected_alloc_id) {
    foreach ($all_internal_results as $res) {
        // Filter by Subject, Semester, and Academic Year to ensure we get the right marks
        if ($res['subject'] === $current_subject && 
            $res['semester'] == $current_semester && 
            $res['academic_year'] === $current_academic_year) {
            $class_marks_map[$res['student_id']] = $res;
        }
    }
}

// --- HANDLE BULK SUBMISSION ---
if (isset($_POST['submit_bulk_results'])) {
    $subject = $_POST['subject'];
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    $submitted_by = $_SESSION['user_id'];
    $marks_data = $_POST['marks']; // Array of student_id => [cia_1, etc]

    $count_success = 0;
    $count_errors = 0;

    foreach ($marks_data as $student_id => $marks) {
        // Skip empty rows if needed, or validate numeric input
        if ($marks['cia_1'] === '' && $marks['cia_2'] === '') continue;

        $cia_1 = floatval($marks['cia_1']);
        $cia_2 = floatval($marks['cia_2']);
        $task_1 = floatval($marks['task_1']);
        $task_2 = floatval($marks['task_2']);
        $attendance = floatval($marks['attendance']);
        $library = floatval($marks['library']);

        // Check if result already exists (Update vs Insert)
        global $pdo;
        $stmt = $pdo->prepare("SELECT id FROM internal_results WHERE student_id = ? AND subject = ? AND semester = ? AND academic_year = ?");
        $stmt->execute([$student_id, $subject, $semester, $academic_year]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            if (updateInternalResult($existing['id'], $cia_1, $task_1, $cia_2, $task_2, $attendance, $library)) {
                $count_success++;
            } else {
                $count_errors++;
            }
        } else {
            // Insert new record
            if (submitInternalResults($student_id, $subject, $semester, $academic_year, $cia_1, $task_1, $cia_2, $task_2, $attendance, $library, $submitted_by)) {
                $count_success++;
            } else {
                $count_errors++;
            }
        }
    }

    if ($count_errors == 0 && $count_success > 0) {
        $success = "Successfully saved results for $count_success student(s).";
    } elseif ($count_success > 0) {
        $error = "Saved $count_success records, but failed for $count_errors.";
    } else {
        $error = "No records were updated or saved.";
    }
    
    // Redirect to show updated data
    header("Location: staff.php?alloc_id=" . $_POST['alloc_id'] . "&success=" . urlencode($success ?? '') . "&error=" . urlencode($error ?? '') . "#internal-results");
    exit();
}

// Handle Quiz Creation
if (isset($_POST['create_quiz'])) {
    $alloc_id = $_POST['alloc_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $time = $_POST['time_limit'];
    
    // Create Quiz
    $quiz_id = createQuiz($alloc_id, $title, $desc, $time, $_SESSION['user_id']);
    
    // Add Questions
    $questions = $_POST['question']; // Array
    $optA = $_POST['option_a'];
    $optB = $_POST['option_b'];
    $optC = $_POST['option_c'];
    $optD = $_POST['option_d'];
    $correct = $_POST['correct'];
    
    for ($i = 0; $i < count($questions); $i++) {
        if (!empty($questions[$i])) {
            addQuizQuestion($quiz_id, $questions[$i], $optA[$i], $optB[$i], $optC[$i], $optD[$i], $correct[$i]);
        }
    }
    $success = "Quiz created successfully!";
}
$staff_quizzes = getStaffQuizzes($_SESSION['user_id']);

// --- QUIZ FILTER LOGIC ---
// Default to the first allocation ID if no filter is selected
$quiz_filter_id = $_GET['quiz_filter_id'] ?? (count($my_allocations) > 0 ? $my_allocations[0]['id'] : '');

// --- MESSAGE FILTER LOGIC ---
$message_filter_id = $_GET['message_filter_id'] ?? (count($my_allocations) > 0 ? $my_allocations[0]['id'] : '');
$message_students = [];
$filtered_messages = []; // Inbox
$filtered_sent_messages = []; // Sent items

// Fetch raw sent messages
$sent_messages = getSentMessages($_SESSION['user_id']);

if ($message_filter_id) {
    foreach ($my_allocations as $alloc) {
        if ($alloc['id'] == $message_filter_id) {
            $message_students = getStudentsByClass($alloc['department'], $alloc['course'], $alloc['current_year']);
            break;
        }
    }
    
    // Get valid student IDs for this class
    $valid_ids = array_column($message_students, 'user_id');
    
    // Filter INBOX (From students in this class)
    if (!empty($messages)) {
        $filtered_messages = array_filter($messages, function($msg) use ($valid_ids) {
            return in_array($msg['sender_id'], $valid_ids);
        });
    }
    
    // Filter SENT (To students in this class)
    if (!empty($sent_messages)) {
        $filtered_sent_messages = array_filter($sent_messages, function($msg) use ($valid_ids) {
            return in_array($msg['receiver_id'], $valid_ids);
        });
    }
} else {
    $filtered_messages = $messages;
    $filtered_sent_messages = $sent_messages;
}

// --- NEW: Handle View Quiz Results ---
$view_quiz_results = [];
$view_quiz_title = "";
if (isset($_GET['view_quiz'])) {
    $view_quiz_id = $_GET['view_quiz'];
    $view_quiz_results = getQuizResults($view_quiz_id);
    
    // Find the title for display
    foreach($staff_quizzes as $sq) {
        if($sq['id'] == $view_quiz_id) {
            $view_quiz_title = $sq['title'];
            break;
        }
    }
}

// Handle sending messages
if (isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = $_POST['message'];
    
    if (sendMessage($_SESSION['user_id'], $receiver_id, $message)) {
        $success = "Message sent successfully!";
    } else {
        $error = "Failed to send message.";
    }
    header("Location: staff.php?success=" . urlencode($success ?? '') . "&error=" . urlencode($error ?? '') . "#messages");
    exit();
}

// Handle marking message as read
if (isset($_GET['mark_read'])) {
    $messageId = $_GET['mark_read'];
    if (markMessageAsRead($messageId)) {
        header("Location: staff.php?success=Message marked as read#messages");
        exit();
    }
}

// Handle delete result
if (isset($_GET['delete_result'])) {
    $resultId = $_GET['delete_result'];
    if (deleteInternalResult($resultId)) {
        $success = "Result deleted successfully!";
    } else {
        $error = "Failed to delete result.";
    }
    header("Location: staff.php?success=" . urlencode($success ?? '') . "&error=" . urlencode($error ?? '') . "#results-overview");
    exit();
}

// --- 2. HANDLE EDIT MODE (Fetch Data) ---
$edit_data = null;

// Get internal results for current staff ONLY
$internal_results = getInternalResults($_SESSION['user_id']);
$my_allocations = getStaffAllocations($_SESSION['user_id']);

// --- LOGIC FOR FILTERING OVERVIEW BY CLASS (Linked to Top Selector) ---
// We now use $selected_alloc_id (from the top dropdown) instead of a separate filter ID
$filtered_results = []; 

if ($selected_alloc_id && !empty($my_allocations)) {
    foreach ($my_allocations as $alloc) {
        if ($alloc['id'] == $selected_alloc_id) {
            // Filter the results array to match the selected Class/Subject details
            $filtered_results = array_filter($internal_results, function($row) use ($alloc) {
                return $row['subject'] === $alloc['subject_name'] 
                    && $row['semester'] == $alloc['semester']
                    && $row['academic_year'] === $alloc['academic_year'];
            });
            break;
        }
    }
} else {
    // Fallback if nothing selected
    $filtered_results = $internal_results; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - College Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .results-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
        }
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            margin: 5px 0;
        }
        .summary-label {
            font-size: 14px;
            color: var(--gray);
        }
        .marks-input {
            width: 100px;
            text-align: center;
            padding: 8px;
        }
        .total-score {
            background: #e8f5e9;
            font-weight: bold;
        }
        .grade-a { background: #e8f5e9; }
        .grade-b { background: #fff3e0; }
        .grade-c { background: #ffebee; }
        .grade-d { background: #fff9c4; }
        .grade-f { background: #ffcdd2; }
        .marks-label {
            font-size: 12px;
            color: var(--gray);
            display: block;
            margin-top: 5px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .current-user-info {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        .profile-form {
            max-width: 700px;
        }
        .form-group input[readonly] {
            background-color: #eee;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Staff Portal</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="active">Dashboard</a></li>
                    <li><a href="#profile">My Profile</a></li>
                    <li><a href="#internal-results">Semester Internal Results</a></li>
                    <li><a href="#results-overview">Results Overview</a></li>
                    <li><a href="#manage-quizzes">Online Quizzes</a></li>
                    <li><a href="#attendance">Mark Attendance</a></li>
                    <li><a href="#messages">Messages</a></li>
                    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?logout=true">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Staff Dashboard</h1>
                    <p>
                        Welcome back, <strong><?= htmlspecialchars($profile['full_name'] ?? $_SESSION['username']) ?></strong>
                        <?php if (!empty($profile['department'])): ?>
                            | <?= htmlspecialchars($profile['department']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <span>Staff</span>
                        <div class="user-avatar"><?= strtoupper(substr($profile['full_name'] ?? $_SESSION['username'], 0, 2)) ?></div>
                    </div>
                </div>
            </header>

            <?php if (isset($success) || isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success ?? $_GET['success']) ?></div>
            <?php endif; ?>
            <?php if (isset($error) || isset($_GET['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error ?? $_GET['error']) ?></div>
            <?php endif; ?>
            
            <div class="current-user-info">
                🔒 You are logged in as: <strong><?= htmlspecialchars($profile['full_name'] ?? $_SESSION['username']) ?></strong> (User ID: <?= $_SESSION['user_id'] ?>)
            </div>
            
            <section id="profile" class="dashboard-section">
                <h2>My Profile</h2>
                <div class="profile-form">
                    <form method="POST" action="staff.php#profile">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="username">Username (Read-only)</label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars($profile['username'] ?? '') ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email (Read-only)</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" readonly>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department">Department (Read-only)</label>
                                <input type="text" id="department" name="department" value="<?= htmlspecialchars($profile['department'] ?? '') ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="subject">Subject / Specialization (Read-only)</label>
                                <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($profile['subject'] ?? '') ?>" readonly>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_staff_profile" class="btn btn-primary">Update Full Name</button>
                    </form>
                </div>
            </section>
            
            <section id="internal-results" class="dashboard-section">
                <h2>Semester Internal Results (Bulk Entry)</h2>

                <?php if (empty($my_allocations)): ?>
                    <div class="alert alert-error">You have not been assigned any subjects yet. Contact Admin.</div>
                <?php else: ?>
                    <div class="form-card" style="background: #e3f2fd; margin-bottom: 20px;">
                        <form method="GET" action="staff.php">
                            <label style="font-weight: bold;">Select Class & Subject:</label>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <select name="alloc_id" style="flex: 1; padding: 8px;" onchange="this.form.submit()">
                                    <?php foreach ($my_allocations as $alloc): ?>
                                        <option value="<?= $alloc['id'] ?>" <?= $selected_alloc_id == $alloc['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($alloc['subject_name']) ?> - 
                                            <?= ordinal($alloc['current_year']) ?> Yr <?= htmlspecialchars($alloc['course']) ?> 
                                            (Sem <?= $alloc['semester'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Load Class</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($selected_alloc_id): ?>
                    <div class="form-card">
                        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0; color: #4361ee;"><?= htmlspecialchars($current_subject) ?></h3>
                                <p style="margin: 5px 0 0 0; color: #666;">
                                    <?= htmlspecialchars($current_class_info) ?> | <strong>Batch: <?= htmlspecialchars($current_academic_year) ?></strong>
                                </p>
                            </div>
                            <div style="background: #fff3e0; padding: 5px 10px; border-radius: 4px; font-size: 0.9em;">
                                <strong>Students:</strong> <?= count($current_students) ?>
                            </div>
                        </div>

                        <?php if (count($current_students) > 0): ?>
                            <form method="POST" action="staff.php#internal-results">
                                <input type="hidden" name="alloc_id" value="<?= $selected_alloc_id ?>">
                                <input type="hidden" name="subject" value="<?= htmlspecialchars($current_subject) ?>">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($current_semester) ?>">
                                <input type="hidden" name="academic_year" value="<?= htmlspecialchars($current_academic_year) ?>">
                                <input type="hidden" name="submit_bulk_results" value="1">

                                <div style="max-height: 600px; overflow-y: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                                        <thead style="position: sticky; top: 0; z-index: 10;">
                                            <tr>
                                                <th style="background: #4361ee; color: white; padding: 10px; border: 1px solid #ddd;">Student Name</th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">CIA-1<br><small>(7.5)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">CIA-2<br><small>(7.5)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">Task-1<br><small>(3)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">Task-2<br><small>(3)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">Att.<br><small>(3)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">Lib.<br><small>(1)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 60px;">Total<br><small>(25)</small></th>
                                                <th style="background: #4361ee; color: white; padding: 5px; border: 1px solid #ddd; width: 50px;">Grd</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($current_students as $student): ?>
                                                <?php 
                                                $sid = $student['id'];
                                                // Pre-fill existing marks if available
                                                $m = $class_marks_map[$sid] ?? [];
                                                ?>
                                                <tr id="row-<?= $sid ?>" style="border-bottom: 1px solid #ddd;">
                                                    <td style="padding: 8px;">
                                                        <strong><?= htmlspecialchars($student['full_name']) ?></strong><br>
                                                        <small style="color:#666;"><?= htmlspecialchars($student['enrollment_no']) ?></small>
                                                    </td>
                                                    <td style="padding: 5px; text-align: center;"><input type="number" name="marks[<?= $sid ?>][cia_1]" class="cia1" style="width: 50px; text-align: center;" min="0" max="7.5" step="0.1" value="<?= $m['cia_1'] ?? '' ?>" oninput="calcRow(<?= $sid ?>)"></td>
                                                    <td style="padding: 5px; text-align: center;"><input type="number" name="marks[<?= $sid ?>][cia_2]" class="cia2" style="width: 50px; text-align: center;" min="0" max="7.5" step="0.1" value="<?= $m['cia_2'] ?? '' ?>" oninput="calcRow(<?= $sid ?>)"></td>
                                                    <td style="padding: 5px; text-align: center;"><input type="number" name="marks[<?= $sid ?>][task_1]" class="task1" style="width: 50px; text-align: center;" min="0" max="3" step="0.1" value="<?= $m['task_1'] ?? '' ?>" oninput="calcRow(<?= $sid ?>)"></td>
                                                    <td style="padding: 5px; text-align: center;"><input type="number" name="marks[<?= $sid ?>][task_2]" class="task2" style="width: 50px; text-align: center;" min="0" max="3" step="0.1" value="<?= $m['task_2'] ?? '' ?>" oninput="calcRow(<?= $sid ?>)"></td>
                                                    <td style="padding: 5px; text-align: center;"><input type="number" name="marks[<?= $sid ?>][attendance]" class="att" style="width: 50px; text-align: center;" min="0" max="3" step="0.1" value="<?= $m['attendance'] ?? '' ?>" oninput="calcRow(<?= $sid ?>)"></td>
                                                    <td style="padding: 5px; text-align: center;"><input type="number" name="marks[<?= $sid ?>][library]" class="lib" style="width: 50px; text-align: center;" min="0" max="1" step="0.1" value="<?= $m['library'] ?? '' ?>" oninput="calcRow(<?= $sid ?>)"></td>
                                                    <td style="padding: 5px; text-align: center;"><input type="text" class="total" readonly style="width: 50px; text-align: center; background: #eee; font-weight: bold;" value="<?= $m['total_marks'] ?? '0' ?>"></td>
                                                    <td style="padding: 5px; text-align: center; font-weight: bold;" class="grade-display">-</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="margin-top: 20px; text-align: right;">
                                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">Save All Results</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">No students found in this class yet.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
            
            <section id="results-overview" class="dashboard-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>My Internal Results Overview</h2>
                    <div style="font-size: 0.9em; color: #666; font-style: italic;">
                        Showing results for: <strong><?= htmlspecialchars($current_subject ?: 'All Subjects') ?></strong>
                    </div>
                </div>

                <div class="results-summary">
                    <div class="summary-card">
                        <div class="summary-value"><?= count($filtered_results) ?></div>
                        <div class="summary-label">Records Shown</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">
                            <?= count(array_filter($filtered_results, function($result) { 
                                return calculateGrade($result['total_marks']) === 'A'; 
                            })) ?>
                        </div>
                        <div class="summary-label">Grade A</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">
                            <?= count(array_filter($filtered_results, function($result) { 
                                $grade = calculateGrade($result['total_marks']);
                                return $grade === 'B' || $grade === 'C' || $grade === 'D'; 
                            })) ?>
                        </div>
                        <div class="summary-label">Grade B/C/D</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">
                            <?= count(array_filter($filtered_results, function($result) { 
                                return calculateGrade($result['total_marks']) === 'F'; 
                            })) ?>
                        </div>
                        <div class="summary-label">Grade F</div>
                    </div>
                </div>
                
                <div class="results-table">
                    <?php if (count($filtered_results) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Register No</th>
                                    <th>Student Name</th>
                                    <th>Subject</th>
                                    <th>Sem</th> <th>CIA-1</th>
                                    <th>Task-1</th>
                                    <th>CIA-2</th>
                                    <th>Task-2</th>
                                    <th>Att.</th> <th>Lib.</th> <th>Total</th>
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered_results as $result): ?>
                                <?php 
                                $total_marks = $result['total_marks'];
                                $grade = calculateGrade($total_marks);
                                $grade_class = 'grade-' . strtolower($grade);
                                ?>
                                <tr class="<?= $grade_class ?>">
                                    <td><?= htmlspecialchars($result['enrollment_no']) ?></td>
                                    <td><?= htmlspecialchars($result['full_name']) ?></td>
                                    <td><?= htmlspecialchars($result['subject'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($result['semester']) ?></td>
                                    <td><?= number_format($result['cia_1'], 1) ?></td>
                                    <td><?= number_format($result['task_1'], 1) ?></td>
                                    <td><?= number_format($result['cia_2'], 1) ?></td>
                                    <td><?= number_format($result['task_2'], 1) ?></td>
                                    <td><?= number_format($result['attendance'], 1) ?></td>
                                    <td><?= number_format($result['library'], 1) ?></td>
                                    <td class="total-score"><?= number_format($total_marks, 1) ?></td>
                                    <td><strong><?= $grade ?></strong></td>
                                    <td>
                                        <?php 
                                        $target_alloc_id = '';
                                        foreach ($my_allocations as $alloc) {
                                            if ($alloc['subject_name'] == $result['subject'] && 
                                                $alloc['semester'] == $result['semester'] && 
                                                $alloc['academic_year'] == $result['academic_year']) {
                                                $target_alloc_id = $alloc['id'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <?php if ($target_alloc_id): ?>
                                            <a href="staff.php?alloc_id=<?= $target_alloc_id ?>#row-<?= $result['student_id'] ?>" 
                                            class="btn btn-sm btn-outline">Edit</a>
                                        <?php else: ?>
                                            <span class="badge" style="background:#ccc;">Archived</span>
                                        <?php endif; ?>
                                        
                                        <a href="staff.php?delete_result=<?= $result['id'] ?>" 
                                        class="btn btn-sm btn-danger" 
                                        onclick="return confirm('Delete this result?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <h3>No Results Found</h3>
                            <p>No results found for the selected filter.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <section id="manage-quizzes" class="dashboard-section">
                <h2>Manage Online Quizzes</h2>
                
                <div class="form-card" style="margin-bottom: 30px;">
                    <h3>Create New Quiz</h3>
                    <form method="POST" action="staff.php#manage-quizzes">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Assign to Class *</label>
                                <select name="alloc_id" required>
                                    <?php foreach ($my_allocations as $alloc): ?>
                                        <option value="<?= $alloc['id'] ?>">
                                            <?= $alloc['subject_name'] ?> (<?= ordinal($alloc['current_year']) ?> Yr)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quiz Title *</label>
                                <input type="text" name="title" required placeholder="e.g. Unit 1 Test">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Time Limit (Minutes) *</label>
                                <input type="number" name="time_limit" required value="20">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description" placeholder="Instructions...">
                            </div>
                        </div>
                        
                        <hr>
                        <h4>Questions</h4>
                        <div id="questions-container">
                            </div>
                        <button type="button" class="btn btn-outline" onclick="addQuestionField()">+ Add Question</button>
                        <br><br>
                        <button type="submit" name="create_quiz" class="btn btn-primary">Create Quiz</button>
                    </form>
                </div>
                
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 15px;">
                        <h3>Your Quizzes</h3>
                        <form method="GET" action="staff.php" id="quizFilterForm">
                            <select name="quiz_filter_id" 
                                    onchange="document.getElementById('quizFilterForm').action = 'staff.php#manage-quizzes'; this.form.submit();" 
                                    style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                                    <?php foreach ($my_allocations as $alloc): ?>
                                        <option value="<?= $alloc['id'] ?>" <?= ($quiz_filter_id == $alloc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($alloc['subject_name']) ?> 
                                            (<?= ordinal($alloc['current_year']) ?> Yr - Sem <?= $alloc['semester'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <div class="results-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Class</th>
                                    <th>Time</th>
                                    <th>Date</th>
                                    <th>Actions</th> </tr>
                            </thead>
                            <tbody>
                                <?php foreach($staff_quizzes as $q): ?>
                                <tr>
                                    <td><?= htmlspecialchars($q['title']) ?></td>
                                    <td><?= htmlspecialchars($q['course']) ?> (<?= ordinal($q['current_year']) ?>)</td>
                                    <td><?= $q['time_limit'] ?> mins</td>
                                    <td><?= date('M d, Y', strtotime($q['created_at'])) ?></td>
                                    <td>
                                        <a href="staff.php?view_quiz=<?= $q['id'] ?>#manage-quizzes" class="btn btn-sm btn-primary">View Results</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (isset($_GET['view_quiz'])): ?>
                        <div id="quiz-results-display" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="margin: 0;">Results for: <span style="color: #4361ee;"><?= htmlspecialchars($view_quiz_title) ?></span></h3>
                                <a href="staff.php#manage-quizzes" class="btn btn-sm btn-outline">Close Results</a>
                            </div>
                            
                            <?php if (count($view_quiz_results) > 0): ?>
                                <div class="results-table">
                                    <table style="width: 100%;">
                                        <thead>
                                            <tr style="background: #f8f9fa;">
                                                <th>Student Name</th>
                                                <th>Enrollment No</th>
                                                <th>Score</th>
                                                <th>Percentage</th>
                                                <th>Status</th>
                                                <th>Submitted At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($view_quiz_results as $res): ?>
                                            <?php 
                                                $percentage = ($res['score'] / $res['total_questions']) * 100;
                                                $pass = $percentage >= 40;
                                            ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($res['full_name']) ?></strong></td>
                                                <td><?= htmlspecialchars($res['enrollment_no']) ?></td>
                                                <td style="font-weight: bold; font-size: 1.1em;"><?= $res['score'] ?> / <?= $res['total_questions'] ?></td>
                                                <td>
                                                    <span style="padding: 4px 8px; border-radius: 4px; color: white; background: <?= $pass ? '#2e7d32' : '#c62828' ?>;">
                                                        <?= number_format($percentage, 0) ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($res['status'] == 'disqualified'): ?>
                                                        <span style="color: red; font-weight: bold;">⚠️ Disqualified (Tab Switch)</span>
                                                    <?php else: ?>
                                                        <span style="color: green;">Completed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('M d, g:i A', strtotime($res['attempted_at'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">No students have attempted this quiz yet.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
            </section>

            <section id="attendance" class="dashboard-section">
                <h2>Mark Attendance</h2>
                
                <div class="form-card">
                    <form method="GET" action="staff.php">
                        <input type="hidden" name="active_tab" value="attendance">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Select Class *</label>
                                <select name="att_alloc_id" required onchange="this.form.submit()">
                                    <option value="">-- Choose Class --</option>
                                    <?php foreach ($my_allocations as $alloc): ?>
                                        <option value="<?= $alloc['id'] ?>" <?= (isset($_GET['att_alloc_id']) && $_GET['att_alloc_id'] == $alloc['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($alloc['subject_name']) ?> (<?= ordinal($alloc['current_year']) ?> Yr)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date *</label>
                                <input type="date" name="att_date" value="<?= $_GET['att_date'] ?? date('Y-m-d') ?>" onchange="this.form.submit()" max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </form>

                    <?php if (isset($_GET['att_alloc_id']) && isset($_GET['att_date'])): ?>
                        <?php 
                            $att_alloc = null;
                            foreach($my_allocations as $a) { if($a['id'] == $_GET['att_alloc_id']) $att_alloc = $a; }
                            
                            // Get students for this class
                            $att_students = getStudentsByClass($att_alloc['department'], $att_alloc['course'], $att_alloc['current_year']);
                            
                            // Get existing absentees for this date (if editing)
                            $existing_absentees = getClassAttendanceByDate($_GET['att_alloc_id'], $_GET['att_date']);
                        ?>
                        
                        <form method="POST" action="staff.php#attendance">
                            <input type="hidden" name="alloc_id" value="<?= $_GET['att_alloc_id'] ?>">
                            <input type="hidden" name="date" value="<?= $_GET['att_date'] ?>">
                            
                            <p class="alert alert-warning" style="margin-bottom: 15px;">
                                <small>Uncheck the box to mark a student <strong>ABSENT</strong>. (Default is Present)</small>
                            </p>

                            <div class="results-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Student Name</th>
                                            <th>Enrollment No</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($att_students as $stu): ?>
                                        <tr>
                                            <td style="text-align: center; width: 50px;">
                                                <input type="checkbox" 
                                                    style="width: 20px; height: 20px; cursor: pointer;"
                                                    name="present_dummy" 
                                                    checked
                                                    disabled
                                                    title="Logic Swapped for simplicity below">
                                                    
                                                <label style="margin-left: 10px; font-weight: bold; color: #c62828;">
                                                    <input type="checkbox" name="absentees[]" value="<?= $stu['user_id'] ?>" 
                                                        <?= in_array($stu['user_id'], $existing_absentees) ? 'checked' : '' ?> 
                                                        style="width: 18px; height: 18px; vertical-align: middle;">
                                                    Mark Absent
                                                </label>
                                            </td>
                                            <td><?= htmlspecialchars($stu['full_name']) ?></td>
                                            <td><?= htmlspecialchars($stu['enrollment_no']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <br>
                            <button type="submit" name="submit_attendance" class="btn btn-primary">Save Attendance</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            
            <section id="messages" class="dashboard-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Messages</h2>
                    <form method="GET" action="staff.php" id="messageFilterForm">
                        <select name="message_filter_id" 
                                onchange="document.getElementById('messageFilterForm').action = 'staff.php#messages'; this.form.submit();" 
                                style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <?php foreach ($my_allocations as $alloc): ?>
                                <option value="<?= $alloc['id'] ?>" <?= ($message_filter_id == $alloc['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($alloc['subject_name']) ?> 
                                    (<?= ordinal($alloc['current_year']) ?> Yr - Sem <?= $alloc['semester'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <div class="form-card" style="margin-bottom: 30px;">
                    <h3>Send Message to Student</h3>
                    <form method="POST" action="staff.php#messages">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="receiver">Select Student</label>
                                <select id="receiver" name="receiver_id" required>
                                    <option value="">Choose Student</option>
                                    <?php foreach ($message_students as $student): ?>
                                    <option value="<?= $student['user_id'] ?>">
                                        <?= htmlspecialchars($student['full_name']) ?> 
                                        (<?= htmlspecialchars($student['enrollment_no']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="4" placeholder="Type your message here..." required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
                
                <h3>Received Messages</h3>
                <div class="tab-buttons" style="margin-bottom: 15px; border-bottom: 2px solid #eee; display:flex; gap: 20px;">
                        <button type="button" onclick="showMsgTab('inbox')" id="btn-inbox" style="padding: 10px 20px; border: none; background: none; border-bottom: 3px solid #4361ee; font-weight: bold; color: #4361ee; cursor: pointer;">
                            Inbox
                        </button>
                        <button type="button" onclick="showMsgTab('sent')" id="btn-sent" style="padding: 10px 20px; border: none; background: none; border-bottom: 3px solid transparent; font-weight: bold; color: #666; cursor: pointer;">
                            Sent
                        </button>
                </div>

                <div id="msg-inbox" class="messages-container">
                    <?php if (count($filtered_messages) > 0): ?>
                        <?php foreach ($filtered_messages as $message): ?>
                        <a href="staff.php?mark_read=<?= $message['id'] ?>#messages" class="message-item <?= $message['is_read'] ? '' : 'unread' ?>">
                            <div class="message-avatar" style="background: var(--primary);"><?= strtoupper(substr($message['sender_name'], 0, 1)) ?></div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="sender"><?= htmlspecialchars($message['sender_name']) ?></span>
                                    <span class="time"><?= date('M d, g:i A', strtotime($message['sent_at'])) ?></span>
                                    <?php if (!$message['is_read']): ?>
                                        <span class="badge" style="background: var(--danger); color: white;">New</span>
                                    <?php endif; ?>
                                </div>
                                <p><?= htmlspecialchars($message['message']) ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <p>No messages received from this class.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="msg-sent" class="messages-container" style="display: none;">
                    <?php if (count($filtered_sent_messages) > 0): ?>
                        <?php foreach ($filtered_sent_messages as $message): ?>
                        <div class="message-item" style="background: #f8f9fa; border-left: 4px solid #aaa;">
                            <div class="message-avatar" style="background: #6c757d;">
                                <?= strtoupper(substr($message['receiver_name'], 0, 1)) ?>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="sender">To: <?= htmlspecialchars($message['receiver_name']) ?></span>
                                    <span class="time"><?= date('M d, g:i A', strtotime($message['sent_at'])) ?></span>
                                </div>
                                <p><?= htmlspecialchars($message['message']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <p>No sent messages found for this class.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum date for due date to today
        const dueDateInput = document.getElementById('due-date');
        if (dueDateInput) {
            const today = new Date().toISOString().split('T')[0];
            dueDateInput.setAttribute('min', today);
        }

        // Calculate total marks in real-time
        const markInputs = ['cia_1', 'task_1', 'cia_2', 'task_2', 'attendance', 'library'];
        markInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', calculateTotalAndGrade);
                input.addEventListener('change', validateMarks);
            }
        });

        calculateTotalAndGrade(); // Initial calculation

        // Form validation
        const form = document.getElementById('internalResultsForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Validate all marks are within range
                const marksValid = validateAllMarks();
                if (!marksValid) {
                    e.preventDefault();
                    // Alert is already shown in validateAllMarks()
                    return;
                }
            });
        }
    });

    // Calculate total marks and grade
    function calculateTotalAndGrade() {
        const cia1 = parseFloat(document.getElementById('cia_1').value) || 0;
        const task1 = parseFloat(document.getElementById('task_1').value) || 0;
        const cia2 = parseFloat(document.getElementById('cia_2').value) || 0;
        const task2 = parseFloat(document.getElementById('task_2').value) || 0;
        const attendance = parseFloat(document.getElementById('attendance').value) || 0;
        const library = parseFloat(document.getElementById('library').value) || 0;
        
        const total = cia1 + task1 + cia2 + task2 + attendance + library;
        document.getElementById('total_display').value = total.toFixed(1);
        
        // Calculate grade
        let grade = 'F';
        if (total >= 22.5) grade = 'A';
        else if (total >= 20) grade = 'B';
        else if (total >= 17.5) grade = 'C';
        else if (total >= 12.5) grade = 'D';
        
        document.getElementById('grade_display').value = grade;
        
        // Color code based on grade
        const gradeDisplay = document.getElementById('grade_display');
        gradeDisplay.style.backgroundColor = 
            grade === 'A' ? '#e8f5e9' :
            grade === 'B' ? '#fff3e0' :
            grade === 'C' ? '#ffebee' :
            grade === 'D' ? '#fff9c4' : '#ffcdd2';
    }

    // Validate individual marks
    function validateMarks(e) {
        const input = e.target;
        const max = parseFloat(input.getAttribute('max'));
        const min = parseFloat(input.getAttribute('min'));
        const value = parseFloat(input.value);
        
        if (isNaN(value) || value < min || value > max) {
            input.style.borderColor = 'red';
        } else {
            input.style.borderColor = '';
        }
    }

    // Validate all marks
    function validateAllMarks() {
        const inputs = [
            {id: 'cia_1', max: 7.5, name: 'CIA-1'},
            {id: 'task_1', max: 3, name: 'Task-1'},
            {id: 'cia_2', max: 7.5, name: 'CIA-2'},
            {id: 'task_2', max: 3, name: 'Task-2'},
            {id: 'attendance', max: 3, name: 'Attendance'},
            {id: 'library', max: 1, name: 'Library'}
        ];
        
        let allValid = true;
        let errorMessages = [];
        
        inputs.forEach(input => {
            const element = document.getElementById(input.id);
            const value = parseFloat(element.value); // Check if it's a valid number
            
            if (isNaN(value) || value < 0 || value > input.max) {
                element.style.borderColor = 'red';
                allValid = false;
                errorMessages.push(`${input.name} must be a number between 0 and ${input.max}`);
            } else {
                element.style.borderColor = '';
            }
        });
        
        if (!allValid) {
            alert('Validation Errors:\n' + errorMessages.join('\n'));
        }
        
        return allValid;
    }

    // Reset form
    function resetForm() {
        document.getElementById('internalResultsForm').reset();
        // Reset border colors
        ['cia_1', 'task_1', 'cia_2', 'task_2', 'attendance', 'library'].forEach(id => {
            document.getElementById(id).style.borderColor = '';
        });
        calculateTotalAndGrade();
    }

    // Delete result
    function deleteResult(resultId) {
        if (confirm('Are you sure you want to delete this result? This action cannot be undone.')) {
            window.location.href = 'staff.php?delete_result=' + resultId + '#results-overview';
        }
    }

    // Smooth scrolling for navigation
    document.querySelectorAll('.sidebar-nav a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const target = document.querySelector(targetId);
            if (target) {
                // Update active class on click
                document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
                
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                // Update URL hash without jumping
                if (history.pushState) {
                    history.pushState(null, null, targetId);
                } else {
                    location.hash = targetId;
                }
            }
        });
    });

    // --- NEW: Scroll Spy (Update Active Link on Scroll) ---
    window.addEventListener('scroll', function() {
        let current = '';
        const sections = document.querySelectorAll('.dashboard-section');
        const navLinks = document.querySelectorAll('.sidebar-nav ul li a');
        
        // 1. Find which section is currently in view
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            // -150 offset triggers the change slightly before the section hits the very top
            if (window.scrollY >= (sectionTop - 150)) {
                current = section.getAttribute('id');
            }
        });

        // 2. Update the active class in the sidebar
        navLinks.forEach(a => {
            a.classList.remove('active');
            const href = a.getAttribute('href');
            
            // If we have a current section, highlight that link
            if (current && href === '#' + current) {
                a.classList.add('active');
            } 
            // If no section is active (we are at the top), highlight "Dashboard"
            else if (!current && href === '#') {
                a.classList.add('active');
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            // Simple fade out
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => { alert.style.display = 'none'; }, 500);
        });
    }, 5000);

    // Ensure the correct tab is active on page load based on hash OR URL param
window.addEventListener('load', () => {
    let targetId = window.location.hash;
    
    // Fallback: Check for 'active_tab' in URL if hash is missing
    if (!targetId) {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('active_tab');
        if (activeTab) {
            targetId = '#' + activeTab;
            // Restore hash to URL for UX
            history.replaceState(null, null, targetId);
        }
    }

    if (targetId) {
        const link = document.querySelector(`.sidebar-nav a[href="${targetId}"]`);
        if (link) {
            document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
            link.classList.add('active');
            
            // Scroll to section
            const targetSection = document.querySelector(targetId);
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: 'smooth' });
            }
        }
    } else {
        // Default to first link
        const firstLink = document.querySelector('.sidebar-nav a');
        if(firstLink) firstLink.classList.add('active');
    }
});

    // Calculation Logic for Bulk Table
function calcRow(studentId) {
    const row = document.getElementById('row-' + studentId);
    
    // Helper to get value safely
    const getVal = (cls) => parseFloat(row.querySelector('.' + cls).value) || 0;
    
    const cia1 = getVal('cia1');
    const cia2 = getVal('cia2');
    const task1 = getVal('task1');
    const task2 = getVal('task2');
    const att = getVal('att');
    const lib = getVal('lib');
    
    // Calculate Total
    const total = cia1 + cia2 + task1 + task2 + att + lib;
    const totalInput = row.querySelector('.total');
    totalInput.value = total.toFixed(1);
    
    // Calculate Grade
    let grade = 'F';
    let color = '#c62828'; // Red for Fail
    
    if (total >= 22.5) { grade = 'A'; color = '#2e7d32'; }
    else if (total >= 20) { grade = 'B'; color = '#f57c00'; }
    else if (total >= 17.5) { grade = 'C'; color = '#e65100'; }
    else if (total >= 12.5) { grade = 'D'; color = '#d84315'; }
    
    const gradeSpan = row.querySelector('.grade-display');
    gradeSpan.textContent = grade;
    gradeSpan.style.color = color;
}

// Initialize calculations on page load
document.addEventListener('DOMContentLoaded', function() {
    // Run calculation for all rows to set initial totals and grades
    // Check if table exists first
    if(document.querySelector('.total')) {
        const rows = document.querySelectorAll('tr[id^="row-"]');
        rows.forEach(row => {
            const id = row.id.replace('row-', '');
            calcRow(id);
        });
    }
});

function addQuestionField() {
    const container = document.getElementById('questions-container');
    const index = container.children.length + 1;
    const html = `
        <div class="question-block" style="background:#f9f9f9; padding:15px; margin-bottom:15px; border-radius:5px; border:1px solid #ddd;">
            <h5>Question ${index}</h5>
            <input type="text" name="question[]" placeholder="Enter Question Text" required style="width:100%; margin-bottom:10px; padding:8px;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <input type="text" name="option_a[]" placeholder="Option A" required>
                <input type="text" name="option_b[]" placeholder="Option B" required>
                <input type="text" name="option_c[]" placeholder="Option C" required>
                <input type="text" name="option_d[]" placeholder="Option D" required>
            </div>
            <label style="margin-top:10px; display:block;">Correct Answer:</label>
            <select name="correct[]" required>
                <option value="A">Option A</option>
                <option value="B">Option B</option>
                <option value="C">Option C</option>
                <option value="D">Option D</option>
            </select>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
// Add one question by default
document.addEventListener('DOMContentLoaded', addQuestionField);

function showMsgTab(tab) {
        // Hide all
        document.getElementById('msg-inbox').style.display = 'none';
        document.getElementById('msg-sent').style.display = 'none';
        
        // Reset buttons
        document.getElementById('btn-inbox').style.borderBottomColor = 'transparent';
        document.getElementById('btn-inbox').style.color = '#666';
        document.getElementById('btn-sent').style.borderBottomColor = 'transparent';
        document.getElementById('btn-sent').style.color = '#666';
        
        // Show selected
        document.getElementById('msg-' + tab).style.display = 'flex'; // grid/flex depending on css
        document.getElementById('btn-' + tab).style.borderBottomColor = '#4361ee';
        document.getElementById('btn-' + tab).style.color = '#4361ee';
    }
    </script>
</body>
</html>