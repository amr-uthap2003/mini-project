<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role_name'] !== 'student') {
    header("Location: login.php");
    exit;
}

// Get student profile
$profile_query = $conn->prepare("
    SELECT r.*, ro.role_name 
    FROM tb_register r
    INNER JOIN tb_roles ro ON r.role_id = ro.role_id
    WHERE r.reg_id = ?
");

if (!$profile_query) {
    die("Error preparing query: " . $conn->error);
}

$profile_query->bind_param("i", $_SESSION['reg_id']);
$profile_query->execute();
$profile = $profile_query->get_result()->fetch_assoc();

if (!$profile) {
    header("Location: logout.php");
    exit;
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $validation_errors = [];
        
        // Validate username
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        if (empty($username)) {
            $validation_errors[] = "Username is required.";
        } elseif (strlen($username) < 4) {
            $validation_errors[] = "Username must be at least 4 characters long.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $validation_errors[] = "Username can only contain letters, numbers, and underscores.";
        }
        
        // Validate email
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        if (empty($email)) {
            $validation_errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Please enter a valid email address.";
        }
        
        // Validate phone
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        if (empty($phone)) {
            $validation_errors[] = "Phone number is required.";
        } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $validation_errors[] = "Please enter a valid phone number.";
        }
        
        // Validate fullname
        $fullname = filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING);
        if (empty($fullname)) {
            $validation_errors[] = "Full name is required.";
        }
        
        // Other fields that don't need strict validation
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $birthdate = filter_input(INPUT_POST, 'birthdate', FILTER_SANITIZE_STRING);
        $education = filter_input(INPUT_POST, 'education', FILTER_SANITIZE_STRING);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);

        if (empty($validation_errors)) {
            // Check if username is already taken
            $check_username = $conn->prepare("SELECT reg_id FROM tb_register WHERE username = ? AND reg_id != ?");
            $check_username->bind_param("si", $username, $_SESSION['reg_id']);
            $check_username->execute();
            $result = $check_username->get_result();

            if ($result->num_rows > 0) {
                $error_message = "Username already taken. Please choose another.";
            } else {
                $update_query = $conn->prepare("
                    UPDATE tb_register 
                    SET username = ?, email = ?, phone = ?, fullname = ?, address = ?, 
                        gender = ?, birthdate = ?, education = ?, bio = ?
                    WHERE reg_id = ?
                ");

                if (!$update_query) {
                    $error_message = "Error preparing update query: " . $conn->error;
                } else {
                    $update_query->bind_param("sssssssssi", $username, $email, $phone, $fullname, $address, 
                                            $gender, $birthdate, $education, $bio, $_SESSION['reg_id']);
                    
                    if ($update_query->execute()) {
                        $_SESSION['username'] = $username;
                        $success_message = "Profile updated successfully!";
                        $profile_query->execute();
                        $profile = $profile_query->get_result()->fetch_assoc();
                    } else {
                        $error_message = "Failed to update profile: " . $update_query->error;
                    }
                }
            }
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }

    if (isset($_POST['change_password'])) {
        $validation_errors = [];
        
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate passwords
        if (empty($current_password)) {
            $validation_errors[] = "Current password is required.";
        }
        
        if (empty($new_password)) {
            $validation_errors[] = "New password is required.";
        } elseif (strlen($new_password) < 8) {
            $validation_errors[] = "New password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password) || 
                  !preg_match('/[a-z]/', $new_password) || 
                  !preg_match('/[0-9]/', $new_password)) {
            $validation_errors[] = "New password must include at least one uppercase letter, one lowercase letter, and one number.";
        }
        
        if (empty($confirm_password)) {
            $validation_errors[] = "Please confirm your new password.";
        } elseif ($new_password !== $confirm_password) {
            $validation_errors[] = "New passwords do not match.";
        }
        
        if (empty($validation_errors)) {
            $verify_query = $conn->prepare("SELECT password FROM tb_register WHERE reg_id = ?");
            if (!$verify_query) {
                $error_message = "Error preparing password verification query: " . $conn->error;
            } else {
                $verify_query->bind_param("i", $_SESSION['reg_id']);
                $verify_query->execute();
                $result = $verify_query->get_result()->fetch_assoc();
                
                if (password_verify($current_password, $result['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_update = $conn->prepare("UPDATE tb_register SET password = ? WHERE reg_id = ?");
                    
                    if (!$password_update) {
                        $error_message = "Error preparing password update query: " . $conn->error;
                    } else {
                        $password_update->bind_param("si", $hashed_password, $_SESSION['reg_id']);
                        
                        if ($password_update->execute()) {
                            $success_message = "Password updated successfully!";
                        } else {
                            $error_message = "Failed to update password: " . $password_update->error;
                        }
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            }
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .profile-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .profile-header h2 {
            color: #198754;
            margin-bottom: 10px;
        }

        .nav-tabs {
            margin-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 10px 20px;
            margin-right: 5px;
            font-weight: 500;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: #198754;
        }

        .nav-tabs .nav-link.active {
            color: #198754;
            background: none;
            border: none;
            border-bottom: 2px solid #198754;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control, .form-select {
            border: 1px solid #ced4da;
            padding: 10px 15px;
            border-radius: 8px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        textarea.form-control {
            min-height: 100px;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .btn {
            padding: 8px 20px;
            border-radius: 8px;
        }

        .btn-outline-secondary {
            border-color: #ced4da;
        }

        .btn-outline-secondary:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
            color: #212529;
        }

        .input-group .btn {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #e9ecef;
            padding: 15px 20px;
            font-weight: 500;
            color: #198754;
        }

        .list-group-item {
            border: none;
            padding: 12px 20px;
            color: #495057;
            transition: all 0.3s ease;
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
            color: #198754;
        }

        .list-group-item.active {
            background-color: #198754;
            color: white;
            border: none;
        }

        .invalid-feedback {
            display: block;
            color: #dc3545;
            margin-top: 5px;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .profile-section {
                padding: 20px;
                margin-top: 20px;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#">BrightMind</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                    <a href="student_dashboard.php" class="text-decoration-none text-success">
                        Quick Links
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="profile.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">My Profile</a>
                        <a href="book_session.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'book_session.php' ? 'active' : ''; ?>">Book Session</a>
                        <a href="my_sessions.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'my_sessions.php' ? 'active' : ''; ?>">My Sessions</a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9">
                <div class="profile-section">
                    <div class="profile-header">
                        <h2>My Profile</h2>
                        <p class="text-muted">Manage your account information</p>
                    </div>

                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">Profile Information</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">Change Password</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="profileTabsContent">
                        <div class="tab-pane fade show active" id="profile" role="tabpanel">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username"
                                               value="<?php echo htmlspecialchars($profile['username']); ?>" 
                                               required pattern="^[a-zA-Z0-9_]+$" minlength="4">
                                        <div class="invalid-feedback">
                                            Username must be at least 4 characters and can only contain letters, numbers, and underscores.
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($profile['email']); ?>" 
                                               required>
                                        <div class="invalid-feedback">
                                            Please enter a valid email address.
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fullname" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="fullname" name="fullname"
                                               value="<?php echo htmlspecialchars($profile['fullname'] ?? ''); ?>" 
                                               required>
                                        <div class="invalid-feedback">
                                            Full name is required.
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?php echo htmlspecialchars($profile['phone']); ?>" 
                                               required pattern="[0-9+\-\s()]+">
                                        <div class="invalid-feedback">
                                            Please enter a valid phone number.
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($profile['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($profile['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($profile['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="birthdate" class="form-label">Birth Date</label>
                                        <input type="date" class="form-control" id="birthdate" name="birthdate"
                                               value="<?php echo htmlspecialchars($profile['birthdate'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="education" class="form-label">Education Background</label>
                                    <textarea class="form-control" id="education" name="education" rows="3"><?php echo htmlspecialchars($profile['education'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="password" role="tabpanel">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="bi bi-eye" id="current_password_icon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Current password is required.
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               required minlength="8" 
                                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="bi bi-eye" id="new_password_icon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Password must be at least 8 characters and include uppercase, lowercase, and numbers.
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="bi bi-eye" id="confirm_password_icon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Passwords must match.
                                    </div>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="bi bi-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="student_dashboard.php" class="btn btn-success">
                            <i class="bi bi-arrow-left-circle me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateField(this);
                });
            });

            function validateField(field) {
                let isValid = true;
                let message = '';

                switch(field.id) {
                    case 'username':
                        const usernamePattern = /^[a-zA-Z0-9_]{4,}$/;
                        isValid = usernamePattern.test(field.value);
                        message = 'Username must be at least 4 characters and can only contain letters, numbers, and underscores';
                        break;

                    case 'email':
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        isValid = emailPattern.test(field.value);
                        message = 'Please enter a valid email address';
                        break;

                    case 'phone':
                        const phonePattern = /^[0-9+\-\s()]+$/;
                        isValid = phonePattern.test(field.value);
                        message = 'Please enter a valid phone number';
                        break;

                    case 'fullname':
                        isValid = field.value.length >= 2;
                        message = 'Full name must be at least 2 characters long';
                        break;

                    case 'new_password':
                        const passwordPattern = /(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}/;
                        isValid = passwordPattern.test(field.value);
                        message = 'Password must be at least 8 characters and include uppercase, lowercase, and numbers';
                        break;

                    case 'confirm_password':
                        const newPassword = document.getElementById('new_password');
                        isValid = field.value === newPassword.value;
                        message = 'Passwords do not match';
                        break;
                }

                if (field.value.length > 0) {
                    if (!isValid) {
                        field.classList.add('is-invalid');
                        field.classList.remove('is-valid');
                        field.nextElementSibling?.classList.add('d-none');
                        const feedback = field.parentElement.querySelector('.invalid-feedback') || 
                                       field.parentElement.parentElement.querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.style.display = 'block';
                            feedback.textContent = message;
                        }
                    } else {
                        field.classList.add('is-valid');
                        field.classList.remove('is-invalid');
                        field.nextElementSibling?.classList.remove('d-none');
                        const feedback = field.parentElement.querySelector('.invalid-feedback') || 
                                       field.parentElement.parentElement.querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.style.display = 'none';
                        }
                    }
                } else {
                    field.classList.remove('is-valid', 'is-invalid');
                    const feedback = field.parentElement.querySelector('.invalid-feedback') || 
                                   field.parentElement.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.style.display = 'none';
                    }
                }
            }

            // Keep the existing togglePassword function
            function togglePassword(inputId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(inputId + '_icon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            }
        });
        

        // Enhanced password toggle functionality
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '_icon');
            const button = icon.parentElement;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
                button.setAttribute('title', 'Hide password');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
                button.setAttribute('title', 'Show password');
            }
        }
    
    </script>
    
    