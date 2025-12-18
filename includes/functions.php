<?php
require_once 'db.php';

function getStaffProfile($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT stf.*, u.username, u.email 
        FROM staff_profiles stf 
        JOIN users u ON stf.user_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getStudentProfile($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getAcademicResults($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM academic_results WHERE student_id = ? ORDER BY academic_year DESC, semester DESC");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTasks($userId, $role) {
    global $pdo;
    if ($role === 'student') {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u1.username as assigned_by_name,
                   u1.role as assigned_by_role,
                   u2.username as evaluated_by_name
            FROM tasks t 
            JOIN users u1 ON t.assigned_by = u1.id 
            LEFT JOIN users u2 ON t.evaluated_by = u2.id
            WHERE t.assigned_to = ? AND u1.role = 'staff'
            ORDER BY t.due_date ASC
        ");
    } else if ($role === 'staff') {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u.username as assigned_to_name,
                   u.role as assigned_to_role
            FROM tasks t 
            JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_by = ? AND u.role = 'student'
            ORDER BY t.due_date ASC
        ");
    } else if ($role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u1.username as assigned_by_name,
                   u2.username as assigned_to_name
            FROM tasks t 
            JOIN users u1 ON t.assigned_by = u1.id 
            JOIN users u2 ON t.assigned_to = u2.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return [];
    }
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get received messages with proper sender names
function getMessages($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, 
               COALESCE(stf.full_name, sp.full_name, u.username) as sender_name,
               u.role as sender_role
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        LEFT JOIN staff_profiles stf ON u.id = stf.user_id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE m.receiver_id = ? 
        ORDER BY m.sent_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentsForHR($filters = []) {
    global $pdo;
    $sql = "SELECT sp.*, u.username FROM student_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.role = 'student'";
    $params = [];
    
    if (!empty($filters['department'])) {
        $sql .= " AND sp.department = ?";
        $params[] = $filters['department'];
    }
    
    if (!empty($filters['batch_year'])) {
        $sql .= " AND sp.batch_year = ?";
        $params[] = $filters['batch_year'];
    }
    
    if (!empty($filters['skills'])) {
        $sql .= " AND sp.skills LIKE ?";
        $params[] = "%{$filters['skills']}%";
    }
    
    $sql .= " ORDER BY sp.full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentsForStaff($staffId = null) {
    global $pdo;
    
    // If no staff ID provided (or for backward compatibility), fall back to all students
    // But for your requirement, we expect $staffId to be passed.
    if ($staffId) {
        // 1. Get the logged-in staff's department
        $stmt = $pdo->prepare("SELECT department FROM staff_profiles WHERE user_id = ?");
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If staff has no profile/department, return empty list
        if (!$staff || empty($staff['department'])) {
            return [];
        }
        $targetDepartment = $staff['department'];
        
        // 2. Get students ONLY from that department
        $stmt = $pdo->prepare("
            SELECT sp.id, u.id as user_id, u.username, sp.full_name, sp.enrollment_no, sp.department 
            FROM users u 
            JOIN student_profiles sp ON u.id = sp.user_id 
            WHERE u.role = 'student' AND sp.department = ?
            ORDER BY sp.full_name
        ");
        $stmt->execute([$targetDepartment]);
    } else {
        // Fallback (should ideally not be used in staff.php anymore)
        $stmt = $pdo->prepare("
            SELECT sp.id, u.id as user_id, u.username, sp.full_name, sp.enrollment_no, sp.department 
            FROM users u 
            JOIN student_profiles sp ON u.id = sp.user_id 
            WHERE u.role = 'student' 
            ORDER BY sp.full_name
        ");
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function evaluateTask($taskId, $staffId, $evaluationResult, $evaluationScore) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET evaluation_result = ?, 
            evaluation_score = ?, 
            evaluated_by = ?, 
            evaluated_at = NOW(),
            status = 'completed'
        WHERE id = ? AND assigned_by = ?
    ");
    return $stmt->execute([$evaluationResult, $evaluationScore, $staffId, $taskId, $staffId]);
}

function updateTaskStatus($taskId, $status) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE tasks 
        SET status = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$status, $taskId]);
}

function getTaskDetails($taskId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u1.username as assigned_by_name,
               u2.username as assigned_to_name,
               u3.username as evaluated_by_name,
               sp.full_name as student_full_name
        FROM tasks t 
        JOIN users u1 ON t.assigned_by = u1.id 
        JOIN users u2 ON t.assigned_to = u2.id 
        LEFT JOIN users u3 ON t.evaluated_by = u3.id
        LEFT JOIN student_profiles sp ON u2.id = sp.user_id
        WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createTask($title, $description, $assignedBy, $assignedTo, $dueDate) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO tasks (title, description, assigned_by, assigned_to, due_date, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    return $stmt->execute([$title, $description, $assignedBy, $assignedTo, $dueDate]);
}

function getStaffStatistics($staffId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_tasks,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM tasks 
        WHERE assigned_by = ?
    ");
    $stmt->execute([$staffId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStudentTasksSummary($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_tasks,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
               AVG(evaluation_score) as average_score
        FROM tasks 
        WHERE assigned_to = ?
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sendMessage($senderId, $receiverId, $message) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message) 
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$senderId, $receiverId, $message]);
}

function markMessageAsRead($messageId) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = TRUE 
        WHERE id = ?
    ");
    return $stmt->execute([$messageId]);
}

function getUnreadMessageCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['unread_count'] ?? 0;
}

function getRecentActivities($userId, $role, $limit = 5) {
    global $pdo;
    
    if ($role === 'staff') {
        $stmt = $pdo->prepare("
            (SELECT 'task_assigned' as type, title as description, created_at as activity_date
             FROM tasks WHERE assigned_by = ?)
            UNION
            (SELECT 'task_evaluated' as type, title as description, evaluated_at as activity_date
             FROM tasks WHERE evaluated_by = ? AND evaluated_at IS NOT NULL)
            ORDER BY activity_date DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $userId, $limit]);
    } else if ($role === 'student') {
        $stmt = $pdo->prepare("
            (SELECT 'task_received' as type, title as description, created_at as activity_date
             FROM tasks WHERE assigned_to = ?)
            UNION
            (SELECT 'task_evaluated' as type, title as description, evaluated_at as activity_date
             FROM tasks WHERE assigned_to = ? AND evaluated_at IS NOT NULL)
            ORDER BY activity_date DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $userId, $limit]);
    } else {
        return [];
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStudentByUserId($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT sp.*, u.username, u.email 
        FROM student_profiles sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateStaffProfile($userId, $data) {
    global $pdo;
    
    $updates = [];
    $params = [];
    
    if (isset($data['full_name'])) {
        $updates[] = "full_name = ?";
        $params[] = $data['full_name'];
    }
    if (isset($data['department'])) {
        $updates[] = "department = ?";
        $params[] = $data['department'];
    }
    if (isset($data['subject'])) {
        $updates[] = "subject = ?";
        $params[] = $data['subject'];
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $updates[] = "updated_at = NOW()";
    
    $sql = "UPDATE staff_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $params[] = $userId;
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error in updateStaffProfile: " . $e->getMessage());
        return false;
    }
}

function updateStudentProfile($userId, $data) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE student_profiles 
        SET full_name = ?, enrollment_no = ?, contact_no = ?, skills = ?, address = ?, 
            area_of_interest = ?, github_link = ?, linkedin_link = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    return $stmt->execute([
        $data['full_name'],
        $data['enrollment_no'],
        $data['contact_no'] ?? null,
        $data['skills'] ?? null,
        $data['address'] ?? null,
        $data['area_of_interest'] ?? null,
        $data['github_link'] ?? null,
        $data['linkedin_link'] ?? null,
        $userId
    ]);
}

function createAcademicResult($studentId, $subject, $semester, $academicYear, $marksObtained, $totalMarks) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO academic_results (student_id, subject, semester, academic_year, marks_obtained, total_marks) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$studentId, $subject, $semester, $academicYear, $marksObtained, $totalMarks]);
}

function getDepartmentStatistics() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT department, COUNT(*) as student_count 
        FROM student_profiles 
        GROUP BY department 
        ORDER BY student_count DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBatchYearStatistics() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT batch_year, COUNT(*) as student_count 
        FROM student_profiles 
        GROUP BY batch_year 
        ORDER BY batch_year DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function canAccessTask($userId, $taskId, $role) {
    global $pdo;
    
    if ($role === 'staff') {
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_by = ?");
    } else if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
    } else if ($role === 'admin') {
        return true;
    } else {
        return false;
    }
    
    $stmt->execute([$taskId, $userId]);
    return $stmt->fetch() !== false;
}

function getOverdueTasks($userId, $role) {
    global $pdo;
    $today = date('Y-m-d');
    
    if ($role === 'staff') {
        $stmt = $pdo->prepare("
            SELECT t.*, u.username as assigned_to_name
            FROM tasks t 
            JOIN users u ON t.assigned_to = u.id 
            WHERE t.assigned_by = ? AND t.due_date < ? AND t.status != 'completed'
            ORDER BY t.due_date ASC
        ");
    } else if ($role === 'student') {
        $stmt = $pdo->prepare("
            SELECT t.*, u.username as assigned_by_name
            FROM tasks t 
            JOIN users u ON t.assigned_by = u.id 
            WHERE t.assigned_to = ? AND t.due_date < ? AND t.status != 'completed'
            ORDER BY t.due_date ASC
        ");
    } else {
        return [];
    }
    
    $stmt->execute([$userId, $today]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ordinal($number) {
    if ($number <= 0) return $number;
    $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number . 'th';
    } else {
        return $number . $suffixes[$number % 10];
    }
}

function getStudentProjects($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM student_projects 
        WHERE student_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addStudentProject($studentId, $projectName, $projectDescription, $projectLink, $technologiesUsed) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO student_projects (student_id, project_name, project_description, project_link, technologies_used) 
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$studentId, $projectName, $projectDescription, $projectLink, $technologiesUsed]);
}

function getStudentAchievements($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM student_achievements 
        WHERE student_id = ? 
        ORDER BY date_achieved DESC, created_at DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addStudentAchievement($studentId, $achievementType, $title, $description, $dateAchieved, $organization) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO student_achievements (student_id, achievement_type, title, description, date_achieved, organization) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$studentId, $achievementType, $title, $description, $dateAchieved, $organization]);
}

function getStudentFiles($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM student_files 
        WHERE student_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function handleFileUpload($studentId, $fileType, $fileTitle, $file) {
    global $pdo;
    
    $fileName = "file_" . time() . "_" . $studentId;
    $fileSize = $file['size'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO student_files (student_id, file_name, file_title, file_type, file_size) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$studentId, $fileName, $fileTitle, $fileType, $fileSize]);
        
        return [
            'success' => true,
            'message' => 'File information saved successfully!'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Failed to save file information: ' . $e->getMessage()
        ];
    }
}

function addStudentWithProfile($username, $email, $password, $full_name, $enrollment_no, $department, $batch_year, $course, $current_year, $contact_no = '', $skills = '', $address = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
        $stmt->execute([$username, $email, $password]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO student_profiles (user_id, full_name, enrollment_no, department, course, batch_year, current_year, contact_no, skills, address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $full_name, $enrollment_no, $department, $course, $batch_year, $current_year, $contact_no, $skills, $address]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding student: " . $e->getMessage());
        return false;
    }
}

function getAllStudents($filters = []) {
    global $pdo;
    
    $sql = "
        SELECT sp.*, u.id as user_id, u.username, u.email, u.created_at as user_created 
        FROM student_profiles sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE u.role = 'student'
    ";
    $params = [];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (sp.full_name LIKE ? OR sp.enrollment_no LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['department'])) {
        $sql .= " AND sp.department = ?";
        $params[] = $filters['department'];
    }
    
    if (!empty($filters['batch_year'])) {
        $sql .= " AND sp.batch_year = ?";
        $params[] = $filters['batch_year'];
    }
    
    $sql .= " ORDER BY sp.full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteStudent($userId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting student: " . $e->getMessage());
        return false;
    }
}

function getAllUsers() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            u.*, 
            sp.full_name as student_full_name,
            stf.full_name as staff_full_name,
            stf.department,
            stf.subject
        FROM users u 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        LEFT JOIN staff_profiles stf ON u.id = stf.user_id AND u.role = 'staff'
        ORDER BY u.role, u.username
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addUser($username, $email, $password, $role, $full_name = '', $department = '', $subject = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);
        $userId = $pdo->lastInsertId();
        
        if ($role === 'staff' && $userId) {
            $stmt = $pdo->prepare("
                INSERT INTO staff_profiles (user_id, full_name, department, subject) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $full_name, $department, $subject]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding user: " . $e->getMessage());
        return false;
    }
}

function deleteUser($userId) {
    global $pdo;
    
    try {
        // 1. Check if user exists
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        if ($user['role'] === 'admin') return false; // Protect Admin
        
        // --- FORCE DELETE START ---
        // Temporarily disable foreign key checks to allow deletion
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->beginTransaction();
        
        // 2. Clean up ALL possible related data
        // We use try-catch blocks to ignore errors if tables don't exist
        
        // Tasks & Messages
        try { $pdo->prepare("DELETE FROM tasks WHERE assigned_by = ? OR assigned_to = ?")->execute([$userId, $userId]); } catch (Exception $e) {}
        try { $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]); } catch (Exception $e) {}
        
        // HR Data
        try { 
            $pdo->prepare("DELETE FROM job_applications WHERE job_id IN (SELECT id FROM jobs WHERE hr_id = ?)")->execute([$userId]); 
            $pdo->prepare("DELETE FROM jobs WHERE hr_id = ?")->execute([$userId]);
        } catch (Exception $e) {}

        // Staff Data
        try {
            $pdo->prepare("DELETE FROM internal_results WHERE submitted_by = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM course_allocations WHERE staff_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM attendance_records WHERE staff_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM staff_profiles WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM quizzes WHERE created_by = ?")->execute([$userId]);
        } catch (Exception $e) {}

        // Student Data
        try {
            $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM academic_results WHERE student_id IN (SELECT id FROM student_profiles WHERE user_id = ?)")->execute([$userId]);
            $pdo->prepare("DELETE FROM job_applications WHERE student_id = ?")->execute([$userId]);
        } catch (Exception $e) {}
        
        // 3. Finally, Delete the User
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        
        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        // --- FORCE DELETE END ---
        
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); // Ensure checks are back on
        
        // Show the specific error on screen for debugging
        die("<div style='background:white; color:red; padding:20px; font-weight:bold; border:2px solid red;'>Delete Failed: " . $e->getMessage() . "</div>"); 
    }
}

function getStaffCount() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'staff'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function getHRCount() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'hr'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function getAdminCount() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function getDepartmentBatchYears($department) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT GROUP_CONCAT(DISTINCT batch_year ORDER BY batch_year DESC) as years 
        FROM student_profiles 
        WHERE department = ?
    ");
    $stmt->execute([$department]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['years'] ?? 'N/A';
}

function getStudentByEnrollment($enrollment_no) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT sp.*, u.username, u.email 
        FROM student_profiles sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE sp.enrollment_no = ?
    ");
    $stmt->execute([$enrollment_no]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function checkUsernameExists($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch() !== false;
}

function checkEmailExists($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

function checkEnrollmentExists($enrollment_no) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM student_profiles WHERE enrollment_no = ?");
    $stmt->execute([$enrollment_no]);
    return $stmt->fetch() !== false;
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function submitInternalResults($student_id, $subject, $semester, $academic_year, $cia_1, $task_1, $cia_2, $task_2, $attendance, $library, $submitted_by = null) {
    global $pdo;
    
    $total_marks = $cia_1 + $task_1 + $cia_2 + $task_2 + $attendance + $library;
    
    try {
        if ($submitted_by === null) {
            error_log("ERROR: submitted_by parameter is required");
            return false;
        }
        
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS internal_results (
                id INT PRIMARY KEY AUTO_INCREMENT,
                student_id INT NOT NULL,
                submitted_by INT NOT NULL,
                subject VARCHAR(100) NOT NULL,
                semester INT NOT NULL,
                academic_year VARCHAR(20) NOT NULL,
                cia_1 DECIMAL(4,2) NOT NULL,
                task_1 DECIMAL(4,2) NOT NULL,
                cia_2 DECIMAL(4,2) NOT NULL,
                task_2 DECIMAL(4,2) NOT NULL,
                attendance DECIMAL(4,2) NOT NULL,
                library DECIMAL(4,2) NOT NULL,
                total_marks DECIMAL(5,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
                FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        $stmt->execute();
        
        $stmt = $pdo->prepare("
            INSERT INTO internal_results 
            (student_id, submitted_by, subject, semester, academic_year, cia_1, task_1, cia_2, task_2, attendance, library, total_marks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $student_id, $submitted_by, $subject, $semester, $academic_year, 
            $cia_1, $task_1, $cia_2, $task_2, 
            $attendance, $library, $total_marks
        ]);
        
    } catch (PDOException $e) {
        error_log("Error submitting internal results: " . $e->getMessage());
        return false;
    }
}

function getInternalResults($staff_id = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'internal_results'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
        
        if (!$tableExists) {
            return [];
        }
        
        if ($staff_id !== null) {
            $stmt = $pdo->prepare("
                SELECT ir.*, sp.enrollment_no, sp.full_name, sp.department,
                       u.username as submitted_by_name,
                       u.role as submitted_by_role
                FROM internal_results ir 
                JOIN student_profiles sp ON ir.student_id = sp.id 
                JOIN users u ON ir.submitted_by = u.id
                WHERE ir.submitted_by = ?
                ORDER BY ir.academic_year DESC, ir.semester DESC, sp.enrollment_no ASC
            ");
            $stmt->execute([$staff_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT ir.*, sp.enrollment_no, sp.full_name, sp.department,
                       u.username as submitted_by_name,
                       u.role as submitted_by_role
                FROM internal_results ir 
                JOIN student_profiles sp ON ir.student_id = sp.id 
                JOIN users u ON ir.submitted_by = u.id
                ORDER BY ir.academic_year DESC, ir.semester DESC, sp.enrollment_no ASC
            ");
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getInternalResults: " . $e->getMessage());
        return [];
    }
}

function deleteInternalResult($resultId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM internal_results WHERE id = ?");
        return $stmt->execute([$resultId]);
    } catch (PDOException $e) {
        error_log("Database error in deleteInternalResult: " . $e->getMessage());
        return false;
    }
}

function calculateGrade($total_marks) {
    if ($total_marks >= 22.5) return 'A';
    if ($total_marks >= 20) return 'B';
    if ($total_marks >= 17.5) return 'C';
    if ($total_marks >= 12.5) return 'D';
    return 'F';
}

function getStudentInternalResults($studentId) {
    global $pdo;
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'internal_results'");
        $stmt->execute();
        $tableExists = $stmt->fetch() !== false;
        
        if (!$tableExists) {
            return [];
        }

        // CHANGED: Added Joins to fetch staff details (full_name or username)
        $stmt = $pdo->prepare("
            SELECT ir.*, 
                   COALESCE(stf.full_name, u.username) as staff_name 
            FROM internal_results ir 
            LEFT JOIN users u ON ir.submitted_by = u.id
            LEFT JOIN staff_profiles stf ON u.id = stf.user_id
            WHERE ir.student_id = ? 
            ORDER BY ir.academic_year DESC, ir.semester DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getStudentInternalResults: " . $e->getMessage());
        return [];
    }
}

function updateInternalResult($resultId, $cia_1, $task_1, $cia_2, $task_2, $attendance, $library) {
    global $pdo;
    
    $total_marks = $cia_1 + $task_1 + $cia_2 + $task_2 + $attendance + $library;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE internal_results 
            SET cia_1 = ?, task_1 = ?, cia_2 = ?, task_2 = ?, attendance = ?, library = ?, total_marks = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$cia_1, $task_1, $cia_2, $task_2, $attendance, $library, $total_marks, $resultId]);
    } catch (PDOException $e) {
        error_log("Database error in updateInternalResult: " . $e->getMessage());
        return false;
    }
}

function getInternalResultById($resultId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT ir.*, sp.enrollment_no, sp.full_name, sp.department 
            FROM internal_results ir 
            JOIN student_profiles sp ON ir.student_id = sp.id 
            WHERE ir.id = ?
        ");
        $stmt->execute([$resultId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getInternalResultById: " . $e->getMessage());
        return null;
    }
}

function checkDuplicateInternalResult($student_id, $semester, $academic_year) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM internal_results 
            WHERE student_id = ? AND semester = ? AND academic_year = ?
        ");
        $stmt->execute([$student_id, $semester, $academic_year]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Database error in checkDuplicateInternalResult: " . $e->getMessage());
        return false;
    }
}

function getInternalResultsStatistics() {
    global $pdo;
    try {
        $stats = [];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM internal_results");
        $stmt->execute();
        $stats['total_records'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT AVG(total_marks) as average FROM internal_results");
        $stmt->execute();
        $stats['average_marks'] = round($stmt->fetch(PDO::FETCH_ASSOC)['average'] ?? 0, 2);
        
        $stmt = $pdo->prepare("SELECT total_marks FROM internal_results");
        $stmt->execute();
        $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $gradeCount = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
        foreach ($allResults as $result) {
            $grade = calculateGrade($result['total_marks']);
            $gradeCount[$grade]++;
        }
        $stats['grade_distribution'] = $gradeCount;
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Database error in getInternalResultsStatistics: " . $e->getMessage());
        return [];
    }
}

function getUserDetailsById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.email, u.role,
            stf.full_name, stf.department, stf.subject
        FROM users u 
        LEFT JOIN staff_profiles stf ON u.id = stf.user_id AND u.role = 'staff'
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserProfileByAdmin($userId, $data) {
    global $pdo;
    
    $currentRole = $data['role'] ?? 'admin';
    if ($currentRole === 'admin') {
        error_log("Attempt to modify admin user (ID: $userId) blocked.");
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        $sql_parts = [
            "username = ?",
            "email = ?",
            "role = ?"
        ];
        $params = [
            $data['username'],
            $data['email'],
            $data['role']
        ];
        
        if (!empty($data['new_password'])) {
            $sql_parts[] = "password = ?";
            $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        
        $sql_query = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ? AND role != 'admin'";
        $params[] = $userId;
        
        $stmt = $pdo->prepare($sql_query);
        $stmt->execute($params);
        
        if ($data['role'] === 'staff') {
            $stmt = $pdo->prepare("
                INSERT INTO staff_profiles (user_id, full_name, department, subject)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    full_name = VALUES(full_name),
                    department = VALUES(department),
                    subject = VALUES(subject),
                    updated_at = NOW()
            ");
            $stmt->execute([
                $userId,
                $data['full_name'] ?? '',
                $data['department'] ?? '',
                $data['subject'] ?? ''
            ]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in updateUserProfileByAdmin: " . $e->getMessage());
        return false;
    }
}

function updateStudentProfileByAdmin($userId, $data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $sql_parts = [
            "username = ?",
            "email = ?"
        ];
        $params = [
            $data['username'] ?? '',
            $data['email'] ?? ''
        ];
        
        if (!empty($data['new_password'])) {
            $sql_parts[] = "password = ?";
            $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        
        $sql_query = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ? AND role = 'student'";
        $params[] = $userId;
        
        $stmt = $pdo->prepare($sql_query);
        $stmt->execute($params);
        
        $stmt = $pdo->prepare("
            UPDATE student_profiles 
            SET full_name = ?, enrollment_no = ?, department = ?, course = ?, batch_year = ?, current_year = ?,
                contact_no = ?, skills = ?, address = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $data['full_name'],
            $data['enrollment_no'],
            $data['department'],
            $data['course'] ?? null,
            $data['batch_year'],
            $data['current_year'] ?? null,
            $data['contact_no'] ?? null,
            $data['skills'] ?? null,
            $data['address'] ?? null,
            $userId
        ]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating student: " . $e->getMessage());
        return false;
    }
}

// [Add to the bottom of college_port/includes/functions.php]

function getStudentInternalDetails($studentId) {
    global $pdo;
    // Fetch internal results to display for result processing
    // We join with academic_results to check if it's already published (optional check)
    $stmt = $pdo->prepare("
        SELECT ir.*, ar.id as academic_result_id 
        FROM internal_results ir
        LEFT JOIN academic_results ar 
        ON ir.student_id = ar.student_id 
        AND ir.subject = ar.subject 
        AND ir.semester = ar.semester
        WHERE ir.student_id = ?
        ORDER BY ir.semester DESC, ir.subject ASC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function publishFinalResult($student_id, $subject, $semester, $academic_year, $internal_marks, $external_marks) {
    global $pdo;
    
    $total_marks_obtained = $internal_marks + $external_marks;
    $max_marks = 100; // Standard total
    
    try {
        // Check if result already exists to update or insert
        $check = $pdo->prepare("SELECT id FROM academic_results WHERE student_id = ? AND subject = ? AND semester = ?");
        $check->execute([$student_id, $subject, $semester]);
        $exists = $check->fetch();

        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE academic_results 
                SET marks_obtained = ?, total_marks = ?, academic_year = ?
                WHERE id = ?
            ");
            return $stmt->execute([$total_marks_obtained, $max_marks, $academic_year, $exists['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO academic_results (student_id, subject, semester, academic_year, marks_obtained, total_marks)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$student_id, $subject, $semester, $academic_year, $total_marks_obtained, $max_marks]);
        }
    } catch (PDOException $e) {
        error_log("Error publishing result: " . $e->getMessage());
        return false;
    }
}

// [Append this to college_port/includes/functions.php]

// Assign a subject to a staff member
function assignCourseToStaff($staff_id, $subject, $department, $course, $semester, $year, $academic_year) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO course_allocations 
            (staff_id, subject_name, department, course, semester, current_year, academic_year) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$staff_id, $subject, $department, $course, $semester, $year, $academic_year]);
    } catch (PDOException $e) {
        return false;
    }
}

// Get all courses assigned to a specific staff member
function getStaffAllocations($staff_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM course_allocations WHERE staff_id = ? ORDER BY academic_year DESC, current_year ASC");
    $stmt->execute([$staff_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Delete an allocation
function deleteAllocation($allocation_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM course_allocations WHERE id = ?");
    return $stmt->execute([$allocation_id]);
}

// Get all allocations (For Admin View)
function getAllAllocations() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ca.*, u.username, sp.full_name 
        FROM course_allocations ca 
        JOIN users u ON ca.staff_id = u.id 
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id
        ORDER BY ca.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get students specific to an allocation (Dept + Course + Year)
function getStudentsByClass($department, $course, $current_year) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT sp.id, u.id as user_id, sp.full_name, sp.enrollment_no 
        FROM student_profiles sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.department = ? AND sp.course = ? AND sp.current_year = ?
        ORDER BY sp.enrollment_no ASC
    ");
    $stmt->execute([$department, $course, $current_year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- QUIZ FUNCTIONS ---

// Create a new quiz
function createQuiz($allocation_id, $title, $description, $time_limit, $created_by) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO quizzes (allocation_id, title, description, time_limit, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$allocation_id, $title, $description, $time_limit, $created_by]);
    return $pdo->lastInsertId();
}

// Add a question to a quiz
function addQuizQuestion($quiz_id, $text, $optA, $optB, $optC, $optD, $correct) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$quiz_id, $text, $optA, $optB, $optC, $optD, $correct]);
}

// Get quizzes created by a specific staff member
function getStaffQuizzes($staff_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT q.*, ca.subject_name, ca.course, ca.current_year, ca.department 
        FROM quizzes q 
        JOIN course_allocations ca ON q.allocation_id = ca.id 
        WHERE q.created_by = ? 
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$staff_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get quizzes available for a student based on their class
function getStudentQuizzes($student_id) {
    global $pdo;
    // 1. Get student profile details
    $stmt = $pdo->prepare("SELECT department, course, current_year FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) return [];

    // 2. Find quizzes assigned to allocations matching this student's class
    // CHANGED: Added LEFT JOIN to quiz_results to fetch score, total_questions, and status directly
    $stmt = $pdo->prepare("
        SELECT q.*, ca.subject_name, 
        COALESCE(sp.full_name, u.username) as staff_name, 
        qr.score, qr.total_questions, qr.status as result_status,
        (CASE WHEN qr.id IS NOT NULL THEN 1 ELSE 0 END) as has_attempted
        FROM quizzes q
        JOIN course_allocations ca ON q.allocation_id = ca.id
        JOIN users u ON ca.staff_id = u.id
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id
        LEFT JOIN quiz_results qr ON q.id = qr.quiz_id AND qr.student_id = ?
        WHERE ca.department = ? AND ca.course = ? AND ca.current_year = ?
        ORDER BY q.created_at DESC
    ");
    // Pass student_id FIRST for the LEFT JOIN condition, then the rest
    $stmt->execute([$student_id, $student['department'], $student['course'], $student['current_year']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all questions for a quiz (without revealing answers)
function getQuizQuestions($quiz_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate score and save result
function submitQuizResult($quiz_id, $student_id, $answers, $status = 'completed') {
    global $pdo;
    
    // Check if already attempted
    $stmt = $pdo->prepare("SELECT id FROM quiz_results WHERE quiz_id = ? AND student_id = ?");
    $stmt->execute([$quiz_id, $student_id]);
    if ($stmt->fetch()) return false; // Block duplicate submission

    // Fetch correct answers
    $stmt = $pdo->prepare("SELECT id, correct_option FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $score = 0;
    $total = count($questions);
    
    foreach ($questions as $q) {
        if (isset($answers[$q['id']]) && $answers[$q['id']] === $q['correct_option']) {
            $score++;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO quiz_results (quiz_id, student_id, score, total_questions, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$quiz_id, $student_id, $score, $total, $status]);
    
    return ['score' => $score, 'total' => $total];
}

// [Add to the bottom of functions.php]

// Get all results for a specific quiz
function getQuizResults($quiz_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT qr.*, sp.full_name, sp.enrollment_no 
        FROM quiz_results qr 
        JOIN student_profiles sp ON qr.student_id = sp.user_id 
        WHERE qr.quiz_id = ? 
        ORDER BY qr.score DESC
    ");
    $stmt->execute([$quiz_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get messages sent BY the user (Universal for Staff & Students)
function getSentMessages($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, 
               COALESCE(stf.full_name, sp.full_name, u.username) as receiver_name,
               u.role as receiver_role 
        FROM messages m 
        JOIN users u ON m.receiver_id = u.id 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN staff_profiles stf ON u.id = stf.user_id
        WHERE m.sender_id = ? 
        ORDER BY m.sent_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get staff members teaching the student's class
function getStudentStaff($studentId) {
    global $pdo;
    
    // 1. Get student's class details
    $stmt = $pdo->prepare("SELECT department, course, current_year FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) return [];

    // 2. Fetch staff assigned to this class (Department + Course + Year)
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, 
               COALESCE(stf.full_name, u.username) as full_name, 
               ca.subject_name
        FROM course_allocations ca
        JOIN users u ON ca.staff_id = u.id
        LEFT JOIN staff_profiles stf ON u.id = stf.user_id
        WHERE ca.department = ? AND ca.course = ? AND ca.current_year = ?
        ORDER BY stf.full_name ASC
    ");
    $stmt->execute([$student['department'], $student['course'], $student['current_year']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// [ADD TO BOTTOM OF includes/functions.php]

// --- ATTENDANCE FUNCTIONS ---

// Submit or Update Attendance for a whole class
function submitAttendance($allocation_id, $date, $absentees, $staff_id) {
    global $pdo;
    
    // 1. Get students
    $stmt = $pdo->prepare("SELECT department, course, current_year FROM course_allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $alloc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$alloc) return false;
    
    $students = getStudentsByClass($alloc['department'], $alloc['course'], $alloc['current_year']);
    
    // 2. FETCH EXISTING ON-DUTY RECORDS
    // We don't want to accidentally overwrite an "On-Duty" status with "Present" or "Absent"
    $stmt = $pdo->prepare("SELECT student_id FROM attendance_records WHERE allocation_id = ? AND attendance_date = ? AND status = 'On-Duty'");
    $stmt->execute([$allocation_id, $date]);
    $on_duty_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    try {
        $pdo->beginTransaction();
        
        foreach ($students as $student) {
            $sid = $student['user_id'];
            
            // LOGIC FIX:
            // If the student is ALREADY marked 'On-Duty', preserve that status.
            // Otherwise, check the absentee array.
            if (in_array($sid, $on_duty_ids)) {
                $status = 'On-Duty';
            } else {
                $status = in_array($sid, $absentees) ? 'Absent' : 'Present';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO attendance_records (student_id, allocation_id, staff_id, attendance_date, status)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), staff_id = VALUES(staff_id)
            ");
            $stmt->execute([$sid, $allocation_id, $staff_id, $date, $status]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Attendance Error: " . $e->getMessage());
        return false;
    }
}

// Get Attendance Report for a Single Student
function getStudentAttendanceReport($student_id) {
    global $pdo;
    
    // Get all subjects assigned to this student's class
    $stmt = $pdo->prepare("SELECT department, course, current_year FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) return [];
    
    // Fetch subjects and calculate attendance for each
    $stmt = $pdo->prepare("
        SELECT ca.id as allocation_id, ca.subject_name, u.username as staff_name,
               COUNT(ar.id) as total_classes,
               SUM(CASE WHEN ar.status IN ('Present', 'On-Duty') THEN 1 ELSE 0 END) as attended_classes
        FROM course_allocations ca
        JOIN users u ON ca.staff_id = u.id
        LEFT JOIN attendance_records ar ON ca.id = ar.allocation_id AND ar.student_id = ?
        WHERE ca.department = ? AND ca.course = ? AND ca.current_year = ?
        GROUP BY ca.id
    ");
    $stmt->execute([$student_id, $profile['department'], $profile['course'], $profile['current_year']]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get Absentees for a specific date (for pre-filling the form)
function getClassAttendanceByDate($allocation_id, $date) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT student_id FROM attendance_records WHERE allocation_id = ? AND attendance_date = ? AND status = 'Absent'");
    $stmt->execute([$allocation_id, $date]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN); // Returns array of IDs
}

// Admin Report: Get Students with Shortage (< 75%)
function getAttendanceShortageList($threshold = 75) {
    global $pdo;
    
    // This query is complex: It calculates overall percentage across ALL subjects
    $stmt = $pdo->prepare("
        SELECT sp.full_name, sp.enrollment_no, sp.department, sp.course, sp.current_year,
               SUM(CASE WHEN ar.status IN ('Present', 'On-Duty') THEN 1 ELSE 0 END) as total_attended,
               COUNT(ar.id) as total_classes,
               (SUM(CASE WHEN ar.status IN ('Present', 'On-Duty') THEN 1 ELSE 0 END) / COUNT(ar.id)) * 100 as percentage
        FROM student_profiles sp
        JOIN attendance_records ar ON sp.user_id = ar.student_id
        GROUP BY sp.user_id
        HAVING percentage < ?
        ORDER BY percentage ASC
    ");
    $stmt->execute([$threshold]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- PLACEMENT / HR FUNCTIONS ---

// Post a new job
function createJob($data, $hr_id) {
    global $pdo;
    $sql = "INSERT INTO jobs (hr_id, job_title, company_name, company_desc, company_website, job_role, domain, description, eligibility_criteria, min_percentage, salary_package, location, last_date_to_apply, assessment_date, assessment_link, interview_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $hr_id, $data['job_title'], $data['company_name'], $data['company_desc'], $data['company_website'],
        $data['job_role'], $data['domain'], $data['description'], $data['eligibility_criteria'],
        $data['min_percentage'], $data['salary_package'], $data['location'],
        $data['last_date_to_apply'], $data['assessment_date'] ?: null, $data['assessment_link'], $data['interview_date'] ?: null
    ]);
}

// Get jobs (for Student view - active only, or HR view - all their jobs)
function getJobs($hr_id = null, $filters = []) {
    global $pdo;
    $sql = "SELECT j.*, u.username as hr_name FROM jobs j JOIN users u ON j.hr_id = u.id";
    $params = [];
    
    if ($hr_id) {
        $sql .= " WHERE j.hr_id = ?";
        $params[] = $hr_id;
    } else {
        // Students see only Open jobs + Filters
        $sql .= " WHERE j.status = 'Open'";
        if (!empty($filters['domain'])) {
            $sql .= " AND j.domain LIKE ?";
            $params[] = "%" . $filters['domain'] . "%";
        }
        if (!empty($filters['role'])) {
            $sql .= " AND j.job_role LIKE ?";
            $params[] = "%" . $filters['role'] . "%";
        }
    }
    
    $sql .= " ORDER BY j.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get single job details
function getJobDetails($job_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Apply for a job
function applyForJob($job_id, $student_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO job_applications (job_id, student_id) VALUES (?, ?)");
        return $stmt->execute([$job_id, $student_id]);
    } catch (PDOException $e) {
        return false; // Likely duplicate application
    }
}

// Get Applications for a specific Job (HR View) with Filters
function getJobApplicants($job_id, $filters = []) {
    global $pdo;
    
    // FIXED: Added JOIN users u ... and changed sp.email to u.email
    $sql = "SELECT ja.*, sp.full_name, sp.enrollment_no, sp.department, sp.skills, u.email, sp.contact_no, sp.course,
            (SELECT AVG(marks_obtained/total_marks*100) FROM academic_results ar WHERE ar.student_id = sp.id) as avg_percentage
            FROM job_applications ja 
            JOIN student_profiles sp ON ja.student_id = sp.user_id 
            JOIN users u ON ja.student_id = u.id
            WHERE ja.job_id = ?";
    
    $params = [$job_id];

    // Filter by Degree/Course
    if (!empty($filters['course'])) {
        $sql .= " AND sp.course = ?";
        $params[] = $filters['course'];
    }
    
    // Filter by Percentage (Calculated)
    if (!empty($filters['min_cgpa'])) {
        $sql .= " HAVING avg_percentage >= ?";
        $params[] = $filters['min_cgpa'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get Student's Applied Jobs
function getStudentApplications($student_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ja.*, j.job_title, j.company_name, j.location, j.assessment_date, j.assessment_link as global_assessment_link, j.status as job_status
        FROM job_applications ja 
        JOIN jobs j ON ja.job_id = j.id 
        WHERE ja.student_id = ? 
        ORDER BY ja.applied_at DESC
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update Application Status (Shortlist, Reject, etc.)
function updateApplicationStatus($app_id, $status, $feedback = null, $interview_link = null) {
    global $pdo;
    $sql = "UPDATE job_applications SET status = ?";
    $params = [$status];
    
    if ($feedback !== null) {
        $sql .= ", hr_feedback = ?";
        $params[] = $feedback;
    }
    if ($interview_link !== null) {
        $sql .= ", interview_link = ?";
        $params[] = $interview_link;
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $app_id;
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// Bulk Update Status (For Shortlisting multiple students)
function bulkShortlist($app_ids, $status) {
    global $pdo;
    $placeholders = implode(',', array_fill(0, count($app_ids), '?'));
    $sql = "UPDATE job_applications SET status = ? WHERE id IN ($placeholders)";
    $params = array_merge([$status], $app_ids);
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// Get Aggregate Percentage for a Student
function getStudentAggregatePercentage($student_profile_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT AVG(marks_obtained/total_marks*100) as agg FROM academic_results WHERE student_id = ?");
    $stmt->execute([$student_profile_id]);
    return round($stmt->fetchColumn(), 2);
}

// [Add to includes/functions.php]

// Get Company Profile by HR ID
function getCompanyProfile($hr_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM company_profiles WHERE hr_id = ?");
    $stmt->execute([$hr_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Update or Create Company Profile
function updateCompanyProfile($hr_id, $data) {
    global $pdo;
    
    // Check if profile exists
    $stmt = $pdo->prepare("SELECT id FROM company_profiles WHERE hr_id = ?");
    $stmt->execute([$hr_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        $sql = "UPDATE company_profiles SET 
                company_name = ?, overview = ?, website = ?, industry = ?, 
                company_size = ?, headquarters = ?, founded_year = ?, 
                specialties = ?, locations = ? 
                WHERE hr_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['company_name'], $data['overview'], $data['website'], $data['industry'],
            $data['company_size'], $data['headquarters'], $data['founded_year'],
            $data['specialties'], $data['locations'], $hr_id
        ]);
    } else {
        $sql = "INSERT INTO company_profiles 
                (hr_id, company_name, overview, website, industry, company_size, headquarters, founded_year, specialties, locations) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $hr_id, $data['company_name'], $data['overview'], $data['website'], $data['industry'],
            $data['company_size'], $data['headquarters'], $data['founded_year'],
            $data['specialties'], $data['locations']
        ]);
    }
}
?>