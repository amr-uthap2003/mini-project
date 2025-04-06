<?php
require_once 'db_connection.php';


$student_id = $_SESSION['reg_id'];
$error_message = '';
$success_message = '';

// Get counselors
$counselors_query = $conn->query("
    SELECT r.reg_id, r.username, r.email 
    FROM tb_register r 
    JOIN tb_roles ro ON r.role_id = ro.role_id 
    WHERE ro.role_name = 'counselor' AND r.status = 'active'
    ORDER BY r.username
");
$counselors = $counselors_query->fetch_all(MYSQLI_ASSOC);

// Define time slots

// Define fixed time slots
$time_slots = [
    ['time' => '09:00 - 10:00', 'label' => '9:00 AM - 10:00 AM'],
    ['time' => '10:00 - 11:00', 'label' => '10:00 AM - 11:00 AM'],
    ['time' => '11:00 - 12:00', 'label' => '11:00 AM - 12:00 PM'],
    ['time' => '13:00 - 14:00', 'label' => '1:00 PM - 2:00 PM'],
    ['time' => '14:00 - 15:00', 'label' => '2:00 PM - 3:00 PM'],
    ['time' => '15:00 - 16:00', 'label' => '3:00 PM - 4:00 PM'],
    ['time' => '16:00 - 17:00', 'label' => '4:00 PM - 5:00 PM'],
    ['time' => '17:00 - 18:00', 'label' => '5:00 PM - 6:00 PM'],
    ['time' => '18:00 - 19:00', 'label' => '6:00 PM - 7:00 PM'],
    ['time' => '19:00 - 20:00', 'label' => '7:00 PM - 8:00 PM'],
    ['time' => '20:00 - 21:00', 'label' => '8:00 PM - 9:00 PM'],
    ['time' => '21:00 - 22:00', 'label' => '9:00 PM - 10:00 PM'],
    ['time' => '22:00 - 23:00', 'label' => '10:00 PM - 11:00 PM'],
    ['time' => '23:00 - 00:00', 'label' => '11:00 PM - 12:00 AM'],
    ['time' => '00:00 - 01:00', 'label' => '12:00 AM - 1:00 AM'],
    ['time' => '01:00 - 02:00', 'label' => '1:00 AM - 2:00 AM'],
    ['time' => '02:00 - 03:00', 'label' => '2:00 AM - 3:00 AM'],
    ['time' => '03:00 - 04:00', 'label' => '3:00 AM - 4:00 AM']
];

// ... rest of the code remains the same ...

$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $counselor_id = filter_input(INPUT_POST, 'counselor_id', FILTER_VALIDATE_INT);
    $booking_date = filter_input(INPUT_POST, 'booking_date', FILTER_SANITIZE_STRING);
    $booking_time = filter_input(INPUT_POST, 'booking_time', FILTER_SANITIZE_STRING);
    $duration = filter_input(INPUT_POST, 'duration', FILTER_VALIDATE_INT);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $session_type = filter_input(INPUT_POST, 'session_type', FILTER_SANITIZE_STRING);

    if (!$counselor_id || !$booking_date || !$booking_time || !$duration || !$reason || !$session_type) {
        $error_message = 'All fields are required';
    } else {
        $result = createBooking($conn, $student_id, $counselor_id, $booking_date, $booking_time, $duration, $reason, $session_type);
        if ($result['success']) {
            header("Location: my_sessions.php?success=1");
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Counseling Session - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .schedule-cell {
            height: 45px;
            width: 90px;
            text-align: center;
            vertical-align: middle !important;
            padding: 5px !important;
            font-size: 0.9rem;
            border: 1px solid #dee2e6;
        }
        .time-slot {
            background-color: #f8f9fa;
            font-weight: 600;
            width: 60px;
        }
        .slot-cell {
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        .slot-cell.available {
            background-color: #198754;
            color: white;
        }
        .slot-cell.unavailable {
            background-color: #dc3545;
            color: white;
            opacity: 0.5;
        }
        .slot-cell:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .schedule-table {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
            margin: 0;
        }
        .schedule-header {
            background-color: #198754;
            color: white;
            font-weight: 600;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="student_dashboard.php">BrightMind Student</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="form-container">
            <h2 class="mb-4">Book a Counseling Session</h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Available Time Slots</h5>
                    <select class="form-select form-select-sm w-auto" id="counselor_id" name="counselor_id" required>
                        <option value="">Select Counselor</option>
                        <?php foreach ($counselors as $counselor): ?>
                            <option value="<?php echo $counselor['reg_id']; ?>">
                                <?php echo htmlspecialchars($counselor['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="card-body p-2">
                    <table class="table schedule-table mb-0">
                        <thead>
                            <tr class="schedule-header">
                                <th class="schedule-cell">Time</th>
                                <?php foreach ($days as $day): ?>
                                    <th class="schedule-cell"><?php echo $day; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="availabilityTable">
                            <?php foreach ($time_slots as $slot): ?>
                                <tr>
                                    <td class="schedule-cell time-slot"><?php echo $slot['label']; ?></td>
                                    <?php foreach ($days as $index => $day): ?>
                                        <td class="schedule-cell slot-cell" 
                                            data-time="<?php echo $slot['time']; ?>" 
                                            data-day="<?php echo $index + 1; ?>">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <input type="hidden" name="counselor_id" id="selected_counselor_id">
                <div class="col-md-6">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" id="booking_date" name="booking_date" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-control" id="booking_time" name="booking_time" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Duration</label>
                    <select class="form-select" name="duration" required>
                        <option value="30">30 minutes</option>
                        <option value="60" selected>1 hour</option>
                        <option value="90">1.5 hours</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session Type</label>
                    <select class="form-select" name="session_type" required>
                        <option value="video">Video Call</option>
                        <option value="text">Text Chat</option>
                        <option value="inperson">In-Person</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Reason for Session</label>
                    <textarea class="form-control" name="reason" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">Book Session</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('counselor_id').addEventListener('change', function() {
            const counselorId = this.value;
            document.getElementById('selected_counselor_id').value = counselorId;
            if (!counselorId) return;

            fetch(`get_counselor_availability.php?counselor_id=${counselorId}`)
                .then(response => response.json())
                .then(data => {
                    const slots = document.querySelectorAll('.slot-cell');
                    slots.forEach(slot => {
                        const time = slot.dataset.time;
                        const day = slot.dataset.day;
                        const isAvailable = data.some(avail => 
                            avail.day_of_week == day && 
                            avail.start_time <= time && 
                            avail.end_time > time
                        );

                        slot.classList.remove('available', 'unavailable');
                        slot.classList.add(isAvailable ? 'available' : 'unavailable');
                        slot.textContent = isAvailable ? 'Available' : '-';

                        if (isAvailable) {
                            slot.onclick = function() {
                                const date = getNextWeekday(parseInt(day));
                                document.getElementById('booking_date').value = date;
                                document.getElementById('booking_time').value = time;
                                this.style.backgroundColor = '#0d6efd';
                                this.textContent = 'Selected';
                            };
                        } else {
                            slot.onclick = null;
                        }
                    });
                })
                .catch(error => console.error('Error:', error));
        });

        function getNextWeekday(dayNum) {
            const today = new Date();
            const currentDay = today.getDay() || 7;
            let daysToAdd = dayNum - currentDay;
            if (daysToAdd <= 0) daysToAdd += 7;
            const targetDate = new Date(today);
            targetDate.setDate(today.getDate() + daysToAdd);
            return targetDate.toISOString().split('T')[0];
        }
    </script>
</body>
</html>