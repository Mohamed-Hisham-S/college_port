<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['hr']);

if (!isset($_GET['id'])) {
    header('Location: hr.php');
    exit();
}

$user_id = $_GET['id'];
$student = getStudentByUserId($user_id); // Returns student_profiles data + username/email

if (!$student) {
    die("Student not found.");
}

// Fetch Portfolio Data
$projects = getStudentProjects($user_id);
$achievements = getStudentAchievements($user_id);
$academics = getAcademicResults($student['id']); // student['id'] is the profile ID

// Calculate Overall CGPA/Percentage if needed
$total_obtained = 0;
$total_max = 0;
foreach($academics as $res) {
    $total_obtained += $res['marks_obtained'];
    $total_max += $res['total_marks'];
}
$aggregate = ($total_max > 0) ? round(($total_obtained / $total_max) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student['full_name']) ?> - Portfolio</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .portfolio-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .portfolio-avatar {
            width: 100px;
            height: 100px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
        }
        .header-info h1 { margin: 0 0 5px 0; font-size: 2.2em; }
        .header-info p { margin: 0; color: #666; font-size: 1.1em; }
        
        .grid-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }
        
        .info-sidebar .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .main-content .section-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .section-title {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 1.4em;
        }
        
        .skill-tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            padding: 5px 12px;
            border-radius: 15px;
            margin: 3px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .project-item, .achievement-item {
            border-left: 3px solid var(--primary);
            padding-left: 15px;
            margin-bottom: 20px;
        }
        
        .meta-link {
            display: block;
            margin-bottom: 10px;
            color: var(--dark);
            text-decoration: none;
        }
        .meta-link:hover { color: var(--primary); }
        
        @media (max-width: 768px) {
            .grid-layout { grid-template-columns: 1fr; }
            .portfolio-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Recruiter Portal</h2></div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="hr.php">← Back to Dashboard</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="portfolio-header">
                <div class="portfolio-avatar">
                    <?= strtoupper(substr($student['full_name'], 0, 2)) ?>
                </div>
                <div class="header-info">
                    <h1><?= htmlspecialchars($student['full_name']) ?></h1>
                    <p><?= htmlspecialchars($student['department']) ?> Student</p>
                    <p>Class of <?= htmlspecialchars($student['batch_year']) ?></p>
                </div>
                <div style="margin-left: auto;">
                    <a href="mailto:<?= htmlspecialchars($student['email']) ?>" class="btn btn-primary">Contact Student</a>
                </div>
            </div>
            
            <div class="grid-layout">
                <div class="info-sidebar">
                    <div class="card">
                        <h3>Contact Details</h3>
                        <div style="margin-top: 15px;">
                            <p class="meta-link">📧 <?= htmlspecialchars($student['email']) ?></p>
                            <p class="meta-link">📞 <?= htmlspecialchars($student['contact_no'] ?? 'N/A') ?></p>
                            <p class="meta-link">📍 <?= htmlspecialchars($student['address'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Professional Links</h3>
                        <div style="margin-top: 15px;">
                            <?php if(!empty($student['linkedin_link'])): ?>
                                <a href="<?= htmlspecialchars($student['linkedin_link']) ?>" target="_blank" class="meta-link" style="color:#0077b5;">
                                    <strong>in</strong> LinkedIn Profile
                                </a>
                            <?php endif; ?>
                            <?php if(!empty($student['github_link'])): ?>
                                <a href="<?= htmlspecialchars($student['github_link']) ?>" target="_blank" class="meta-link" style="color:#333;">
                                    <strong>GitHub</strong> Profile
                                </a>
                            <?php endif; ?>
                            <?php if(empty($student['linkedin_link']) && empty($student['github_link'])): ?>
                                <p style="color:#666;">No links provided.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Skills</h3>
                        <div style="margin-top: 15px;">
                            <?php 
                            if (!empty($student['skills'])) {
                                $skills = explode(',', $student['skills']);
                                foreach($skills as $skill) {
                                    echo '<span class="skill-tag">' . htmlspecialchars(trim($skill)) . '</span>';
                                }
                            } else {
                                echo '<p style="color:#666;">No skills listed.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="main-content-area">
                    <div class="section-card">
                        <h2 class="section-title">Academic Summary</h2>
                        <div style="display: flex; justify-content: space-between; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <div>
                                <strong>Course:</strong><br>
                                <?= htmlspecialchars($student['course'] ?? 'N/A') ?>
                            </div>
                            <div>
                                <strong>Current Year:</strong><br>
                                <?= htmlspecialchars($student['current_year'] ?? 'N/A') ?>
                            </div>
                            <div>
                                <strong>Enrollment No:</strong><br>
                                <?= htmlspecialchars($student['enrollment_no']) ?>
                            </div>
                            <div>
                                <strong>Aggregate:</strong><br>
                                <span style="font-size: 1.2em; font-weight: bold; color: var(--primary);"><?= $aggregate ?>%</span>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <h2 class="section-title">Projects</h2>
                        <?php if (count($projects) > 0): ?>
                            <?php foreach($projects as $proj): ?>
                                <div class="project-item">
                                    <div style="display:flex; justify-content:space-between;">
                                        <h3 style="margin:0;"><?= htmlspecialchars($proj['project_name']) ?></h3>
                                        <?php if($proj['project_link']): ?>
                                            <a href="<?= htmlspecialchars($proj['project_link']) ?>" target="_blank" style="font-size:0.9em;">View Link ↗</a>
                                        <?php endif; ?>
                                    </div>
                                    <p style="color: #555; margin: 5px 0;"><?= htmlspecialchars($proj['project_description']) ?></p>
                                    <small style="color: #666;"><strong>Tech Stack:</strong> <?= htmlspecialchars($proj['technologies_used']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#666; font-style:italic;">No projects added yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="section-card">
                        <h2 class="section-title">Achievements & Certifications</h2>
                        <?php if (count($achievements) > 0): ?>
                            <?php foreach($achievements as $ach): ?>
                                <div class="achievement-item">
                                    <div style="display:flex; justify-content:space-between;">
                                        <h3 style="margin:0;"><?= htmlspecialchars($ach['title']) ?></h3>
                                        <span class="badge" style="background:#eee; color:#333; padding:2px 8px; border-radius:10px; font-size:0.8em;">
                                            <?= ucfirst($ach['achievement_type']) ?>
                                        </span>
                                    </div>
                                    <p style="margin: 5px 0; color:#555;"><?= htmlspecialchars($ach['description']) ?></p>
                                    <small style="color: #666;">
                                        <strong>Organization:</strong> <?= htmlspecialchars($ach['organization']) ?> | 
                                        <strong>Date:</strong> <?= date('M Y', strtotime($ach['date_achieved'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#666; font-style:italic;">No achievements added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>