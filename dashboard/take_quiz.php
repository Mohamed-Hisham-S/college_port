<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['student']);

if (!isset($_GET['id'])) {
    die("Invalid Quiz ID");
}

$quiz_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

// Get Quiz Details
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) die("Quiz not found.");

// Check if already attempted
$stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quiz_id, $student_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// If submitted, show results immediately
if ($result) {
    $percent = ($result['score'] / $result['total_questions']) * 100;
    $color = $percent >= 40 ? 'green' : 'red';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Quiz Result</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>body{display:flex;justify-content:center;align-items:center;height:100vh;text-align:center;background:#f5f7fb;}</style>
    </head>
    <body>
        <div class="card" style="padding:40px; max-width:500px;">
            <h1>Quiz Completed</h1>
            <?php if($result['status'] === 'disqualified'): ?>
                <h3 style="color:red;">⚠️ You were disqualified for switching tabs.</h3>
            <?php endif; ?>
            <h2>Your Score: <span style="color:<?= $color ?>"><?= $result['score'] ?> / <?= $result['total_questions'] ?></span></h2>
            <p>You may close this window now.</p>
            <button onclick="window.close()" class="btn btn-primary">Close Window</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Handle Submission
if ($_POST) {
    $answers = $_POST['answer'] ?? [];
    $status = $_POST['status'] ?? 'completed'; // 'completed' or 'disqualified'
    submitQuizResult($quiz_id, $student_id, $answers, $status);
    header("Location: take_quiz.php?id=$quiz_id");
    exit();
}

$questions = getQuizQuestions($quiz_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Quiz: <?= htmlspecialchars($quiz['title']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { user-select: none; /* Disable text selection */ }
        .quiz-container { max-width: 800px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .question-box { margin-bottom: 20px; padding: 15px; border-bottom: 1px solid #eee; }
        .options label { display: block; margin: 8px 0; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 5px; transition: 0.2s; }
        .options label:hover { background: #f0f7ff; border-color: #4361ee; }
        .options input { margin-right: 10px; }
        #timer { position: fixed; top: 10px; right: 20px; background: #e74c3c; color: white; padding: 10px 20px; border-radius: 5px; font-weight: bold; font-size: 1.2em; z-index: 1000; }
        .warning-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,0,0,0.9); color:white; text-align:center; z-index:9999; flex-direction:column; justify-content:center; }
    </style>
</head>
<body oncontextmenu="return false;"> <div id="timer">Time Left: <span id="time">00:00</span></div>

    <div class="quiz-container">
        <h2><?= htmlspecialchars($quiz['title']) ?></h2>
        <p class="alert alert-error">⚠️ <strong>WARNING:</strong> Do not switch tabs, minimize the window, or click outside. The system will detect it and auto-submit your quiz immediately.</p>
        
        <form method="POST" id="quizForm">
            <input type="hidden" name="status" id="submissionStatus" value="completed">
            
            <?php foreach($questions as $index => $q): ?>
                <div class="question-box">
                    <p><strong>Q<?= $index+1 ?>: <?= htmlspecialchars($q['question_text']) ?></strong></p>
                    <div class="options">
                        <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="A"> <?= htmlspecialchars($q['option_a']) ?></label>
                        <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="B"> <?= htmlspecialchars($q['option_b']) ?></label>
                        <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="C"> <?= htmlspecialchars($q['option_c']) ?></label>
                        <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="D"> <?= htmlspecialchars($q['option_d']) ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1em;">Submit Quiz</button>
        </form>
    </div>

    <script>
        // 1. Initialize Flags
        let isTimeUp = false; 
        let isSubmitted = false;

        // 2. Timer Logic
        let timeLimit = <?= $quiz['time_limit'] ?> * 60; // Minutes to seconds
        const timerElement = document.getElementById('time');
        
        const countdown = setInterval(() => {
            const minutes = Math.floor(timeLimit / 60);
            let seconds = timeLimit % 60;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            timerElement.textContent = `${minutes}:${seconds}`;
            
            if (timeLimit <= 0) {
                clearInterval(countdown);
                
                // Set these flags BEFORE the alert to prevent the blur event from disqualifying
                isTimeUp = true; 
                isSubmitted = true; 
                
                alert("Time's up! Submitting your quiz now.");
                document.getElementById('quizForm').submit();
            }
            timeLimit--;
        }, 1000);

        // 3. Security: Detect Tab Switching / Minimizing
        document.addEventListener("visibilitychange", function() {
            if (document.hidden) {
                submitViolation("You switched tabs/windows. Your quiz is being submitted automatically.");
            }
        });

        // 4. Security: Detect Focus Loss (Clicking outside browser)
        window.addEventListener("blur", function() {
            // Only trigger violation if time is NOT up
            if (!isTimeUp) {
                submitViolation("You left the quiz window. Auto-submitting...");
            }
        });

        function submitViolation(message) {
            // Prevent multiple alerts or disqualification if time is up
            if(isSubmitted || isTimeUp) return;
            
            isSubmitted = true;
            
            alert("⚠️ SECURITY ALERT: " + message);
            document.getElementById('submissionStatus').value = 'disqualified'; 
            document.getElementById('quizForm').submit();
        }
        
        // Prevent Copy/Paste
        document.addEventListener('copy', (e) => e.preventDefault());
        document.addEventListener('paste', (e) => e.preventDefault());
        document.addEventListener('cut', (e) => e.preventDefault());
    </script>
</body>
</html>