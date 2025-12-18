<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['student']);

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    logout();
    header('Location: ../index.php');
    exit();
}

// Handle Job Application
if (isset($_POST['apply_job'])) {
    $job_id = $_POST['job_id'];
    $student_id = $_SESSION['user_id'];
    
    // Check if function exists to prevent crash
    if (function_exists('applyForJob')) {
        if (applyForJob($job_id, $student_id)) {
            // Success: Redirect to avoid form resubmission on refresh
            header("Location: student.php?success=" . urlencode("Successfully applied for the job!") . "#placements");
            exit();
        } else {
            $error = "You have already applied for this job or an error occurred.";
        }
    } else {
        $error = "Error: System function 'applyForJob' is missing. Please contact Admin.";
    }
}

$profile = getStudentProfile($_SESSION['user_id']);
$results = getAcademicResults($profile['id'] ?? 0);
$messages = getMessages($_SESSION['user_id']);
$internalResults = getStudentInternalResults($profile['id'] ?? 0);
$my_quizzes = getStudentQuizzes($_SESSION['user_id']);

// Fetch my teachers
$my_staff = getStudentStaff($_SESSION['user_id']);
$sent_messages = getSentMessages($_SESSION['user_id']);

// Handle sending message to staff
if (isset($_POST['send_message_to_staff'])) {
    $receiver_id = $_POST['receiver_id'];
    $message_content = $_POST['message'];
    
    if (sendMessage($_SESSION['user_id'], $receiver_id, $message_content)) {
        $success = "Message sent to staff successfully!";
    } else {
        $error = "Failed to send message.";
    }
    // Redirect to clear post data
    header("Location: student.php?success=" . urlencode($success ?? '') . "#messages");
    exit();
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $enrollment_no = $_POST['enrollment_no'];
    $contact_no = $_POST['contact_no'] ?? '';
    $skills = $_POST['skills'] ?? '';
    $address = $_POST['address'] ?? '';
    $area_of_interest = $_POST['area_of_interest'] ?? '';
    $github_link = $_POST['github_link'] ?? '';
    $linkedin_link = $_POST['linkedin_link'] ?? '';
    
    if (updateStudentProfile($_SESSION['user_id'], [
        'full_name' => $full_name,
        'enrollment_no' => $enrollment_no,
        'contact_no' => $contact_no,
        'skills' => $skills,
        'address' => $address,
        'area_of_interest' => $area_of_interest,
        'github_link' => $github_link,
        'linkedin_link' => $linkedin_link
    ])) {
        $success = "Profile updated successfully!";
        $profile = getStudentProfile($_SESSION['user_id']);
    } else {
        $error = "Failed to update profile.";
    }
}

// Handle project addition
if (isset($_POST['add_project'])) {
    $project_name = $_POST['project_name'];
    $project_description = $_POST['project_description'] ?? '';
    $project_link = $_POST['project_link'] ?? '';
    $technologies_used = $_POST['technologies_used'] ?? '';
    
    if (addStudentProject($_SESSION['user_id'], $project_name, $project_description, $project_link, $technologies_used)) {
        $success = "Project added successfully!";
    } else {
        $error = "Failed to add project.";
    }
}

// Handle achievement addition
if (isset($_POST['add_achievement'])) {
    $achievement_type = $_POST['achievement_type'];
    $title = $_POST['title'];
    $description = $_POST['description'] ?? '';
    $date_achieved = $_POST['date_achieved'] ?? date('Y-m-d');
    $organization = $_POST['organization'] ?? '';
    
    if (addStudentAchievement($_SESSION['user_id'], $achievement_type, $title, $description, $date_achieved, $organization)) {
        $success = "Achievement added successfully!";
    } else {
        $error = "Failed to add achievement.";
    }
}

$projects = getStudentProjects($_SESSION['user_id']);
$achievements = getStudentAchievements($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - College Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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
        .tab-container {
            margin-top: 20px;
        }
        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        .tab-button {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
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
                <h2>Student Portal</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="active">Dashboard</a></li>
                    <li><a href="#profile">My Profile</a></li>
                    <li><a href="#portfolio">My Portfolio</a></li>
                    <li><a href="#results">Academic Results</a></li>
                    <li><a href="#internal-results">Internal Results</a></li>
                    <li><a href="#online-quizzes">Online Quizzes</a></li>
                    <li><a href="#attendance-report" onclick="openTab('attendance-report')">Attendance Report</a></li>
                    <li><a href="#messages">Messages</a></li>
                    <li><a href="#placements">Placements & Jobs</a></li>
                    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?logout=true">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Student Dashboard</h1>
                    <p>Welcome back, <?= htmlspecialchars($profile['full_name'] ?? $_SESSION['username']) ?></p>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <span>Student</span>
                        <div class="user-avatar"><?= strtoupper(substr($profile['full_name'] ?? $_SESSION['username'], 0, 2)) ?></div>
                    </div>
                </div>
            </header>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <section id="profile" class="dashboard-section">
                <h2>My Profile</h2>
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($profile['full_name'], 0, 2)) ?>
                        </div>
                        <div class="profile-info">
                            <h3><?= htmlspecialchars($profile['full_name']) ?></h3>
                            <p><?= htmlspecialchars($profile['enrollment_no']) ?> | <?= htmlspecialchars($profile['department']) ?></p>
                            <p>Batch <?= htmlspecialchars($profile['batch_year']) ?></p>
                            
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
                    
                    <form method="POST" class="profile-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($profile['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="enrollment_no">Enrollment No *</label>
                                <input type="text" id="enrollment_no" name="enrollment_no" value="<?= htmlspecialchars($profile['enrollment_no']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_no">Contact Number</label>
                                <input type="tel" id="contact_no" name="contact_no" value="<?= htmlspecialchars($profile['contact_no'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="skills">Skills (comma separated)</label>
                                <input type="text" id="skills" name="skills" value="<?= htmlspecialchars($profile['skills'] ?? '') ?>" placeholder="e.g., PHP, JavaScript, MySQL">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="area_of_interest">Area of Interest</label>
                            <input type="text" id="area_of_interest" name="area_of_interest" value="<?= htmlspecialchars($profile['area_of_interest'] ?? '') ?>" placeholder="e.g., Web Development, Data Science, AI">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="github_link">GitHub Profile</label>
                                <input type="url" id="github_link" name="github_link" value="<?= htmlspecialchars($profile['github_link'] ?? '') ?>" placeholder="https://github.com/yourusername">
                            </div>
                            <div class="form-group">
                                <label for="linkedin_link">LinkedIn Profile</label>
                                <input type="url" id="linkedin_link" name="linkedin_link" value="<?= htmlspecialchars($profile['linkedin_link'] ?? '') ?>" placeholder="https://linkedin.com/in/yourprofile">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3" placeholder="Your current address"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </section>
            
            <section id="portfolio" class="dashboard-section">
                <h2>My Portfolio</h2>
                
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-button active" onclick="openTab('projects')">Projects</button>
                        <button class="tab-button" onclick="openTab('achievements')">Achievements</button>
                        <button class="tab-button" onclick="openTab('certificates')">Certificates</button>
                    </div>
                    
                    <div id="projects" class="tab-content active">
                        <h3>My Projects</h3>
                        
                        <div class="form-card" style="margin-bottom: 20px;">
                            <h4>Add New Project</h4>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="project_name">Project Name *</label>
                                    <input type="text" id="project_name" name="project_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="project_description">Project Description</label>
                                    <textarea id="project_description" name="project_description" rows="3" placeholder="Describe your project..."></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="project_link">Project Link/URL</label>
                                        <input type="url" id="project_link" name="project_link" placeholder="https://github.com/yourusername/project">
                                    </div>
                                    <div class="form-group">
                                        <label for="technologies_used">Technologies Used</label>
                                        <input type="text" id="technologies_used" name="technologies_used" placeholder="e.g., PHP, MySQL, JavaScript, React">
                                    </div>
                                </div>
                                <button type="submit" name="add_project" class="btn btn-primary">Add Project</button>
                            </form>
                        </div>
                        
                        <div class="projects-list">
                            <?php if (count($projects) > 0): ?>
                                <?php foreach ($projects as $project): ?>
                                <div class="project-card">
                                    <h4><?= htmlspecialchars($project['project_name']) ?></h4>
                                    <?php if (!empty($project['project_description'])): ?>
                                        <p><?= htmlspecialchars($project['project_description']) ?></p>
                                    <?php endif; ?>
                                    <div class="task-footer">
                                        <?php if (!empty($project['technologies_used'])): ?>
                                            <span><strong>Technologies:</strong> <?= htmlspecialchars($project['technologies_used']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($project['project_link'])): ?>
                                            <span><a href="<?= htmlspecialchars($project['project_link']) ?>" target="_blank" class="link-button">View Project</a></span>
                                        <?php endif; ?>
                                        <span>Added: <?= date('M d, Y', strtotime($project['created_at'])) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 20px;">
                                    No projects added yet. Add your first project above!
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="achievements" class="tab-content">
                        <h3>My Achievements</h3>
                        
                        <div class="form-card" style="margin-bottom: 20px;">
                            <h4>Add New Achievement</h4>
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="achievement_type">Achievement Type *</label>
                                        <select id="achievement_type" name="achievement_type" required>
                                            <option value="">Select Type</option>
                                            <option value="competition">Competition</option>
                                            <option value="certificate">Certificate</option>
                                            <option value="extracurricular">Extracurricular Activity</option>
                                            <option value="award">Award</option>
                                            <option value="internship">Internship</option>
                                            <option value="workshop">Workshop</option>
                                            <option value="hackathon">Hackathon</option>
                                            <option value="research">Research Paper</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="title">Title *</label>
                                        <input type="text" id="title" name="title" required placeholder="e.g., First Prize in Coding Competition">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="3" placeholder="Describe your achievement..."></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="date_achieved">Date Achieved</label>
                                        <input type="date" id="date_achieved" name="date_achieved">
                                    </div>
                                    <div class="form-group">
                                        <label for="organization">Organization</label>
                                        <input type="text" id="organization" name="organization" placeholder="e.g., Google, Microsoft, College Name">
                                    </div>
                                </div>
                                <button type="submit" name="add_achievement" class="btn btn-primary">Add Achievement</button>
                            </form>
                        </div>
                        
                        <div class="achievements-list">
                            <?php if (count($achievements) > 0): ?>
                                <?php foreach ($achievements as $achievement): ?>
                                <div class="achievement-card">
                                    <div class="task-header">
                                        <h4><?= htmlspecialchars($achievement['title']) ?></h4>
                                        <span class="badge"><?= ucfirst($achievement['achievement_type']) ?></span>
                                    </div>
                                    <?php if (!empty($achievement['description'])): ?>
                                        <p><?= htmlspecialchars($achievement['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="task-footer">
                                        <?php if (!empty($achievement['organization'])): ?>
                                            <span><strong>Organization:</strong> <?= htmlspecialchars($achievement['organization']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($achievement['date_achieved'])): ?>
                                            <span>Achieved: <?= date('M d, Y', strtotime($achievement['date_achieved'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 20px;">
                                    No achievements added yet. Add your first achievement above!
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="certificates" class="tab-content">
                        <h3>My Certificates & Courses</h3>
                        
                        <div class="form-card" style="margin-bottom: 20px;">
                            <h4>Add Certificate/Course</h4>
                            <form method="POST">
                                <input type="hidden" name="achievement_type" value="certificate">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cert_title">Certificate Title *</label>
                                        <input type="text" id="cert_title" name="title" required placeholder="e.g., Google Cloud Certified">
                                    </div>
                                    <div class="form-group">
                                        <label for="cert_organization">Issuing Organization *</label>
                                        <input type="text" id="cert_organization" name="organization" required placeholder="e.g., Google, Coursera, Udemy">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cert_date">Issue Date</label>
                                        <input type="date" id="cert_date" name="date_achieved">
                                    </div>
                                    <div class="form-group">
                                        <label for="cert_duration">Duration/Credits</label>
                                        <input type="text" id="cert_duration" name="description" placeholder="e.g., 6 weeks, 3 credit hours">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cert_skills">Skills Learned</label>
                                    <input type="text" id="cert_skills" name="skills_learned" placeholder="e.g., Cloud Computing, Machine Learning, Python">
                                </div>
                                <button type="submit" name="add_achievement" class="btn btn-primary">Add Certificate</button>
                            </form>
                        </div>
                        
                        <div class="achievements-list">
                            <?php 
                            $certificates = array_filter($achievements, function($achievement) {
                                return $achievement['achievement_type'] === 'certificate';
                            });
                            ?>
                            
                            <?php if (count($certificates) > 0): ?>
                                <?php foreach ($certificates as $certificate): ?>
                                <div class="achievement-card">
                                    <div class="task-header">
                                        <h4><?= htmlspecialchars($certificate['title']) ?></h4>
                                        <span class="badge" style="background: #28a745;">Certificate</span>
                                    </div>
                                    <div class="task-footer">
                                        <span><strong>Issued by:</strong> <?= htmlspecialchars($certificate['organization']) ?></span>
                                        <?php if (!empty($certificate['date_achieved'])): ?>
                                            <span>Issued: <?= date('M d, Y', strtotime($certificate['date_achieved'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($certificate['description'])): ?>
                                        <p><strong>Details:</strong> <?= htmlspecialchars($certificate['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 20px;">
                                    No certificates added yet. Add your first certificate above!
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
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
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #666; padding: 20px;">
                                        No academic results have been posted yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section id="internal-results" class="dashboard-section">
                <h2>My Internal Results</h2>
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
                                <?php
                                $total_marks = $result['total_marks'];
                                $grade = calculateGrade($total_marks); 
                                ?>
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
                                    <td style="font-weight: bold;"><?= htmlspecialchars($total_marks) ?></td>
                                    <td style="font-weight: bold;"><?= htmlspecialchars($grade) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">
                            No internal results have been posted by your staff yet.
                        </p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="online-quizzes" class="dashboard-section">
                <h2>Online Quizzes & Tasks</h2>
                <div class="tasks-container">
                    <?php if(empty($my_quizzes)): ?>
                        <p>No quizzes assigned to you at the moment.</p>
                    <?php else: ?>
                        <?php foreach($my_quizzes as $q): ?>
                        <div class="task-card <?= $q['has_attempted'] ? 'completed' : 'pending' ?>">
                            <div class="task-header">
                                <h4><?= htmlspecialchars($q['title']) ?></h4>
                                <span class="task-status"><?= $q['has_attempted'] ? 'Completed' : 'Active' ?></span>
                            </div>
                            <p><strong>Subject:</strong> <?= htmlspecialchars($q['subject_name']) ?></p>
                            <p><strong>Time Limit:</strong> <?= $q['time_limit'] ?> Minutes</p>
                            <p><strong>Staff:</strong> <?= htmlspecialchars($q['staff_name']) ?></p>
                            
                            <div class="task-footer" style="display: block;"> <?php if($q['has_attempted']): ?>
                                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #eee; text-align: center;">
                                        <?php 
                                            $percent = ($q['total_questions'] > 0) ? ($q['score'] / $q['total_questions']) * 100 : 0;
                                            $color = $percent >= 40 ? '#2e7d32' : '#c62828';
                                            $status_text = $percent >= 40 ? 'Pass' : 'Fail';
                                        ?>
                                        <div style="font-size: 1.2em; font-weight: bold; color: <?= $color ?>; margin-bottom: 4px;">
                                            Score: <?= $q['score'] ?> / <?= $q['total_questions'] ?>
                                        </div>
                                        <div style="font-size: 0.9em; color: #666;">
                                            Percentage: <strong><?= round($percent) ?>%</strong> (<?= $status_text ?>)
                                        </div>
                                        
                                        <?php if($q['result_status'] == 'disqualified'): ?>
                                            <div style="color: red; font-size: 0.85em; margin-top: 5px; font-weight: bold;">
                                                ⚠️ Disqualified (Tab Switch)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: #666; font-size: 0.9em;">Due: <?= $q['time_limit'] ?> Mins</span>
                                        <a href="take_quiz.php?id=<?= $q['id'] ?>" 
                                        target="_blank" 
                                        class="btn btn-sm btn-primary"
                                        onclick="return confirm('WARNING: Once you start, you cannot stop. Switching tabs will disqualify you. Ready?');">
                                        Start Quiz
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section id="attendance-report" class="dashboard-section">
                <h2>Attendance Report</h2>
                <?php 
                    $att_report = getStudentAttendanceReport($_SESSION['user_id']); 
                ?>
                
                <div class="results-table">
                    <?php if (count($att_report) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Staff</th>
                                    <th>Total Classes</th>
                                    <th>Attended</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($att_report as $row): ?>
                                <?php 
                                    $percent = ($row['total_classes'] > 0) ? ($row['attended_classes'] / $row['total_classes']) * 100 : 0;
                                    $status_color = $percent >= 75 ? 'green' : ($percent >= 65 ? 'orange' : 'red');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($row['staff_name']) ?></td>
                                    <td><?= $row['total_classes'] ?></td>
                                    <td><?= $row['attended_classes'] ?></td>
                                    <td style="font-weight: bold; font-size: 1.1em;">
                                        <?= number_format($percent, 1) ?>%
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?= $status_color ?>;">
                                            <?= $percent >= 75 ? 'Good' : 'Low' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No attendance records found.</p>
                    <?php endif; ?>
                </div>
            </section>
            
            <section id="messages" class="dashboard-section">
                <h2>Messages</h2>
                
                <div class="form-card" style="margin-bottom: 30px; background: #e3f2fd;">
                    <h3>Contact My Staff</h3>
                    <form method="POST" action="student.php#messages">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="staff_receiver">Select Staff Member</label>
                                <select id="staff_receiver" name="receiver_id" required>
                                    <option value="">-- Choose Teacher --</option>
                                    <?php foreach ($my_staff as $staff): ?>
                                    <option value="<?= $staff['id'] ?>">
                                        <?= htmlspecialchars($staff['full_name']) ?> (<?= htmlspecialchars($staff['subject_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="msg_content">Message</label>
                            <textarea id="msg_content" name="message" rows="3" placeholder="Ask a doubt or send a request..." required></textarea>
                        </div>
                        <button type="submit" name="send_message_to_staff" class="btn btn-primary">Send Message</button>
                    </form>
                </div>

                <h3>Inbox</h3>
                <div class="tab-buttons" style="margin-top: 30px; margin-bottom: 15px; border-bottom: 2px solid #eee; display:flex; gap: 20px;">
                        <button type="button" onclick="showMsgTab('inbox')" id="btn-inbox" style="padding: 10px 20px; border: none; background: none; border-bottom: 3px solid #4361ee; font-weight: bold; color: #4361ee; cursor: pointer;">
                            Inbox
                        </button>
                        <button type="button" onclick="showMsgTab('sent')" id="btn-sent" style="padding: 10px 20px; border: none; background: none; border-bottom: 3px solid transparent; font-weight: bold; color: #666; cursor: pointer;">
                            Sent
                        </button>
                </div>

                <div id="msg-inbox" class="messages-container">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message-item <?= $message['is_read'] ? '' : 'unread' ?>">
                            <div class="message-avatar">
                                <?= strtoupper(substr($message['sender_name'], 0, 1)) ?>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="sender">
                                        <?= htmlspecialchars($message['sender_name']) ?> 
                                        <small style="color:#666; font-weight:normal;">(<?= ucfirst($message['sender_role']) ?>)</small>
                                    </span>
                                    <span class="time"><?= date('M d, g:i A', strtotime($message['sent_at'])) ?></span>
                                </div>
                                <p><?= htmlspecialchars($message['message']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No messages received yet.</p>
                    <?php endif; ?>
                </div>

                <div id="msg-sent" class="messages-container" style="display: none;">
                    <?php if (count($sent_messages) > 0): ?>
                        <?php foreach ($sent_messages as $message): ?>
                        <div class="message-item" style="background: #f8f9fa; border-left: 4px solid #aaa;">
                            <div class="message-avatar" style="background: #6c757d;">
                                <?= strtoupper(substr($message['receiver_name'], 0, 1)) ?>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="sender">
                                        To: <?= htmlspecialchars($message['receiver_name']) ?>
                                        <small style="color:#666; font-weight:normal;">(<?= ucfirst($message['receiver_role']) ?>)</small>
                                    </span>
                                    <span class="time"><?= date('M d, g:i A', strtotime($message['sent_at'])) ?></span>
                                </div>
                                <p><?= htmlspecialchars($message['message']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <p>No sent messages found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <section id="placements" class="dashboard-section">
                <h2>Placement Portal</h2>
                
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button type="button" class="tab-button active" onclick="openSubTab(event, 'jobs-list')">Job Openings</button>
                        <button type="button" class="tab-button" onclick="openSubTab(event, 'my-applications')">My Applications</button>
                    </div>

                    <div id="jobs-list" class="sub-tab-content" style="display: block;">
                        <?php 
                        // Ensure getJobs is defined in functions.php
                        $all_jobs = function_exists('getJobs') ? getJobs(null) : []; 
                        $my_apps_raw = function_exists('getStudentApplications') ? getStudentApplications($_SESSION['user_id']) : [];
                        $my_app_ids = array_column($my_apps_raw, 'job_id');
                        ?>
                        
                        <?php if(empty($all_jobs)): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">No job openings available at the moment.</p>
                        <?php else: ?>
                            <div class="jobs-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                                <?php foreach($all_jobs as $job): ?>
                                <div class="task-card" style="border-left: 4px solid var(--primary); padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                                    <div class="task-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                        <h4 style="margin: 0; font-size: 1.1em;"><?= htmlspecialchars($job['job_title']) ?></h4>
                                        <span class="badge" style="background:#e3f2fd; color:#1565c0; padding: 3px 8px; border-radius: 10px; font-size: 0.8em;"><?= htmlspecialchars($job['domain']) ?></span>
                                    </div>
                                    <p style="font-weight:bold; color:#444; margin-bottom: 5px;">
                                        <a href="student_view_company.php?hr_id=<?= $job['hr_id'] ?>" 
                                        title="View Company Profile"
                                        style="color: inherit; text-decoration: none; border-bottom: 1px dotted #666;">
                                        <?= htmlspecialchars($job['company_name']) ?> <span style="font-size: 0.8em;">↗</span>
                                        </a>
                                    </p>
                                    
                                    <div style="font-size: 0.9em; margin: 10px 0; color: #666; line-height: 1.6;">
                                        <div>📍 <?= htmlspecialchars($job['location']) ?></div>
                                        <div>💰 <?= htmlspecialchars($job['salary_package']) ?></div>
                                        <div>📅 Apply by: <?= date('M d', strtotime($job['last_date_to_apply'])) ?></div>
                                    </div>

                                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                                        <?php if(in_array($job['id'], $my_app_ids)): ?>
                                            <button class="btn btn-sm btn-outline" disabled style="opacity: 0.6; cursor: default;">Applied</button>
                                        <?php else: ?>
                                            <form method="POST" action="student.php#placements">
                                                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                                <button type="submit" name="apply_job" class="btn btn-sm btn-primary">Apply Now</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="student_view_job.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline"> View Details </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="my-applications" class="sub-tab-content" style="display:none;">
                        <?php if(empty($my_apps_raw)): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">You haven't applied to any jobs yet.</p>
                        <?php else: ?>
                            <div class="results-table">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8f9fa; text-align: left;">
                                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">Company</th>
                                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">Role</th>
                                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">Status</th>
                                            <th style="padding: 12px; border-bottom: 2px solid #ddd;">Action / Info</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($my_apps_raw as $app): ?>
                                        <tr>
                                            <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong><?= htmlspecialchars($app['company_name']) ?></strong></td>
                                            <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($app['job_title']) ?></td>
                                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                                <?php 
                                                $statusColor = '#1565c0'; // Default Blue
                                                $statusBg = '#e3f2fd';
                                                if($app['status'] == 'Rejected') { $statusColor = '#c62828'; $statusBg = '#ffebee'; }
                                                if($app['status'] == 'Selected') { $statusColor = '#2e7d32'; $statusBg = '#e8f5e9'; }
                                                if($app['status'] == 'Shortlisted') { $statusColor = '#ef6c00'; $statusBg = '#fff3e0'; }
                                                ?>
                                                <span style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                                    <?= $app['status'] ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                                <?php if($app['status'] == 'Shortlisted' && !empty($app['global_assessment_link'])): ?>
                                                    <a href="<?= htmlspecialchars($app['global_assessment_link']) ?>" target="_blank" class="btn btn-sm btn-primary">Take Assessment</a>
                                                <?php elseif($app['status'] == 'Interview_Scheduled' && !empty($app['interview_link'])): ?>
                                                    <a href="<?= htmlspecialchars($app['interview_link']) ?>" target="_blank" class="btn btn-sm btn-success">Join Interview</a>
                                                <?php endif; ?>
                                                
                                                <?php if(!empty($app['hr_feedback'])): ?>
                                                    <div style="font-size: 0.85em; color: #666; margin-top: 5px; font-style: italic;">
                                                        "<?= htmlspecialchars($app['hr_feedback']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <script>
                // Specific function to handle sub-tabs within Placements
                function openSubTab(evt, id) {
                    // Hide all sub-tabs
                    document.querySelectorAll('#placements .sub-tab-content').forEach(el => el.style.display = 'none');
                    
                    // Remove active class from buttons in this container
                    document.querySelectorAll('#placements .tab-button').forEach(btn => btn.classList.remove('active'));
                    
                    // Show selected tab and activate button
                    document.getElementById(id).style.display = 'block';
                    evt.currentTarget.classList.add('active');
                }
                </script>
            </section>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
    function openTab(tabName) {
        
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active');
        });
        document.getElementById(tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    // --- NEW: Scroll Spy for Student Dashboard ---
    window.addEventListener('scroll', function() {
        let current = '';
        const sections = document.querySelectorAll('.dashboard-section');
        const navLinks = document.querySelectorAll('.sidebar-nav ul li a');
        
        // 1. Find which section is currently in view
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            // -150 offset triggers the change slightly before the section hits the very top
            if (window.scrollY >= (sectionTop - 150)) {
                current = section.getAttribute('id');
            }
        });

        // 2. Update the active class in the sidebar
        navLinks.forEach(a => {
            a.classList.remove('active');
            const href = a.getAttribute('href');
            
            // Highlight current section
            if (current && href === '#' + current) {
                a.classList.add('active');
            } 
            // Highlight Dashboard if at top
            else if (!current && href === '#') {
                a.classList.add('active');
            }
        });
    });
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.display = 'none';
        });
    }, 5000);

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
        document.getElementById('msg-' + tab).style.display = 'grid'; // grid matches CSS .messages-container
        document.getElementById('btn-' + tab).style.borderBottomColor = '#4361ee';
        document.getElementById('btn-' + tab).style.color = '#4361ee';
    }
    </script>
</body>
</html>