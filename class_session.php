<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], ['counselor', 'student'])) {
    header("Location: login.php");
    exit();
}

$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch class details
$sql = "SELECT c.*, r.fullname as counselor_name 
        FROM tb_classes c 
        JOIN tb_register r ON c.counselor_id = r.reg_id 
        WHERE c.class_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();

if (!$class) {
    header("Location: class_management.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($class['class_name']); ?> - Session</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            max-width: 100%;
            margin-bottom: 20px;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        .chat-container {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .message {
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
        }
        .message-sent {
            background-color: #e3f2fd;
            margin-left: 20%;
        }
        .message-received {
            background-color: #f8f9fa;
            margin-right: 20%;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                <a href="class_management.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Classes
                </a>
            </div>
            <div class="card-body">
                <?php if ($class['session_type'] === 'video'): ?>
                    <div class="video-container">
                        <?php if (!empty($class['meeting_link'])): ?>
                            <iframe src="<?php echo htmlspecialchars($class['meeting_link']); ?>" 
                                    allow="camera; microphone; fullscreen; display-capture"></iframe>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> Video link not available.
                                <?php if ($_SESSION['role_name'] === 'counselor'): ?>
                                    Please add a meeting link in class settings.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($class['session_type'] === 'text'): ?>
                    <div class="chat-container" id="chatContainer">
                        <!-- Messages will appear here -->
                    </div>
                    <form id="messageForm" class="d-flex gap-2">
                        <input type="text" class="form-control" id="messageInput" 
                               placeholder="Type your message...">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This is an in-person class session.
                        <p class="mb-0 mt-2">Please refer to your schedule for location details.</p>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <h6>Class Information:</h6>
                    <p><strong>Counselor:</strong> <?php echo htmlspecialchars($class['counselor_name']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($class['description']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($class['session_type'] === 'text'): ?>
    <script>
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const chatContainer = document.getElementById('chatContainer');

        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (message) {
                addMessage(message, true);
                messageInput.value = '';
            }
        });

        function addMessage(message, isSent) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSent ? 'message-sent' : 'message-received'}`;
            messageDiv.textContent = message;
            chatContainer.appendChild(messageDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    </script>
    <?php endif; ?>
</body>
</html>