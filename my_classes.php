<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connection.php';

// Debug mode - set to true to force buttons to appear regardless of time
$DEBUG_MODE = true;

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
    function executeQuery($conn, $query, $params = [], $types = '') {
        if (isset($GLOBALS['stmt'])) {
            $GLOBALS['stmt']->close();
        }
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error preparing query: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result === false && $stmt->errno) {
            throw new Exception("Error getting result: " . $stmt->error);
        }
        
        $GLOBALS['stmt'] = $stmt;
        return $result;
    }

    // Get past sessions count
    $past_sessions_result = executeQuery(
        $conn,
        "SELECT COUNT(*) as count 
        FROM tb_bookings b 
        WHERE b.student_id = ? 
        AND (b.booking_date < CURDATE() 
            OR (b.booking_date = CURDATE() AND b.booking_time < CURTIME()))",
        [$user_id],
        'i'
    );
    $past_sessions_count = $past_sessions_result->fetch_assoc()['count'];

    $role_result = executeQuery(
        $conn,
        "SELECT role_name FROM tb_roles WHERE role_id = ?",
        [$role_id],
        'i'
    );
    $role_data = $role_result->fetch_assoc();
    $role = $role_data['role_name'];
    $is_student = ($role === 'student');

    $chat_result = executeQuery(
        $conn,
        "SELECT DISTINCT m.booking_id 
        FROM tb_messages m
        JOIN tb_bookings b ON m.booking_id = b.booking_id
        WHERE b.student_id = ? AND m.sender_id = b.counselor_id",
        [$user_id],
        'i'
    );
    $active_chats = array_column($chat_result->fetch_all(MYSQLI_ASSOC), 'booking_id');

    $notifications_result = executeQuery(
        $conn,
        "SELECT b.booking_date, b.booking_time, r.fullname as counselor_name 
        FROM tb_bookings b
        JOIN tb_register r ON b.counselor_id = r.reg_id
        WHERE b.student_id = ? AND b.status = 'cancelled'
        ORDER BY b.booking_date DESC LIMIT 5",
        [$user_id],
        'i'
    );

    $meetings_result = executeQuery(
        $conn,
        "SELECT b.booking_id, b.booking_date, b.booking_time, b.status, 
        b.session_type, 
        r.fullname as counselor_name, r.reg_id as counselor_id
        FROM tb_bookings b
        INNER JOIN tb_register r ON b.counselor_id = r.reg_id
        WHERE b.student_id = ? 
        AND b.status = 'approved'
        AND b.booking_date >= CURDATE()
        ORDER BY b.booking_date ASC, b.booking_time ASC",
        [$user_id],
        'i'
    );
    $meetings = $meetings_result->fetch_all(MYSQLI_ASSOC);

    $messages_result = executeQuery(
        $conn,
        "SELECT m.message_id, m.message, m.created_at, r.fullname as counselor_name 
        FROM tb_messages m
        JOIN tb_bookings b ON m.booking_id = b.booking_id
        JOIN tb_register r ON b.counselor_id = r.reg_id
        WHERE b.student_id = ?
        ORDER BY m.created_at DESC",
        [$user_id],
        'i'
    );
    $messages = $messages_result->fetch_all(MYSQLI_ASSOC);

    if (isset($GLOBALS['stmt'])) {
        $GLOBALS['stmt']->close();
    }

} catch (Exception $e) {
    error_log("Database error in my_classes.php: " . $e->getMessage());
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - BrightMind</title>
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
        .session-card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background-color: white;
            border: none;
            border-radius: 8px;
        }
        .join-chat-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 260px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .join-chat-header {
            background-color: #0066ff;
            color: white;
            padding: 10px 15px;
            border-radius: 10px 10px 0 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .powered-by {
            font-size: 12px;
            font-weight: 500;
        }
        .close-button {
            color: white;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
        }
        .join-chat-body {
            padding: 15px;
        }
        .chat-message {
            background-color: #f8f8f8;
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 15px;
            display: inline-block;
            max-width: 90%;
            font-size: 14px;
        }
        .color-selector {
            display: flex;
            align-items: center;
            margin: 15px 0;
            gap: 10px;
        }
        .color-label {
            font-size: 12px;
            color: #666;
        }
        .color-input {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
            width: 80px;
            font-size: 12px;
        }
        .btn-start-chat {
            background: #0066ff;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 14px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }
        .btn-start-chat:hover {
            background: #0052cc;
            color: white;
        }
        .arrow-icon {
            font-size: 14px;
        }
        .main-content {
            margin-left: 240px;
            padding: 20px;
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
        .session-title {
            font-size: 32px;
            font-weight: 500;
            color: #2c3136;
            margin-bottom: 20px;
        }
        .alert-info {
            background-color: #e1f5fe;
            border-color: #b3e5fc;
            color: #0277bd;
            border-radius: 8px;
        }
        .info-text {
            background-color: #e1f5fe;
            padding: 15px;
            border-radius: 8px;
            color: #0277bd;
            margin-top: 15px;
        }
        .session-info {
            padding: 15px;
        }
        .session-info p {
            margin-bottom: 10px;
            font-size: 15px;
        }
        .session-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .session-header h5 {
            margin: 0;
            font-weight: 500;
        }
        .chat-button-active {
            margin-top: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .active-session {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        .btn-video {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        .btn-video:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }
        .btn-video.active-session {
            background-color: #ffc107;
            border-color: #ffc107;
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
                            <a class="nav-link active" href="my_classes.php">
                                <i class="bi bi-calendar-check"></i> My Sessions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="past_sessions.php">
                                <i class="bi bi-clock-history"></i> Past Sessions
                                <?php if ($past_sessions_count > 0): ?>
                                    <span class="badge bg-secondary ms-2"><?php echo $past_sessions_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                                <i class="bi bi-bell"></i> Notifications
                                <?php if ($notifications_result && $notifications_result->num_rows > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $notifications_result->num_rows; ?></span>
                                <?php endif; ?>
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
                    <h2 class="session-title">My Counseling Sessions</h2>
                    
                    <?php if ($DEBUG_MODE): ?>
                    <div class="debug-info">
                        <h5>Debug Information</h5>
                        <p>Today's date: <?php echo date('Y-m-d'); ?></p>
                        <p>Current time: <?php echo date('H:i:s'); ?></p>
                        <p>Number of meetings: <?php echo count($meetings); ?></p>
                        <?php foreach ($meetings as $index => $meeting): ?>
                            <div>
                                <strong>Meeting #<?php echo ($index+1); ?>:</strong>
                                <ul>
                                    <li>Date: <?php echo $meeting['booking_date']; ?></li>
                                    <li>Time: <?php echo $meeting['booking_time']; ?></li>
                                    <li>Is today: <?php echo (date('Y-m-d') == $meeting['booking_date']) ? 'Yes' : 'No'; ?></li>
                                    <?php
                                    $sessionTime = strtotime($meeting['booking_date'] . ' ' . $meeting['booking_time']);
                                    $currentTime = time();
                                    $timeDiff = $sessionTime - $currentTime;
                                    ?>
                                    <li>Time difference: <?php echo $timeDiff; ?> seconds (<?php echo round($timeDiff/60); ?> minutes)</li>
                                    <li>Within timeframe: <?php echo ($timeDiff > -3600 && $timeDiff < 3600) ? 'Yes' : 'No'; ?></li>
                                    <li>Counselor: <?php echo htmlspecialchars($meeting['counselor_name']); ?></li>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($messages)): ?>
                        <div class="alert alert-info">
                            <p>You have new messages from your counselors:</p>
                            <ul>
                                <?php foreach (array_slice($messages, 0, 5) as $message): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($message['counselor_name']); ?></strong>: 
                                        <?php echo htmlspecialchars($message['message']); ?> 
                                        <em>(<?php echo date('F d, Y h:i A', strtotime($message['created_at'])); ?>)</em>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($meetings)): ?>
                        <div class="alert alert-info">No active sessions found.</div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($meetings as $meeting): ?>
                                <?php 
                                $isToday = date('Y-m-d') == $meeting['booking_date'];
                                $sessionTime = strtotime($meeting['booking_date'] . ' ' . $meeting['booking_time']);
                                $currentTime = time();
                                $timeDiff = $sessionTime - $currentTime;
                                $isWithinTimeframe = $timeDiff > -3600 && $timeDiff < 3600;
                                $canJoin = $isToday && ($isWithinTimeframe || $DEBUG_MODE);
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card session-card" data-booking-id="<?php echo $meeting['booking_id']; ?>" data-counselor-id="<?php echo $meeting['counselor_id']; ?>">
                                        <div class="session-header">
                                            <h5>Session with <?php echo htmlspecialchars($meeting['counselor_name']); ?></h5>
                                        </div>
                                        <div class="session-info">
                                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($meeting['booking_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($meeting['booking_time'])); ?></p>
                                            <p><strong>Type:</strong> <?php echo htmlspecialchars($meeting['session_type']); ?></p>
                                            
                                            <?php if ($canJoin): ?>
                                                <div class="chat-button-active">
                                                    <?php if ($meeting['session_type'] === 'video'): ?>
                                                        <a href="video_call.php?counselor_id=<?php echo $meeting['counselor_id']; ?>&booking_id=<?php echo $meeting['booking_id']; ?>" 
                                                           class="btn btn-primary btn-video" id="video-btn-<?php echo $meeting['booking_id']; ?>"
                                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Join video session">
                                                            <i class="bi bi-camera-video-fill"></i> Join Meeting
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="chat.php?counselor_id=<?php echo $meeting['counselor_id']; ?>&booking_id=<?php echo $meeting['booking_id']; ?>" 
                                                           class="btn btn-primary btn-chat" id="chat-btn-<?php echo $meeting['booking_id']; ?>"
                                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Join this chat session">
                                                            <i class="bi bi-chat-dots-fill"></i> Start Chat
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="info-text">
                                                    Session will be available 1 hour before the scheduled time.
                                                </div>
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

    <!-- Join.chat floating widget - appears only if there's an active CHAT session -->
    <?php if (!empty($meetings) && (array_filter($meetings, function($m) use ($DEBUG_MODE) {
        $isToday = date('Y-m-d') == $m['booking_date'];
        $sessionTime = strtotime($m['booking_date'] . ' ' . $m['booking_time']);
        $currentTime = time();
        $timeDiff = $sessionTime - $currentTime;
        // Only show for chat sessions, not video sessions
        return $isToday && ($timeDiff > -3600 && $timeDiff < 3600 || $DEBUG_MODE) && $m['session_type'] !== 'video';
    }) || $DEBUG_MODE)): ?>
    <div class="join-chat-container" id="joinChatWidget">
        <div class="join-chat-header">
            <div class="powered-by">Powered by Join.chat</div>
            <button type="button" class="close-button" id="closeJoinChat">&times;</button>
        </div>
        <div class="join-chat-body">
            <div class="chat-message">
                👋 Hello. Now you can change the entire color range of Join chat just by choosing a color.
            </div>
            <div class="color-selector">
                <span class="color-label">Select color</span>
                <input type="text" class="color-input" value="#0066ff" id="chatColorInput">
            </div>
            <?php 
            // Get the first active CHAT meeting for the widget
            $activeBookingId = 0;
            $activeCounselorId = 0;
            
            foreach ($meetings as $meeting) {
                $isToday = date('Y-m-d') == $meeting['booking_date'];
                $sessionTime = strtotime($meeting['booking_date'] . ' ' . $meeting['booking_time']);
                $currentTime = time();
                $timeDiff = $sessionTime - $currentTime;
                $isWithinTimeframe = $timeDiff > -3600 && $timeDiff < 3600;
                
                // Only consider chat sessions, not video sessions
                if ($isToday && ($isWithinTimeframe || $DEBUG_MODE) && $meeting['session_type'] !== 'video') {
                    $activeBookingId = $meeting['booking_id'];
                    $activeCounselorId = $meeting['counselor_id'];
                    break;
                }
            }
            
            // If we're in debug mode and no active chat meeting was found, use the first chat session
            if ($DEBUG_MODE && $activeBookingId == 0 && !empty($meetings)) {
                foreach ($meetings as $meeting) {
                    if ($meeting['session_type'] !== 'video') {
                        $activeBookingId = $meeting['booking_id'];
                        $activeCounselorId = $meeting['counselor_id'];
                        break;
                    }
                }
            }
            ?>
            <a href="chat.php?counselor_id=<?php echo $activeCounselorId; ?>&booking_id=<?php echo $activeBookingId; ?>" 
               class="btn btn-start-chat" id="startChatBtn">
                Start chat
                <span class="arrow-icon">&#10095;</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationsModalLabel">
                        <i class="bi bi-bell-fill me-2"></i>Cancelled Sessions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php 
                    $notifications_result->data_seek(0);
                    if ($notifications_result->num_rows > 0) {
                        while ($notice = $notifications_result->fetch_assoc()): 
                        ?>
                            <div class="alert alert-warning mb-2">
                                <p class="mb-1">
                                    Your session with <?php echo htmlspecialchars($notice['counselor_name']); ?> 
                                    scheduled for <?php echo date('M d, Y', strtotime($notice['booking_date'])); ?> 
                                    at <?php echo date('h:i A', strtotime($notice['booking_time'])); ?> 
                                    has been cancelled.
                                </p>
                            </div>
                        <?php endwhile;
                    } else {
                        echo '<p>No cancelled sessions.</p>';
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Handle Join.chat widget
            const closeButton = document.getElementById('closeJoinChat');
            const joinChatWidget = document.getElementById('joinChatWidget');
            const colorInput = document.getElementById('chatColorInput');
            const startChatBtn = document.getElementById('startChatBtn');
            
            if (closeButton) {
                closeButton.addEventListener('click', function() {
                    joinChatWidget.style.display = 'none';
                });
            }
            
            if (colorInput && startChatBtn) {
                colorInput.addEventListener('input', function() {
                    const color = this.value;
                    startChatBtn.style.backgroundColor = color;
                    
                    // Update the button's href to include the color
                    const startButtonHref = startChatBtn.getAttribute('href').split('&color=')[0];
                    startChatBtn.setAttribute('href', `${startButtonHref}&color=${encodeURIComponent(color)}`);
                    
                    // Also update any in-page chat buttons with the same color
                    const chatButtons = document.querySelectorAll('.btn-chat');
                    chatButtons.forEach(button => {
                        button.style.backgroundColor = color;
                        button.style.borderColor = color;
                    });
                });
            }
            
            function checkChatStatus() {
                const bookingIds = <?php echo json_encode(array_column($meetings, 'booking_id')); ?>;
                
                bookingIds.forEach(async (bookingId) => {
                    try {
                        const response = await fetch(`check_chat_status.php?booking_id=${bookingId}`);
                        const data = await response.json();
                        console.log(data);
                        const cardElement = document.querySelector(`[data-booking-id="${bookingId}"]`);
                        if (cardElement && data.chat_started) {
                            const chatButtonContainer = cardElement.querySelector('.session-info');
                            if (chatButtonContainer && !chatButtonContainer.querySelector('.btn-chat')) {
                                const counselorId = cardElement.dataset.counselorId;
                                const buttonHTML = `
                                    <div class="chat-button-active">
                                        <a href="chat.php?counselor_id=${counselorId}&booking_id=${bookingId}" 
                                           class="btn btn-primary btn-chat">
                                            <i class="bi bi-chat-dots-fill"></i> Start Chat
                                        </a>
                                    </div>
                                `;
                                const infoText = chatButtonContainer.querySelector('.info-text');
                                if (infoText) {
                                    infoText.remove();
                                }
                                chatButtonContainer.insertAdjacentHTML('beforeend', buttonHTML);
                            }
                        }
                    } catch (error) {
                        console.error('Error checking chat status:', error);
                    }
                });
            }

            function checkVideoSessions() {
                const videoButtons = document.querySelectorAll('.btn-video');
                
                videoButtons.forEach(button => {
                    const bookingId = button.id.split('-')[2];
                    
                    fetch(`check_video_status.php?booking_id=${bookingId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.is_active) {
                                button.classList.remove('btn-primary');
                                button.classList.add('btn-warning', 'active-session');
                                button.innerHTML = '<i class="bi bi-camera-video-fill"></i> Join Active Meeting';
                            }
                        })
                        .catch(error => console.error('Error checking video status:', error));
                });
            }

            // Check video sessions every 30 seconds
            setInterval(checkVideoSessions, 30000);
            // Initial video check
            checkVideoSessions();

            // Poll for chat status updates
            setInterval(checkChatStatus, 10000);
        });
    </script>
</body>
</html>