<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = getUserRole();
    header("Location: dashboard/{$role}.php");
    exit();
}

// Capture error AND success messages from URL
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        $role = getUserRole();
        header("Location: dashboard/{$role}.php");
        exit();
    } else {
        $error = 'Invalid credentials';
    }

        // Inside the $_POST block:
    $loginResult = login($username, $password);

    if ($loginResult === true) {
        $role = getUserRole();
        header("Location: dashboard/{$role}.php");
        exit();
    } elseif ($loginResult === 'pending') {
        $error = 'Your account is pending Admin approval.';
    } elseif ($loginResult === 'rejected') {
        $error = 'Your account activation request was rejected.';
    } else {
        $error = 'Invalid credentials';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Portal - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1>College Portal</h1>
                <p>Management & Placement System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            
            <div class="login-footer">
                <p style="margin-bottom: 10px;">Are you a Recruiter? <a href="register_hr.php" style="font-weight: bold;">Register Here</a></p>
                <p>Contact Admin for account issues</p>
            </div>
        </div>
    </div>
</body>
</html>