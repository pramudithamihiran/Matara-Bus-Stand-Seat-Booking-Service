<?php
include 'config.php';
require_once 'tab_auth.php';

requireTabAuth('login.php');

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// ============ PHPMailer ============
require 'lib/PHPMailer/Exception.php';
require 'lib/PHPMailer/PHPMailer.php';
require 'lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============ VARIABLES ============
$bus_id = 0;
$seats = '';
$date = '';
$pickup = '';
$dropoff = '';
$customer_email = '';
$customer_name = '';
$ref_code = '';
$receipt_bus_name = "SUPER COACH";
$receipt_bus_contact = '';      // ✅ NEW: Bus contact number
$booking_id = 0;
$error = '';

$route_name = '';
$distance_km = 0;
$fare_per_seat = 0;
$total_fare = 0;
$seat_count = 0;

if (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
    $customer_email = $_SESSION['user_email'];
}
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    $customer_name = $_SESSION['user_name'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $bus_id = isset($_POST['bus_id']) ? intval($_POST['bus_id']) : 0;
    $seats = isset($_POST['selected_seats']) ? trim($_POST['selected_seats']) : '';
    $date = isset($_POST['booking_date']) ? trim($_POST['booking_date']) : '';
    $pickup = isset($_POST['pickup']) ? trim($_POST['pickup']) : '';
    $dropoff = isset($_POST['dropoff']) ? trim($_POST['dropoff']) : '';
    
    $route_name = isset($_POST['route_name']) ? trim($_POST['route_name']) : '';
    $distance_km = isset($_POST['distance_km']) ? floatval($_POST['distance_km']) : 0;
    $fare_per_seat = isset($_POST['fare_per_seat']) ? floatval($_POST['fare_per_seat']) : 0;
    $total_fare = isset($_POST['total_fare']) ? floatval($_POST['total_fare']) : 0;
    
    if (!empty($seats)) {
        $seat_array = explode(',', $seats);
        $seat_count = count($seat_array);
    }
    
    if ($bus_id <= 0) {
        $error = "Invalid bus selection!";
    } elseif (empty($seats)) {
        $error = "No seats selected!";
    } elseif (empty($date)) {
        $error = "Travel date is required!";
    } elseif (empty($pickup)) {
        $error = "Pickup location is required!";
    } elseif (empty($dropoff)) {
        $error = "Dropoff location is required!";
    } elseif (empty($customer_email)) {
        $error = "Customer email is required! Please login again.";
    }
    
    // ✅ UPDATED: Get bus_name AND contact_no
    if (empty($error) && $bus_id > 0) {
        $bus_query = $conn->prepare("SELECT bus_name, contact_no FROM buses WHERE id = ?");
        $bus_query->bind_param("i", $bus_id);
        $bus_query->execute();
        $bus_result = $bus_query->get_result();
        
        if ($bus_result && $bus_result->num_rows > 0) {
            $bus_data = $bus_result->fetch_assoc();
            $receipt_bus_name = $bus_data['bus_name'];
            $receipt_bus_contact = $bus_data['contact_no'] ?? 'N/A';  // ✅ NEW: Get contact
        } else {
            $error = "Bus not found!";
        }
        $bus_query->close();
    }
    
    if (empty($error)) {
        $ref_code = "BK-" . strtoupper(substr(md5(time() . rand()), 0, 5));
        
        $check_ref = $conn->prepare("SELECT id FROM bookings WHERE ref_code = ?");
        $check_ref->bind_param("s", $ref_code);
        $check_ref->execute();
        $check_ref_result = $check_ref->get_result();
        
        while ($check_ref_result->num_rows > 0) {
            $ref_code = "BK-" . strtoupper(substr(md5(time() . rand()), 0, 5));
            $check_ref->execute();
            $check_ref_result = $check_ref->get_result();
        }
        $check_ref->close();
    }
    
    if (empty($error)) {
        $col_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'customer_email'");
        $email_field = ($col_check && $col_check->num_rows > 0) ? 'customer_email' : 'email';
        
        $name_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'customer_name'");
        $has_name_field = ($name_check && $name_check->num_rows > 0);
        
        $drop_col_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'dropoff_location'");
        $drop_field = ($drop_col_check && $drop_col_check->num_rows > 0) ? 'dropoff_location' : 'drop_location';
        
        $sql = "INSERT INTO bookings (
                    bus_id, 
                    seat_numbers, 
                    journey_date, 
                    pickup_location, 
                    $drop_field, 
                    $email_field, 
                    ref_code,
                    boarding_point,
                    dropping_point,
                    distance_km,
                    fare_per_seat,
                    total_fare";
        
        if ($has_name_field) {
            $sql .= ", customer_name";
        }
        $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
        
        if ($has_name_field) {
            $sql .= ", ?";
        }
        $sql .= ")";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            if ($has_name_field) {
                $stmt->bind_param("issssssssddds", 
                    $bus_id, $seats, $date, $pickup, $dropoff, 
                    $customer_email, $ref_code, $pickup, $dropoff, 
                    $distance_km, $fare_per_seat, $total_fare, $customer_name
                );
            } else {
                $stmt->bind_param("issssssssddd", 
                    $bus_id, $seats, $date, $pickup, $dropoff, 
                    $customer_email, $ref_code, $pickup, $dropoff, 
                    $distance_km, $fare_per_seat, $total_fare
                );
            }
            
            if ($stmt->execute()) {
                $booking_id = $stmt->insert_id;
            } else {
                $error = "Execute error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    if (empty($error) && $booking_id > 0) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'pramudithamihiran@gmail.com';
            $mail->Password   = 'dllemhcpkwapwydf';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('pramudithamihiran@gmail.com', $receipt_bus_name . ' Bus Service');
            
            // ============ CUSTOMER EMAIL ============
            $mail->clearAddresses();
            $mail->addAddress($customer_email);
            $mail->isHTML(true);
            $mail->Subject = 'Booking Confirmation - ' . $ref_code;
            $mail->Body    = "
                <div style='font-family:Arial,sans-serif; max-width:500px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:16px; background:#fafafa;'>
                    <div style='text-align:center; padding-bottom:15px; border-bottom:2px solid #003580;'>
                        <h2 style='color:#003580; margin:0;'>" . htmlspecialchars($receipt_bus_name) . "</h2>
                        <p style='color:#888; margin:5px 0 0;'>Matara Bus Service</p>
                    </div>
                    <div style='padding:15px 0;'>
                        <h4 style='color:#28a745; text-align:center;'>✓ Booking Confirmed!</h4>
                        <hr style='border:1px dashed #eee;'>
                        <p><strong>Reference Code:</strong> <span style='font-size:20px; color:#003580; font-weight:bold;'>$ref_code</span></p>
                        <p><strong>Passenger:</strong> " . htmlspecialchars($customer_name) . "</p>
                        <p><strong>Travel Date:</strong> $date</p>
                        <p><strong>Seats:</strong> $seats ($seat_count seats)</p>
                        <p><strong>Route:</strong> $pickup → $dropoff</p>
                        
                        <!-- ✅ NEW: Contact Bus Number in Customer Email -->
                        <div style='background:#e8f0fe; border-left:4px solid #003580; padding:12px 15px; border-radius:8px; margin:15px 0;'>
                            <p style='margin:0; color:#003580; font-weight:bold; font-size:13px;'>📞 CONTACT BUS</p>
                            <p style='margin:5px 0 0; font-size:18px; color:#003580; font-weight:bold;'>" . htmlspecialchars($receipt_bus_contact) . "</p>
                            <p style='margin:5px 0 0; font-size:11px; color:#666;'>Call this number for any inquiries about your journey</p>
                        </div>
                        
                        <hr style='border:1px dashed #eee;'>
                        <p><strong>Distance:</strong> " . number_format($distance_km, 1) . " km</p>
                        <p><strong>Fare per seat:</strong> Rs. " . number_format($fare_per_seat, 2) . "</p>
                        <p style='font-size:18px; color:#28a745;'><strong>Total Fare: Rs. " . number_format($total_fare, 2) . "</strong></p>
                        <hr style='border:1px dashed #eee;'>
                        <p style='font-size:12px; color:#888; text-align:center;'>Please present this confirmation at the boarding point.</p>
                    </div>
                    <div style='text-align:center; padding-top:15px; border-top:1px solid #eee; font-size:12px; color:#aaa;'>
                        <p>Thank you for choosing " . htmlspecialchars($receipt_bus_name) . "</p>
                    </div>
                </div>
            ";
            $mail->send();

            // ============ ADMIN EMAIL ============
            $mail->clearAddresses();
            $mail->addAddress('pramudithamihiran@gmail.com');
            $mail->Subject = 'New Booking Alert! - ' . $ref_code;
            $mail->Body    = "
                <div style='font-family:Arial,sans-serif; max-width:500px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:16px; background:#f9f9f9;'>
                    <h2 style='color:#003580; text-align:center;'>🚌 NEW BOOKING RECEIVED</h2>
                    <hr style='border:1px dashed #ccc;'>
                    <p><strong>Bus:</strong> " . htmlspecialchars($receipt_bus_name) . " (ID: $bus_id)</p>
                    <p><strong>Ref Code:</strong> <span style='color:#003580; font-weight:bold;'>$ref_code</span></p>
                    <p><strong>Passenger:</strong> " . htmlspecialchars($customer_name) . "</p>
                    <p><strong>Email:</strong> $customer_email</p>
                    <p><strong>Date:</strong> $date</p>
                    <p><strong>Seats:</strong> <span style='color:#28a745; font-weight:bold;'>$seats</span></p>
                    <p><strong>Route:</strong> $pickup → $dropoff</p>
                    
                    <!-- ✅ NEW: Contact Number in Admin Email -->
                    <p><strong>Bus Contact:</strong> <span style='color:#003580; font-weight:bold;'>" . htmlspecialchars($receipt_bus_contact) . "</span></p>
                    
                    <p><strong>Distance:</strong> " . number_format($distance_km, 1) . " km</p>
                    <p><strong>Total Fare:</strong> <span style='color:#28a745; font-weight:bold;'>Rs. " . number_format($total_fare, 2) . "</span></p>
                    <hr style='border:1px dashed #ccc;'>
                    <p style='font-size:12px; color:#888;'>Booking ID: $booking_id</p>
                </div>
            ";
            $mail->send();

        } catch (Exception $e) {
            // Email error ignored
        }
    }
    
    if (!empty($error)) {
        echo "<script>
                alert('" . addslashes($error) . "');
                window.location.href = 'select_seats.php?bus_id=$bus_id&date=$date';
              </script>";
        exit();
    }
    
} else {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($receipt_bus_name) ?> | Booking Success</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #0f0022 0%, #1a0033 50%, #0a0018 100%) !important;
            background-attachment: fixed !important;
            padding-top: 90px;
            padding-bottom: 40px;
            min-height: 100vh;
            color: #e0e0e0;
        }
        
        .main-wrapper {
            display: flex; 
            justify-content: center; 
            align-items: flex-start; 
            min-height: calc(100vh - 120px); 
            padding: 20px 15px; 
        }
        
        /* ===== RECEIPT CARD ===== */
        .receipt { 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            width: 100%; 
            max-width: 480px; 
            padding: 30px 28px; 
            border-radius: 24px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 183, 0, 0.2);
            border-top: 6px solid #ffb700;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .receipt .icon {
            font-size: 55px;
            color: #28a745;
            margin-bottom: 8px;
            filter: drop-shadow(0 0 20px rgba(40, 167, 69, 0.4));
        }
        
        .receipt h3 { 
            color: #ffffff; 
            margin-bottom: 3px; 
            text-transform: uppercase;
            font-weight: 700;
            font-size: 20px;
            letter-spacing: 0.5px;
        }
        
        .receipt .bus-sub {
            color: #b0b0b0;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        /* ===== SUCCESS MESSAGE ===== */
        .success-msg { 
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80; 
            padding: 10px 20px; 
            border-radius: 12px; 
            margin-bottom: 18px; 
            font-weight: 700; 
            font-size: 13px;
            letter-spacing: 0.5px;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .success-msg i {
            margin-right: 8px;
        }
        
        /* ===== JOURNEY DETAILS ===== */
        .receipt-details { 
            text-align: left; 
            background: rgba(15, 0, 34, 0.6);
            padding: 16px 18px; 
            border-radius: 14px; 
            margin-bottom: 14px; 
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .detail-row { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 8px; 
            font-size: 12.5px; 
            border-bottom: 1px dashed rgba(255, 183, 0, 0.1);
            padding-bottom: 6px; 
            gap: 10px;
        }
        
        .detail-row:last-child { 
            border-bottom: none; 
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .label { 
            color: #b0b0b0; 
            font-weight: 500;
            white-space: nowrap;
        }
        
        .label i {
            color: #ffb700;
            margin-right: 5px;
        }
        
        .value { 
            color: #ffffff; 
            font-weight: 600; 
            text-align: right;
            word-break: break-word;
        }
        
        .value.highlight {
            color: #ffb700;
        }
        
        /* ===== CONTACT BOX (NEW) ===== */
        .contact-box {
            background: rgba(74, 144, 226, 0.08);
            border-left: 4px solid #4a90e2;
            border: 1px solid rgba(74, 144, 226, 0.2);
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 14px;
            text-align: left;
        }
        
        .contact-box h4 {
            color: #4a90e2;
            font-size: 12px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
        
        .contact-box h4 i {
            color: #4a90e2;
            margin-right: 5px;
        }
        
        .contact-box p {
            font-size: 13px;
            color: #e0e0e0;
            margin: 0;
        }
        
        .contact-box a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
        }
        
        .contact-box a:hover {
            color: #ffb700;
            text-decoration: underline;
        }
        
        .contact-box i.fa-phone {
            color: #4a90e2;
            margin-right: 6px;
        }
        
        /* ===== FARE BOX ===== */
        .fare-box {
            background: rgba(255, 183, 0, 0.08);
            border-left: 4px solid #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.2);
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            text-align: left;
        }
        
        .fare-box h4 {
            color: #ffb700;
            font-size: 12px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
        
        .fare-box h4 i {
            color: #ffb700;
            margin-right: 5px;
        }
        
        .fare-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12.5px;
        }
        
        .fare-row:last-child {
            margin-bottom: 0;
        }
        
        .fare-row .fare-label {
            color: #b0b0b0;
        }
        
        .fare-row .fare-value {
            font-weight: 600;
            color: #ffb700;
        }
        
        .fare-divider {
            border: none;
            border-top: 1px dashed rgba(255, 183, 0, 0.3);
            margin: 8px 0;
        }
        
        .fare-row.total-row .fare-label {
            font-weight: 600;
            color: #ffb700;
            font-size: 13px;
        }
        
        .fare-row.total-row .fare-value {
            color: #4ade80;
            font-size: 17px;
            font-weight: 700;
        }
        
        /* ===== REFERENCE BOX ===== */
        .ref-box { 
            background: rgba(255, 183, 0, 0.1);
            border: 2px dashed rgba(255, 183, 0, 0.5);
            padding: 14px; 
            border-radius: 14px; 
            margin-bottom: 20px; 
        }
        
        .ref-box .ref-label {
            font-size: 10px; 
            color: #b0b0b0; 
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }
        
        .ref-box .ref-code {
            font-weight: 700; 
            color: #ffb700; 
            font-size: 24px; 
            letter-spacing: 2px;
            margin-top: 4px;
        }
        
        /* ===== BUTTONS ===== */
        .btn-container { 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
        }
        
        .view-btn {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            border: none;
            width: 100%;
            padding: 13px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .print-btn { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            border: 2px solid rgba(255, 183, 0, 0.4);
            width: 100%; 
            padding: 13px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 14px; 
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .print-btn:hover { 
            background: rgba(255, 183, 0, 0.25);
            border-color: #ffb700;
            transform: translateY(-2px);
        }
        
        .home-btn { 
            background: transparent; 
            color: #b0b0b0; 
            border: 2px solid rgba(255, 183, 0, 0.2); 
            width: 100%; 
            padding: 13px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 14px; 
            text-decoration: none; 
            display: flex; 
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            text-align: center; 
            font-family: 'Poppins', sans-serif;
        }
        
        .home-btn:hover { 
            color: #ffb700;
            border-color: #ffb700;
            transform: translateY(-2px);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .receipt {
                padding: 25px 20px;
            }
            .ref-box .ref-code {
                font-size: 20px;
            }
            .receipt h3 {
                font-size: 18px;
            }
            .detail-row {
                font-size: 12px;
            }
        }
        
        /* ===== PRINT ===== */
        @media print { 
            .btn-container, header, .main-wrapper .icon { 
                display: none !important; 
            }
            .main-wrapper { 
                margin-top: 0; 
                padding: 0; 
            }
            body { 
                background: white !important; 
                padding: 0; 
                color: #333;
            } 
            .receipt { 
                box-shadow: none !important; 
                border: 1px solid #ddd; 
                margin: auto; 
                background: white !important;
                color: #333 !important;
                border-top: 6px solid #ffb700;
            } 
            .receipt h3 { color: #003580 !important; }
            .value { color: #333 !important; }
            .label { color: #666 !important; }
            .ref-box { background: #f8f9fa !important; border-color: #003580 !important; }
            .ref-box .ref-code { color: #003580 !important; }
            .receipt-details { background: #f8f9fa !important; }
            .contact-box { background: #f0f6ff !important; border-color: #003580 !important; }
            .contact-box h4 { color: #003580 !important; }
            .contact-box a { color: #003580 !important; }
            .fare-box { background: #f8f9fa !important; }
            .fare-row .fare-value { color: #003580 !important; }
            .fare-row.total-row .fare-value { color: #28a745 !important; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-wrapper">
        <div class="receipt">
            
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h3><?= htmlspecialchars($receipt_bus_name); ?></h3>
            <p class="bus-sub">Matara Bus Service</p>
            
            <div class="success-msg">
                <i class="fas fa-check"></i> BOOKING CONFIRMED
            </div>
            
            <!-- ===== JOURNEY DETAILS ===== -->
            <div class="receipt-details">
                <div class="detail-row">
                    <span class="label"><i class="fas fa-calendar-day"></i> Journey Date</span>
                    <span class="value"><?= htmlspecialchars($date); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-chair"></i> Seats</span>
                    <span class="value highlight"><?= htmlspecialchars($seats); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> From</span>
                    <span class="value"><?= htmlspecialchars($pickup); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-flag-checkered"></i> To</span>
                    <span class="value"><?= htmlspecialchars($dropoff); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value" style="font-size:11px;"><?= htmlspecialchars($customer_email); ?></span>
                </div>
            </div>
            
            <!-- ===== CONTACT BOX (NEW) ===== -->
            <?php if (!empty($receipt_bus_contact) && $receipt_bus_contact !== 'N/A'): ?>
            <div class="contact-box">
                <h4><i class="fas fa-phone-alt"></i> Contact Bus</h4>
                <p>
                    <i class="fas fa-phone"></i> 
                    <a href="tel:<?= htmlspecialchars($receipt_bus_contact) ?>">
                        <?= htmlspecialchars($receipt_bus_contact) ?>
                    </a>
                </p>
                <p style="font-size:11px; color:#888; margin-top:5px;">
                    Call this number for any inquiries about your journey
                </p>
            </div>
            <?php endif; ?>
            
            <!-- ===== FARE BREAKDOWN ===== -->
            <?php if ($distance_km > 0): ?>
            <div class="fare-box">
                <h4><i class="fas fa-calculator"></i> Fare Breakdown</h4>
                <div class="fare-row">
                    <span class="fare-label">Distance:</span>
                    <span class="fare-value"><?= number_format($distance_km, 1) ?> km</span>
                </div>
                <div class="fare-row">
                    <span class="fare-label">Fare per seat:</span>
                    <span class="fare-value">Rs. <?= number_format($fare_per_seat, 2) ?></span>
                </div>
                <div class="fare-row">
                    <span class="fare-label">Seats (<?= $seat_count ?>):</span>
                    <span class="fare-value">× <?= $seat_count ?></span>
                </div>
                <hr class="fare-divider">
                <div class="fare-row total-row">
                    <span class="fare-label">Total Fare:</span>
                    <span class="fare-value">Rs. <?= number_format($total_fare, 2) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== REFERENCE CODE ===== -->
            <div class="ref-box">
                <div class="ref-label">REFERENCE CODE</div>
                <div class="ref-code"><?= htmlspecialchars($ref_code); ?></div>
            </div>

            <!-- ===== BUTTONS ===== -->
            <div class="btn-container">
                <a href="booking_details.php?ref=<?= $ref_code ?>" class="view-btn">
                    <i class="fas fa-eye"></i> View Booking Details
                </a>
                <button class="print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <a href="dashboard.php" class="home-btn">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>
            
        </div>
    </div>

</body>
</html>