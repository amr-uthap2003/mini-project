<?php
require_once 'db_connection.php';
requireRole('student');

$student_id = $_SESSION['reg_id'];

// Get all bookings for the student
$bookings = getStudentBookings($conn, $student_id);

// Success message if redirected from booking page
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Your session has been booked successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="../student_dashboard.php">BrightMind Student</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                    <a href="student_dashboard.php" class="text-decoration-none text-success">
                        Quick Links
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="profile.php" class="list-group-item list-group-item-action">My Profile</a>
                        <a href="book_session.php" class="list-group-item list-group-item-action">Book Session</a>
                        <a href="my_sessions.php" class="list-group-item list-group-item-action active">My Sessions</a>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <h2>My Sessions</h2>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <div class="card mt-3">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="true">Upcoming</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="false">Past</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab" aria-controls="cancelled" aria-selected="false">Cancelled</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                                <div class="mt-3">
                                    <?php
                                    $upcoming_sessions = array_filter($bookings, function($booking) {
                                        return ($booking['status'] == 'pending' || $booking['status'] == 'approved') && 
                                               ($booking['booking_date'] >= date('Y-m-d'));
                                    });
                                    
                                    if (count($upcoming_sessions) > 0):
                                    ?>
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Counselor</th>
                                                    <th>Duration</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($upcoming_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($session['booking_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($session['counselor_name']); ?></td>
                                                        <td><?php echo $session['duration']; ?> mins</td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            switch($session['status']) {
                                                                case 'pending':
                                                                    $status_class = 'badge bg-warning';
                                                                    break;
                                                                case 'approved':
                                                                    $status_class = 'badge bg-success';
                                                                    break;
                                                                case 'rejected':
                                                                    $status_class = 'badge bg-danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="<?php echo $status_class; ?>"><?php echo ucfirst($session['status']); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($session['status'] == 'pending'): ?>
                                                                <a href="cancel_session.php?id=<?php echo $session['booking_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this session?')">Cancel</a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="alert alert-info mt-3">You have no upcoming sessions.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
                                <div class="mt-3">
                                    <?php
                                    $past_sessions = array_filter($bookings, function($booking) {
                                        return $booking['status'] == 'completed' || 
                                              ($booking['booking_date'] < date('Y-m-d') && $booking['status'] == 'approved');
                                    });
                                    
                                    if (count($past_sessions) > 0):
                                    ?>
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Counselor</th>
                                                    <th>Duration</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($past_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($session['booking_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($session['counselor_name']); ?></td>
                                                        <td><?php echo $session['duration']; ?> mins</td>
                                                        <td><span class="badge bg-secondary">Completed</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="alert alert-info mt-3">You have no past sessions.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                                <div class="mt-3">
                                    <?php
                                    $cancelled_sessions = array_filter($bookings, function($booking) {
                                        return $booking['status'] == 'cancelled' || $booking['status'] == 'rejected';
                                    });
                                    
                                    if (count($cancelled_sessions) > 0):
                                    ?>
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Counselor</th>
                                                    <th>Duration</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cancelled_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($session['booking_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($session['counselor_name']); ?></td>
                                                        <td><?php echo $session['duration']; ?> mins</td>
                                                        <td>
                                                            <?php 
                                                            $status_class = $session['status'] == 'cancelled' ? 'badge bg-secondary' : 'badge bg-danger';
                                                            ?>
                                                            <span class="<?php echo $status_class; ?>"><?php echo ucfirst($session['status']); ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="alert alert-info mt-3">You have no cancelled sessions.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>