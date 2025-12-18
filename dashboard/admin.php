<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

// --- ADD THESE LINES TO FIX THE MESSAGE DISPLAY ---
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
// --------------------------------------------------

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    logout();
    header('Location: ../index.php');
    exit();
}

// Handle Approval/Rejection
if (isset($_POST['update_status'])) {
    $u_id = $_POST['user_id'];
    $new_status = $_POST['status']; // 'active' or 'rejected'
    
    if ($new_status === 'rejected') {
        // --- LOGIC CHANGE: If rejected, DELETE the user immediately ---
        if (deleteUser($u_id)) {
            // Redirect to refresh the list and show success message
            header("Location: admin.php?success=" . urlencode("User request rejected and deleted successfully.") . "#pending-approvals");
            exit();
        } else {
            $error = "Failed to delete user.";
        }
    } else {
        // --- If approved, just update the status to 'active' ---
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $u_id])) {
            header("Location: admin.php?success=" . urlencode("User approved successfully!") . "#pending-approvals");
            exit();
        } else {
            $error = "Failed to update status.";
        }
    }
}

// Fetch Pending Users
$stmt = $pdo->prepare("SELECT * FROM users WHERE status = 'pending' AND role = 'hr'");
$stmt->execute();
$pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'department' => $_GET['department'] ?? '',
    'batch_year' => $_GET['batch_year'] ?? ''
];

// Handle Course Allocation
if (isset($_POST['allocate_subject'])) { // FIXED: Matches button name
    $staff_id = $_POST['staff_id'];
    $subject = $_POST['subject_name'];   // FIXED: Matches input name
    $dept = $_POST['department'];
    $course = $_POST['course'];          // Added to form below
    $semester = $_POST['semester'];
    $year = $_POST['current_year'];      // Added to form below
    $ac_year = $_POST['academic_year'];

    if (assignCourseToStaff($staff_id, $subject, $dept, $course, $semester, $year, $ac_year)) {
        $success = "Course assigned successfully!";
    } else {
        $error = "Failed to assign course.";
    }
}

// Handle Deletion of Allocation
if (isset($_GET['delete_allocation'])) {
    if (deleteAllocation($_GET['delete_allocation'])) {
        $success = "Allocation removed successfully.";
    }
}
$allAllocations = getAllAllocations(); // Fetch for list

// Handle add student form submission
// REPLACE the old "add_student" block with this one

if (isset($_POST['add_student'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $enrollment_no = $_POST['enrollment_no'];
    $department = $_POST['department'];
    $batch_year = $_POST['batch_year'];
    $course = $_POST['course'] ?? null;
    $current_year = $_POST['current_year'] ?? null;
    $contact_no = $_POST['contact_no'] ?? '';
    $skills = $_POST['skills'] ?? '';
    $address = $_POST['address'] ?? '';
    
    if (addStudentWithProfile($username, $email, $password, $full_name, $enrollment_no, $department, $batch_year, $course, $current_year, $contact_no, $skills, $address)) {
        $success = "Student added successfully!";
    } else {
        $error = "Failed to add student. Username or email might already exist.";
    }
}

// Handle add staff/hr/admin
// REPLACE the old "add_user" block with this one:

if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    // Get staff-specific fields
    $full_name = $_POST['full_name'] ?? '';
    $department = $_POST['department'] ?? '';
    $subject = $_POST['subject'] ?? '';
    
    if (addUser($username, $email, $password, $role, $full_name, $department, $subject)) {
        $success = ucfirst($role) . " added successfully!";
    } else {
        $error = "Failed to add user. Username or email might already exist.";
    }
}

// Handle delete student
if (isset($_GET['delete_student'])) {
    $userId = $_GET['delete_student'];
    if (deleteStudent($userId)) {
        $success = "Student deleted successfully!";
    } else {
        $error = "Failed to delete student.";
    }
}

// Handle delete user
if (isset($_GET['delete_user'])) {
    $userId = $_GET['delete_user'];
    if (deleteUser($userId)) {
        // Redirect to remove the 'delete_user' parameter from URL
        header("Location: admin.php?success=" . urlencode("User deleted successfully!") . "#manage-users");
        exit();
    } else {
        $error = "Failed to delete user.";
    }
}

// Get all data for display
$students = getAllStudents($filters);
$allUsers = getAllUsers();
$studentCount = count($students);
$staffCount = getStaffCount();
$hrCount = getHRCount();
$adminCount = getAdminCount();

// --- REPORTS FILTER LOGIC START ---
$rpt_dept = $_GET['rpt_dept'] ?? '';
$rpt_course = $_GET['rpt_course'] ?? '';
$rpt_year = $_GET['rpt_year'] ?? '';
$rpt_subject = $_GET['rpt_subject'] ?? '';

$report_results = [];
$rpt_available_subjects = [];

// 1. Determine which tab should be active
$active_tab = 'dashboard-home'; // Default tab
if ($rpt_dept || (isset($_GET['tab_target']) && $_GET['tab_target'] == 'reports-tab')) {
    $active_tab = 'reports-tab';
}

if ($rpt_dept && $rpt_course && $rpt_year) {
    // ... (Keep your existing query logic here) ...
    // Fetch Available Subjects
    $stmt = $pdo->prepare("SELECT DISTINCT ir.subject FROM internal_results ir JOIN student_profiles sp ON ir.student_id = sp.id WHERE sp.department = ? AND sp.course = ? AND sp.current_year = ? ORDER BY ir.subject");
    $stmt->execute([$rpt_dept, $rpt_course, $rpt_year]);
    $rpt_available_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch Filtered Results
    $sql = "SELECT ir.*, sp.enrollment_no, sp.full_name, sp.department, sp.course, sp.current_year,
            u.username as submitted_by_name, u.role as submitted_by_role
            FROM internal_results ir 
            JOIN student_profiles sp ON ir.student_id = sp.id 
            JOIN users u ON ir.submitted_by = u.id
            WHERE sp.department = ? AND sp.course = ? AND sp.current_year = ?";
    $params = [$rpt_dept, $rpt_course, $rpt_year];
    
    if ($rpt_subject) {
        $sql .= " AND ir.subject = ?";
        $params[] = $rpt_subject;
    }
    
    $sql .= " ORDER BY ir.academic_year DESC, ir.semester DESC, sp.enrollment_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $report_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Default: Show NOTHING until filters are selected
    $report_results = []; 
}
// --- REPORTS FILTER LOGIC END ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - College Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
        }
        .stat-label {
            color: var(--gray);
            font-size: 0.9em;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .tab-container {
            margin-top: 20px;
        }
        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab-button {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            white-space: nowrap;
        }
        .tab-button.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .user-role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .role-admin { background: #dc3545; color: white; }
        .role-staff { background: #fd7e14; color: white; }
        .role-hr { background: #20c997; color: white; }
        .role-student { background: #6f42c1; color: white; }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#dashboard" onclick="openTab('dashboard-home', this)">Dashboard</a></li>
                    <li><a href="#manage-students" onclick="openTab('students', this)">Manage Students</a></li>
                    <li><a href="#manage-users" onclick="openTab('users', this)">Manage Users</a></li>
                    <li><a href="#add-student" onclick="openTab('add-student-tab', this)">Add Student</a></li>
                    <li><a href="#add-user" onclick="openTab('add-user-tab', this)">Add User</a></li>
                    <li><a href="#reports" onclick="openTab('reports-tab', this)">Reports</a></li>
                    <li><a href="#attendance-shortage" onclick="openTab('attendance-shortage', this)">Attendance Shortage</a></li>
                    <li><a href="#" onclick="openTab('subject-allocation'); return false;">Subject Allocation</a></li>
                    <li><a href="publish_results.php">Publish Results</a></li>
                    <li><a href="#pending-approvals" onclick="openTab('pending-approvals', this)">Pending Approvals (<?= count($pending_users) ?>)</a></li>
                    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?logout=true">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?></p>
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
            
            <div id="dashboard-home" class="tab-content <?= $active_tab == 'dashboard-home' ? 'active' : '' ?>">
                <section class="dashboard-section">
                    <h2>System Overview</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Students</div>
                            <div class="stat-number"><?= $studentCount ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Staff Members</div>
                            <div class="stat-number"><?= $staffCount ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">HR Recruiters</div>
                            <div class="stat-number"><?= $hrCount ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Admins</div>
                            <div class="stat-number"><?= $adminCount ?></div>
                        </div>
                    </div>
                </section>
            </div>
            
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button <?= $active_tab == 'students' ? 'active' : '' ?>" onclick="openTab('students', this)">Manage Students</button>
                    <button class="tab-button" onclick="openTab('users', this)">Manage Users</button>
                    <button class="tab-button" onclick="openTab('add-student-tab', this)">Add Student</button>
                    <button class="tab-button" onclick="openTab('add-user-tab', this)">Add User</button>
                    <button class="tab-button" onclick="openTab('subject-allocation')">Subject Allocation</button>
                    <button class="tab-button <?= $active_tab == 'reports-tab' ? 'active' : '' ?>" onclick="openTab('reports-tab', this)">Reports</button>
                    <button class="tab-button" onclick="openTab('attendance-shortage', this)">Attendance Shortage</button>
                    
                    <button class="tab-button" onclick="openTab('pending-approvals', this)">
                        Pending Approvals 
                        <?php if(count($pending_users) > 0): ?>
                            <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 5px;">
                                <?= count($pending_users) ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div id="students" class="tab-content <?= $active_tab == 'students' ? 'active' : '' ?>">
                    <h2>Manage Students (<?= $studentCount ?>)</h2>
                    
                    <div class="search-filters">
                        <form method="GET">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="search">Search</label>
                                    <input type="text" id="search" name="search" placeholder="Search by name or enrollment no..." value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="department-filter">Department</label>
                                    <select id="department-filter" name="department">
                                        <option value="">All Departments</option>
                                        <option value="Computer Science" <?= $filters['department'] === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                        <option value="Electronics" <?= $filters['department'] === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                        <option value="Mechanical" <?= $filters['department'] === 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                        <option value="Civil" <?= $filters['department'] === 'Civil' ? 'selected' : '' ?>>Civil</option>
                                        <option value="Electrical" <?= $filters['department'] === 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="batch_year-filter">Batch Year</label>
                                    <select id="batch_year-filter" name="batch_year">
                                        <option value="">All Batches</option>
                                        <?php for ($year = date('Y'); $year >= 2018; $year--): ?>
                                            <option value="<?= $year ?>" <?= $filters['batch_year'] == $year ? 'selected' : '' ?>><?= $year ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="admin.php" class="btn btn-outline">Clear Filters</a>
                        </form>
                    </div>
                    
                    <div class="results-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Enrollment No</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Class Details</th>
                                    <th>Department</th>
                                    <th>Batch</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['enrollment_no']) ?></td>
                                        <td><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td><?= htmlspecialchars($student['username']) ?></td>
                                        <td style="font-weight: 500;">
                                            <?php
                                                // e.g., "1st Year B.Sc"
                                                echo htmlspecialchars(ordinal($student['current_year'] ?? 0)) . ' Year';
                                                echo ' ' . htmlspecialchars($student['course'] ?? '');
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($student['department']) ?></td>
                                        <td><?= htmlspecialchars($student['batch_year']) ?></td>
                                        <td><?= htmlspecialchars($student['contact_no'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_student.php?id=<?= $student['user_id'] ?>" class="btn btn-sm btn-outline">View</a>
                                                <a href="edit_student.php?id=<?= $student['user_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="?delete_student=<?= $student['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
                                            No students found. <a href="#add-student" onclick="openTab('add-student-tab', this)">Add the first student</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="users" class="tab-content">
                    <h2>Manage System Users</h2><br>
                    
                    <?php
                    $admins = [];
                    $staffs = [];
                    $hrs = [];
                    
                    foreach ($allUsers as $user) {
                        if ($user['id'] == $_SESSION['user_id']) continue; 
                        
                        if ($user['role'] === 'admin') {
                            $admins[] = $user;
                        } else if ($user['role'] === 'staff') {
                            $staffs[] = $user;
                        } else if ($user['role'] === 'hr') {
                            $hrs[] = $user;
                        }
                    }
                    ?>
                    
                    <section class="dashboard-section" style="box-shadow: none; padding-top: 10px; padding-bottom: 0;">
                        <h3 style="display: flex; align-items: center; gap: 10px;">
                            <span class="user-role-badge role-admin">Admins</span> 
                            <span style="font-size: 1rem; color: #666;">(<?= count($admins) ?>)</span>
                        </h3>
                        <div class="results-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($admins) > 0): ?>
                                        <?php foreach ($admins as $user): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <span class="btn btn-sm btn-outline" style="cursor: not-allowed; opacity: 0.5;">Cannot Edit/Delete Admin</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" style="text-align:center; padding: 20px; color: #666;">No other Admins found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="dashboard-section" style="box-shadow: none; padding-top: 10px; padding-bottom: 0;">
                        <h3 style="display: flex; align-items: center; gap: 10px;">
                            <span class="user-role-badge role-staff">Staff</span> 
                            <span style="font-size: 1rem; color: #666;">(<?= count($staffs) ?>)</span>
                        </h3>
                        <div class="results-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Department</th>
                                        <th>Subject / Specialization</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($staffs) > 0): ?>
                                        <?php foreach ($staffs as $user): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                            <td><?= htmlspecialchars($user['staff_full_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($user['department'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($user['subject'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="?delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this staff member? This action cannot be undone.')">Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" style="text-align:center; padding: 20px; color: #666;">No Staff members found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="dashboard-section" style="box-shadow: none; padding-top: 10px; padding-bottom: 0;">
                        <h3 style="display: flex; align-items: center; gap: 10px;">
                            <span class="user-role-badge role-hr">HRs</span> 
                            <span style="font-size: 1rem; color: #666;">(<?= count($hrs) ?>)</span>
                        </h3>
                        <div class="results-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($hrs) > 0): ?>
                                        <?php foreach ($hrs as $user): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="?delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this HR user? This action cannot be undone.')">Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" style="text-align:center; padding: 20px; color: #666;">No HR users found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    
                </div>
                
                <div id="add-student-tab" class="tab-content">
                    <h2>Add New Student</h2>
                    <div class="form-card">
                        <form method="POST" action="admin.php#add-student-tab">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">Username *</label>
                                    <input type="text" id="username" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">Password *</label>
                                    <input type="password" id="password" name="password" required>
                                </div>
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="enrollment_no">Enrollment Number *</label>
                                    <input type="text" id="enrollment_no" name="enrollment_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="department">Department *</label>
                                    <select id="department" name="department" required>
                                        <option value="">Select Department</option>
                                        <option value="Computer Science">Computer Science</option>
                                        <option value="Electronics">Electronics</option>
                                        <option value="Mechanical">Mechanical</option>
                                        <option value="Civil">Civil</option>
                                        <option value="Electrical">Electrical</option>
                                        <option value="Information Technology">Information Technology</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="course">Course (e.g., B.Sc, B.Tech)</label>
                                    <select id="course" name="course">
                                        <option value="">Select Course</option>
                                        <option value="B.Sc">B.Sc</option>
                                        <option value="B.Tech">B.Tech</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="B.A">B.A</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="M.Tech">M.Tech</option>
                                        <option value="Ph.D">Ph.D</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="current_year">Current Year (e.g., 1st, 2nd)</label>
                                    <select id="current_year" name="current_year">
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                        <option value="5">5th Year (Integrated)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="batch_year">Batch (Joining Year) *</label>
                                    <select id="batch_year" name="batch_year" required>
                                        <option value="">Select Batch Year</option>
                                        <?php for ($year = date('Y'); $year >= 2018; $year--): ?>
                                            <option value="<?= $year ?>"><?= $year ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="contact_no">Contact Number</label>
                                    <input type="tel" id="contact_no" name="contact_no">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="skills">Skills (comma separated)</label>
                                <input type="text" id="skills" name="skills" placeholder="e.g., PHP, JavaScript, MySQL">
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" placeholder="Full address"></textarea>
                            </div>
                            
                            <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                        </form>
                    </div>
                </div>
                
                <div id="add-user-tab" class="tab-content">
                    <h2>Add New User (Staff/HR/Admin)</h2>
                    <div class="form-card">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="user_username">Username *</label>
                                    <input type="text" id="user_username" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="user_email">Email *</label>
                                    <input type="email" id="user_email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="user_password">Password * (min. 6 chars)</label>
                                    <input type="password" id="user_password" name="password" required>
                                </div>
                                <div class="form-group">
                                    <label for="role">Role *</label>
                                    <select id="role" name="role" required onchange="toggleStaffFields()">
                                        <option value="">Select Role</option>
                                        <option value="staff">Staff</option>
                                        <option value="hr">HR Recruiter</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="staff_fields" style="display:none; border: 1px solid #eee; padding: 15px; border-radius: 8px; background: #fcfcfc;">
                                <h4 style="margin-top:0; margin-bottom: 15px; color: var(--primary);">Staff Profile Details</h4>
                                <div class="form-group">
                                    <label for="user_full_name">Full Name *</label>
                                    <input type="text" id="user_full_name" name="full_name" placeholder="Full name for staff member">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="department">Department</label>
                                        <select id="department" name="department">
                                            <option value="">Select Department</option>
                                            <option value="Computer Science">Computer Science</option>
                                            <option value="Electronics">Electronics</option>
                                            <option value="Mechanical">Mechanical</option>
                                            <option value="Civil">Civil</option>
                                            <option value="Electrical">Electrical</option>
                                            <option value="Information Technology">Information Technology</option>
                                            <option value="Administration">Administration</option>
                                            <option value="Library">Library</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="subject">Subject / Specialization</label>
                                        <input type="text" id="subject" name="subject" placeholder="e.g., Database Management, Physics">
                                    </div>
                                </div>
                            </div>
                            <br>
                            <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                        </form>
                    </div>
                </div>
                
                <div id="reports-tab" class="tab-content <?= $active_tab == 'reports-tab' ? 'active' : '' ?>">
                    <h2>System Reports</h2>
                    
                    <div class="filter-card">
                        <form method="GET" action="admin.php">
                            <input type="hidden" name="tab_target" value="reports-tab"> 
                            
                            <div class="form-row" style="align-items: end;">
                                <div class="form-group" style="margin:0;">
                                    <label>Department</label>
                                    <select name="rpt_dept" required onchange="this.form.submit()">
                                        <option value="">Select</option>
                                        <?php 
                                        $depts = ['Computer Science','Electronics','Mechanical','Civil','Electrical','Information Technology'];
                                        foreach($depts as $d) echo "<option value='$d' ".($rpt_dept==$d?'selected':'').">$d</option>";
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Course</label>
                                    <select name="rpt_course" required onchange="this.form.submit()">
                                        <option value="">Select</option>
                                        <?php 
                                        $courses = ['B.Sc','B.Tech','B.Com','B.A','M.Sc','M.Tech'];
                                        foreach($courses as $c) echo "<option value='$c' ".($rpt_course==$c?'selected':'').">$c</option>";
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label>Year</label>
                                    <select name="rpt_year" required onchange="this.form.submit()">
                                        <option value="">Select</option>
                                        <?php for($i=1;$i<=5;$i++) echo "<option value='$i' ".($rpt_year==$i?'selected':'').">$i Year</option>"; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0; min-width: 200px;">
                                    <label>Subject (Optional)</label>
                                    <select name="rpt_subject" onchange="this.form.submit()" style="border-color: var(--primary);">
                                        <option value="">-- All Subjects --</option>
                                        <?php foreach($rpt_available_subjects as $sub): ?>
                                            <option value="<?= htmlspecialchars($sub) ?>" <?= $rpt_subject == $sub ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sub) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <a href="admin.php#reports" class="btn btn-outline" style="height: 48px; line-height: 24px;">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div style="margin-bottom: 30px;">
                        <h3>Internal Results Report</h3>
                        
                        <?php if (count($report_results) > 0): ?>
                            <div class="results-summary" style="margin-bottom: 20px;">
                                <div class="summary-card">
                                    <div class="summary-value"><?= count($report_results) ?></div>
                                    <div class="summary-label">Total Records</div>
                                </div>
                                <div class="summary-card">
                                    <div class="summary-value">
                                        <?= count($report_results) > 0 ? 
                                            round(array_sum(array_column($report_results, 'total_marks')) / count($report_results), 2) : 0 ?>
                                    </div>
                                    <div class="summary-label">Avg Marks</div>
                                </div>
                            </div>

                            <div class="results-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Register No</th>
                                            <th>Student Name</th>
                                            <th>Class Details</th>
                                            <th>Subject</th>
                                            <th>Staff</th>
                                            <th>Marks Breakdown</th>
                                            <th>Total</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_results as $result): ?>
                                        <?php 
                                        $total_marks = $result['total_marks'];
                                        $grade = calculateGrade($total_marks);
                                        $grade_class = 'grade-' . strtolower($grade);
                                        ?>
                                        <tr class="<?= $grade_class ?>">
                                            <td><?= htmlspecialchars($result['enrollment_no']) ?></td>
                                            <td><?= htmlspecialchars($result['full_name']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($result['department']) ?><br>
                                                <small><?= htmlspecialchars($result['course'] ?? '') ?> - <?= htmlspecialchars($result['current_year'] ?? '') ?> Yr</small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($result['subject'] ?? 'N/A') ?><br>
                                                <small>Sem: <?= htmlspecialchars($result['semester']) ?></small>
                                            </td>
                                            <td>
                                                <span class="user-role-badge role-<?= $result['submitted_by_role'] ?? 'staff' ?>">
                                                    <?= htmlspecialchars($result['submitted_by_name'] ?? 'Unknown') ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 0.85em;">
                                                CIA: <?= $result['cia_1'] ?>+<?= $result['cia_2'] ?><br>
                                                Task: <?= $result['task_1'] ?>+<?= $result['task_2'] ?><br>
                                                Att/Lib: <?= $result['attendance'] ?>+<?= $result['library'] ?>
                                            </td>
                                            <td class="total-score"><?= number_format($total_marks, 1) ?></td>
                                            <td><strong><?= $grade ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666; background: #f9f9f9; border-radius: 8px;">
                                <?php if ($rpt_dept): ?>
                                    <h3>No Results Found</h3>
                                    <p>No students found for the selected Class/Subject filter.</p>
                                <?php else: ?>
                                    <h3 style="color: var(--primary);">Generate Report</h3>
                                    <p>Please select a <strong>Department, Course, and Year</strong> above to view student results.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="attendance-shortage" class="tab-content">
                    <h2 style="color: #c62828;">⚠️ Attendance Shortage List (< 75%)</h2>
                    <p style="color: #666; margin-bottom: 20px;">Students appearing here are currently ineligible for exams based on attendance criteria.</p>
                    
                    <?php 
                    // 75 is the threshold percentage
                    $shortage_list = getAttendanceShortageList(75); 
                    ?>
                    
                    <div class="results-table">
                        <?php if (count($shortage_list) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Enrollment No</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Class</th>
                                        <th>Overall %</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shortage_list as $stu): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($stu['enrollment_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($stu['full_name']) ?></td>
                                        <td><?= htmlspecialchars($stu['department']) ?></td>
                                        <td>
                                            <?= htmlspecialchars(ordinal($stu['current_year'])) ?> Yr <?= htmlspecialchars($stu['course']) ?>
                                        </td>
                                        <td style="color: #c62828; font-weight: bold; font-size: 1.1em;">
                                            <?= number_format($stu['percentage'], 1) ?>%
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline" onclick="alert('TODO: Implement One-Click Warning System for <?= $stu['full_name'] ?>')">
                                                Send Warning
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-success" style="text-align: center; padding: 20px;">
                                ✅ Great news! No students are currently below 75% attendance.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="subject-allocation" class="tab-content">
                    <h2>Subject Allocation</h2>
                    
                    <div class="form-card" style="margin-bottom: 30px;">
                        <h3>Assign Subject to Staff</h3>
                        <form method="POST" action="admin.php#subject-allocation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="staff_id">Select Staff Member *</label>
                                    <select id="staff_id" name="staff_id" required onchange="autoFillDept()">
                                        <option value="">Choose Staff...</option>
                                        <?php foreach ($allUsers as $user): ?>
                                            <?php if ($user['role'] === 'staff'): ?>
                                                <option value="<?= $user['id'] ?>" data-dept="<?= htmlspecialchars($user['department'] ?? '') ?>">
                                                    <?= htmlspecialchars($user['staff_full_name'] ?: $user['username']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="subject_name">Subject Name *</label>
                                    <input type="text" id="subject_name" name="subject_name" placeholder="e.g. Data Structures" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="alloc_dept">Department *</label>
                                    <input type="text" id="alloc_dept" name="department" placeholder="Auto-filled from Staff" required readonly style="background-color: #eee;">
                                </div>
                                
                                <div class="form-group">
                                    <label for="alloc_course">Course *</label>
                                    <select id="alloc_course" name="course" required>
                                        <option value="B.Tech">B.Tech</option>
                                        <option value="B.Sc">B.Sc</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="B.A">B.A</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="M.Tech">M.Tech</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="alloc_year">Current Year *</label>
                                    <select id="alloc_year" name="current_year" required onchange="updateAcademicDetails()">
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="alloc_semester">Semester *</label>
                                    <select id="alloc_semester" name="semester" required>
                                        <option value="">Select Year First</option>
                                        </select>
                                </div>

                                <div class="form-group">
                                    <label for="academic_year">Academic Year (Batch)</label>
                                    <input type="text" id="academic_year_display" name="academic_year" required readonly style="background-color: #eee;">
                                </div>
                            </div>

                            <button type="submit" name="allocate_subject" class="btn btn-primary">Allocate Subject</button>
                        </form>
                        
                        <script>
                        function autoFillDept() {
                            const staffSelect = document.getElementById('staff_id');
                            const deptInput = document.getElementById('alloc_dept');
                            
                            // Get the data-dept attribute from the selected option
                            const selectedOption = staffSelect.options[staffSelect.selectedIndex];
                            if (selectedOption && selectedOption.getAttribute('data-dept')) {
                                deptInput.value = selectedOption.getAttribute('data-dept');
                            } else {
                                deptInput.value = '';
                            }
                        }

                        function updateAcademicDetails() {
                            const yearSelect = document.getElementById('alloc_year');
                            const academicYearInput = document.getElementById('academic_year_display');
                            const semesterSelect = document.getElementById('alloc_semester');
                            
                            const selectedYear = parseInt(yearSelect.value);
                            
                            if (!selectedYear) {
                                academicYearInput.value = '';
                                semesterSelect.innerHTML = '<option value="">Select Year First</option>';
                                return;
                            }

                            // 1. Calculate Academic Year (Batch logic requested)
                            // If current year is 2025:
                            // 1st Year -> 2025-26
                            // 2nd Year -> 2024-25
                            // 3rd Year -> 2023-24
                            const currentRealYear = new Date().getFullYear(); 
                            // Note: You can hardcode this to 2025 if you want it fixed, or use currentRealYear for dynamic
                            // Based on your specific request for 1st year = 2025-26:
                            const baseYear = 2025; // Or use currentRealYear
                            
                            const startYear = baseYear - (selectedYear - 1);
                            const endYear = startYear + 1;
                            // Format: "2023-24" (using short year for the second part)
                            const shortEndYear = endYear.toString().slice(-2);
                            
                            academicYearInput.value = `${startYear}-${shortEndYear}`;

                            // 2. Populate Semesters
                            // 1st Year -> Sem 1, 2
                            // 2nd Year -> Sem 3, 4
                            // 3rd Year -> Sem 5, 6
                            // 4th Year -> Sem 7, 8
                            const semStart = (selectedYear * 2) - 1;
                            const semEnd = selectedYear * 2;
                            
                            semesterSelect.innerHTML = ''; // Clear existing
                            
                            const opt1 = document.createElement('option');
                            opt1.value = semStart;
                            opt1.text = 'Semester ' + semStart;
                            semesterSelect.add(opt1);
                            
                            const opt2 = document.createElement('option');
                            opt2.value = semEnd;
                            opt2.text = 'Semester ' + semEnd;
                            semesterSelect.add(opt2);
                        }
                        </script>
                    </div>

                    <div class="results-table">
                        <h3>Current Allocations</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Subject</th>
                                    <th>Department</th>
                                    <th>Class Details</th> <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($allAllocations) && count($allAllocations) > 0): ?>
                                    <?php foreach ($allAllocations as $alloc): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($alloc['full_name'] ?: $alloc['username']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($alloc['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($alloc['department']) ?></td>
                                        <td>
                                            <?= htmlspecialchars(ordinal($alloc['current_year'])) ?> Year 
                                            (Sem <?= htmlspecialchars($alloc['semester'] ?? '?') ?>)
                                            <br>
                                            <?= htmlspecialchars($alloc['course']) ?> | Batch: <?= htmlspecialchars($alloc['academic_year']) ?>
                                        </td>
                                        <td>
                                            <a href="admin.php?delete_allocation=<?= $alloc['id'] ?>#subject-allocation" 
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to remove this subject allocation?');">
                                            Remove
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5">No subjects allocated yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="pending-approvals" class="tab-content">
                    <h2>Pending HR Approvals</h2>
                    
                    <?php if (empty($pending_users)): ?>
                        <div class="alert alert-success">No pending approvals found. All clear!</div>
                    <?php else: ?>
                        <div class="results-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_users as $p_user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p_user['username']) ?></td>
                                        <td><?= htmlspecialchars($p_user['email']) ?></td>
                                        <td><span class="badge" style="background:orange;">HR</span></td>
                                        <td><?= date('M d, Y', strtotime($p_user['created_at'])) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 10px;">
                                                <form method="POST" action="admin.php#pending-approvals">
                                                    <input type="hidden" name="user_id" value="<?= $p_user['id'] ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">Approve</button>
                                                </form>
                                                <form method="POST" action="admin.php#pending-approvals">
                                                    <input type="hidden" name="user_id" value="<?= $p_user['id'] ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-danger" onclick="return confirm('Reject this user?')">Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Student Details</h3>
            <div id="studentModalContent">
                </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    
    <script>
    
    /**
     * Main function to switch tabs and update active states
     * @param {string} tabName - The ID of the tab content to show (e.g., 'students', 'users')
     * @param {HTMLElement} [clickedElement] - The element that was clicked (optional, used to set active)
     */
    function openTab(tabName, clickedElement) {
        // 1. Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // 2. Remove active class from all tab buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active');
        });
        
        // 3. Remove active class from all sidebar links
        document.querySelectorAll('.sidebar-nav a').forEach(a => {
            a.classList.remove('active');
        });
        
        // 4. Show the selected tab content
        const tabContent = document.getElementById(tabName);
        if (tabContent) {
            tabContent.classList.add('active');
        }
        
        // 5. Find and activate the matching tab button
        const tabButton = document.querySelector(`.tab-button[onclick*="'${tabName}'"]`);
        if (tabButton) {
            tabButton.classList.add('active');
        }
        
        // 6. Find and activate the matching sidebar link
        const sidebarLinkMap = {
            'students': '#manage-students',
            'users': '#manage-users',
            'add-student-tab': '#add-student',
            'add-user-tab': '#add-user',
            'reports-tab': '#reports'
        };
        const sidebarLinkSelector = sidebarLinkMap[tabName];
        
        if (sidebarLinkSelector) {
            const sidebarLink = document.querySelector(`.sidebar-nav a[href="${sidebarLinkSelector}"]`);
            if (sidebarLink) {
                sidebarLink.classList.add('active');
            }
        } else {
            // Default to Dashboard link if no match (e.g., admin.php page load)
            const dashboardLink = document.querySelector('.sidebar-nav a[href="admin.php"]');
            if (dashboardLink) {
                dashboardLink.classList.add('active');
            }
        }
        
        // 7. Update the URL hash
        const newHash = sidebarLinkSelector || '';
        if (history.pushState) {
            // Check if we are on the base admin.php page before changing hash
            if (window.location.pathname.endsWith('admin.php')) {
                history.pushState(null, null, newHash);
            }
        } else {
            if (window.location.pathname.endsWith('admin.php')) {
                location.hash = newHash;
            }
        }
    }
    
    /**
     * Handle page load and check for URL hash
     */

    document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    const tabMap = {
        '#dashboard': 'dashboard-home', // <--- Added this line
        '#manage-students': 'students', 
        '#manage-users': 'users', 
        '#add-student': 'add-student-tab',
        '#add-user': 'add-user-tab', 
        '#reports': 'reports-tab',
        '#attendance-shortage': 'attendance-shortage',
        '#pending-approvals': 'pending-approvals'
    };
    
    let tabName = tabMap[hash];
    
    if (tabName) {
        // If there's a hash in the URL, open that tab
        openTab(tabName);
    } else {
        // FIX: Check if PHP already set a tab to 'active' (like Reports)
        const activeTabAlreadySet = document.querySelector('.tab-content.active');
        
        if (!activeTabAlreadySet) {
            // Only default to students if NO tab is currently active
            openTab('students');
        } else {
            // If PHP set the tab, just make sure the Sidebar & Button are highlighted
            const activeId = activeTabAlreadySet.id;
            
            // Highlight Tab Button
            const btn = document.querySelector(`.tab-button[onclick*="'${activeId}'"]`);
            if(btn) btn.classList.add('active');

            // Highlight Sidebar Link
            // Reverse lookup the map to find the hash for the sidebar link
            const hashKey = Object.keys(tabMap).find(key => tabMap[key] === activeId);
            if(hashKey) {
                const link = document.querySelector(`.sidebar-nav a[href="${hashKey}"]`);
                if(link) link.classList.add('active');
            }
        }
    }
});
    
    // --- Other JavaScript functions ---

    // Modal functionality
    const modal = document.getElementById('studentModal');
    const span = document.getElementsByClassName('close')[0];
    
    // ===== VIEW STUDENT JS REMOVED =====
    // The old .view-student alert code is no longer needed.
    // ===================================
    
    // Close modal
    if(span) {
        span.onclick = function() {
            modal.style.display = "none";
        }
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    
    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.display = 'none';
        });
    }, 5000);
    
    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const password = this.querySelector('input[type="password"]');
            if (password && password.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                password.focus();
            }
        });
    });

    // Toggle staff fields in "Add User" form
    function toggleStaffFields() {
        var role = document.getElementById('role').value;
        var staffFields = document.getElementById('staff_fields');
        var fullNameInput = document.getElementById('user_full_name');
        
        if (role === 'staff') {
            staffFields.style.display = 'block';
            fullNameInput.required = true; // Make full name required for staff
        } else {
            staffFields.style.display = 'none';
            fullNameInput.required = false;
        }
    }
    </script>
    
    <style>
        /* Add these styles if not already present */
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
        .total-score {
            background: #e8f5e9;
            font-weight: bold;
        }
        .grade-a { background: #e8f5e9; }
        .grade-b { background: #fff3e0; }
        .grade-c { background: #ffebee; }
        .grade-d { background: #fff9c4; }
        .grade-f { background: #ffcdd2; }
        
        /* User role badges */
        .user-role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .role-admin { background: #dc3545; color: white; }
        .role-staff { background: #fd7e14; color: white; }
        .role-hr { background: #20c997; color: white; }
        .role-student { background: #6f42c1; color: white; }
    </style>
</body>
</html>