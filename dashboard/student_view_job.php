<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['student']);

if (!isset($_GET['id'])) {
    header('Location: student.php');
    exit();
}

$job_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

// 1. Fetch Job Details
$job = getJobDetails($job_id);
if (!$job) {
    echo "<div style='padding:20px; font-family:sans-serif;'><h3>Job Not Found</h3><a href='student.php'>Back to Dashboard</a></div>";
    exit();
}

// 2. Fetch Company Details (for logo/info)
$company = getCompanyProfile($job['hr_id']);

// 3. Check Application Status
$my_apps = getStudentApplications($student_id);
$application_status = null;
foreach($my_apps as $app) {
    if ($app['job_id'] == $job_id) {
        $application_status = $app['status'];
        break;
    }
}

// 4. Handle Apply Action (if submitted from this page)
if (isset($_POST['apply_now'])) {
    if ($application_status) {
        $error = "You have already applied.";
    } else {
        if (applyForJob($job_id, $student_id)) {
            $success = "Application submitted successfully!";
            $application_status = 'Applied'; // Update local status
        } else {
            $error = "Failed to submit application.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['job_title']) ?> - Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .job-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            border-left: 6px solid var(--primary);
        }
        .header-content h1 { margin: 0 0 5px 0; font-size: 2em; color: var(--dark); }
        .header-content p { margin: 0; color: #666; font-size: 1.1em; }
        
        .company-link { color: #666; text-decoration: none; font-weight: 600; }
        .company-link:hover { color: var(--primary); text-decoration: underline; }

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

        .info-item { margin-bottom: 15px; }
        .info-label { display: block; font-size: 0.85em; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { display: block; font-size: 1.1em; font-weight: 600; color: #333; }

        .main-content-area .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 1.3em;
            color: var(--primary);
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1em;
            background: #e3f2fd; color: #1565c0; /* Default Applied */
        }
        .status-Shortlisted { background: #fff3e0; color: #ef6c00; }
        .status-Selected { background: #e8f5e9; color: #2e7d32; }
        .status-Rejected { background: #ffebee; color: #c62828; }

        .btn-apply {
            display: inline-block;
            width: 100%;
            text-align: center;
            padding: 15px;
            font-size: 1.1em;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .grid-layout { grid-template-columns: 1fr; }
            .job-header { flex-direction: column; text-align: center; gap: 15px; }
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Student Portal</h2></div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="student.php#placements">← Back to Jobs</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <?php if (isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <div class="job-header">
                <div class="header-content">
                    <h1><?= htmlspecialchars($job['job_title']) ?></h1>
                    <p>
                        at <a href="student_view_company.php?hr_id=<?= $job['hr_id'] ?>" class="company-link">
                            <?= htmlspecialchars($job['company_name']) ?> ↗
                        </a>
                    </p>
                </div>
                <div>
                    <?php if ($application_status): ?>
                        <span class="status-badge status-<?= $application_status ?>"><?= $application_status ?></span>
                    <?php else: ?>
                        <form method="POST">
                            <button type="submit" name="apply_now" class="btn btn-primary btn-apply">Apply Now</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-layout">
                <div class="info-sidebar">
                    <div class="card">
                        <h3>Job Overview</h3>
                        <div class="info-item">
                            <span class="info-label">Role</span>
                            <span class="info-value"><?= htmlspecialchars($job['job_role']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Domain</span>
                            <span class="info-value"><?= htmlspecialchars($job['domain']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Salary (CTC)</span>
                            <span class="info-value"><?= htmlspecialchars($job['salary_package']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Location</span>
                            <span class="info-value"><?= htmlspecialchars($job['location']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Apply By</span>
                            <span class="info-value" style="color: #d32f2f;">
                                <?= date('d M, Y', strtotime($job['last_date_to_apply'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Important Dates</h3>
                        <div class="info-item">
                            <span class="info-label">Assessment Date</span>
                            <span class="info-value">
                                <?= $job['assessment_date'] ? date('d M, Y h:i A', strtotime($job['assessment_date'])) : 'TBD' ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Interview Date</span>
                            <span class="info-value">
                                <?= $job['interview_date'] ? date('d M, Y h:i A', strtotime($job['interview_date'])) : 'TBD' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="main-content-area">
                    <div class="section">
                        <h2 class="section-title">Job Description</h2>
                        <div style="line-height: 1.8; color: #444; white-space: pre-wrap;"><?= htmlspecialchars($job['description']) ?></div>
                    </div>

                    <div class="section">
                        <h2 class="section-title">Eligibility Criteria</h2>
                        <div style="line-height: 1.8; color: #444; white-space: pre-wrap;"><?= htmlspecialchars($job['eligibility_criteria']) ?></div>
                        
                        <?php if($job['min_percentage'] > 0): ?>
                            <div style="margin-top: 15px; padding: 10px; background: #fff3e0; border-left: 4px solid #ff9800; color: #e65100;">
                                <strong>Minimum Academic Requirement:</strong> <?= $job['min_percentage'] ?>% Aggregate
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>