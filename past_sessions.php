<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';

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

try {
    // Get past sessions
    $past_sessions_result = $conn->prepare(
        "SELECT b.booking_id, b.booking_date, b.booking_time, b.session_type, b.status,
        r.fullname as counselor_name, r.reg_id as counselor_id,
        (SELECT COUNT(*) FROM tb_messages WHERE booking_id = b.booking_id) as message_count
        FROM tb_bookings b
        JOIN tb_register r ON b.counselor_id = r.reg_id
        WHERE b.student_id = ? 
        AND (b.booking_date < CURDATE() 
            OR (b.booking_date = CURDATE() AND b.booking_time < CURTIME()))
        ORDER BY b.booking_date DESC, b.booking_time DESC"
    );
    
    $past_sessions_result->bind_param('i', $user_id);
    $past_sessions_result->execute();
    $past_sessions = $past_sessions_result->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Database error in past_sessions.php: " . $e->getMessage());
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Sessions - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f5f5f5; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            padding-top: 20px;
            background-color: #2c3136;
            color: white;
        }
        .nav-link {
            color: rgba(255,255,255,.75);
            padding: 10px 20px;
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255,.1);
        }
        .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,.2);
        }
        .nav-link i { 
            margin-right: 10px; 
            font-size: 18px;
        }
        .main-content {
            margin-left: 240px;
            padding: 20px;
        }
        .session-title {
            font-size: 32px;
            font-weight: 500;
            color: #2c3136;
            margin-bottom: 20px;
        }
        .session-card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background-color: white;
            border: none;
            border-radius: 8px;
        }
        .session-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .session-info {
            padding: 15px;
        }
        .session-info p {
            margin-bottom: 10px;
            font-size: 15px;
        }
        .badge-text {
            background-color: #17a2b8;
        }
        .badge-video {
            background-color: #28a745;
        }
        .message-count {
            font-size: 0.9em;
            color: #6c757d;
        }
        .btn-video {
            background-color: #28a745;
            color: white;
        }
        .btn-video:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <h5>BrightMind Logo</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_classes.php">
                                <i class="bi bi-calendar-check"></i> My Sessions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="past_sessions.php">
                                <i class="bi bi-clock-history"></i> Past Sessions
                                <span class="badge bg-secondary ms-2"><?php echo count($past_sessions); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="container mt-4">
                    <h2 class="session-title">Past Counseling Sessions</h2>
                    
                    <?php if (empty($past_sessions)): ?>
                        <div class="alert alert-info">No past sessions found.</div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($past_sessions as $session): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card session-card">
                                        <div class="session-header">
                                            <h5 class="mb-0">Session with <?php echo htmlspecialchars($session['counselor_name']); ?></h5>
                                            <span class="badge <?php echo $session['session_type'] === 'video' ? 'badge-video' : 'badge-text'; ?>">
                                                <?php echo $session['session_type'] === 'video' ? 'Video Call' : 'Text Chat'; ?>
                                            </span>
                                        </div>
                                        <div class="session-info">
                                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($session['booking_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($session['booking_time'])); ?></p>
                                            <p><strong>Status:</strong> <?php echo ucfirst($session['status']); ?></p>
                                            <?php if ($session['session_type'] === 'text' && $session['message_count'] > 0): ?>
                                                <p class="message-count">
                                                    <i class="bi bi-chat-dots"></i> 
                                                    <?php echo $session['message_count']; ?> messages exchanged
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($session['session_type'] === 'text'): ?>
                                                <a href="chat_history.php?booking_id=<?php echo $session['booking_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-clock-history"></i> View Chat History
                                                </a>
                                            <?php elseif ($session['session_type'] === 'video'): ?>
                                                <a href="video_history.php?booking_id=<?php echo $session['booking_id']; ?>" 
                                                   class="btn btn-video btn-sm">
                                                    <i class="bi bi-camera-video"></i> View Call Details
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>