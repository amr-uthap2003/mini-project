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

// Get booking_id and message from POST parameters
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Check if the message is not empty
if (!empty($message)) {
    try {
        // Insert the message into the database
        $insert_query = "INSERT INTO tb_messages (booking_id, sender_id, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('iis', $booking_id, $user_id, $message);
        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        
        // Redirect back to the chat page
        header("Location: chat.php?booking_id=$booking_id&counselor_id={$_POST['counselor_id']}");
        exit();
    } catch (Exception $e) {
        error_log("Database error in send_message.php: " . $e->getMessage());
        die("Database error: " . $e->getMessage());
    }
} else {
    // Redirect back to the chat page if the message is empty
    header("Location: chat.php?booking_id=$booking_id&counselor_id={$_POST['counselor_id']}");
    exit();
}
?>