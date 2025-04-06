<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || !isset($_POST['booking_id']) || !isset($_POST['message'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$sender_id = $_SESSION['reg_id'];
$booking_id = (int)$_POST['booking_id'];
$message = trim($_POST['message']);

$stmt = $conn->prepare("INSERT INTO tb_messages (booking_id, sender_id, message) VALUES (?, ?, ?)");
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param("iis", $booking_id, $sender_id, $message);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}