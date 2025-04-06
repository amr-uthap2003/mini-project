<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || !isset($_GET['booking_id'])) {
    exit(json_encode(['error' => 'Unauthorized access']));
}

$booking_id = $_GET['booking_id'];
$counselor_id = $_SESSION['reg_id'];

// Check if there's an active video session
$query = "SELECT COUNT(*) as active_count 
          FROM tb_video_sessions 
          WHERE booking_id = ? 
          AND status = 'active' 
          AND created_at >= NOW() - INTERVAL 1 HOUR";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    'is_active' => $data['active_count'] > 0
]);

$stmt->close();
$conn->close();