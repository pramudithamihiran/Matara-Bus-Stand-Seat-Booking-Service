<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Only Super Admin can block buses
if ($_SESSION['role'] !== 'super_admin') {
    echo "<script>alert('Access Denied! Only Super Admin can block buses.'); window.location='admin_dashboard.php';</script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['block_bus'])) {
    $bus_id = intval($_POST['bus_id']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    
    // ============ VALIDATION ============
    if (empty($bus_id) || empty($start_date) || empty($end_date)) {
        echo "<script>alert('Please fill all fields!'); window.location='admin_dashboard.php';</script>";
        exit();
    }
    
    $today = date('Y-m-d');
    if ($start_date < $today) {
        echo "<script>alert('Start date cannot be in the past!'); window.location='admin_dashboard.php';</script>";
        exit();
    }
    
    if ($end_date < $start_date) {
        echo "<script>alert('End date must be after start date!'); window.location='admin_dashboard.php';</script>";
        exit();
    }
    
    // ============ CHECK IF BUS EXISTS ============
    $check_bus_sql = "SELECT id FROM buses WHERE id = ?";
    $check_bus_stmt = $conn->prepare($check_bus_sql);
    $check_bus_stmt->bind_param("i", $bus_id);
    $check_bus_stmt->execute();
    $check_bus_result = $check_bus_stmt->get_result();
    
    if ($check_bus_result->num_rows == 0) {
        echo "<script>alert('Bus not found!'); window.location='admin_dashboard.php';</script>";
        exit();
    }
    $check_bus_stmt->close();
    
    // ============ GENERATE DATE RANGE ============
    $begin = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end = $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $end);
    
    $inserted = 0;
    $skipped = 0;
    
    foreach ($daterange as $date) {
        $formatted_date = $date->format("Y-m-d");
        
        // ============ CHECK DUPLICATE ============
        $check_sql = "SELECT id FROM bus_unavailable_dates WHERE bus_id = ? AND unavailable_date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $bus_id, $formatted_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $skipped++;
        } else {
            // ============ INSERT USING PREPARED STATEMENT ============
            $sql = "INSERT INTO bus_unavailable_dates (bus_id, unavailable_date) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $bus_id, $formatted_date);
            if ($stmt->execute()) {
                $inserted++;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    // ============ REDIRECT WITH MESSAGE ============
    if ($inserted > 0) {
        echo "<script>alert('$inserted date(s) blocked successfully!" . ($skipped > 0 ? " ($skipped date(s) already blocked)" : "") . "'); window.location='admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('No new dates were blocked. All dates already blocked or invalid.'); window.location='admin_dashboard.php';</script>";
    }
    exit();
}

// If accessed directly without POST
header("Location: admin_dashboard.php");
exit();
?>