<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || !isset($_GET['booking_id'])) {
    echo json_encode([]);
    exit();
}

$booking_id = (int)$_GET['booking_id'];
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : date('Y-m-d H:i:s', strtotime('-3 seconds'));

$stmt = $conn->prepare("
    SELECT m.*, r.fullname 
    FROM tb_messages m
    JOIN tb_register r ON m.sender_id = r.reg_id
    WHERE m.booking_id = ? AND m.created_at > ?
    ORDER BY m.created_at ASC
");

if ($stmt === false) {
    echo json_encode([]);
    exit();
}

$stmt->bind_param("is", $booking_id, $last_check);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'message_id' => $row['message_id'],
        'sender_id' => $row['sender_id'],
        'message' => htmlspecialchars($row['message']),
        'created_at' => date('h:i A', strtotime($row['created_at']))
    ];
}

echo json_encode($messages);