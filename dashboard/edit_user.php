<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Handle the update submission
if (isset($_POST['update_user'])) {
    $userId = $_POST['user_id'];
    
    // Collect data from POST
    $data = [
        'user_id' => $userId,
        'username' => $_POST['username'],
        'email' => $_POST['email'],
        'role' => $_POST['role'],
        'new_password' => $_POST['new_password'], // <-- ADDED THIS
        'full_name' => $_POST['full_name'] ?? null,
        'department' => $_POST['department'] ?? null,
        'subject' => $_POST['subject'] ?? null
    ];
    
    // Use the new function to update
    if (updateUserProfileByAdmin($userId, $data)) {
        header("Location: edit_user.php?id=$userId&success=User updated successfully!");
        exit();
    } else {
        $error = "Failed to update user. Username or email might already exist.";
    }
}

// Get user details for the form
if (!isset($_GET['id'])) {
    header('Location: admin.php?error=No user selected');
    exit();
}

$user_id = $_GET['id'];
$user = getUserDetailsById($user_id); // Use new helper function

if (!$user) {
    header('Location: admin.php?error=User not found');
    exit();
}

// Prevent editing admins
if ($user['role'] === 'admin') {
     header('Location: admin.php?error=Cannot edit admin users from this form');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Panel</title>
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
                    <li><a href="admin.php#manage-students">Manage Students</a></li>
                    <li><a href="admin.php#manage-users" class="active">Manage Users</a></li>
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
                    <h1>Edit User</h1>
                    <p>Editing profile for <?= htmlspecialchars($user['username']) ?></p>
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
                <h2>Edit User Details (Staff/HR)</h2>
                <div class="form-card">
                    <form method="POST" action="edit_user.php?id=<?= $user_id ?>">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                            <small style="color: var(--gray); font-size: 0.8em;">This will reset the user's password. They will no longer be able to use their old one.</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">Role *</label>
                                <select id="role" name="role" required onchange="toggleStaffFields()">
                                    <option value="staff" <?= $user['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
                                    <option value="hr" <?= $user['role'] == 'hr' ? 'selected' : '' ?>>HR Recruiter</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="staff_fields" style="display:none; border: 1px solid #eee; padding: 15px; border-radius: 8px; background: #fcfcfc;">
                            <h4 style="margin-top:0; margin-bottom: 15px; color: var(--primary);">Staff Profile Details</h4>
                            <div class="form-group">
                                <label for="user_full_name">Full Name * (Required for Staff)</label>
                                <input type="text" id="user_full_name" name="full_name" placeholder="Full name for staff member" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <select id="department" name="department">
                                        <option value="">Select Department</option>
                                        <option value="Computer Science" <?= $user['department'] == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                        <option value="Electronics" <?= $user['department'] == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                        <option value="Mechanical" <?= $user['department'] == 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                        <option value="Civil" <?= $user['department'] == 'Civil' ? 'selected' : '' ?>>Civil</option>
                                        <option value="Electrical" <?= $user['department'] == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                        <option value="Information Technology" <?= $user['department'] == 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                                        <option value="Administration" <?= $user['department'] == 'Administration' ? 'selected' : '' ?>>Administration</option>
                                        <option value="Library" <?= $user['department'] == 'Library' ? 'selected' : '' ?>>Library</option>
                                        <option value="Other" <?= $user['department'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="subject">Subject / Specialization</label>
                                    <input type="text" id="subject" name="subject" placeholder="e.g., Database Management, Physics" value="<?= htmlspecialchars($user['subject'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <br>
                        
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                        <a href="admin.php#manage-users" class="btn btn-outline">Cancel</a>
                    </form>
                </div>
            </section>
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
    <script>
    // This function shows/hides the staff fields based on the role dropdown
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
    
    // Run the function on page load to set the correct initial state
    document.addEventListener('DOMContentLoaded', function() {
        toggleStaffFields();
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    });
    </script>
</body>
</html>