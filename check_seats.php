<?php
// Turn off error reporting for JSON
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

header('Content-Type: application/json');

// ============ GET PARAMETERS ============
$date = isset($_GET['date']) ? $_GET['date'] : '';
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;

// ============ VALIDATION ============
if (empty($date) || $bus_id <= 0) {
    echo json_encode([]);
    exit();
}

// ============ GET BOOKED SEATS ============
$booked_seats = [];

$sql = "SELECT seat_numbers FROM bookings WHERE journey_date = '$date' AND bus_id = '$bus_id' AND status != 'cancelled'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $seats = explode(',', $row['seat_numbers']);
        foreach ($seats as $seat) {
            $clean = trim($seat);
            if ($clean !== '') {
                $booked_seats[] = intval($clean);
            }
        }
    }
}

// Remove duplicates and sort
$booked_seats = array_values(array_unique($booked_seats));
sort($booked_seats);

echo json_encode($booked_seats);
exit();
?>