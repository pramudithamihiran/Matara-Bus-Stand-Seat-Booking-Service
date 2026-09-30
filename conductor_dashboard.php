<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ TAB AUTHENTICATION CHECK ============
requireTabAuth('admin_login.php');

// Check if conductor is logged in
if (!isset($_SESSION['conductor_logged_in']) || $_SESSION['conductor_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$bus_id = $_SESSION['bus_id'];
$bus_name = $_SESSION['bus_name'];
$conductor_name = $_SESSION['conductor_name'];
$today = date('Y-m-d');

// ============ AUTO RESET RIDE STATUS FOR NEW DAY ============
// If ride_status is 'completed' but last_ride_date is not today, reset to 'pending'
$reset_sql = "UPDATE buses 
              SET ride_status = 'pending', 
                  ride_completed_date = NULL 
              WHERE id = ? 
              AND ride_status = 'completed' 
              AND (last_ride_date IS NULL OR last_ride_date != ?)";
$reset_stmt = $conn->prepare($reset_sql);
$reset_stmt->bind_param("is", $bus_id, $today);
$reset_stmt->execute();
$reset_stmt->close();

// ============ GET BUS STATUS ============
$status_sql = "SELECT status, ride_status FROM buses WHERE id = ?";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("i", $bus_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$bus_status = $status_result->fetch_assoc();
$status_stmt->close();

// ============ GET TODAY'S BOOKINGS ============
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
            background: linear-gradient(135deg, #0f0022 0%, #1a0033 50%, #0a0018 100%) !important;
            background-attachment: fixed !important;
            padding-top: 130px;
            padding-bottom: 40px;
            min-height: 100vh;
            color: #e0e0e0;
        }
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 15px; }
        
        /* ===== DASHBOARD HEADER ===== */
        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .dash-header h2 { 
            color: #ffffff; 
            font-weight: 700; 
            font-size: 24px; 
        }
        
        .dash-header h2 i { margin-right: 10px; color: #ffb700; }
        
        .dash-header .badge {
            background: rgba(255, 183, 0, 0.1);
            border: 1px solid rgba(255, 183, 0, 0.3);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            color: #ffb700;
            font-weight: 600;
        }
        
        .dash-header .badge i { margin-right: 6px; }
        
        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 25px 20px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 40px rgba(255, 183, 0, 0.15);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .stat-card i { font-size: 32px; color: #ffb700; margin-bottom: 10px; }
        .stat-card .number { font-size: 28px; font-weight: 700; color: #ffffff; }
        .stat-card .label { font-size: 13px; color: #b0b0b0; font-weight: 500; }
        
        .stat-card.green i { color: #4ade80; }
        .stat-card.blue i { color: #60a5fa; }
        .stat-card.orange i { color: #fb923c; }
        .stat-card.purple i { color: #c084fc; }
        
        /* ===== STATUS BOX ===== */
        .status-box {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 22px 25px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .status-box .status-label { font-weight: 600; color: #c9c9c9; font-size: 14px; }
        .status-box .status-label i { color: #ffb700; margin-right: 5px; }
        
        .status-badge {
            padding: 6px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }
        
        .status-pending { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .status-active { 
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .status-completed { 
            background: rgba(0, 123, 255, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(0, 123, 255, 0.3);
        }
        
        /* ===== BUTTONS ===== */
        .btn-ride {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-ride:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }
        
        .btn-complete {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-complete:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.5);
        }
        
        .btn-ride i, .btn-complete i { margin-right: 6px; }
        
        .ride-completed-text {
            color: #4ade80;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* ===== BOOKING CARD ===== */
        .booking-card {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #ffb700;
            transition: all 0.3s ease;
        }
        
        .booking-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 40px rgba(255, 183, 0, 0.15);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .booking-card .info { flex: 1; }
        
        .booking-card .info .ref { font-size: 12px; color: #888; font-weight: 500; }
        .booking-card .info .ref strong { color: #ffb700; }
        
        .booking-card .info h4 { 
            font-size: 16px; 
            font-weight: 700; 
            color: #ffffff; 
            margin: 5px 0; 
        }
        
        .booking-card .info h4 i { color: #ffb700; margin-right: 6px; }
        
        .booking-card .info .details { 
            font-size: 13px; 
            color: #b0b0b0; 
            margin: 3px 0; 
        }
        
        .booking-card .info .details i { 
            width: 20px; 
            color: #ffb700; 
        }
        
        .booking-card .info .seats { margin-top: 8px; }
        
        .booking-card .info .seat-badge {
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px 3px;
            display: inline-block;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .booking-card .status { text-align: right; }
        
        .booking-card .status .badge {
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        
        .badge-confirmed { 
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .badge-pending { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        /* ===== NO BOOKINGS ===== */
        .no-bookings {
            text-align: center;
            padding: 60px 20px;
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .no-bookings i { 
            font-size: 60px; 
            color: rgba(255, 183, 0, 0.3); 
            margin-bottom: 15px; 
        }
        
        .no-bookings h3 { color: #ffffff; margin-bottom: 5px; }
        .no-bookings p { color: #b0b0b0; font-size: 14px; }
        
        /* ===== LOGOUT BUTTON ===== */
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white;
            padding: 10px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-block;
            font-size: 13px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-logout:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.5);
        }
        
        .btn-logout i { margin-right: 6px; }
        
        /* ===== ALERT ===== */
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
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border-left: 4px solid #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .alert-success i { color: #28a745; }
        
        /* ===== SECTION TITLE ===== */
        .section-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i { color: #ffb700; }
        
        .section-title .count {
            font-size: 13px;
            color: #b0b0b0;
            font-weight: 400;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .booking-card { flex-direction: column; text-align: center; }
            .booking-card .status { text-align: center; margin-top: 10px; width: 100%; }
            .status-box { flex-direction: column; text-align: center; }
            .dash-header { flex-direction: column; text-align: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 480px) {
            body { padding-top: 120px; }
            .stats-grid { grid-template-columns: 1fr; }
            .dash-header h2 { font-size: 20px; }
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
            <span class="badge" style="background:rgba(255,183,0,0.15);margin-left:10px;">
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
                <span class="ride-completed-text">
                    <i class="fas fa-check-circle"></i> Ride Completed
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TODAY'S BOOKINGS ===== -->
    <h3 class="section-title">
        <i class="fas fa-users"></i> Today's Passengers
        <span class="count">(<?= $total_bookings ?> bookings)</span>
    </h3>
    
    <?php if (!empty($bookings_data)): ?>
        <?php foreach($bookings_data as $row): 
            $seat_array = explode(',', $row['seat_numbers']);
        ?>
            <div class="booking-card">
                <div class="info">
                    <div class="ref">
                        <i class="fas fa-qrcode"></i> Ref: <strong><?= htmlspecialchars($row['ref_code']) ?></strong>
                    </div>
                    <h4><i class="fas fa-user"></i> <?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?></h4>
                    <div class="details">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($row['customer_email'] ?? 'N/A') ?>
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