<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || $_SESSION['role'] !== 'counselor') {
    header("Location: login.php");
    exit;
}

$counselor_id = $_SESSION['reg_id'];
$error_message = '';
$success_message = '';

// Fetch counselor details
$query = "SELECT * FROM tb_register WHERE reg_id = ?";
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counselor = $result->fetch_assoc();
    $stmt->close();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $specialization = filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING);
    $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);

    $update_query = "UPDATE tb_register SET username=?, email=?, phone=?, specialization=?, bio=? WHERE reg_id=?";
    if ($stmt = $conn->prepare($update_query)) {
        $stmt->bind_param("sssssi", $username, $email, $phone, $specialization, $bio, $counselor_id);
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            $_SESSION['username'] = $username;
            // Refresh counselor data
            $counselor['username'] = $username;
            $counselor['email'] = $email;
            $counselor['phone'] = $phone;
            $counselor['specialization'] = $specialization;
            $counselor['bio'] = $bio;
        } else {
            $error_message = "Failed to update profile";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BrightMind Counselor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="counselor_dashboard.php">
                <i class="fas fa-brain me-2"></i>BrightMind Counselor
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-list me-2"></i>Quick Links
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="counselor_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <a href="manage_availability.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-alt me-2"></i>Manage Availability
                        </a>
                        <a href="pending_sessions.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-clock me-2"></i>Pending Sessions
                        </a>
                        <a href="session_history.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-history me-2"></i>Session History
                        </a>
                        <a href="counselor_profile.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>My Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                    value="<?php echo htmlspecialchars($counselor['username'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                    value="<?php echo htmlspecialchars($counselor['email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                    value="<?php echo htmlspecialchars($counselor['phone'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" 
                                    value="<?php echo htmlspecialchars($counselor['specialization'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($counselor['bio'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>