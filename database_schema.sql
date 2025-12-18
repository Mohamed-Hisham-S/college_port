CREATE DATABASE college_port;
USE college_port;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'student', 'hr') NOT NULL,
    status ENUM('active', 'pending', 'rejected') DEFAULT 'active', -- Status field mentioned in auth.php
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Student profiles
CREATE TABLE student_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    enrollment_no VARCHAR(20) UNIQUE NOT NULL,
    department VARCHAR(50) NOT NULL,
    course VARCHAR(50), -- Added field
    batch_year INT NOT NULL,
    current_year INT, -- Added field
    contact_no VARCHAR(15),
    skills TEXT,
    address TEXT,
    area_of_interest VARCHAR(500),
    github_link VARCHAR(500),
    linkedin_link VARCHAR(500),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Staff profiles (Referenced in functions.php)
CREATE TABLE staff_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(100),
    department VARCHAR(100),
    subject VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Final Academic results (Official results published by Admin)
CREATE TABLE academic_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    semester INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    marks_obtained DECIMAL(5,2) NOT NULL,
    total_marks DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE
);

-- Course Allocation table
CREATE TABLE course_allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    course VARCHAR(50) NOT NULL,
    semester INT NOT NULL,
    current_year INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Internal results table (Entered by Staff)
CREATE TABLE internal_results (
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
);

-- Quiz system tables
CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    allocation_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    time_limit INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (allocation_id) REFERENCES course_allocations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option CHAR(1) NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE quiz_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    status ENUM('completed', 'disqualified') DEFAULT 'completed',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Attendance tracking
CREATE TABLE attendance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    allocation_id INT NOT NULL,
    staff_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'On-Duty') NOT NULL,
    UNIQUE KEY (student_id, allocation_id, attendance_date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (allocation_id) REFERENCES course_allocations(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

-- Company Profiles for HRs
CREATE TABLE company_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    overview TEXT,
    website VARCHAR(500),
    industry VARCHAR(100),
    company_size VARCHAR(50),
    headquarters VARCHAR(255),
    founded_year INT,
    specialties TEXT,
    locations TEXT,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Placement Portal (Jobs and Applications)
CREATE TABLE jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_id INT NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    company_name VARCHAR(255),
    company_desc TEXT,
    company_website VARCHAR(500),
    job_role VARCHAR(255),
    domain VARCHAR(100),
    description TEXT,
    eligibility_criteria TEXT,
    min_percentage DECIMAL(5,2) DEFAULT 0,
    salary_package VARCHAR(100),
    location VARCHAR(255),
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    last_date_to_apply DATE,
    assessment_date DATETIME,
    assessment_link VARCHAR(500),
    interview_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE job_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('Applied', 'Shortlisted', 'Interview_Scheduled', 'Selected', 'Rejected') DEFAULT 'Applied',
    hr_feedback TEXT,
    interview_link VARCHAR(500),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student Portfolio Items
CREATE TABLE student_projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    project_description TEXT,
    project_link VARCHAR(500),
    technologies_used VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE student_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    achievement_type ENUM('competition', 'certificate', 'extracurricular', 'award', 'internship', 'workshop', 'hackathon', 'research') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date_achieved DATE,
    organization VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default Sample Users (Password: password)
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('staff1', 'staff@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
('student1', 'student@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('hr1', 'hr@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr');
