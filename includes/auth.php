<?php
session_start();
require_once 'db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array(getUserRole(), $allowedRoles)) {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
}

function login($username, $password) {
    global $pdo;
    
    try {
        // Fetch 'status' as well
        $stmt = $pdo->prepare("SELECT id, username, role, password, status FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // --- SECURITY CHECK START ---
            if ($user['status'] === 'pending') {
                // You might want to handle this error message specifically in index.php
                return 'pending'; 
            }
            if ($user['status'] === 'rejected') {
                return 'rejected';
            }
            // --- SECURITY CHECK END ---

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Start a new session for redirect (if needed)
    session_start();
}
?>