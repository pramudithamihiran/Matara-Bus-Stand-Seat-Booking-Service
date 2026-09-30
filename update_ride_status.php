<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ CONDUCTOR LOGIN CHECK ============
if (!isset($_SESSION['conductor_logged_in']) || $_SESSION['conductor_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

if (!isTabAuthenticated()) {
    header("Location: admin_login.php");
    exit();
}

$bus_id = $_SESSION['bus_id'];
$today = date('Y-m-d');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    header("Location: conductor_dashboard.php");
    exit();
}

// ============ UPDATE RIDE STATUS ============
if ($action === 'start') {
    $sql = "UPDATE buses 
            SET ride_status = 'active', 
                last_ride_date = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $today, $bus_id);
    
    if ($stmt->execute()) {
        header("Location: conductor_dashboard.php?msg=started");
    } else {
        header("Location: conductor_dashboard.php?msg=error");
    }
    $stmt->close();
    
} elseif ($action === 'complete') {
    $sql = "UPDATE buses 
            SET ride_status = 'completed', 
                ride_completed_date = ?,
                last_ride_date = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $today, $today, $bus_id);
    
    if ($stmt->execute()) {
        header("Location: conductor_dashboard.php?msg=completed");
    } else {
        header("Location: conductor_dashboard.php?msg=error");
    }
    $stmt->close();
} else {
    header("Location: conductor_dashboard.php");
}

exit();
?>