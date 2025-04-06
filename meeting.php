<?php
session_start();
require_once 'db_connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['reg_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php?error=no_meeting');
    exit();
}

$meeting_id = $_GET['id'];
$user_id = $_SESSION['reg_id'];
$role = $_SESSION['role_name'];

// Different verification for counselors and students
if ($role === 'counselor') {
    $check_query = "SELECT b.*, c.fullname as counselor_name, s.fullname as student_name,
                       c.reg_id as counselor_id, s.reg_id as student_id
                FROM tb_bookings b
                JOIN tb_register c ON b.counselor_id = c.reg_id
                JOIN tb_register s ON b.student_id = s.reg_id
                WHERE b.meeting_link = ? AND b.counselor_id = ? AND b.status = 'approved'";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $meeting_id, $user_id);
} else {
    // Students need permission to join
    $check_query = "SELECT b.*, c.fullname as counselor_name, s.fullname as student_name,
                       c.reg_id as counselor_id, s.reg_id as student_id
                FROM tb_bookings b
                JOIN tb_register c ON b.counselor_id = c.reg_id
                JOIN tb_register s ON b.student_id = s.reg_id
                WHERE b.meeting_link = ? AND b.student_id = ? 
                AND b.status = 'approved' AND b.student_requested = 1";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $meeting_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();

if (!$meeting) {
    if ($role === 'student') {
        header('Location: dashboard.php?error=need_permission');
    } else {
        header('Location: dashboard.php?error=invalid_meeting');
    }
    exit();
}

$display_name = ($role === 'counselor') ? $meeting['counselor_name'] : $meeting['student_name'];
$other_name = ($role === 'counselor') ? $meeting['student_name'] : $meeting['counselor_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrightMind Counseling Session</title>
    <script src='https://meet.jit.si/external_api.js'></script>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100%;
            overflow: hidden;
            background-color: #f0f2f5;
        }
        #meet {
            height: 100vh;
            width: 100%;
            background-color: #000;
        }
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            font-family: Arial, sans-serif;
        }
        .meeting-info {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 1000;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <div class="meeting-info">
        <div>Counselor: <?php echo htmlspecialchars($meeting['counselor_name']); ?></div>
        <div>Student: <?php echo htmlspecialchars($meeting['student_name']); ?></div>
        <div>Date: <?php echo date('Y-m-d', strtotime($meeting['booking_date'])); ?></div>
        <div>Time: <?php echo date('H:i', strtotime($meeting['booking_time'])); ?></div>
    </div>
    <div id="meet">
        <div class="loading">
            Loading meeting room...
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const domain = 'meet.jit.si';
            const options = {
                roomName: 'BrightMind_<?php echo htmlspecialchars($meeting_id); ?>',
                width: '100%',
                height: '100%',
                parentNode: document.querySelector('#meet'),
                userInfo: {
                    displayName: '<?php echo htmlspecialchars($display_name); ?> (<?php echo ucfirst($role); ?>)',
                    email: '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>'
                },
                configOverwrite: {
                    startWithAudioMuted: true,
                    startWithVideoMuted: true,
                    prejoinPageEnabled: false,
                    disableDeepLinking: true
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'desktop', 
                        'fullscreen', 'hangup', 'chat',
                        'settings', 'raisehand', 'videoquality',
                        'tileview', <?php echo $role === 'counselor' ? "'mute-everyone', 'security'" : ''; ?>
                    ],
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_WATERMARK_FOR_GUESTS: false,
                    MOBILE_APP_PROMO: false,
                    DISABLE_JOIN_LEAVE_NOTIFICATIONS: true,
                    DISABLE_VIDEO_BACKGROUND: true
                }
            };

            try {
                const api = new JitsiMeetExternalAPI(domain, options);
                
                api.addEventListener('videoConferenceJoined', () => {
                    console.log('User joined the meeting');
                    document.querySelector('.loading').style.display = 'none';
                });

                api.addEventListener('videoConferenceLeft', () => {
                    window.location.href = 'dashboard.php?meeting=ended';
                });

                <?php if ($role === 'counselor'): ?>
                api.addEventListener('participantJoined', (participant) => {
                    console.log('Student joined:', participant);
                });
                <?php endif; ?>

                api.addEventListener('error', (error) => {
                    console.error('Meeting error:', error);
                    document.querySelector('.loading').innerHTML = 
                        'Error joining meeting. Please refresh the page.';
                });
            } catch (error) {
                console.error('Failed to initialize meeting:', error);
                document.querySelector('.loading').innerHTML = 
                    'Failed to load meeting. Please check your connection and try again.';
            }
        });
    </script>
</body>
</html>