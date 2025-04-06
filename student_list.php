<?php
session_start();
require_once 'db_connection.php';

// Check authentication
if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'counselor') {
    header("Location: login.php");
    exit();
}

// Handle AJAX requests first, before any HTML output
if (isset($_GET['action'])) {
    ob_clean(); // Clear any previous output
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_student_sessions' && isset($_GET['student_id'])) {
        try {
            $student_id = intval($_GET['student_id']);
            $sql = "SELECT 
                        r.*,
                        b.booking_id,
                        b.booking_date,
                        b.booking_time,
                        b.duration,
                        b.reason,
                        b.status AS session_status
                    FROM 
                        tb_register r
                    JOIN 
                        tb_bookings b ON r.reg_id = b.student_id
                    WHERE 
                        r.reg_id = ?
                    ORDER BY 
                        b.booking_date DESC";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            
            $stmt->bind_param("i", $student_id);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $sessions = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['status' => 'success', 'sessions' => $sessions]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No sessions found']);
            }
        } catch (Exception $e) {
            error_log("Error fetching sessions: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit();
    }
    
    if ($_GET['action'] == 'update_status' && isset($_GET['booking_id']) && isset($_GET['status'])) {
        try {
            $booking_id = intval($_GET['booking_id']);
            $status = $_GET['status'];
            
            // Validate status
            $valid_statuses = ['pending', 'approved', 'rejected', 'cancelled'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Invalid status");
            }
            
            $sql = "UPDATE tb_bookings SET status = ? WHERE booking_id = ? AND status != 'cancelled'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $status, $booking_id);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success']);
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error updating status: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Update failed']);
        }
        exit();
    }
}
// Fetch students with booked sessions
$sql = "SELECT DISTINCT
            r.reg_id, 
            r.username,
            r.fullname, 
            r.email,
            r.education,
            COUNT(b.booking_id) as total_sessions,
            SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_sessions,
            MAX(b.booking_date) as latest_session_date
        FROM 
            tb_register r
        JOIN 
            tb_bookings b ON r.reg_id = b.student_id
        WHERE 
            r.role_id = 2
        GROUP BY 
            r.reg_id, r.username, r.fullname, r.email, r.education
        ORDER BY 
            latest_session_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Counselor Dashboard - Student Sessions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
        }
        .student-list {
            list-style-type: none;
            padding: 0;
        }
        .student-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .student-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .session-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .badge-total {
            background-color: #0d6efd;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            width: 90%;
            max-width: 700px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .session-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .btn-view {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .btn-view:hover {
            background-color: #0b5ed7;
        }
        .btn-close-modal {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
        }
        .action-buttons {
            margin-top: 10px;
        }
        .btn-approve {
            background-color: #198754;
            color: white;
            margin-right: 10px;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people"></i> Students with Booked Sessions</h2>
            <a href="counselor_dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if($result && $result->num_rows > 0): ?>
        <ul class="student-list">
            <?php while($row = $result->fetch_assoc()): ?>
            <li class="student-item">
                <div class="d-flex justify-content-between align-items-center">
                <div>
                        <h5 class="mb-1">
                            <?php echo htmlspecialchars($row['fullname'] ?: $row['username']); ?>
                        </h5>
                        <div class="text-muted">
                            <i class="bi bi-mortarboard"></i> 
                            <?php echo htmlspecialchars($row['education'] ?: 'Not specified'); ?> • 
                            <i class="bi bi-envelope"></i> 
                            <?php echo htmlspecialchars($row['email']); ?>
                </div>
                        <small class="text-muted">
                            Latest Session: <?php echo date('F j, Y', strtotime($row['latest_session_date'])); ?>
                        </small>
                </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if($row['pending_sessions'] > 0): ?>
                            <span class="session-badge badge-pending">
                                <?php echo $row['pending_sessions']; ?> Pending
                            </span>
                        <?php endif; ?>
                        <span class="session-badge badge-total">
                            <?php echo $row['total_sessions']; ?> Total
                        </span>
                        <button class="btn btn-view" onclick="viewStudentSessions(<?php echo $row['reg_id']; ?>)">
                            <i class="bi bi-calendar-check"></i> View Sessions
                        </button>
                </div>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No students have booked sessions yet.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Session Details Modal -->
    <div id="sessionDetailsModal" class="modal">
        <div class="modal-content">
            <h3 id="studentNameHeader" class="mb-4"></h3>
            <div id="sessionDetailsContent"></div>
            <div class="text-end mt-4">
                <button class="btn btn-close-modal" onclick="closeSessionDetails()">Close</button>
                        </div>
                    </div>
                </div>
                
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewStudentSessions(studentId) {
            fetch(`?action=get_student_sessions&student_id=${studentId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.sessions && data.sessions.length > 0) {
                    const sessions = data.sessions;
                    const modalContent = document.getElementById('sessionDetailsContent');
                    const modal = document.getElementById('sessionDetailsModal');
                    
                    modalContent.dataset.studentId = studentId;
                    
                    document.getElementById('studentNameHeader').textContent = 
                        `Sessions for ${sessions[0].fullname || sessions[0].username}`;
                    
                    modalContent.innerHTML = sessions.map(session => `
                        <div class="session-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><i class="bi bi-calendar"></i> <strong>Date:</strong> 
                                        ${new Date(session.booking_date).toLocaleDateString()}</p>
                                    <p><i class="bi bi-clock"></i> <strong>Time:</strong> 
                                        ${session.booking_time}</p>
                                    <p><i class="bi bi-hourglass"></i> <strong>Duration:</strong> 
                                        ${session.duration} minutes</p>
                        </div>
                                <div class="col-md-6">
                                    <p><i class="bi bi-chat-text"></i> <strong>Reason:</strong> 
                                        ${session.reason}</p>
                                    <p><i class="bi bi-info-circle"></i> <strong>Status:</strong> 
                                        <span class="badge bg-${getStatusColor(session.session_status)}">
                                            ${session.session_status.toUpperCase()}
                                        </span>
                                    </p>
                                    ${session.session_status === 'pending' ? `
                                        <div class="action-buttons">
                                            <button class="btn btn-approve btn-sm" onclick="updateStatus(${session.booking_id}, 'approved')">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                            <button class="btn btn-reject btn-sm" onclick="updateStatus(${session.booking_id}, 'rejected')">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </button>
                    </div>
                                    ` : `
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle"></i> 
                                                Session has been ${session.session_status}
                                            </small>
                        </div>
                                    `}
                    </div>
                </div>
                        </div>
                    `).join('');

                    // Show the modal
            modal.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to fetch student sessions');
            });
        }

        function updateStatus(bookingId, status) {
            if (!confirm(`Are you sure you want to ${status} this session?`)) {
                return;
            }

            fetch(`?action=update_status&booking_id=${bookingId}&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const studentId = document.querySelector('#sessionDetailsContent').dataset.studentId;
                        viewStudentSessions(studentId);
                        
                        // Show success message
                        alert(`Session has been ${status} successfully`);
                        
                        // Reload the page after a short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        alert('Failed to update session status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update session status');
                });
        }

        function getStatusColor(status) {
            switch(status.toLowerCase()) {
                case 'pending': return 'warning';
                case 'approved': return 'success';
                case 'rejected': return 'danger';
                case 'cancelled': return 'secondary';
                default: return 'primary';
            }
        }

        function closeSessionDetails() {
            document.getElementById('sessionDetailsModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('sessionDetailsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>