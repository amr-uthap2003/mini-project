<?php
require_once 'db_connection.php';

// Define fixed time slots
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

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .schedule-cell {
            width: 150px;
            height: 60px;
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
        }
        .time-slot {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        .available {
            background-color: #d4edda;
            color: #155724;
        }
        .unavailable {
            background-color: #f8f9fa;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Counseling Schedule</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th class="text-center">Time</th>
                                <?php foreach ($days as $day): ?>
                                    <th class="text-center"><?php echo $day; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_slots as $slot): ?>
                                <tr>
                                    <td class="schedule-cell time-slot">
                                        <?php echo $slot['label']; ?>
                                    </td>
                                    <?php foreach ($days as $day): ?>
                                        <td class="schedule-cell available">
                                            Available
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>