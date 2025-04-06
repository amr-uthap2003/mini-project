<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || !isset($_GET['booking_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$booking_id = (int)$_GET['booking_id'];
$user_id = $_SESSION['reg_id'];

// Check if counselor has started the chat
$query = "SELECT COUNT(*) as chat_exists 
          FROM tb_messages m
          JOIN tb_bookings b ON m.booking_id = b.booking_id
          WHERE m.booking_id = ? 
          AND m.sender_id = b.counselor_id";

$stmt = $conn->prepare($query);
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
    exit();
}

$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode([
    'chat_started' => $data['chat_exists'] > 0,
    'booking_id' => $booking_id
]);