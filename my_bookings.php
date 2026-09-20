<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'] ?? 'User';
$today = date('Y-m-d');

// ============ CHECK FOR SUCCESS MESSAGE ============
$success_msg = '';
if (isset($_GET['success']) && $_GET['success'] == 'cancelled') {
    $success_msg = "Booking cancelled successfully!";
}

// ============ GET FILTER PARAMETERS ============
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ============ GET BOOKINGS ============
$sql = "SELECT bk.*, b.bus_name, b.bus_number, b.departure_time, b.start_location, b.end_location 
        FROM bookings bk 
        JOIN buses b ON bk.bus_id = b.id 
        WHERE bk.customer_email = ?";

if ($filter == 'upcoming') {
    $sql .= " AND bk.journey_date >= ?";
} elseif ($filter == 'past') {
    $sql .= " AND bk.journey_date < ?";
}

$sql .= " ORDER BY bk.journey_date DESC, bk.booking_time DESC";

$stmt = $conn->prepare($sql);

if ($filter == 'upcoming' || $filter == 'past') {
    $stmt->bind_param("ss", $user_email, $today);
} else {
    $stmt->bind_param("s", $user_email);
}

$stmt->execute();
$result = $stmt->get_result();

// ============ GET STATS ============
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN journey_date >= ? THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN journey_date < ? THEN 1 ELSE 0 END) as past
              FROM bookings WHERE customer_email = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("sss", $today, $today, $user_email);
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
            background: #f0f4f8; 
            padding-top: 80px;
            padding-bottom: 40px;
        }
        
        .container { 
            max-width: 1100px; 
            margin: 20px auto; 
            padding: 0 15px; 
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            color: #003580;
            font-weight: 700;
            font-size: 24px;
        }
        
        .page-header h2 i {
            margin-right: 10px;
        }
        
        .page-header .user-info {
            background: #e8f0fe;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            color: #003580;
            font-weight: 500;
        }
        
        .page-header .user-info i {
            margin-right: 6px;
        }
        
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
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-success i {
            color: #28a745;
            font-size: 18px;
        }
        
        /* ===== FILTER TABS ===== */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-tabs .tab {
            padding: 10px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
            color: #666;
            border: 2px solid #e0e0e0;
        }
        
        .filter-tabs .tab:hover {
            border-color: #003580;
            color: #003580;
        }
        
        .filter-tabs .tab.active {
            background: #003580;
            color: white;
            border-color: #003580;
        }
        
        .filter-tabs .tab i {
            margin-right: 6px;
        }
        
        /* ===== STATS ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            padding: 15px 20px;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            text-align: center;
        }
        
        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
            color: #003580;
        }
        
        .stat-box .label {
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }
        
        /* ===== BOOKING CARD ===== */
        .booking-card {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 5px solid #28a745;
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .booking-card.expired {
            border-left-color: #888;
            opacity: 0.6;
        }
        
        .booking-card .booking-info {
            flex: 1;
        }
        
        .booking-card .booking-info .ref {
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }
        
        .booking-card .booking-info .ref strong {
            color: #003580;
            font-size: 14px;
        }
        
        .booking-card .booking-info h4 {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 3px 0;
        }
        
        .booking-card .booking-info h4 i {
            color: #003580;
            margin-right: 8px;
        }
        
        .booking-card .booking-info .details {
            font-size: 13px;
            color: #666;
            margin: 2px 0;
        }
        
        .booking-card .booking-info .details i {
            width: 20px;
            color: #003580;
        }
        
        .booking-card .booking-info .seats {
            margin-top: 5px;
        }
        
        .booking-card .booking-info .seats .seat-badge {
            display: inline-block;
            background: #e8f0fe;
            color: #003580;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px 3px;
        }
        
        .booking-card .booking-status {
            text-align: right;
        }
        
        .booking-card .booking-status .badge {
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 8px;
        }
        
        .badge-upcoming {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-past {
            background: #e2e8f0;
            color: #718096;
        }
        
        .booking-card .booking-status .btn-group {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .booking-card .booking-status .btn {
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        
        .btn-details {
            background: #e8f0fe;
            color: #003580;
        }
        .btn-details:hover {
            background: #d4e0f5;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
        }
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
        }
        .btn-print:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        /* ===== NO BOOKINGS ===== */
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
        
        .no-bookings .btn {
            margin-top: 15px;
            background: #003580;
            color: white;
            padding: 10px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .booking-card {
                flex-direction: column;
                text-align: center;
            }
            .booking-card .booking-status {
                text-align: center;
                margin-top: 15px;
                width: 100%;
            }
            .booking-card .booking-status .btn-group {
                justify-content: center;
            }
            .filter-tabs {
                justify-content: center;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
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
            <div class="number" style="color:#28a745;"><?= $stats['upcoming'] ?? 0 ?></div>
            <div class="label">Upcoming</div>
        </div>
        <div class="stat-box">
            <div class="number" style="color:#888;"><?= $stats['past'] ?? 0 ?></div>
            <div class="label">Past</div>
        </div>
    </div>

    <!-- ===== BOOKINGS LIST ===== -->
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): 
            $is_past = ($row['journey_date'] < $today);
            $can_cancel = !$is_past;
            $status_class = $is_past ? 'expired' : '';
            $badge_class = $is_past ? 'badge-past' : 'badge-upcoming';
            $status_text = $is_past ? 'Past' : 'Upcoming';
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
                        <i class="fas fa-clock"></i> <?= $row['departure_time'] ?> | 
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