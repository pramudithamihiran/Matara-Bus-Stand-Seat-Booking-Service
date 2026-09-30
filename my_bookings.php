<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ CHECK IF USER IS LOGGED IN ============
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isTabAuthenticated()) {
    unset($_SESSION['user_logged_in']);
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'] ?? 'User';

$current_datetime = date('Y-m-d H:i:s');

$success_msg = '';
if (isset($_GET['success']) && $_GET['success'] == 'cancelled') {
    $success_msg = "Booking cancelled successfully!";
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ============ GET BOOKINGS ============
$sql = "SELECT bk.*, 
               b.bus_name, 
               b.bus_number, 
               b.departure_time AS bus_departure_time, 
               b.start_location, 
               b.end_location 
        FROM bookings bk 
        JOIN buses b ON bk.bus_id = b.id 
        WHERE bk.customer_email = ?";

if ($filter == 'upcoming') {
    $sql .= " AND (bk.journey_date > CURDATE() OR (bk.journey_date = CURDATE() AND CONCAT(bk.journey_date, ' ', b.departure_time) > NOW()))";
} elseif ($filter == 'past') {
    $sql .= " AND (bk.journey_date < CURDATE() OR (bk.journey_date = CURDATE() AND CONCAT(bk.journey_date, ' ', b.departure_time) <= NOW()))";
}

$sql .= " ORDER BY bk.journey_date DESC, bk.booking_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

// ============ GET STATS ============
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN (bk.journey_date > CURDATE() OR (bk.journey_date = CURDATE() AND CONCAT(bk.journey_date, ' ', b.departure_time) > NOW())) THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN (bk.journey_date < CURDATE() OR (bk.journey_date = CURDATE() AND CONCAT(bk.journey_date, ' ', b.departure_time) <= NOW())) THEN 1 ELSE 0 END) as past
              FROM bookings bk
              JOIN buses b ON bk.bus_id = b.id
              WHERE bk.customer_email = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $user_email);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Matara Bus</title>
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
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 15px; }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 { 
            color: #ffffff; 
            font-weight: 700; 
            font-size: 24px; 
        }
        
        .page-header h2 i { margin-right: 10px; color: #ffb700; }
        
        .page-header .user-info {
            background: rgba(255, 183, 0, 0.15);
            border: 1px solid rgba(255, 183, 0, 0.3);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            color: #ffb700;
            font-weight: 600;
        }
        
        .page-header .user-info i { margin-right: 6px; }
        
        /* ===== ALERT ===== */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border-left: 4px solid #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .alert-success i { color: #28a745; font-size: 18px; }
        
        /* ===== FILTER TABS ===== */
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        
        .filter-tabs .tab {
            padding: 10px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            color: #b0b0b0;
            border: 2px solid rgba(255, 183, 0, 0.15);
        }
        
        .filter-tabs .tab:hover { 
            border-color: #ffb700; 
            color: #ffb700;
            transform: translateY(-2px);
        }
        
        .filter-tabs .tab.active { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            border-color: #ffb700;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .filter-tabs .tab i { margin-right: 6px; }
        
        /* ===== STATS ROW ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 183, 0, 0.3);
        }
        
        .stat-box .number { font-size: 32px; font-weight: 700; color: #ffb700; }
        .stat-box .label { font-size: 12px; color: #b0b0b0; font-weight: 500; margin-top: 5px; }
        
        /* ===== BOOKING CARD ===== */
        .booking-card {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            border-radius: 16px;
            padding: 22px 25px;
            margin-bottom: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 5px solid #ffb700;
        }
        
        .booking-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 40px rgba(255, 183, 0, 0.15);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .booking-card.expired { 
            border-left-color: #555; 
            opacity: 0.65; 
        }
        
        .booking-card .booking-info { flex: 1; }
        
        .booking-card .booking-info .ref { font-size: 12px; color: #888; font-weight: 500; }
        .booking-card .booking-info .ref strong { color: #ffb700; font-size: 14px; }
        
        .booking-card .booking-info h4 { 
            font-size: 17px; 
            font-weight: 700; 
            color: #ffffff; 
            margin: 5px 0; 
        }
        
        .booking-card .booking-info h4 i { color: #ffb700; margin-right: 8px; }
        
        .booking-card .booking-info .details { 
            font-size: 13px; 
            color: #b0b0b0; 
            margin: 3px 0; 
        }
        
        .booking-card .booking-info .details i { 
            width: 20px; 
            color: #ffb700; 
        }
        
        .booking-card .booking-info .seats { margin-top: 8px; }
        
        .booking-card .booking-info .seats .seat-badge {
            display: inline-block;
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px 3px;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        /* ===== BOOKING STATUS ===== */
        .booking-card .booking-status { text-align: right; }
        
        .booking-card .booking-status .badge {
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .badge-upcoming { 
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .badge-past { 
            background: rgba(108, 117, 125, 0.15);
            color: #b0b0b0;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .badge-departed { 
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .booking-card .booking-status .btn-group {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .booking-card .booking-status .btn {
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        
        .btn-details { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .btn-details:hover { 
            background: rgba(255, 183, 0, 0.25);
            transform: translateY(-2px);
        }
        
        .btn-cancel { 
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .btn-cancel:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-disabled {
            background: rgba(108, 117, 125, 0.15);
            color: #888;
            cursor: not-allowed;
            opacity: 0.6;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .btn-print { 
            background: rgba(108, 117, 125, 0.2);
            color: #b0b0b0;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .btn-print:hover { 
            background: rgba(108, 117, 125, 0.35);
            transform: translateY(-2px);
        }
        
        .btn i { margin-right: 5px; }
        
        /* ===== NO BOOKINGS ===== */
        .no-bookings {
            text-align: center;
            padding: 60px 20px;
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .no-bookings i { 
            font-size: 60px; 
            color: rgba(255, 183, 0, 0.3); 
            margin-bottom: 15px; 
        }
        
        .no-bookings h3 { color: #ffffff; margin-bottom: 5px; }
        .no-bookings p { color: #b0b0b0; font-size: 14px; }
        
        .no-bookings .btn {
            margin-top: 20px;
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
            transition: all 0.3s;
        }
        
        .no-bookings .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .booking-card { flex-direction: column; text-align: center; }
            .booking-card .booking-status { text-align: center; margin-top: 15px; width: 100%; }
            .booking-card .booking-status .btn-group { justify-content: center; }
            .filter-tabs { justify-content: center; }
            .page-header { flex-direction: column; text-align: center; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 480px) {
            .container { padding: 0 12px; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-box { padding: 15px 10px; }
            .stat-box .number { font-size: 26px; }
            .booking-card { padding: 18px 15px; }
            .page-header h2 { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header">
        <h2><i class="fas fa-ticket-alt"></i> My Bookings</h2>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($user_name) ?>
        </div>
    </div>

    <!-- ===== SUCCESS MESSAGE ===== -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <!-- ===== FILTER TABS ===== -->
    <div class="filter-tabs">
        <a href="my_bookings.php?filter=all" class="tab <?= $filter == 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
        </a>
        <a href="my_bookings.php?filter=upcoming" class="tab <?= $filter == 'upcoming' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Upcoming
        </a>
        <a href="my_bookings.php?filter=past" class="tab <?= $filter == 'past' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> Past
        </a>
    </div>

    <!-- ===== STATS ===== -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?? 0 ?></div>
            <div class="label">Total Bookings</div>
        </div>
        <div class="stat-box">
            <div class="number" style="color:#4ade80;"><?= $stats['upcoming'] ?? 0 ?></div>
            <div class="label">Upcoming</div>
        </div>
        <div class="stat-box">
            <div class="number" style="color:#b0b0b0;"><?= $stats['past'] ?? 0 ?></div>
            <div class="label">Past</div>
        </div>
    </div>

    <!-- ===== BOOKINGS LIST ===== -->
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): 
            $departure_datetime = strtotime($row['journey_date'] . ' ' . $row['bus_departure_time']);
            $current_timestamp = time();
            
            $can_cancel = ($departure_datetime > $current_timestamp);
            
            if ($can_cancel) {
                $status_class = '';
                $badge_class = 'badge-upcoming';
                $status_text = 'Upcoming';
            } else {
                $status_class = 'expired';
                $badge_class = 'badge-departed';
                $status_text = 'Departed';
            }
        ?>
            <div class="booking-card <?= $status_class ?>">
                <div class="booking-info">
                    <div class="ref">
                        <i class="fas fa-qrcode"></i> Ref: <strong><?= htmlspecialchars($row['ref_code']) ?></strong>
                        <span style="margin-left:15px;">
                            <i class="fas fa-clock"></i> Booked: <?= isset($row['booking_time']) ? date('d M Y', strtotime($row['booking_time'])) : 'N/A' ?>
                        </span>
                    </div>
                    <h4><i class="fas fa-bus"></i> <?= htmlspecialchars($row['bus_name']) ?></h4>
                    <div class="details">
                        <i class="fas fa-route"></i> <?= htmlspecialchars($row['start_location']) ?> → <?= htmlspecialchars($row['end_location']) ?>
                    </div>
                    <div class="details">
                        <i class="fas fa-clock"></i> <?= $row['bus_departure_time'] ?> | 
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($row['bus_number']) ?>
                    </div>
                    <div class="details">
                        <i class="fas fa-calendar-day"></i> <?= date('l, F d, Y', strtotime($row['journey_date'])) ?>
                    </div>
                    <div class="seats">
                        <i class="fas fa-chair"></i> Seats: 
                        <?php 
                        $seat_array = explode(',', $row['seat_numbers']);
                        foreach ($seat_array as $seat): 
                        ?>
                            <span class="seat-badge"><?= trim($seat) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="booking-status">
                    <span class="badge <?= $badge_class ?>">
                        <?= $status_text ?>
                    </span>
                    <div class="btn-group">
                        <a href="booking_details.php?ref=<?= $row['ref_code'] ?>" class="btn btn-details">
                            <i class="fas fa-eye"></i> Details
                        </a>
                        
                        <?php if ($can_cancel): ?>
                            <a href="cancel_booking.php?id=<?= $row['id'] ?>" class="btn btn-cancel" onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone!')">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php else: ?>
                            <button class="btn btn-disabled" disabled title="Cannot cancel - bus has already departed">
                                <i class="fas fa-lock"></i> Cannot Cancel
                            </button>
                        <?php endif; ?>
                        
                        <a href="booking_details.php?ref=<?= $row['ref_code'] ?>&print=1" class="btn btn-print">
                            <i class="fas fa-print"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-bookings">
            <i class="fas fa-ticket-alt"></i>
            <h3>No Bookings Found</h3>
            <p>
                <?php if ($filter == 'upcoming'): ?>
                    You don't have any upcoming bookings.
                <?php elseif ($filter == 'past'): ?>
                    You don't have any past bookings.
                <?php else: ?>
                    You haven't made any bookings yet.
                <?php endif; ?>
            </p>
            <a href="bus_list.php" class="btn">
                <i class="fas fa-search"></i> Browse Buses
            </a>
        </div>
    <?php endif; ?>

</div>

</body>
</html>