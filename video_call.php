<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id'])) {
    header("Location: login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$user_id = $_SESSION['reg_id'];
$is_counselor = $_SESSION['role_id'] == 3;

// Verify booking access
$booking_query = "SELECT b.*, s.fullname as student_name, c.fullname as counselor_name, cl.meeting_link 
                 FROM tb_bookings b 
                 JOIN tb_register s ON b.student_id = s.reg_id 
                 JOIN tb_register c ON b.counselor_id = c.reg_id
                 LEFT JOIN tb_classes cl ON b.counselor_id = cl.counselor_id
                 WHERE b.booking_id = ? AND (b.student_id = ? OR b.counselor_id = ?)";

$stmt = $conn->prepare($booking_query);
$stmt->bind_param("iii", $booking_id, $user_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: dashboard.php");
    exit();
}

// Update meeting status
if ($is_counselor && isset($_POST['action'])) {
    $action = $_POST['action'];
    $student_id = $_POST['student_id'];
    
    $update_query = "UPDATE tb_bookings SET meeting_status = ? WHERE booking_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $action, $booking_id);
    $stmt->execute();
    
    exit(json_encode(['success' => true]));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Call - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .video-container {
            height: calc(100vh - 100px);
            background: #000;
            position: relative;
        }
        #localVideo, #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        #localVideo {
            position: absolute;
            width: 200px;
            height: 150px;
            top: 20px;
            right: 20px;
            border: 2px solid #fff;
            border-radius: 8px;
            z-index: 1;
        }
        .controls {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 10px;
        }
        .waiting-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            z-index: 1000;
        }
        .names-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .name-tag {
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-control {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-dark">
    <div class="container-fluid p-0">
        <?php if (!$is_counselor): ?>
        <div id="waitingScreen" class="waiting-screen">
            <h3>Waiting for counselor's permission...</h3>
            <div class="spinner-border text-light mt-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="names-container">
            <div class="name-tag">
                <i class="bi bi-person-video3"></i>
                <span><?php echo htmlspecialchars($booking['counselor_name']); ?></span>
            </div>
            <div class="name-tag">
                <i class="bi bi-person"></i>
                <span><?php echo htmlspecialchars($booking['student_name']); ?></span>
            </div>
        </div>

        <div class="video-container">
            <video id="remoteVideo" autoplay playsinline></video>
            <video id="localVideo" autoplay playsinline muted></video>
        </div>

        <div class="controls">
            <?php if ($is_counselor): ?>
            <button id="allowBtn" class="btn btn-success me-2">
                <i class="bi bi-check-lg"></i> Allow Student
            </button>
            <?php endif; ?>
            <button id="muteBtn" class="btn btn-light btn-control me-2">
                <i class="bi bi-mic-fill"></i>
            </button>
            <button id="videoBtn" class="btn btn-light btn-control me-2">
                <i class="bi bi-camera-video-fill"></i>
            </button>
            <button id="endCallBtn" class="btn btn-danger btn-control" onclick="window.location.href='class_management.php'">
                <i class="bi bi-telephone-x-fill"></i>
            </button>
        </div>
    </div>

    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    <script>
        const isCounselor = <?php echo $is_counselor ? 'true' : 'false'; ?>;
        const bookingId = <?php echo $booking_id; ?>;
        const studentId = <?php echo $booking['student_id']; ?>;
        let localStream;
        let isAudioMuted = false;
        let isVideoMuted = false;

        async function startVideoCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: true
                });
                
                const localVideo = document.getElementById('localVideo');
                localVideo.srcObject = localStream;

                setupControls();

            } catch (error) {
                console.error('Error accessing media devices:', error);
                alert('Please allow camera and microphone access to join the video call.');
            }
        }

        function setupControls() {
            const muteBtn = document.getElementById('muteBtn');
            const videoBtn = document.getElementById('videoBtn');

            muteBtn.addEventListener('click', () => {
                if (localStream) {
                    const audioTrack = localStream.getAudioTracks()[0];
                    isAudioMuted = !isAudioMuted;
                    audioTrack.enabled = !isAudioMuted;
                    muteBtn.innerHTML = isAudioMuted ? 
                        '<i class="bi bi-mic-mute-fill"></i>' : 
                        '<i class="bi bi-mic-fill"></i>';
                    muteBtn.classList.toggle('btn-danger', isAudioMuted);
                    muteBtn.classList.toggle('btn-light', !isAudioMuted);
                }
            });

            videoBtn.addEventListener('click', () => {
                if (localStream) {
                    const videoTrack = localStream.getVideoTracks()[0];
                    isVideoMuted = !isVideoMuted;
                    videoTrack.enabled = !isVideoMuted;
                    videoBtn.innerHTML = isVideoMuted ? 
                        '<i class="bi bi-camera-video-off-fill"></i>' : 
                        '<i class="bi bi-camera-video-fill"></i>';
                    videoBtn.classList.toggle('btn-danger', isVideoMuted);
                    videoBtn.classList.toggle('btn-light', !isVideoMuted);
                }
            });

            document.getElementById('endCallBtn').addEventListener('click', () => {
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                }
            });
        }

        if (isCounselor) {
            startVideoCall();
            
            document.getElementById('allowBtn').addEventListener('click', function() {
                fetch('video_call.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=allowed&booking_id=${bookingId}&student_id=${studentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.disabled = true;
                        this.innerHTML = '<i class="bi bi-check-lg"></i> Student Allowed';
                    }
                });
            });
        } else {
            checkPermission();
        }

        function checkPermission() {
            fetch(`check_permission.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'allowed') {
                        document.getElementById('waitingScreen').style.display = 'none';
                        startVideoCall();
                    } else {
                        setTimeout(checkPermission, 3000);
                    }
                });
        }
    </script>
</body>
</html>