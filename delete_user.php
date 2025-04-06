<?php
require_once 'db_connection.php';
requireRole('admin');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Check if user exists and is not the current admin
    $check_query = $conn->prepare("SELECT r.reg_id, ro.role_name 
                                 FROM tb_register r
                                 JOIN tb_roles ro ON r.role_id = ro.role_id
                                 WHERE r.reg_id = ? AND r.reg_id != ?");
    $current_user_id = $_SESSION['reg_id'];
    $check_query->bind_param("ii", $user_id, $current_user_id);
    $check_query->execute();
    $result = $check_query->get_result();

    if ($result->num_rows > 0) {
        // Delete the user
        $delete_query = $conn->prepare("DELETE FROM tb_register WHERE reg_id = ?");
        $delete_query->bind_param("i", $user_id);
        
        if ($delete_query->execute()) {
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting user: " . $conn->error;
        }
        $delete_query->close();
    } else {
        $_SESSION['error_message'] = "Invalid user or cannot delete current admin.";
    }
    $check_query->close();
} else {
    $_SESSION['error_message'] = "Invalid user ID.";
}

// Redirect back to manage users page
header("Location: manage_users.php");
exit();
?>