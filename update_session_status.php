<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'counselor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    if ($booking_id && $status) {
        $update_query = "UPDATE tb_bookings SET status = ? WHERE booking_id = ? AND counselor_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sii", $status, $booking_id, $_SESSION['reg_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}