<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || !isset($_GET['booking_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['reg_id'];
$booking_id = (int)$_GET['booking_id'];

// Get chat session details
$session_query = "SELECT b.*, 
    s.fullname as student_name, s.reg_id as student_id,
    c.fullname as counselor_name, c.reg_id as counselor_id
    FROM tb_bookings b
    JOIN tb_register s ON b.student_id = s.reg_id
    JOIN tb_register c ON b.counselor_id = c.reg_id
    WHERE b.booking_id = ? AND (b.student_id = ? OR b.counselor_id = ?)";

$stmt = $conn->prepare($session_query);
if ($stmt === false) {
    die("Error preparing session query: " . $conn->error);
}
$stmt->bind_param("iii", $booking_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$chat_session = $result->fetch_assoc();

if (!$chat_session) {
    header("Location: dashboard.php");
    exit();
}

// Get chat messages
$messages_query = "SELECT m.*, r.fullname 
    FROM tb_messages m
    JOIN tb_register r ON m.sender_id = r.reg_id
    WHERE m.booking_id = ?
    ORDER BY m.created_at ASC";

$stmt = $conn->prepare($messages_query);
if ($stmt === false) {
    die("Error preparing messages query: " . $conn->error);
}
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$messages = $stmt->get_result();

$other_user = ($user_id == $chat_session['student_id']) ? 
    $chat_session['counselor_name'] : 
    $chat_session['student_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Session - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; }
        .chat-container {
            height: calc(100vh - 180px);
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .chat-messages {
            height: calc(100% - 130px);
            overflow-y: auto;
            padding: 15px;
        }
        .message {
            max-width: 75%;
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
            cursor: pointer;
        }
        .message.sent {
            background-color: #007bff;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .message.received {
            background-color: #e9ecef;
            color: #212529;
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        .chat-input {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            position: relative;
        }
        .back-button {
            text-decoration: none;
            color: #6c757d;
        }
        .back-button:hover {
            color: #343a40;
        }
        .paste-tooltip {
            display: none;
            position: absolute;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 5px;
            white-space: nowrap;
        }
        .chat-input:hover .paste-tooltip {
            display: block;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="chat-container">
            <div class="chat-header">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="javascript:history.back()" class="back-button">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <h5 class="mb-0">Chat with <?php echo htmlspecialchars($other_user); ?></h5>
                    <div style="width: 24px;"></div>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php while ($message = $messages->fetch_assoc()): ?>
                    <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                        <?php echo htmlspecialchars($message['message']); ?>
                        <div class="message-time">
                            <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="chat-input">
                <form id="messageForm" class="d-flex gap-2">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="text" name="message" class="form-control" placeholder="Type your message..." required id="messageInput">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i>
                    </button>
                </form>
                <span class="paste-tooltip">Press Ctrl+V to paste</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chatMessages = document.getElementById('chatMessages');
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        scrollToBottom();

        messageInput.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = e.clipboardData.getData('text');
            messageInput.value = text;
        });

        messageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(messageForm);
            
            try {
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'message sent';
                        messageDiv.innerHTML = `
                            ${formData.get('message')}
                            <div class="message-time">${new Date().toLocaleTimeString()}</div>
                        `;
                        chatMessages.appendChild(messageDiv);
                        scrollToBottom();
                        messageForm.reset();
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });

        setInterval(async () => {
            try {
                const response = await fetch(`get_messages.php?booking_id=<?php echo $booking_id; ?>`);
                if (response.ok) {
                    const messages = await response.json();
                    if (messages.length > 0) {
                        messages.forEach(msg => {
                            if (msg.sender_id != <?php echo $user_id; ?>) {
                                const messageDiv = document.createElement('div');
                                messageDiv.className = 'message received';
                                messageDiv.innerHTML = `
                                    ${msg.message}
                                    <div class="message-time">${msg.created_at}</div>
                                `;
                                chatMessages.appendChild(messageDiv);
                                scrollToBottom();
                            }
                        });
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }, 3000);
    </script>
</body>
</html>