<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ CHECK IF USER IS LOGGED IN ============
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
$booking_id = 0;
$error = '';

// ============ GET USER DETAILS ============
if (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
    $customer_email = $_SESSION['user_email'];
}
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    $customer_name = $_SESSION['user_name'];
}

// ============ PROCESS POST DATA ============
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $bus_id = isset($_POST['bus_id']) ? intval($_POST['bus_id']) : 0;
    $seats = isset($_POST['selected_seats']) ? trim($_POST['selected_seats']) : (isset($_POST['seats']) ? trim($_POST['seats']) : '');
    $date = isset($_POST['booking_date']) ? trim($_POST['booking_date']) : (isset($_POST['travel_date']) ? trim($_POST['travel_date']) : '');
    $pickup = isset($_POST['pickup']) ? trim($_POST['pickup']) : '';
    $dropoff = isset($_POST['dropoff']) ? trim($_POST['dropoff']) : (isset($_POST['destination']) ? trim($_POST['destination']) : '');
    
    // ============ VALIDATION ============
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
    
    // ============ GET BUS NAME ============
    if (empty($error) && $bus_id > 0) {
        $bus_query = $conn->prepare("SELECT bus_name FROM buses WHERE id = ?");
        $bus_query->bind_param("i", $bus_id);
        $bus_query->execute();
        $bus_result = $bus_query->get_result();
        
        if ($bus_result && $bus_result->num_rows > 0) {
            $bus_data = $bus_result->fetch_assoc();
            $receipt_bus_name = $bus_data['bus_name'];
        } else {
            $error = "Bus not found!";
        }
        $bus_query->close();
    }
    
    // ============ GENERATE REFERENCE CODE ============
    if (empty($error)) {
        $ref_code = "BK-" . strtoupper(substr(md5(time() . rand()), 0, 5));
        
        // Check if ref_code already exists
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
    
    // ============ INSERT BOOKING ============
    if (empty($error)) {
        // Check which email column exists
        $col_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'customer_email'");
        $email_field = ($col_check && $col_check->num_rows > 0) ? 'customer_email' : 'email';
        
        // Check which name column exists
        $name_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'customer_name'");
        $name_field = ($name_check && $name_check->num_rows > 0) ? 'customer_name' : '';
        
        // Check if dropoff_location column exists
        $drop_col_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'dropoff_location'");
        $drop_field = ($drop_col_check && $drop_col_check->num_rows > 0) ? 'dropoff_location' : 'drop_location';
        
        // Build INSERT query dynamically
        $sql = "INSERT INTO bookings (bus_id, seat_numbers, journey_date, pickup_location, $drop_field, $email_field, ref_code";
        
        // Add customer_name if column exists
        if (!empty($name_field)) {
            $sql .= ", $name_field";
        }
        $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?";
        
        if (!empty($name_field)) {
            $sql .= ", ?";
        }
        $sql .= ")";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        if (!empty($name_field)) {
            $stmt->bind_param("isssssss", $bus_id, $seats, $date, $pickup, $dropoff, $customer_email, $ref_code, $customer_name);
        } else {
            $stmt->bind_param("issssss", $bus_id, $seats, $date, $pickup, $dropoff, $customer_email, $ref_code);
        }
        
        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id;
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    
    // ============ SEND EMAILS ============
    if (empty($error) && $booking_id > 0) {
        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'pramudithamihiran@gmail.com';
            $mail->Password   = 'dllemhcpkwapwydf';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('pramudithamihiran@gmail.com', $receipt_bus_name . ' Bus Service');
            
            // ============ EMAIL 1: TO CUSTOMER ============
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
                        <p><strong>Seats:</strong> $seats</p>
                        <p><strong>Route:</strong> $pickup → $dropoff</p>
                        <hr style='border:1px dashed #eee;'>
                        <p style='font-size:12px; color:#888; text-align:center;'>Please present this confirmation at the boarding point.</p>
                    </div>
                    <div style='text-align:center; padding-top:15px; border-top:1px solid #eee; font-size:12px; color:#aaa;'>
                        <p>Thank you for choosing " . htmlspecialchars($receipt_bus_name) . "</p>
                    </div>
                </div>
            ";
            $mail->send();

            // ============ EMAIL 2: TO ADMIN/OWNER ============
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
                    <hr style='border:1px dashed #ccc;'>
                    <p style='font-size:12px; color:#888;'>Booking ID: $booking_id</p>
                </div>
            ";
            $mail->send();

        } catch (Exception $e) {
            // Email error - but booking is still saved
        }
    }
    
    // ============ IF ERROR, REDIRECT BACK ============
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
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            padding-top: 80px;
            padding-bottom: 40px;
            min-height: 100vh;
        }
        
        .main-wrapper {
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: calc(100vh - 120px); 
            padding: 20px; 
        }
        
        .receipt { 
            background: white; 
            width: 100%; 
            max-width: 450px; 
            padding: 35px; 
            border-radius: 24px; 
            box-shadow: 0 15px 50px rgba(0,0,0,0.12);
            border-top: 8px solid #28a745;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .receipt .icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .receipt h3 { 
            color: #003580; 
            margin-bottom: 5px; 
            text-transform: uppercase;
            font-weight: 700;
            font-size: 22px;
        }
        
        .receipt .bus-sub {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .success-msg { 
            background: #e7f5ea; 
            color: #28a745; 
            padding: 12px 20px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            font-weight: 600; 
            font-size: 14px;
        }
        
        .success-msg i {
            margin-right: 8px;
        }
        
        .receipt-details { 
            text-align: left; 
            background: #f8faff; 
            padding: 18px 20px; 
            border-radius: 14px; 
            margin-bottom: 20px; 
            border: 1px solid #eef2f7;
        }
        
        .detail-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 8px; 
            font-size: 13px; 
            border-bottom: 1px dashed #eef2f7; 
            padding-bottom: 6px; 
        }
        
        .detail-row:last-child { 
            border-bottom: none; 
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .label { 
            color: #888; 
            font-weight: 500;
        }
        
        .value { 
            color: #1a1a2e; 
            font-weight: 600; 
        }
        
        .value.highlight {
            color: #003580;
        }
        
        .ref-box { 
            background: #e8f0fe; 
            border: 2px dashed #003580; 
            padding: 18px; 
            border-radius: 14px; 
            margin-bottom: 25px; 
        }
        
        .ref-box .ref-label {
            font-size: 11px; 
            color: #888; 
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .ref-box .ref-code {
            font-weight: 700; 
            color: #003580; 
            font-size: 28px; 
            letter-spacing: 2px;
        }
        
        .btn-container { 
            display: flex; 
            flex-direction: column; 
            gap: 12px; 
        }
        
        .print-btn { 
            background: linear-gradient(135deg, #003580, #004d99);
            color: white; 
            border: none; 
            width: 100%; 
            padding: 16px; 
            border-radius: 14px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 15px; 
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .print-btn:hover { 
            background: linear-gradient(135deg, #00255a, #003580);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,53,128,0.3);
        }
        
        .print-btn i {
            margin-right: 8px;
        }
        
        .view-btn {
            background: #28a745;
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            display: block;
            box-sizing: border-box;
            transition: all 0.3s;
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }
        
        .view-btn:hover {
            background: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.3);
        }
        
        .view-btn i {
            margin-right: 8px;
        }
        
        .home-btn { 
            background: transparent; 
            color: #003580; 
            border: 2px solid #003580; 
            width: 100%; 
            padding: 14px; 
            border-radius: 14px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 15px; 
            text-decoration: none; 
            display: block; 
            box-sizing: border-box; 
            transition: all 0.3s;
            text-align: center; 
            font-family: 'Poppins', sans-serif;
        }
        
        .home-btn:hover { 
            background: #e8f0fe;
            transform: translateY(-2px);
        }
        
        .home-btn i {
            margin-right: 8px;
        }
        
        @media (max-width: 480px) {
            .receipt {
                padding: 25px 20px;
            }
            .ref-box .ref-code {
                font-size: 22px;
            }
        }
        
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
            } 
            .receipt { 
                box-shadow: none !important; 
                border: 1px solid #ddd; 
                margin: auto; 
                border-top: 8px solid #003580;
            } 
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
            
            <div class="receipt-details">
                <div class="detail-row">
                    <span class="label"><i class="fas fa-calendar-day"></i> Journey Date</span>
                    <span class="value"><?= htmlspecialchars($date); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-chair"></i> Seat Numbers</span>
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
                    <span class="value" style="font-size:12px;"><?= htmlspecialchars($customer_email); ?></span>
                </div>
            </div>

            <div class="ref-box">
                <div class="ref-label">REFERENCE CODE</div>
                <div class="ref-code"><?= htmlspecialchars($ref_code); ?></div>
            </div>

            <div class="btn-container">
                <a href="booking_details.php?ref=<?= $ref_code ?>" class="view-btn">
                    <i class="fas fa-eye"></i> View Booking Details
                </a>
                <button class="print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <a href="dashboard.php" class="home-btn">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>
            
        </div>
    </div>

</body>
</html>