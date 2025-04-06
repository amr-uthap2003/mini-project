<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';

// Verify database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Connection failed: " . ($conn ? $conn->connect_error : "No connection"));
    die("Connection failed: " . ($conn ? $conn->connect_error : "No connection"));
}

if (!isset($_SESSION['reg_id']) || !isset($_SESSION['role_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['reg_id'];
$role_id = $_SESSION['role_id'];

// Get booking_id and counselor_id from URL parameters
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$counselor_id = isset($_GET['counselor_id']) ? intval($_GET['counselor_id']) : 0;

// Fetch chat messages for the session
$messages_result = executeQuery(
    $conn,
    "SELECT m.message_id, m.message, m.created_at, r.fullname as sender_name 
    FROM tb_messages m
    JOIN tb_bookings b ON m.booking_id = b.booking_id
    JOIN tb_register r ON m.sender_id = r.reg_id
    WHERE b.booking_id = ?
    ORDER BY m.created_at ASC",
    [$booking_id],
    'i'
);
$messages = $messages_result->fetch_all(MYSQLI_ASSOC);

// Function to safely execute prepared statements
function executeQuery($conn, $query, $params = [], $types = '') {
    if (isset($GLOBALS['stmt'])) {
        $GLOBALS['stmt']->close();
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error preparing query: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false && $stmt->errno) {
        throw new Exception("Error getting result: " . $stmt->error);
    }
    
    $GLOBALS['stmt'] = $stmt;
    return $result;
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $insert_query = "INSERT INTO tb_messages (booking_id, sender_id, message, created_at) VALUES (?, ?, ?, NOW())";
        executeQuery($conn, $insert_query, [$booking_id, $user_id, $message], 'iis');
        header("Location: chat.php?booking_id=$booking_id&counselor_id=$counselor_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Counselor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; }
        .chat-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .message {
            margin-bottom: 15px;
        }
        .message .sender {
            font-weight: bold;
        }
        .message .timestamp {
            font-size: 0.8em;
            color: gray;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <h2>Chat with Counselor</h2>
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
                <div class="message">
                    <div class="sender"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                    <div><?php echo htmlspecialchars($msg['message']); ?></div>
                    <div class="timestamp"><?php echo date('F d, Y h:i A', strtotime($msg['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <form action="" method="POST" class="mt-3">
            <div class="input-group">
                <input type="text" class="form-control" name="message" placeholder="Type your message..." required>
                <button class="btn btn-primary" type="submit">Send</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>