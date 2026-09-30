<?php
// ============ TURN OFF ERROR DISPLAY (for JSON) ============
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

header('Content-Type: application/json');

// ============ GET PARAMETERS ============
$route = isset($_GET['route']) ? trim($_GET['route']) : '';
$boarding = isset($_GET['boarding']) ? trim($_GET['boarding']) : '';
$dropping = isset($_GET['dropping']) ? trim($_GET['dropping']) : '';
$seats = isset($_GET['seats']) ? intval($_GET['seats']) : 1;

// ============ VALIDATION ============
if (empty($route) || empty($boarding) || empty($dropping)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit();
}

// ============ GET FARE RATE ============
$rate_sql = "SELECT rate_per_km FROM fare_settings LIMIT 1";
$rate_result = $conn->query($rate_sql);
$rate_per_km = $rate_result->fetch_assoc()['rate_per_km'] ?? 10.00;

// ============ GET BOARDING DISTANCE ============
$boarding_sql = "SELECT distance_km FROM route_stops WHERE route_category = ? AND city_name = ? LIMIT 1";
$boarding_stmt = $conn->prepare($boarding_sql);
$boarding_stmt->bind_param("ss", $route, $boarding);
$boarding_stmt->execute();
$boarding_result = $boarding_stmt->get_result();

if ($boarding_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Boarding point not found'
    ]);
    exit();
}
$boarding_distance = floatval($boarding_result->fetch_assoc()['distance_km']);
$boarding_stmt->close();

// ============ GET DROPPING DISTANCE ============
$dropping_sql = "SELECT distance_km FROM route_stops WHERE route_category = ? AND city_name = ? LIMIT 1";
$dropping_stmt = $conn->prepare($dropping_sql);
$dropping_stmt->bind_param("ss", $route, $dropping);
$dropping_stmt->execute();
$dropping_result = $dropping_stmt->get_result();

if ($dropping_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Dropping point not found'
    ]);
    exit();
}
$dropping_distance = floatval($dropping_result->fetch_assoc()['distance_km']);
$dropping_stmt->close();

// ============ CALCULATE DISTANCE ============
$distance = abs($dropping_distance - $boarding_distance);

if ($distance <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Boarding and dropping points cannot be the same'
    ]);
    exit();
}

// ============ CALCULATE FARE ============
$fare_per_seat = $distance * $rate_per_km;
$total_fare = $fare_per_seat * $seats;

// ============ RETURN JSON ============
echo json_encode([
    'success' => true,
    'boarding' => $boarding,
    'dropping' => $dropping,
    'boarding_distance' => $boarding_distance,
    'dropping_distance' => $dropping_distance,
    'distance' => $distance,
    'rate_per_km' => $rate_per_km,
    'fare_per_seat' => round($fare_per_seat, 2),
    'seats' => $seats,
    'total_fare' => round($total_fare, 2)
]);
exit();
?>