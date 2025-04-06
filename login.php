<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    switch(strtolower($_SESSION['role_name'])) {
        case 'admin':
            header("Location: admin_dashboard.php");
            exit;
        case 'counselor':
            header("Location: counselor_dashboard.php");
            exit;
        case 'student':
            header("Location: student_dashboard.php");
            exit;
    }
}

// Ensure database is selected
$conn->select_db("BrightMind");

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username_or_email = $_POST['username_or_email'];
    $password = $_POST['password'];
    
    error_log("Login attempt for user: " . $username_or_email);
    
    $login_result = validateLogin($conn, $username_or_email, $password);
    
    // Add after validating login
if ($login_result['success']) {
    // Debug logging
    error_log("Debug - Login Result: " . print_r($login_result, true));
    error_log("Debug - Role Name: " . $login_result['role_name']);
    
    $_SESSION['reg_id'] = $login_result['reg_id'];
    $_SESSION['username'] = $login_result['username'];
    $_SESSION['role_name'] = $login_result['role_name'];
    $_SESSION['role_id'] = $login_result['role_id'];
    $_SESSION['logged_in'] = true;
    
    error_log("Debug - Session Data: " . print_r($_SESSION, true));
    
    switch(strtolower($_SESSION['role_name'])) {
        case 'admin':
            error_log("Redirecting to admin dashboard");
            header("Location: admin_dashboard.php");
            exit;
        case 'counselor':
            error_log("Redirecting to counselor dashboard");
            header("Location: counselor_dashboard.php");
            exit;
        case 'student':
            error_log("Redirecting to student dashboard");
            header("Location: student_dashboard.php");
            exit;
        default:
            error_log("Invalid role detected: " . $_SESSION['role_name']);
            $error_message = "Invalid role configuration. Please contact support.";
    }
} else {
    error_log("Login failed: " . $login_result['message']);
    $error_message = $login_result['message'];
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: none;
            padding: 1.5rem 1rem;
        }
        .btn-primary {
            padding: 0.8rem;
        }
        .toggle-password {
            cursor: pointer;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center mb-0">Login to BrightMind</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                Registration successful! Please login.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="username_or_email" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="username_or_email" 
                                       name="username_or_email" required 
                                       value="<?php echo isset($_POST['username_or_email']) ? htmlspecialchars($_POST['username_or_email']) : ''; ?>">
                                <div class="invalid-feedback">
                                    Please enter your username or email.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" 
                                           name="password" required>
                                    <span class="input-group-text toggle-password" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                    <div class="invalid-feedback">
                                        Please enter your password.
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="login" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="signup.php" class="text-decoration-none">Sign up</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            const form = document.querySelector('form');

            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
            });

            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>