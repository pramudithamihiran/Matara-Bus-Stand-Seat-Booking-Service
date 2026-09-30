<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ TAB AUTHENTICATION CHECK ============
requireTabAuth('login.php');

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit();
}

$user_email = $_SESSION['user_email'];

// Check if booking exists and belongs to user
$check_sql = "SELECT id, journey_date, bus_id, seat_numbers, ref_code FROM bookings WHERE id = ? AND customer_email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("is", $booking_id, $user_email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0) {
    echo "<script>
            alert('Booking not found!');
            window.location='my_bookings.php';
          </script>";
    exit();
}

$booking = $check_result->fetch_assoc();
$check_stmt->close();

// Check if booking date is in future
$today = date('Y-m-d');
if ($booking['journey_date'] < $today) {
    echo "<script>
            alert('Cannot cancel past bookings!');
            window.location='my_bookings.php';
          </script>";
    exit();
}

// ============ 1. INSERT INTO booking_cancellations ============
$insert_log_sql = "INSERT INTO booking_cancellations 
                   (booking_id, bus_id, seat_numbers, journey_date, ref_code, cancelled_by) 
                   VALUES (?, ?, ?, ?, ?, ?)";
$insert_log_stmt = $conn->prepare($insert_log_sql);
$cancelled_by = $_SESSION['user_name'] ?? 'Customer';
$insert_log_stmt->bind_param("iissss", $booking_id, $booking['bus_id'], $booking['seat_numbers'], $booking['journey_date'], $booking['ref_code'], $cancelled_by);
$insert_log_stmt->execute();
$insert_log_stmt->close();

// ============ 2. DELETE FROM bookings ============
$delete_sql = "DELETE FROM bookings WHERE id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $booking_id);

if ($delete_stmt->execute()) {
    if ($delete_stmt->affected_rows > 0) {
        header("Location: my_bookings.php?success=cancelled");
        exit();
    } else {
        echo "<script>
                alert('Booking not found or already cancelled!');
                window.location='my_bookings.php';
              </script>";
        exit();
    }
} else {
    echo "<script>
            alert('Error cancelling booking: " . addslashes($conn->error) . "');
            window.location='my_bookings.php';
          </script>";
    exit();
}
$delete_stmt->close();
?>