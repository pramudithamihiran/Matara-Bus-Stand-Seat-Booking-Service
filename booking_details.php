<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ GET BOOKING REFERENCE FROM URL ============
$ref_code = isset($_GET['ref']) ? mysqli_real_escape_string($conn, $_GET['ref']) : '';

if (empty($ref_code)) {
    header("Location: index.php");
    exit();
}

// ============ GET BOOKING DETAILS ============
$sql = "SELECT bk.*, b.bus_name, b.bus_number, b.departure_time, b.start_location, b.end_location
        FROM bookings bk
        JOIN buses b ON bk.bus_id = b.id
        WHERE bk.ref_code = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $ref_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Booking not found!'); window.location='index.php';</script>";
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// ============ FUNCTION TO GET SAFE VALUE ============
function getValue($data, $key, $default = 'N/A') {
    return isset($data[$key]) && !empty($data[$key]) ? htmlspecialchars($data[$key]) : $default;
}

// ============ GET SEATS ARRAY ============
$seat_array = isset($booking['seat_numbers']) ? explode(',', $booking['seat_numbers']) : [];

// ============ CHECK IF COLUMNS EXIST ============
$has_customer_name = isset($booking['customer_name']);
$has_customer_phone = isset($booking['customer_phone']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Matara Bus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            padding-top: 80px;
            padding-bottom: 40px;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .ticket-card {
            background: white;
            border-radius: 24px;
            padding: 0;
            box-shadow: 0 15px 50px rgba(0,0,0,0.12);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #003580, #004d99);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .ticket-header .bus-name {
            font-size: 22px;
            font-weight: 700;
        }
        
        .ticket-header .bus-name i {
            margin-right: 10px;
        }
        
        .ticket-header .ref-code {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .ticket-header .ref-code i {
            margin-right: 8px;
        }
        
        .ticket-body {
            padding: 30px;
        }
        
        .status-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px dashed #e8f0fe;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .status-badge {
            padding: 8px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-expired {
            background: #e2e8f0;
            color: #718096;
        }
        
        .date-booked {
            color: #888;
            font-size: 13px;
        }
        
        .date-booked i {
            margin-right: 5px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .detail-item {
            background: #f8faff;
            padding: 12px 18px;
            border-radius: 12px;
        }
        
        .detail-item .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .detail-item .value {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a2e;
            margin-top: 3px;
        }
        
        .detail-item .value i {
            margin-right: 5px;
            color: #003580;
        }
        
        .detail-item.full {
            grid-column: 1 / -1;
        }
        
        .seats-display {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 5px;
        }
        
        .seat-badge {
            background: #003580;
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .ticket-footer {
            background: #f8faff;
            padding: 15px 30px;
            text-align: center;
            border-top: 2px dashed #e8f0fe;
            font-size: 13px;
            color: #888;
        }
        
        .ticket-footer i {
            color: #003580;
            margin: 0 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 10px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
        }
        
        .btn-print:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-home {
            background: #003580;
            color: white;
        }
        
        .btn-home:hover {
            background: #00255a;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: #e8f0fe;
            color: #003580;
        }
        
        .btn-back:hover {
            background: #d4e0f5;
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            .detail-item.full {
                grid-column: 1;
            }
            .ticket-header {
                flex-direction: column;
                text-align: center;
            }
            .status-section {
                flex-direction: column;
                text-align: center;
            }
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .action-buttons .btn {
                text-align: center;
            }
            .ticket-body {
                padding: 20px;
            }
        }
        
        @media print {
            .action-buttons {
                display: none !important;
            }
            body {
                background: white !important;
                padding-top: 40px !important;
            }
            .ticket-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    
    <div class="ticket-card">
        
        <!-- Ticket Header -->
        <div class="ticket-header">
            <div class="bus-name">
                <i class="fas fa-bus"></i> <?= getValue($booking, 'bus_name') ?>
            </div>
            <div class="ref-code">
                <i class="fas fa-qrcode"></i> <?= getValue($booking, 'ref_code') ?>
            </div>
        </div>
        
        <!-- Ticket Body -->
        <div class="ticket-body">
            
            <!-- Status -->
            <div class="status-section">
                <?php 
                $today = date('Y-m-d');
                $is_past = (isset($booking['journey_date']) && $booking['journey_date'] < $today);
                $status = isset($booking['status']) ? $booking['status'] : 'pending';
                if ($is_past && $status != 'cancelled') { $status = 'expired'; }
                $status_class = 'status-' . strtolower($status);
                ?>
                <span class="status-badge <?= $status_class ?>">
                    <i class="fas <?= $status == 'confirmed' ? 'fa-check-circle' : ($status == 'cancelled' ? 'fa-times-circle' : 'fa-clock') ?>"></i>
                    <?= ucfirst($status) ?>
                </span>
                <span class="date-booked">
                    <i class="fas fa-clock"></i> Booked: <?= isset($booking['booking_time']) ? date('d M Y, h:i A', strtotime($booking['booking_time'])) : 'N/A' ?>
                </span>
            </div>
            
            <!-- Details -->
            <div class="details-grid">
                <!-- Bus Number -->
                <div class="detail-item">
                    <div class="label"><i class="fas fa-id-card"></i> Bus Number</div>
                    <div class="value"><?= getValue($booking, 'bus_number') ?></div>
                </div>
                
                <!-- Departure Time -->
                <div class="detail-item">
                    <div class="label"><i class="fas fa-clock"></i> Departure Time</div>
                    <div class="value"><?= getValue($booking, 'departure_time') ?></div>
                </div>
                
                <!-- Route -->
                <div class="detail-item full">
                    <div class="label"><i class="fas fa-route"></i> Route</div>
                    <div class="value">
                        <i class="fas fa-map-marker-alt"></i> <?= getValue($booking, 'start_location') ?> 
                        <i class="fas fa-arrow-right" style="margin:0 8px; color:#888;"></i> 
                        <i class="fas fa-flag-checkered"></i> <?= getValue($booking, 'end_location') ?>
                    </div>
                </div>
                
                <!-- Travel Date -->
                <div class="detail-item">
                    <div class="label"><i class="fas fa-calendar-day"></i> Travel Date</div>
                    <div class="value"><?= isset($booking['journey_date']) ? date('F d, Y', strtotime($booking['journey_date'])) : 'N/A' ?></div>
                </div>
                
                <!-- Email -->
                <div class="detail-item">
                    <div class="label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="value"><?= getValue($booking, 'customer_email', getValue($booking, 'email')) ?></div>
                </div>
                
                <!-- Passenger Name (if exists) -->
                <?php if ($has_customer_name && !empty($booking['customer_name'])): ?>
                <div class="detail-item">
                    <div class="label"><i class="fas fa-user"></i> Passenger</div>
                    <div class="value"><?= getValue($booking, 'customer_name') ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Passenger Phone (if exists) -->
                <?php if ($has_customer_phone && !empty($booking['customer_phone'])): ?>
                <div class="detail-item">
                    <div class="label"><i class="fas fa-phone"></i> Phone</div>
                    <div class="value"><?= getValue($booking, 'customer_phone') ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Pickup Location -->
                <div class="detail-item">
                    <div class="label"><i class="fas fa-map-marker-alt"></i> Boarding Point</div>
                    <div class="value"><?= getValue($booking, 'pickup_location') ?></div>
                </div>
                
                <!-- Dropoff Location -->
                <div class="detail-item">
                    <div class="label"><i class="fas fa-flag-checkered"></i> Dropping Point</div>
                    <div class="value"><?= getValue($booking, 'dropoff_location', getValue($booking, 'drop_location')) ?></div>
                </div>
                
                <!-- Seats -->
                <div class="detail-item full">
                    <div class="label"><i class="fas fa-chair"></i> Seat Numbers</div>
                    <div class="seats-display">
                        <?php if (!empty($seat_array)): ?>
                            <?php foreach ($seat_array as $seat): ?>
                                <span class="seat-badge">Seat <?= trim($seat) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#888;">No seats selected</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="my_bookings.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> My Bookings
                </a>
                <a href="dashboard.php" class="btn btn-home">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="#" onclick="window.print(); return false;" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Ticket
                </a>
            </div>
            
        </div>
        
        <!-- Ticket Footer -->
        <div class="ticket-footer">
            <i class="fas fa-shield-alt"></i> 
            Please present this ticket at the boarding point.
            <br>
            <small>For support, contact: support@matarabus.lk</small>
        </div>
        
    </div>
    
</div>

</body>
</html>