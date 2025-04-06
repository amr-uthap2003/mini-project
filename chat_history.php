<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || !isset($_SESSION['role_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['reg_id'];
$role_id = $_SESSION['role_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$booking_id) {
    header("Location: " . ($role_id == 3 ? 'counselor_past_sessions.php' : 'past_sessions.php'));
    exit();
}

try {
    // Verify session ownership
    $session_check = $conn->prepare(
        "SELECT b.*, 
        s.fullname as student_name,
        c.fullname as counselor_name,
        b.student_id, b.counselor_id, b.session_type
        FROM tb_bookings b
        JOIN tb_register s ON b.student_id = s.reg_id
        JOIN tb_register c ON b.counselor_id = c.reg_id
        WHERE b.booking_id = ? AND (b.student_id = ? OR b.counselor_id = ?)"
    );
    $session_check->bind_param("iii", $booking_id, $user_id, $user_id);
    $session_check->execute();
    $session_result = $session_check->get_result();
    
    if ($session_result->num_rows === 0) {
        header("Location: " . ($role_id == 3 ? 'counselor_past_sessions.php' : 'past_sessions.php'));
        exit();
    }
    
    $session_data = $session_result->fetch_assoc();
    
    // Get chat messages
    $messages_query = $conn->prepare(
        "SELECT m.*, r.fullname as sender_name 
        FROM tb_messages m
        JOIN tb_register r ON m.sender_id = r.reg_id
        WHERE m.booking_id = ?
        ORDER BY m.created_at ASC"
    );
    $messages_query->bind_param("i", $booking_id);
    $messages_query->execute();
    $messages = $messages_query->get_result();
    
} catch (Exception $e) {
    error_log("Error in chat_history.php: " . $e->getMessage());
    die("An error occurred while retrieving the chat history.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($session_data['session_type']); ?> Session History - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .chat-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        .chat-messages {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        .message {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 15px;
            max-width: 70%;
            word-wrap: break-word;
        }
        .message.sent {
            align-items: flex-end;
        }
        .message.received {
            align-items: flex-start;
        }
        .message.sent .message-content {
            background-color: #007bff;
            color: white;
        }
        .message.received .message-content {
            background-color: #e9ecef;
            color: #212529;
        }
        .message-meta {
            font-size: 0.8em;
            color: #6c757d;
            margin: 5px 0;
        }
        .back-button {
            margin: 20px 0;
        }
        .session-info {
            color: #6c757d;
            font-size: 0.9em;
        }
        .session-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.9em;
            font-weight: 500;
            border-radius: 0.25rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <div class="back-button">
            <a href="<?php echo $role_id == 3 ? 'counselor_past_sessions.php' : 'past_sessions.php'; ?>" 
               class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Past Sessions
            </a>
        </div>

        <div class="chat-container">
            <div class="chat-header">
                <h4>
                    <?php echo ucfirst($session_data['session_type']); ?> Session History
                    <?php 
                    $type_badge = $session_data['session_type'] === 'video' ? 'bg-primary' : 'bg-success';
                    ?>
                    <span class="badge <?php echo $type_badge; ?> session-badge">
                        <?php echo ucfirst($session_data['session_type']); ?>
                    </span>
                </h4>
                <div class="session-info">
                    <p class="mb-1">
                        <strong>Student:</strong> <?php echo htmlspecialchars($session_data['student_name']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Counselor:</strong> <?php echo htmlspecialchars($session_data['counselor_name']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Date:</strong> 
                        <?php echo date('F d, Y', strtotime($session_data['booking_date'])); ?>
                    </p>
                    <p class="mb-0">
                        <strong>Time:</strong> 
                        <?php echo date('h:i A', strtotime($session_data['booking_time'])); ?>
                    </p>
                </div>
            </div>

            <div class="chat-messages">
                <?php if ($messages && $messages->num_rows > 0): ?>
                    <?php while ($message = $messages->fetch_assoc()): ?>
                        <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                            <div class="message-meta">
                                <?php echo htmlspecialchars($message['sender_name']); ?>
                            </div>
                            <div class="message-content">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                            <div class="message-meta">
                                <?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No messages found for this session.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>