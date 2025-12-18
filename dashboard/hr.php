<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['hr']);

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    header('Location: ../index.php');
    exit();
}

// 1. Handle New Job Post
if (isset($_POST['post_job'])) {
    // Fetch the company profile for the logged-in HR
    $companyProfile = getCompanyProfile($_SESSION['user_id']);

    // Check if profile exists
    if (!$companyProfile) {
        $error = "Please complete your 'Company Profile' before posting a job.";
    } else {
        $data = [
            'job_title' => $_POST['job_title'],
            // Auto-fill from Company Profile
            'company_name' => $companyProfile['company_name'],
            'company_desc' => $companyProfile['overview'],
            'company_website' => $companyProfile['website'],
            
            // Manual inputs
            'job_role' => $_POST['job_role'],
            'domain' => $_POST['domain'],
            'description' => $_POST['description'],
            'eligibility_criteria' => $_POST['eligibility_criteria'],
            'min_percentage' => $_POST['min_percentage'] ?? 0,
            'salary_package' => $_POST['salary_package'],
            'location' => $_POST['location'],
            'last_date_to_apply' => $_POST['last_date_to_apply'],
            'assessment_date' => $_POST['assessment_date'],
            'assessment_link' => $_POST['assessment_link'],
            'interview_date' => $_POST['interview_date']
        ];
        
        if (createJob($data, $_SESSION['user_id'])) {
            $success = "Job posted successfully!";
        } else {
            $error = "Failed to post job.";
        }
    }
}

// 4. Handle Company Profile Update
if (isset($_POST['update_company_profile'])) {
    $data = [
        'company_name' => $_POST['company_name'],
        'overview' => $_POST['overview'],
        'website' => $_POST['website'],
        'industry' => $_POST['industry'],
        'company_size' => $_POST['company_size'],
        'headquarters' => $_POST['headquarters'],
        'founded_year' => $_POST['founded_year'],
        'specialties' => $_POST['specialties'],
        'locations' => $_POST['locations']
    ];
    
    if (updateCompanyProfile($_SESSION['user_id'], $data)) {
        $success = "Company profile updated successfully!";
    } else {
        $error = "Failed to update company profile.";
    }
}

// Fetch existing profile data to pre-fill forms
$company_profile = getCompanyProfile($_SESSION['user_id']);

// 2. Handle Status Updates (Shortlist/Reject/Select)
if (isset($_POST['update_status'])) {
    $app_id = $_POST['app_id'];
    $status = $_POST['status']; // e.g., 'Shortlisted', 'Rejected'
    $feedback = $_POST['feedback'] ?? '';
    $interview_link = $_POST['interview_link'] ?? null;
    
    if (updateApplicationStatus($app_id, $status, $feedback, $interview_link)) {
        $success = "Candidate status updated to $status.";
    }
}

// 3. Handle Bulk Actions
if (isset($_POST['bulk_action'])) {
    $selected_apps = $_POST['selected_apps'] ?? [];
    $action = $_POST['bulk_status'];
    
    if (!empty($selected_apps)) {
        if (bulkShortlist($selected_apps, $action)) {
            $success = count($selected_apps) . " candidates updated to $action.";
        }
    }
}

$my_jobs = getJobs($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Dashboard - Recruitment Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .job-card { background: white; padding: 20px; border-radius: 8px; border-left: 5px solid var(--secondary); margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .applicant-row { background: white; border-bottom: 1px solid #eee; transition: 0.2s; }
        .applicant-row:hover { background: #f9f9f9; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        .status-Applied { background: #e3f2fd; color: #1565c0; }
        .status-Shortlisted { background: #fff3e0; color: #ef6c00; }
        .status-Selected { background: #e8f5e9; color: #2e7d32; }
        .status-Rejected { background: #ffebee; color: #c62828; }
        .filter-sidebar { background: white; padding: 20px; border-radius: 8px; height: fit-content; }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header"><h2>Recruiter Portal</h2></div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#dashboard" onclick="showSection('dashboard')" class="active">Dashboard</a></li>
                    <li><a href="#company-profile" onclick="showSection('company-profile')">Company Profile</a></li> <li><a href="#post-job" onclick="showSection('post-job')">Post New Job</a></li>
                    <li><a href="#manage-jobs" onclick="showSection('manage-jobs')">Manage Jobs</a></li>
                    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?logout=true">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>HR Dashboard</h1>
                    <p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
                <div class="header-right"><span class="user-info">HR Recruiter</span></div>
            </header>

            <?php if (isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

            <section id="dashboard" class="dashboard-section active-section">
                <h2>Overview</h2>
                <div class="dashboard-cards">
                    <div class="card">
                        <h3>Active Jobs</h3>
                        <div class="stat"><?= count($my_jobs) ?></div>
                    </div>
                    <div class="card">
                        <h3>Total Applications</h3>
                        <div class="stat">
                            <?php 
                            $total = 0;
                            foreach($my_jobs as $j) {
                                // Simple count query could be optimized
                                $apps = getJobApplicants($j['id']);
                                $total += count($apps);
                            }
                            echo $total;
                            ?>
                        </div>
                    </div>
                </div>
            </section>

            <section id="company-profile" class="dashboard-section" style="display: none;">
                <h2>Manage Company Profile</h2>
                <p class="text-muted">This information will be displayed to students and auto-filled in your job posts.</p>
                
                <div class="form-card">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Company Name *</label>
                                <input type="text" name="company_name" value="<?= htmlspecialchars($company_profile['company_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Website URL</label>
                                <input type="url" name="website" value="<?= htmlspecialchars($company_profile['website'] ?? '') ?>" placeholder="https://example.com">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Company Overview / About Us</label>
                            <textarea name="overview" rows="5" placeholder="Tell us about your company..."><?= htmlspecialchars($company_profile['overview'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Industry</label>
                                <select name="industry">
                                    <option value="">Select Industry</option>
                                    <?php 
                                    $inds = ['IT Services', 'Product', 'FinTech', 'EdTech', 'Healthcare', 'Manufacturing', 'Consulting'];
                                    foreach($inds as $i) {
                                        $sel = ($company_profile['industry'] ?? '') == $i ? 'selected' : '';
                                        echo "<option value='$i' $sel>$i</option>";
                                    }
                                    ?>
                                    <option value="Other" <?= ($company_profile['industry'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Company Size</label>
                                <select name="company_size">
                                    <option value="">Select Size</option>
                                    <?php 
                                    $sizes = ['1-10', '11-50', '51-200', '201-500', '500-1000', '1000+'];
                                    foreach($sizes as $s) {
                                        $sel = ($company_profile['company_size'] ?? '') == $s ? 'selected' : '';
                                        echo "<option value='$s' $sel>$s employees</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Headquarters</label>
                                <input type="text" name="headquarters" value="<?= htmlspecialchars($company_profile['headquarters'] ?? '') ?>" placeholder="City, Country">
                            </div>
                            <div class="form-group">
                                <label>Founded Year</label>
                                <input type="number" name="founded_year" value="<?= htmlspecialchars($company_profile['founded_year'] ?? '') ?>" placeholder="e.g. 2010">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Specialties</label>
                            <input type="text" name="specialties" value="<?= htmlspecialchars($company_profile['specialties'] ?? '') ?>" placeholder="e.g. Artificial Intelligence, Cloud Computing, Big Data">
                        </div>
                        
                        <div class="form-group">
                            <label>Office Locations</label>
                            <input type="text" name="locations" value="<?= htmlspecialchars($company_profile['locations'] ?? '') ?>" placeholder="e.g. Mumbai, Bangalore, Singapore">
                        </div>

                        <button type="submit" name="update_company_profile" class="btn btn-primary">Save Company Profile</button>
                    </form>
                </div>
            </section>

            <section id="post-job" class="dashboard-section" style="display: none;">
                <h2>Post a New Job Opportunity</h2>
                
                <div style="background: #e3f2fd; color: #0d47a1; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 0.9em;">
                    <strong>Note:</strong> Company details (Name, Description, Website) will be automatically attached from your 
                    <a href="#company-profile" onclick="showSection('company-profile')" style="text-decoration: underline; color: #0d47a1;">Company Profile</a>.
                </div>

                <div class="form-card">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Job Title *</label>
                                <input type="text" name="job_title" required placeholder="e.g. Junior Developer">
                            </div>
                            <div class="form-group">
                                <label>Job Role</label>
                                <input type="text" name="job_role" placeholder="e.g. Software Engineer">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Domain</label>
                                <select name="domain">
                                    <option value="IT">IT / Software</option>
                                    <option value="Core">Core Engineering</option>
                                    <option value="Sales">Sales & Marketing</option>
                                    <option value="Management">Management</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salary Package</label>
                                <input type="text" name="salary_package" placeholder="e.g. 6 LPA">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Job Description</label>
                            <textarea name="description" rows="4" required placeholder="Roles & Responsibilities..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Eligibility Criteria</label>
                            <textarea name="eligibility_criteria" rows="2" placeholder="e.g. No Standing Arrears, 60% throughout"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Min. Percentage (Auto Filter)</label>
                                <input type="number" name="min_percentage" step="0.1" placeholder="e.g. 60">
                            </div>
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" placeholder="e.g. Bangalore / Remote">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Last Date to Apply *</label>
                                <input type="date" name="last_date_to_apply" required>
                            </div>
                        </div>
                        
                        <hr>
                        <h4>Assessment & Interview Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Assessment Date</label>
                                <input type="datetime-local" name="assessment_date">
                            </div>
                            <div class="form-group">
                                <label>Assessment Link</label>
                                <input type="url" name="assessment_link" placeholder="HackerRank/Google Form Link">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tentative Interview Date</label>
                            <input type="datetime-local" name="interview_date">
                        </div>

                        <button type="submit" name="post_job" class="btn btn-primary">Publish Job</button>
                    </form>
                </div>
            </section>

            <section id="manage-jobs" class="dashboard-section" style="display: none;">
                <h2>Manage Jobs & Applications</h2>
                
                <?php if(empty($my_jobs)): ?>
                    <p>No jobs posted yet.</p>
                <?php else: ?>
                    <?php 
                        // Determine which job to show details for
                        $selected_job_id = $_GET['view_job'] ?? $my_jobs[0]['id'];
                        $selected_job = null;
                        foreach($my_jobs as $j) { if($j['id'] == $selected_job_id) $selected_job = $j; }
                        
                        // Get Applicants
                        $filters = [
                            'course' => $_GET['filter_course'] ?? '',
                            'min_cgpa' => $_GET['filter_cgpa'] ?? ''
                        ];
                        $applicants = getJobApplicants($selected_job_id, $filters);
                    ?>

                    <div style="display: flex; gap: 20px;">
                        <div style="width: 250px; flex-shrink: 0;">
                            <h4>Your Job Posts</h4>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach($my_jobs as $j): ?>
                                    <a href="?view_job=<?= $j['id'] ?>#manage-jobs" 
                                       class="btn btn-outline" 
                                       style="text-align: left; <?= $j['id'] == $selected_job_id ? 'background:#e3f2fd; border-color:#2196f3;' : '' ?>">
                                       <strong><?= htmlspecialchars($j['job_title']) ?></strong><br>
                                       <small><?= date('M d', strtotime($j['created_at'])) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="flex: 1;">
                            <div class="job-card">
                                <h3><?= htmlspecialchars($selected_job['job_title']) ?> <span class="badge" style="float:right; background:#eee; color:#333;"><?= $selected_job['status'] ?></span></h3>
                                <p><strong>Role:</strong> <?= htmlspecialchars($selected_job['job_role']) ?> | <strong>Salary:</strong> <?= htmlspecialchars($selected_job['salary_package']) ?></p>
                                <p><strong>Applicants:</strong> <?= count($applicants) ?> Students</p>
                            </div>

                            <div class="form-card" style="padding: 15px; margin-bottom: 20px; background: #f5f5f5;">
                                <form method="GET" action="hr.php">
                                    <input type="hidden" name="view_job" value="<?= $selected_job_id ?>">
                                    <div style="display: flex; gap: 10px; align-items: end;">
                                        <div class="form-group" style="margin:0;">
                                            <label>Filter Course</label>
                                            <select name="filter_course">
                                                <option value="">All Courses</option>
                                                <option value="B.Tech" <?= ($filters['course'] == 'B.Tech') ? 'selected' : '' ?>>B.Tech</option>
                                                <option value="B.Sc" <?= ($filters['course'] == 'B.Sc') ? 'selected' : '' ?>>B.Sc</option>
                                            </select>
                                        </div>
                                        <div class="form-group" style="margin:0;">
                                            <label>Min Percentage</label>
                                            <input type="number" name="filter_cgpa" placeholder="e.g. 75" style="width: 100px;" value="<?= $filters['min_cgpa'] ?>">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                        <a href="?view_job=<?= $selected_job_id ?>#manage-jobs" class="btn btn-sm btn-outline">Clear</a>
                                    </div>
                                    <script>
                                        // Ensure hash is maintained on submit
                                        document.querySelector('form[action="hr.php"]').addEventListener('submit', function() {
                                            this.action = 'hr.php#manage-jobs';
                                        });
                                    </script>
                                </form>
                            </div>

                            <form method="POST">
                                <div style="margin-bottom: 10px;">
                                    <strong>Bulk Action:</strong>
                                    <select name="bulk_status" style="padding: 5px;">
                                        <option value="Shortlisted">Shortlist Selected</option>
                                        <option value="Selected">Mark as Selected</option>
                                        <option value="Rejected">Reject Selected</option>
                                    </select>
                                    <button type="submit" name="bulk_action" class="btn btn-sm btn-primary">Apply</button>
                                </div>
                                <div class="results-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" onclick="toggleAll(this)"></th>
                                                <th>Student</th>
                                                <th>Academic</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($applicants as $app): ?>
                                            <tr class="applicant-row">
                                                <td><input type="checkbox" name="selected_apps[]" value="<?= $app['id'] ?>" class="app-check"></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($app['full_name']) ?></strong><br>
                                                    <small><?= htmlspecialchars($app['enrollment_no']) ?></small><br>
                                                    <a href="hr_view_student.php?id=<?= $app['student_id'] ?>" target="_blank" class="btn btn-sm btn-primary" style="text-decoration: none; color: white;">View Portfolio</a>
                                                </td>
                                                <td>
                                                    <?= $app['course'] ?><br>
                                                    <strong><?= number_format((float)$app['avg_percentage'], 1) ?>%</strong>
                                                </td>
                                                <td><span class="status-badge status-<?= $app['status'] ?>"><?= $app['status'] ?></span></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline" onclick="openFeedbackModal(<?= $app['id'] ?>, '<?= $app['full_name'] ?>')">Update Status</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div id="feedbackModal" class="modal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="modal-content" style="background:white; width:400px; margin: 100px auto; padding:20px; border-radius:8px;">
            <h3>Update Status for <span id="modalStudentName"></span></h3>
            <form method="POST">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="app_id" id="modalAppId">
                
                <div class="form-group">
                    <label>New Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Shortlisted">Shortlisted (for Assessment)</option>
                        <option value="Interview_Scheduled">Interview Scheduled</option>
                        <option value="Selected">Selected</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Interview Link (Optional)</label>
                    <input type="text" name="interview_link" placeholder="Meet/Zoom Link">
                </div>
                
                <div class="form-group">
                    <label>Feedback/Remarks</label>
                    <textarea name="feedback" rows="3" placeholder="Reason for rejection or next steps..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('feedbackModal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Tab switching
        function showSection(id) {
            document.querySelectorAll('.dashboard-section').forEach(el => el.style.display = 'none');
            document.getElementById(id).style.display = 'block';
            
            // Update sidebar active state
            document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
            document.querySelector(`.sidebar-nav a[href="#${id}"]`).classList.add('active');
        }

        // Handle URL hash on load
        window.onload = function() {
            if(location.hash) {
                const id = location.hash.substring(1);
                if(document.getElementById(id)) showSection(id);
            }
        };

        function toggleAll(source) {
            checkboxes = document.getElementsByClassName('app-check');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function openFeedbackModal(appId, name) {
            document.getElementById('modalAppId').value = appId;
            document.getElementById('modalStudentName').innerText = name;
            document.getElementById('feedbackModal').style.display = 'block';
        }
    </script>
</body>
</html>