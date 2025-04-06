<?php
// Check if session is active before starting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connection.php';

// Only try to end login session if user was logged in
if (isset($_SESSION['reg_id'])) {
    endLoginSession($conn, $_SESSION['reg_id']);
    
    // Clear all session variables
    $_SESSION = array();

    // Delete the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }

    // Destroy the session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
?>

<!DOCTYPE html>
<!-- Rest of the HTML remains unchanged -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .logout-icon {
            font-size: 3rem;
            color: #198754;
            margin-bottom: 1rem;
        }
        .countdown {
            font-size: 1.2rem;
            color: #6c757d;
            margin: 1rem 0;
        }
        .redirect-link {
            color: #198754;
            text-decoration: none;
        }
        .redirect-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <div class="logout-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h2 class="mb-4">Successfully Logged Out</h2>
        <p>Thank you for using BrightMind!</p>
        <div class="countdown">
            Redirecting in <span id="countdown">5</span> seconds...
        </div>
        <p>
            <a href="new.html" class="redirect-link">Click here if not redirected automatically</a>
        </p>
    </div>

    <script>
        let seconds = 5;
        const countdownDisplay = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownDisplay.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'new.html';
            }
        }, 1000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</body>
</html>