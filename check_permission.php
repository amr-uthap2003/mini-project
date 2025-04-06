<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id'])) {
    exit(json_encode(['status' => 'error']));
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$query = "SELECT meeting_status FROM tb_bookings WHERE booking_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['status' => $result['meeting_status'] ?? 'waiting']);