<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if conductor is logged in
if (!isset($_SESSION['conductor_logged_in']) || $_SESSION['conductor_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$bus_id = $_SESSION['bus_id'];
$bus_name = $_SESSION['bus_name'];
$conductor_name = $_SESSION['conductor_name'];
$today = date('Y-m-d');
$success_msg = '';

// ============ GET BUS STATUS ============
$status_sql = "SELECT status, ride_status FROM buses WHERE id = ?";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("i", $bus_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$bus_status = $status_result->fetch_assoc();
$status_stmt->close();

// ============ GET TODAY'S BOOKINGS (status column නැතිව) ============
$sql = "SELECT bk.*, b.bus_name, b.bus_number, b.departure_time 
        FROM bookings bk 
        JOIN buses b ON bk.bus_id = b.id 
        WHERE bk.bus_id = ? AND bk.journey_date = ?
        ORDER BY bk.booking_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $bus_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$total_bookings = $result->num_rows;

// Calculate total seats
$total_seats = 0;
$bookings_data = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $seats = explode(',', $row['seat_numbers']);
        $total_seats += count($seats);
        $row['seat_count'] = count($seats);
        $bookings_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conductor Dashboard - Matara Bus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: #f0f4f8; 
            padding-top: 80px;
            padding-bottom: 40px;
        }
        
        .container { 
            max-width: 1100px; 
            margin: 20px auto; 
            padding: 0 15px; 
        }
        
        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .dash-header h2 {
            color: #003580;
            font-weight: 700;
            font-size: 24px;
        }
        
        .dash-header h2 i {
            margin-right: 10px;
        }
        
        .dash-header .badge {
            background: #e8f0fe;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            color: #003580;
            font-weight: 500;
        }
        
        .dash-header .badge i {
            margin-right: 6px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card i {
            font-size: 32px;
            color: #003580;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        .stat-card .label {
            font-size: 13px;
            color: #888;
            font-weight: 500;
        }
        
        .stat-card.green i { color: #28a745; }
        .stat-card.blue i { color: #007bff; }
        .stat-card.orange i { color: #fd7e14; }
        .stat-card.purple i { color: #6f42c1; }
        
        .status-box {
            background: white;
            padding: 20px 25px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .status-box .status-label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .status-box .status-label i {
            color: #003580;
            margin-right: 5px;
        }
        
        .status-badge {
            padding: 6px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-active { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
        
        .btn-ride {
            background: #28a745;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-ride:hover {
            background: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        .btn-complete {
            background: #007bff;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-complete:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .btn-ride i, .btn-complete i {
            margin-right: 6px;
        }
        
        .booking-card {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #28a745;
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .booking-card .info {
            flex: 1;
        }
        
        .booking-card .info .ref {
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }
        
        .booking-card .info .ref strong {
            color: #003580;
        }
        
        .booking-card .info h4 {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 3px 0;
        }
        
        .booking-card .info h4 i {
            color: #003580;
            margin-right: 6px;
        }
        
        .booking-card .info .details {
            font-size: 13px;
            color: #666;
            margin: 2px 0;
        }
        
        .booking-card .info .details i {
            width: 20px;
            color: #003580;
        }
        
        .booking-card .info .seats {
            margin-top: 5px;
        }
        
        .booking-card .info .seat-badge {
            background: #e8f0fe;
            color: #003580;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px 3px;
            display: inline-block;
        }
        
        .booking-card .status {
            text-align: right;
        }
        
        .booking-card .status .badge {
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        
        .no-bookings {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        
        .no-bookings i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .no-bookings h3 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .no-bookings p {
            color: #888;
            font-size: 14px;
        }
        
        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-logout i {
            margin-right: 6px;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-success i {
            color: #28a745;
        }
        
        @media (max-width: 768px) {
            .booking-card {
                flex-direction: column;
                text-align: center;
            }
            .booking-card .status {
                text-align: center;
                margin-top: 10px;
                width: 100%;
            }
            .status-box {
                flex-direction: column;
                text-align: center;
            }
            .dash-header {
                flex-direction: column;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    
    <!-- ===== DASHBOARD HEADER ===== -->
    <div class="dash-header">
        <h2><i class="fas fa-bus"></i> Conductor Dashboard</h2>
        <div>
            <span class="badge">
                <i class="fas fa-bus"></i> <?= htmlspecialchars($bus_name) ?>
            </span>
            <span class="badge" style="background:#e8f0fe;margin-left:10px;">
                <i class="fas fa-user"></i> <?= htmlspecialchars($conductor_name) ?>
            </span>
            <a href="logout.php" class="btn-logout" style="margin-left:10px;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- ===== SUCCESS MESSAGE ===== -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'started'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Ride started successfully!
            </div>
        <?php elseif ($_GET['msg'] == 'completed'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Ride completed successfully!
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ===== STATISTICS ===== -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <i class="fas fa-ticket-alt"></i>
            <div class="number"><?= $total_bookings ?></div>
            <div class="label">Today's Bookings</div>
        </div>
        <div class="stat-card green">
            <i class="fas fa-chair"></i>
            <div class="number"><?= $total_seats ?></div>
            <div class="label">Total Seats Booked</div>
        </div>
        <div class="stat-card orange">
            <i class="fas fa-calendar-day"></i>
            <div class="number"><?= date('d M Y') ?></div>
            <div class="label">Today's Date</div>
        </div>
        <div class="stat-card purple">
            <i class="fas fa-clock"></i>
            <div class="number"><?= date('h:i A') ?></div>
            <div class="label">Current Time</div>
        </div>
    </div>

    <!-- ===== BUS STATUS ===== -->
    <div class="status-box">
        <div>
            <span class="status-label"><i class="fas fa-info-circle"></i> Bus Status:</span>
            <span class="status-badge status-<?= strtolower($bus_status['ride_status'] ?? 'pending') ?>">
                <i class="fas <?= ($bus_status['ride_status'] ?? 'pending') == 'pending' ? 'fa-clock' : (($bus_status['ride_status'] ?? 'pending') == 'active' ? 'fa-play' : 'fa-check-circle') ?>"></i>
                <?= ucfirst($bus_status['ride_status'] ?? 'Pending') ?>
            </span>
        </div>
        <div>
            <?php if (($bus_status['ride_status'] ?? 'pending') == 'pending'): ?>
                <form method="POST" action="update_ride_status.php" style="display:inline;">
                    <input type="hidden" name="bus_id" value="<?= $bus_id ?>">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" class="btn-ride" onclick="return confirm('Start the ride for today?')">
                        <i class="fas fa-play"></i> Start Ride
                    </button>
                </form>
            <?php elseif (($bus_status['ride_status'] ?? 'pending') == 'active'): ?>
                <form method="POST" action="update_ride_status.php" style="display:inline;">
                    <input type="hidden" name="bus_id" value="<?= $bus_id ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn-complete" onclick="return confirm('Mark this ride as completed?')">
                        <i class="fas fa-check"></i> Complete Ride
                    </button>
                </form>
            <?php else: ?>
                <span style="color:#28a745;font-weight:600;">
                    <i class="fas fa-check-circle"></i> Ride Completed
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TODAY'S BOOKINGS ===== -->
    <h3 style="margin-bottom:15px;color:#003580;">
        <i class="fas fa-users"></i> Today's Passengers
        <span style="font-size:13px;color:#888;font-weight:400;margin-left:10px;">
            (<?= $total_bookings ?> bookings)
        </span>
    </h3>
    
    <?php if (!empty($bookings_data)): ?>
        <?php foreach($bookings_data as $row): 
            $seat_array = explode(',', $row['seat_numbers']);
            $status = 'confirmed'; // Default status since column doesn't exist
        ?>
            <div class="booking-card">
                <div class="info">
                    <div class="ref">
                        <i class="fas fa-qrcode"></i> Ref: <strong><?= htmlspecialchars($row['ref_code']) ?></strong>
                    </div>
                    <h4><i class="fas fa-user"></i> <?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?></h4>
                    <div class="details">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($row['customer_email']) ?>
                    </div>
                    <div class="details">
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['pickup_location']) ?> 
                        <i class="fas fa-arrow-right" style="margin:0 5px;color:#ccc;"></i> 
                        <?= htmlspecialchars($row['dropoff_location'] ?? $row['drop_location'] ?? 'N/A') ?>
                    </div>
                    <div class="details">
                        <i class="fas fa-clock"></i> <?= $row['departure_time'] ?>
                    </div>
                    <div class="seats">
                        <i class="fas fa-chair"></i> Seats: 
                        <?php foreach ($seat_array as $seat): ?>
                            <span class="seat-badge"><?= trim($seat) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="status">
                    <span class="badge badge-confirmed">
                        Confirmed
                    </span>
                    <div style="margin-top:5px;font-size:11px;color:#888;">
                        <?= $row['seat_count'] ?> seat<?= $row['seat_count'] > 1 ? 's' : '' ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-bookings">
            <i class="fas fa-ticket-alt"></i>
            <h3>No Bookings Today</h3>
            <p>No passengers have booked seats for today.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>