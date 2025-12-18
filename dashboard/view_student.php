<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

// Get student details for the form
if (!isset($_GET['id'])) {
    header('Location: admin.php?error=No student selected');
    exit();
}

$student_user_id = $_GET['id'];
$profile = getStudentByUserId($student_user_id); // Fetches from student_profiles

if (!$profile) {
    header('Location: admin.php?error=Student not found');
    exit();
}

// Get the student_profile_id (which is different from the user_id)
$student_profile_id = $profile['id'];

// Fetch all related student data
$results = getAcademicResults($student_profile_id);
$internalResults = getStudentInternalResults($student_profile_id);
$tasks = getTasks($student_user_id, 'student');
$projects = getStudentProjects($student_user_id);
$achievements = getStudentAchievements($student_user_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-card {
            max-width: 100%;
        }
        .detail-row .label {
            width: 150px;
        }
        .badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin: 2px;
        }
        .project-card, .achievement-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
        }
        .link-button {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .link-button:hover {
            text-decoration: underline;
        }
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
                    <h1>View Student Profile</h1>
                    <p><?= htmlspecialchars($profile['full_name']) ?></p>
                </div>
                <div class="header-right">
                    <a href="admin.php#manage-students" class="btn btn-outline" style="margin-right: 20px;">Back to List</a>
                    <a href="edit_student.php?id=<?= $student_user_id ?>" class="btn btn-primary">Edit This Student</a>
                </div>
            </header>

            <section class="dashboard-section">
                <h2>Student Details</h2>
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($profile['full_name'], 0, 2)) ?>
                        </div>
                        <div class="profile-info">
                            <h3><?= htmlspecialchars($profile['full_name']) ?></h3>
                            <p><?= htmlspecialchars($profile['enrollment_no']) ?> | <?= htmlspecialchars($profile['department']) ?></p>
                            <p>Batch <?= htmlspecialchars($profile['batch_year']) ?> | <?= htmlspecialchars(ordinal($profile['current_year'] ?? 0)) ?> Year <?= htmlspecialchars($profile['course'] ?? '') ?></p>
                            
                            <?php if (!empty($profile['github_link']) || !empty($profile['linkedin_link'])): ?>
                                <div style="margin-top: 10px;">
                                    <?php if (!empty($profile['github_link'])): ?>
                                        <a href="<?= htmlspecialchars($profile['github_link']) ?>" target="_blank" class="link-button">GitHub</a> |
                                    <?php endif; ?>
                                    <?php if (!empty($profile['linkedin_link'])): ?>
                                        <a href="<?= htmlspecialchars($profile['linkedin_link']) ?>" target="_blank" class="link-button">LinkedIn</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-row">
                            <span class="label">Username</span>
                            <span class="value"><?= htmlspecialchars($profile['username']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Email</span>
                            <span class="value"><?= htmlspecialchars($profile['email']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Contact Number</span>
                            <span class="value"><?= htmlspecialchars($profile['contact_no'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Skills</span>
                            <span class="value"><?= htmlspecialchars($profile['skills'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Area of Interest</span>
                            <span class="value"><?= htmlspecialchars($profile['area_of_interest'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Address</span>
                            <span class="value"><?= htmlspecialchars($profile['address'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </section>
            
            <section id="internal-results" class="dashboard-section">
    <h2>Internal Results</h2>
    <div class="results-table">
        <?php if (count($internalResults) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Staff</th> <th>Semester</th>
                        <th>Academic Year</th>
                        <th>CIA-1 (7.5)</th>
                        <th>Task-1 (3)</th>
                        <th>CIA-2 (7.5)</th>
                        <th>Task-2 (3)</th>
                        <th>Attendance (3)</th>
                        <th>Library (1)</th>
                        <th>Total (25)</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($internalResults as $result): ?>
                    <?php $grade = calculateGrade($result['total_marks']); ?>
                    <tr>
                        <td><?= htmlspecialchars($result['subject'] ?? 'N/A') ?></td>
                        <td style="color: #555; font-style: italic;">
                            <?= htmlspecialchars($result['staff_name'] ?? 'Unknown') ?>
                        </td>
                        <td><?= htmlspecialchars($result['semester']) ?></td>
                        <td><?= htmlspecialchars($result['academic_year']) ?></td>
                        <td><?= htmlspecialchars($result['cia_1']) ?></td>
                        <td><?= htmlspecialchars($result['task_1']) ?></td>
                        <td><?= htmlspecialchars($result['cia_2']) ?></td>
                        <td><?= htmlspecialchars($result['task_2']) ?></td>
                        <td><?= htmlspecialchars($result['attendance']) ?></td>
                        <td><?= htmlspecialchars($result['library']) ?></td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($result['total_marks']) ?></td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($grade) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 20px;">No internal results found.</p>
        <?php endif; ?>
    </div>
</section>
            
            <section id="results" class="dashboard-section">
                <h2>Academic Results</h2>
                <div class="results-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Semester</th>
                                <th>Academic Year</th>
                                <th>Marks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($results) > 0): ?>
                                <?php foreach ($results as $result): ?>
                                <tr>
                                    <td><?= htmlspecialchars($result['subject']) ?></td>
                                    <td><?= htmlspecialchars($result['semester']) ?></td>
                                    <td><?= htmlspecialchars($result['academic_year']) ?></td>
                                    <td><?= htmlspecialchars($result['marks_obtained']) ?>/<?= htmlspecialchars($result['total_marks']) ?></td>
                                    <td>
                                        <?php 
                                        $percentage = ($result['marks_obtained'] / $result['total_marks']) * 100;
                                        $status = $percentage >= 40 ? 'Pass' : 'Fail';
                                        $statusClass = $percentage >= 40 ? 'status-pass' : 'status-fail';
                                        ?>
                                        <span class="<?= $statusClass ?>"><?= $status ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; color: #666; padding: 20px;">No academic results found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section id="tasks" class="dashboard-section">
                <h2>Assigned Tasks</h2>
                <div class="tasks-container">
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach ($tasks as $task): ?>
                        <div class="task-card <?= $task['status'] ?>">
                            <div class="task-header">
                                <h4><?= htmlspecialchars($task['title']) ?></h4>
                                <span class="task-status"><?= ucfirst($task['status']) ?></span>
                            </div>
                            <p><strong>Assigned by:</strong> <?= htmlspecialchars($task['assigned_by_name']) ?> (Staff)</p>
                            <p><?= htmlspecialchars($task['description']) ?></p>
                            <div class="task-footer">
                                <span>Due: <?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                            </div>
                            
                            <?php if ($task['evaluation_result']): ?>
                                <div class="evaluation-result" style="margin-top: 15px; padding: 10px; background: #e8f5e9; border-radius: 5px;">
                                    <h5>📊 Evaluation Results:</h5>
                                    <p><strong>Score:</strong> <span style="font-size: 1.2em; font-weight: bold; color: #2e7d32;"><?= $task['evaluation_score'] ?>/100</span></p>
                                    <p><strong>Feedback from <?= htmlspecialchars($task['evaluated_by_name'] ?? 'Staff') ?>:</strong></p>
                                    <p style="font-style: italic;">"<?= htmlspecialchars($task['evaluation_result']) ?>"</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No tasks found.</p>
                    <?php endif; ?>
                </div>
            </section>
            
            <section id="portfolio" class="dashboard-section">
                <h2>Student Portfolio</h2>
                
                <h3>Projects</h3>
                <div class="projects-list">
                    <?php if (count($projects) > 0): ?>
                        <?php foreach ($projects as $project): ?>
                        <div class="project-card">
                            <h4><?= htmlspecialchars($project['project_name']) ?></h4>
                            <p><?= htmlspecialchars($project['project_description']) ?></p>
                            <div class="task-footer">
                                <span><strong>Technologies:</strong> <?= htmlspecialchars($project['technologies_used']) ?></span>
                                <span><a href="<?= htmlspecialchars($project['project_link']) ?>" target="_blank" class="link-button">View Project</a></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No projects added.</p>
                    <?php endif; ?>
                </div>
                
                <h3 style="margin-top: 20px;">Achievements</h3>
                <div class="achievements-list">
                    <?php if (count($achievements) > 0): ?>
                        <?php foreach ($achievements as $achievement): ?>
                        <div class="achievement-card">
                            <div class="task-header">
                                <h4><?= htmlspecialchars($achievement['title']) ?></h4>
                                <span class="badge"><?= ucfirst($achievement['achievement_type']) ?></span>
                            </div>
                            <p><?= htmlspecialchars($achievement['description']) ?></p>
                            <div class="task-footer">
                                <span><strong>Organization:</strong> <?= htmlspecialchars($achievement['organization']) ?></span>
                                <span>Achieved: <?= date('M d, Y', strtotime($achievement['date_achieved'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No achievements added.</p>
                    <?php endif; ?>
                </div>
            </section>
            
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
</body>
</html>