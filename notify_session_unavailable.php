<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

$counselor_id = $_SESSION['reg_id'];

if (isset($_GET['booking_id'])) {
    $booking_id = $_GET['booking_id'];
    
    // Get student details
    $student_query = "SELECT b.student_id, b.booking_date, b.booking_time, r.email, r.fullname 
                     FROM tb_bookings b 
                     JOIN tb_register r ON b.student_id = r.reg_id
                     WHERE b.booking_id = ? AND b.counselor_id = ?";
    $stmt = $conn->prepare($student_query);
    if ($stmt === false) {
        die("Error preparing query: " . $conn->error);
    }
    $stmt->bind_param("ii", $booking_id, $counselor_id);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student_data = $student_result->fetch_assoc();
    
    if ($student_data) {
        // Update booking status
        $update_query = "UPDATE tb_bookings SET status = 'cancelled' WHERE booking_id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt === false) {
            die("Error preparing update query: " . $conn->error);
        }
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        
        // Add notification
        $notification_message = "Your counseling session scheduled for " . 
                              date('M d, Y', strtotime($student_data['booking_date'])) . 
                              " at " . date('h:i A', strtotime($student_data['booking_time'])) . 
                              " has been cancelled as the counselor is unavailable.";
        
        $notify_query = "INSERT INTO tb_notifications (user_id, message, created_at, status) 
                        VALUES (?, ?, NOW(), 'unread')";
        $stmt = $conn->prepare($notify_query);
        if ($stmt === false) {
            die("Error preparing notification query: " . $conn->error);
        }
        $stmt->bind_param("is", $student_data['student_id'], $notification_message);
        $stmt->execute();
        
        // Send email notification
        $to = $student_data['email'];
        $subject = "Counseling Session Unavailable Notice";
        $message = "Dear " . $student_data['fullname'] . ",\n\n";
        $message .= $notification_message . "\n\n";
        $message .= "We apologize for any inconvenience caused. Please reschedule your session at your earliest convenience.\n\n";
        $message .= "Best regards,\nBrightMind Counseling Team";
        $headers = "From: noreply@brightmind.com";
        
        mail($to, $subject, $message, $headers);
        
        $_SESSION['success_message'] = "Unavailable notice sent successfully.";
    }
    
    header("Location: class_management.php");
    exit();
}

header("Location: class_management.php");
exit();
?>