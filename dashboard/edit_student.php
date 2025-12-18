<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Handle the update
if (isset($_POST['update_student'])) {
    $userId = $_POST['user_id'];
    
    // Collect data from POST
    $data = [
        'user_id' => $userId,
        'username' => $_POST['username'],
        'email' => $_POST['email'],
        'new_password' => $_POST['new_password'], // <-- ADDED THIS
        'full_name' => $_POST['full_name'],
        'enrollment_no' => $_POST['enrollment_no'],
        'department' => $_POST['department'],
        'course' => $_POST['course'] ?? null,
        'batch_year' => $_POST['batch_year'],
        'current_year' => $_POST['current_year'] ?? null,
        'contact_no' => $_POST['contact_no'] ?? null,
        'skills' => $_POST['skills'] ?? null,
        'address' => $_POST['address'] ?? null
    ];
    
    // Use the existing function from functions.php to update
    if (updateStudentProfileByAdmin($userId, $data)) {
        header("Location: edit_student.php?id=$userId&success=Student updated successfully!");
        exit();
    } else {
        $error = "Failed to update student. Username, email, or enrollment no might already exist.";
    }
}

// Get student details for the form
if (!isset($_GET['id'])) {
    header('Location: admin.php?error=No student selected');
    exit();
}

$student_id = $_GET['id'];
$student = getStudentByUserId($student_id); // This function is already in functions.php

if (!$student) {
    header('Location: admin.php?error=Student not found');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin.php">Dashboard</a></li>
                    <li><a href="admin.php#manage-students" class="active">Manage Students</a></li>
                    <li><a href="admin.php#manage-users">Manage Users</a></li>
                    <li><a href="admin.php#add-student">Add Student</a></li>
                    <li><a href="admin.php#add-user">Add User</a></li>
                    <li><a href="admin.php#reports">Reports</a></li>
                    <li><a href="admin.php?logout=true">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Edit Student</h1>
                    <p>Editing profile for <?= htmlspecialchars($student['full_name']) ?></p>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <span>Admin</span>
                        <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 2)) ?></div>
                    </div>
                </div>
            </header>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <section class="dashboard-section">
                <h2>Edit Student Details</h2>
                <div class="form-card">
                    <form method="POST" action="edit_student.php?id=<?= $student_id ?>">
                        <input type="hidden" name="user_id" value="<?= $student_id ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars($student['username']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                            <small style="color: var(--gray); font-size: 0.8em;">This will reset the student's password. They will no longer be able to use their old one.</small>
                        </div>
                        <div class="form-row">
                             <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="enrollment_no">Enrollment Number *</label>
                                <input type="text" id="enrollment_no" name="enrollment_no" value="<?= htmlspecialchars($student['enrollment_no']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department">Department *</label>
                                <select id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science" <?= $student['department'] == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                    <option value="Electronics" <?= $student['department'] == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                    <option value="Mechanical" <?= $student['department'] == 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                    <option value="Civil" <?= $student['department'] == 'Civil' ? 'selected' : '' ?>>Civil</option>
                                    <option value="Electrical" <?= $student['department'] == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                    <option value="Information Technology" <?= $student['department'] == 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="batch_year">Batch (Joining Year) *</label>
                                <select id="batch_year" name="batch_year" required>
                                    <option value="">Select Batch Year</option>
                                    <?php for ($year = date('Y'); $year >= 2018; $year--): ?>
                                        <option value="<?= $year ?>" <?= $student['batch_year'] == $year ? 'selected' : '' ?>><?= $year ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="course">Course (e.g., B.Sc, B.Tech)</label>
                                <select id="course" name="course">
                                    <option value="">Select Course</option>
                                    <option value="B.Sc" <?= $student['course'] == 'B.Sc' ? 'selected' : '' ?>>B.Sc</option>
                                    <option value="B.Tech" <?= $student['course'] == 'B.Tech' ? 'selected' : '' ?>>B.Tech</option>
                                    <option value="B.Com" <?= $student['course'] == 'B.Com' ? 'selected' : '' ?>>B.Com</option>
                                    <option value="B.A" <?= $student['course'] == 'B.A' ? 'selected' : '' ?>>B.A</option>
                                    <option value="M.Sc" <?= $student['course'] == 'M.Sc' ? 'selected' : '' ?>>M.Sc</option>
                                    <option value="M.Tech" <?= $student['course'] == 'M.Tech' ? 'selected' : '' ?>>M.Tech</option>
                                    <option value="Ph.D" <?= $student['course'] == 'Ph.D' ? 'selected' : '' ?>>Ph.D</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="current_year">Current Year (e.g., 1st, 2nd)</label>
                                <select id="current_year" name="current_year">
                                    <option value="">Select Year</option>
                                    <option value="1" <?= $student['current_year'] == '1' ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2" <?= $student['current_year'] == '2' ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3" <?= $student['current_year'] == '3' ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4" <?= $student['current_year'] == '4' ? 'selected' : '' ?>>4th Year</option>
                                    <option value="5" <?= $student['current_year'] == '5' ? 'selected' : '' ?>>5th Year (Integrated)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_no">Contact Number</label>
                            <input type="tel" id="contact_no" name="contact_no" value="<?= htmlspecialchars($student['contact_no'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="skills">Skills (comma separated)</label>
                            <input type="text" id="skills" name="skills" placeholder="e.g., PHP, JavaScript, MySQL" value="<?= htmlspecialchars($student['skills'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3" placeholder="Full address"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                        <a href="admin.php#manage-students" class="btn btn-outline">Cancel</a>
                    </form>
                </div>
            </section>
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
</body>
</html>