<?php
session_start();
require_once 'db_connection.php';

$error_message = "";
$success = false;

// Fetch available roles for the dropdown
$roles = getAllRoles($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 2; // Default to student if not specified
    
    if (empty($username) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } else {
        $result = registerUser($conn, $username, $email, $phone, $password, $role_id);
        if ($result['success']) {
            $_SESSION['registration_success'] = "Account created successfully! Please log in.";
            header("Location: login.php");
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - BrightMind</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .signup-container { max-width: 500px; margin: 60px auto; }
        .card { border: none; box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .card-header { background-color: #3a5998; color: white; text-align: center; padding: 20px; }
        .btn-primary { background-color: #3a5998; border-color: #3a5998; }
        .btn-primary:hover { background-color: #2d4373; border-color: #2d4373; }
        .login-link { text-align: center; margin-top: 20px; }
        .password-toggle { cursor: pointer; position: absolute; right: 10px; top: 10px; }
        .input-group { position: relative; }
        .is-valid {
            border-color: #198754 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .feedback { display: none; font-size: 80%; margin-top: 0.25rem; }
        .valid-feedback { color: #198754; }
        .invalid-feedback { color: #dc3545; }
        .is-valid ~ .valid-feedback,
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="card">
            <div class="card-header">
                <h3>BrightMind</h3>
                <p class="mb-0">Create Your Account</p>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="signupForm" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="valid-feedback">Username is valid!</div>
                        <div class="invalid-feedback">Username must be at least 3 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="valid-feedback">Email is valid!</div>
                        <div class="invalid-feedback">Please enter a valid email address</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                        <div class="valid-feedback">Phone number is valid!</div>
                        <div class="invalid-feedback">Please enter a valid phone number (must have 10 digits)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Select Role</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_id'] == 2) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a role</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePasswordVisibility('password')">
                                <i class="bi bi-eye" id="password-toggle-icon"></i>
                            </span>
                        </div>
                        <div class="valid-feedback">Password is strong!</div>
                        <div class="invalid-feedback">Password must be at least 8 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                                <i class="bi bi-eye" id="confirm_password-toggle-icon"></i>
                            </span>
                        </div>
                        <div class="valid-feedback">Passwords match!</div>
                        <div class="invalid-feedback">Passwords do not match</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">I agree to the Terms of Service and Privacy Policy</label>
                        <div class="invalid-feedback">You must agree to the terms and conditions</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Sign Up</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Log In</a></p>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId + '-toggle-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('signupForm');
            const fields = {
                username: {
                    element: document.getElementById('username'),
                    validate: (value) => value.length >= 3
                },
                email: {
                    element: document.getElementById('email'),
                    validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
                },
                phone: {
                    element: document.getElementById('phone'),
                    validate: (value) => value.length >= 10
                },
                password: {
                    element: document.getElementById('password'),
                    validate: (value) => value.length >= 8
                },
                confirm_password: {
                    element: document.getElementById('confirm_password'),
                    validate: (value) => value === document.getElementById('password').value
                }
            };

            // Add live validation to each field
            Object.keys(fields).forEach(fieldName => {
                const field = fields[fieldName];
                field.element.addEventListener('input', function() {
                    const isValid = field.validate(this.value.trim());
                    this.classList.toggle('is-valid', isValid);
                    this.classList.toggle('is-invalid', !isValid);
                });
            });

            // Update confirm password validation when password changes
            fields.password.element.addEventListener('input', function() {
                const confirmField = fields.confirm_password.element;
                if (confirmField.value) {
                    const isValid = confirmField.value === this.value;
                    confirmField.classList.toggle('is-valid', isValid);
                    confirmField.classList.toggle('is-invalid', !isValid);
                }
            });

            // Form submission validation
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                Object.keys(fields).forEach(fieldName => {
                    const field = fields[fieldName];
                    const value = field.element.value.trim();
                    const fieldIsValid = field.validate(value);
                    
                    field.element.classList.toggle('is-valid', fieldIsValid);
                    field.element.classList.toggle('is-invalid', !fieldIsValid);
                    
                    if (!fieldIsValid) isValid = false;
                });

                const terms = document.getElementById('terms');
                if (!terms.checked) {
                    terms.classList.add('is-invalid');
                    isValid = false;
                } else {
                    terms.classList.remove('is-invalid');
                }

                if (!isValid) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>