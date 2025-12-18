<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['student']);

if (!isset($_GET['hr_id'])) {
    header('Location: student.php');
    exit();
}

$hr_id = $_GET['hr_id'];

// Fetch Company Profile
// Ensure getCompanyProfile() is in functions.php (added in previous step)
$company = getCompanyProfile($hr_id);

// Handle case where HR hasn't filled the profile yet
if (!$company) {
    echo "<div style='padding:20px; font-family:sans-serif;'><h3>Company Profile Unavailable</h3><p>The recruiter has not updated their company profile details yet.</p><a href='student.php'>Back to Dashboard</a></div>";
    exit();
}

// Fetch other open jobs by this company
// We reuse getJobs($hr_id) which fetches ALL jobs for that HR
$all_company_jobs = getJobs($hr_id);
$open_jobs = array_filter($all_company_jobs, function($job) {
    return $job['status'] === 'Open';
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name']) ?> - Company Profile</title>
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
            border-left: 6px solid var(--secondary);
        }
        .company-avatar {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            color: #666;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            text-transform: uppercase;
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
            margin-bottom: 15px;
            color: var(--dark);
            font-size: 1.3em;
            font-weight: 600;
        }
        
        .tag {
            display: inline-block;
            background: #eee;
            padding: 4px 10px;
            border-radius: 4px;
            margin: 2px;
            font-size: 0.9em;
        }
        
        .job-mini-card {
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: 0.2s;
        }
        .job-mini-card:hover { border-color: var(--primary); background: #f8f9fa; }

        @media (max-width: 768px) {
            .grid-layout { grid-template-columns: 1fr; }
            .portfolio-header { flex-direction: column; text-align: center; }
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
            <div class="portfolio-header">
                <div class="company-avatar">
                    <?= substr($company['company_name'], 0, 1) ?>
                </div>
                <div class="header-info">
                    <h1><?= htmlspecialchars($company['company_name']) ?></h1>
                    <p><?= htmlspecialchars($company['industry']) ?> | HQ: <?= htmlspecialchars($company['headquarters']) ?></p>
                </div>
                <div style="margin-left: auto;">
                    <?php if(!empty($company['website'])): ?>
                        <a href="<?= htmlspecialchars($company['website']) ?>" target="_blank" class="btn btn-primary">Visit Website ↗</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid-layout">
                <div class="info-sidebar">
                    <div class="card">
                        <h3>Key Facts</h3>
                        <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                            <div>
                                <small style="color:#666;">Company Size</small><br>
                                <strong><?= htmlspecialchars($company['company_size'] ?? 'N/A') ?> Employees</strong>
                            </div>
                            <div>
                                <small style="color:#666;">Founded In</small><br>
                                <strong><?= htmlspecialchars($company['founded_year'] ?? 'N/A') ?></strong>
                            </div>
                            <div>
                                <small style="color:#666;">Headquarters</small><br>
                                <strong><?= htmlspecialchars($company['headquarters'] ?? 'N/A') ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Locations</h3>
                        <p style="margin-top: 10px; color: #555;">
                            <?= htmlspecialchars($company['locations'] ?? 'Not specified') ?>
                        </p>
                    </div>

                    <div class="card">
                        <h3>Specialties</h3>
                        <div style="margin-top: 10px;">
                            <?php 
                            if (!empty($company['specialties'])) {
                                $specs = explode(',', $company['specialties']);
                                foreach($specs as $s) {
                                    echo '<span class="tag">' . htmlspecialchars(trim($s)) . '</span>';
                                }
                            } else {
                                echo '<span class="text-muted">N/A</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="main-content-area">
                    <div class="section-card">
                        <h2 class="section-title">About <?= htmlspecialchars($company['company_name']) ?></h2>
                        <div style="line-height: 1.8; color: #444; white-space: pre-wrap;"><?= htmlspecialchars($company['overview']) ?></div>
                    </div>

                    <div class="section-card">
                        <h2 class="section-title">Open Positions</h2>
                        <?php if (count($open_jobs) > 0): ?>
                            <?php foreach($open_jobs as $job): ?>
                                <div class="job-mini-card">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <h4 style="margin:0; font-size:1.1em;"><?= htmlspecialchars($job['job_title']) ?></h4>
                                        <a href="student.php#placements" class="btn btn-sm btn-outline">Apply</a>
                                    </div>
                                    <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9em;">
                                        <?= htmlspecialchars($job['location']) ?> • <?= htmlspecialchars($job['salary_package']) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#666; font-style:italic;">No other open positions at the moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>