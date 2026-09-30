<?php
include 'config.php';
require_once 'tab_auth.php';

requireTabAuth('admin_login.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); 
    exit();
}

$role = $_SESSION['role'] ?? 'bus_owner';
$owner_username = $_SESSION['admin_username'] ?? 'Unknown';

$success = '';
$error = '';
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_fields': $error = "Please fill all fields!"; break;
        case 'past_date': $error = "Start date cannot be in the past!"; break;
        case 'invalid_range': $error = "End date must be after start date!"; break;
        case 'bus_not_found': $error = "Bus not found!"; break;
        case 'no_new_dates': $error = "No new dates were blocked. All dates already blocked."; break;
        default: $error = "Something went wrong!";
    }
}

if (isset($_GET['delete_bus']) && $role === 'super_admin') {
    $bus_id = intval($_GET['delete_bus']);
    $conn->query("DELETE FROM bookings WHERE bus_id = $bus_id");
    $delete_stmt = $conn->prepare("DELETE FROM buses WHERE id = ?");
    $delete_stmt->bind_param("i", $bus_id);
    if ($delete_stmt->execute()) {
        $success = "Bus and all its bookings deleted successfully!";
    } else {
        $error = "Error deleting bus!";
    }
    $delete_stmt->close();
}

$stats_sql = "SELECT COUNT(*) as total FROM buses";
if ($role !== 'super_admin') {
    $stats_sql .= " WHERE owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$total_buses = $conn->query($stats_sql)->fetch_assoc()['total'];

$booking_stats_sql = "SELECT COUNT(*) as total FROM bookings bk JOIN buses b ON bk.bus_id = b.id";
if ($role !== 'super_admin') {
    $booking_stats_sql .= " WHERE b.owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$total_bookings = $conn->query($booking_stats_sql)->fetch_assoc()['total'];

$today = date('Y-m-d');
$today_bookings = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE journey_date = '$today'")->fetch_assoc()['total'];

$revenue_sql = "SELECT COALESCE(SUM(total_fare), 0) as revenue FROM bookings bk JOIN buses b ON bk.bus_id = b.id";
if ($role !== 'super_admin') {
    $revenue_sql .= " WHERE b.owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$total_revenue = $conn->query($revenue_sql)->fetch_assoc()['revenue'] ?? 0;

$bus_sql = "SELECT * FROM buses";
$bus_conditions = [];
if ($role !== 'super_admin') {
    $bus_conditions[] = "owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
if (!empty($_GET['filter_route'])) {
    $bus_conditions[] = "end_location = '" . mysqli_real_escape_string($conn, $_GET['filter_route']) . "'";
}
if (count($bus_conditions) > 0) {
    $bus_sql .= " WHERE " . implode(" AND ", $bus_conditions);
}
$bus_sql .= " ORDER BY id DESC";
$buses_result = $conn->query($bus_sql);

$where_clauses = [];
if ($role !== 'super_admin') {
    $where_clauses[] = "b.owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
if (!empty($_GET['route_category'])) {
    $where_clauses[] = "b.end_location = '" . mysqli_real_escape_string($conn, $_GET['route_category']) . "'";
}
if (!empty($_GET['bus_id'])) {
    $where_clauses[] = "bk.bus_id = " . intval($_GET['bus_id']);
}
if (!empty($_GET['journey_date'])) {
    $where_clauses[] = "bk.journey_date = '" . mysqli_real_escape_string($conn, $_GET['journey_date']) . "'";
}
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where_clauses[] = "(u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR bk.ref_code LIKE '%$search%')";
}
$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT bk.*, b.bus_name as bus_title, b.end_location AS route_category, b.ride_status, u.full_name, u.email 
        FROM bookings bk 
        JOIN buses b ON bk.bus_id = b.id 
        LEFT JOIN users u ON bk.customer_email = u.email" . $where_sql . " ORDER BY bk.id DESC LIMIT 100";
$result = $conn->query($sql);

$route_query = "SELECT DISTINCT end_location FROM buses WHERE end_location IS NOT NULL AND end_location != ''";
if ($role !== 'super_admin') {
    $route_query .= " AND owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$route_query .= " ORDER BY end_location ASC";
$routes_result = $conn->query($route_query);

$all_buses_sql = "SELECT id, bus_name, bus_number FROM buses ORDER BY bus_name ASC";
$all_buses_result = $conn->query($all_buses_sql);

$blocked_dates_query = "SELECT bud.*, b.bus_name, b.bus_number 
                        FROM bus_unavailable_dates bud 
                        JOIN buses b ON bud.bus_id = b.id 
                        WHERE bud.unavailable_date >= CURDATE()";
if ($role !== 'super_admin') {
    $blocked_dates_query .= " AND b.owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$blocked_dates_query .= " ORDER BY bud.unavailable_date ASC LIMIT 20";
$blocked_dates_result = $conn->query($blocked_dates_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Matara Bus Service</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
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
        
        .container { 
            max-width: 1300px; 
            margin: 20px auto; 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .dash-header h2 { font-size: 24px; font-weight: 700; color: #ffffff; }
        .dash-header h2 i { color: #ffb700; margin-right: 10px; }
        .dash-header .user-badge { 
            background: rgba(255, 183, 0, 0.15);
            border: 1px solid rgba(255, 183, 0, 0.3);
            padding: 8px 18px; 
            border-radius: 30px; 
            font-size: 14px; 
            color: #ffb700; 
            font-weight: 600; 
        }
        .dash-header .user-badge i { margin-right: 6px; }
        .dash-header .user-badge .role-badge { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            padding: 2px 10px; 
            border-radius: 20px; 
            font-size: 11px; 
            margin-left: 8px; 
            font-weight: 700;
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { 
            background: rgba(15, 0, 34, 0.7);
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
        
        .filter-bar { 
            background: rgba(15, 0, 34, 0.6);
            padding: 20px; 
            border-radius: 14px; 
            margin-bottom: 20px; 
            display: flex; 
            gap: 12px; 
            align-items: center; 
            border: 1px solid rgba(255, 183, 0, 0.15);
            flex-wrap: wrap; 
        }
        .filter-bar select, .filter-bar input { 
            padding: 10px 16px; 
            border-radius: 10px; 
            border: 2px solid #2a2a2a; 
            font-family: 'Poppins', sans-serif; 
            font-size: 13px; 
            background: #0a0a0a; 
            color: #e0e0e0;
            outline: none; 
            min-width: 140px; 
            flex: 1 1 auto; 
        }
        .filter-bar select:focus, .filter-bar input:focus { 
            border-color: #ffb700; 
            box-shadow: 0 0 0 3px rgba(255, 183, 0, 0.1); 
        }
        .filter-bar .btn-filter { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            padding: 10px 24px; 
            border-radius: 10px; 
            border: none; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 13px; 
            white-space: nowrap; 
            flex: 0 0 auto; 
            transition: all 0.2s; 
            font-family: 'Poppins', sans-serif; 
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        .filter-bar .btn-filter:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        .filter-bar .btn-filter i { margin-right: 6px; }
        .filter-bar .reset-link { 
            color: #b0b0b0; 
            font-size: 13px; 
            text-decoration: none; 
            white-space: nowrap; 
            flex: 0 0 auto; 
            padding: 10px 5px; 
            font-weight: 500; 
        }
        .filter-bar .reset-link:hover { color: #ffb700; text-decoration: underline; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th { 
            background: linear-gradient(135deg, #1a0033, #2d1b4e);
            color: #ffb700; 
            padding: 14px 12px; 
            font-weight: 600; 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            text-align: center;
            border-bottom: 2px solid rgba(255, 183, 0, 0.3);
        }
        td { 
            padding: 12px; 
            border-bottom: 1px solid rgba(255, 183, 0, 0.08);
            text-align: center; 
            vertical-align: middle; 
            color: #e0e0e0;
        }
        tr:hover td { background: rgba(255, 183, 0, 0.03); }
        
        .status-badge { 
            padding: 4px 14px; 
            border-radius: 30px; 
            font-size: 11px; 
            font-weight: 600; 
            display: inline-block; 
        }
        .status-active { 
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .status-blocked { 
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .status-pending { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        .status-completed { 
            background: rgba(0, 123, 255, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(0, 123, 255, 0.3);
        }
        
        .btn { padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; display: inline-block; transition: all 0.2s; font-family: 'Poppins', sans-serif; }
        .btn-primary { background: linear-gradient(135deg, #003580, #004d99); color: white; } 
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 53, 128, 0.4); }
        .btn-success { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; } 
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #dc3545, #b91c1c); color: white; } 
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4); }
        .btn-warning { background: linear-gradient(135deg, #ffb700, #f5a623); color: #0f0022; } 
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 183, 0, 0.4); }
        .btn-sm { padding: 6px 14px; font-size: 12px; }
        
        .action-link { margin: 0 4px; text-decoration: none; font-weight: 500; font-size: 16px; }
        .action-link.edit { color: #fb923c; } 
        .action-link.delete { color: #ff6b6b; } 
        .action-link.block { color: #60a5fa; }
        .action-link:hover { text-decoration: underline; transform: scale(1.1); display: inline-block; }
        
        .section-title { 
            font-size: 18px; 
            font-weight: 600; 
            color: #ffffff; 
            margin: 30px 0 15px 0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .section-title i { margin-right: 10px; color: #ffb700; }
        
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
        .alert-danger { 
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border-left: 4px solid #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .modal-overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.7); 
            backdrop-filter: blur(10px); 
            z-index: 999; 
            justify-content: center; 
            align-items: center; 
        }
        .modal-overlay.active { display: flex; }
        .modal-box { 
            background: rgba(22, 22, 22, 0.95);
            backdrop-filter: blur(30px);
            padding: 35px; 
            border-radius: 20px; 
            max-width: 450px; 
            width: 90%; 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 183, 0, 0.2);
            animation: modalIn 0.3s ease; 
        }
        @keyframes modalIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-box h3 { font-size: 20px; color: #ffb700; margin-bottom: 20px; text-align: center; }
        .modal-box h3 i { margin-right: 10px; }
        .modal-box label { font-size: 13px; font-weight: 500; display: block; margin-top: 12px; color: #c9c9c9; }
        .modal-box input { 
            width: 100%; 
            padding: 10px 14px; 
            border-radius: 10px; 
            border: 2px solid #2a2a2a; 
            font-family: 'Poppins', sans-serif; 
            margin-top: 4px; 
            box-sizing: border-box; 
            background: #0a0a0a; 
            color: #e0e0e0;
            outline: none;
            color-scheme: dark;
        }
        .modal-box input:focus { border-color: #ffb700; box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1); }
        .modal-box .btn { width: 100%; margin-top: 15px; text-align: center; }
        .modal-box .btn-close { 
            background: rgba(108, 117, 125, 0.2);
            color: #b0b0b0; 
            width: 100%; 
            margin-top: 8px;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        .modal-box .btn-close:hover { background: rgba(108, 117, 125, 0.35); transform: translateY(-2px); }
        
        /* ===== SELECT2 DARK THEME ===== */
        .select2-container--default .select2-selection--single {
            height: 42px !important;
            background: #0a0a0a !important;
            border: 2px solid #2a2a2a !important;
            border-radius: 10px !important;
            padding: 5px 12px !important;
            font-family: 'Poppins', sans-serif !important;
            font-size: 13px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px !important;
            color: #e0e0e0 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #ffb700 transparent transparent transparent !important;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #ffb700 !important;
            box-shadow: 0 0 0 3px rgba(255, 183, 0, 0.1) !important;
        }
        .select2-dropdown {
            background: #161616 !important;
            border: 2px solid rgba(255, 183, 0, 0.4) !important;
            border-radius: 10px !important;
            font-family: 'Poppins', sans-serif !important;
            font-size: 13px !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.5) !important;
        }
        .select2-search--dropdown .select2-search__field {
            border-radius: 8px !important;
            padding: 8px 12px !important;
            border: 2px solid #2a2a2a !important;
            background: #0a0a0a !important;
            color: #e0e0e0 !important;
            font-family: 'Poppins', sans-serif !important;
        }
        .select2-results__option {
            padding: 10px 15px !important;
            color: #e0e0e0 !important;
            background: #161616 !important;
        }
        .select2-results__option--highlighted[aria-selected] {
            background-color: rgba(255, 183, 0, 0.2) !important;
            color: #ffb700 !important;
        }
        .select2-results__option[aria-selected=true] {
            background-color: rgba(255, 183, 0, 0.15) !important;
            color: #ffb700 !important;
        }
        .select2-container { min-width: 200px; flex: 1 1 auto; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: repeat(2, 1fr); } 
            .filter-bar { flex-direction: column; align-items: stretch; } 
            .filter-bar select, .filter-bar input, .filter-bar .btn-filter, .filter-bar .reset-link { width: 100%; flex: none; min-width: unset; } 
            .filter-bar .btn-filter { text-align: center; } 
            .dash-header { flex-direction: column; align-items: flex-start; } 
            .select2-container { min-width: 100%; }
        }
        @media (max-width: 480px) { 
            .stats-grid { grid-template-columns: 1fr; } 
            .container { padding: 15px; } 
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">

    <div class="dash-header">
        <h2><i class="fas fa-chart-pie"></i> Dashboard</h2>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <?php if ($role === 'super_admin'): ?>
                <a href="manage_routes.php" class="btn btn-primary btn-sm"><i class="fas fa-route"></i> Manage Routes</a>
                <a href="fare_settings.php" class="btn btn-warning btn-sm"><i class="fas fa-money-bill-wave"></i> Fare Settings</a>
            <?php endif; ?>
            <div class="user-badge">
                <i class="fas fa-user-cog"></i> 
                <?= htmlspecialchars($owner_username); ?> 
                <span class="role-badge"><?= $role === 'super_admin' ? 'Super Admin' : 'Bus Owner' ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card blue"><i class="fas fa-bus"></i><div class="number"><?= $total_buses ?></div><div class="label">Total Buses</div></div>
        <div class="stat-card green"><i class="fas fa-ticket-alt"></i><div class="number"><?= $total_bookings ?></div><div class="label">Total Bookings</div></div>
        <div class="stat-card orange"><i class="fas fa-calendar-day"></i><div class="number"><?= $today_bookings ?></div><div class="label">Today's Bookings</div></div>
        <div class="stat-card purple"><i class="fas fa-money-bill-wave"></i><div class="number">Rs. <?= number_format($total_revenue, 0) ?></div><div class="label">Total Revenue</div></div>
    </div>

    <!-- ============ BUS MANAGEMENT ============ -->
    <?php if ($role === 'super_admin'): ?>
        <div class="section-title">
            <span><i class="fas fa-list"></i> Registered Buses Management</span>
            <a href="add_bus.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add New Bus</a>
        </div>
        
        <form method="GET" style="margin-bottom: 15px;">
            <select name="filter_route" id="filterRouteSelect" onchange="this.form.submit()" style="width: 300px;">
                <option value="">-- All Destinations --</option>
                <?php 
                $dest_list = $conn->query("SELECT DISTINCT end_location FROM buses WHERE end_location IS NOT NULL AND end_location != '' ORDER BY end_location ASC");
                while($d = $dest_list->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($d['end_location']) ?>" <?= (isset($_GET['filter_route']) && $_GET['filter_route'] == $d['end_location']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['end_location']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>ID</th><th>Bus Name</th><th>Destination</th><th>Number</th><th>Owner</th><th>Status</th><th>Ride Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($buses_result && $buses_result->num_rows > 0): ?>
                        <?php while($bus = $buses_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $bus['id'] ?></td>
                            <td><strong><?= htmlspecialchars($bus['bus_name']) ?></strong></td>
                            <td>
                                <?php if (!empty($bus['end_location'])): ?>
                                    <span class="status-badge status-active"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($bus['end_location']) ?></span>
                                <?php else: ?>
                                    <span style="color:#888;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                            <td><?= htmlspecialchars($bus['owner_username']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($bus['status'] ?? 'active') ?>"><?= ucfirst($bus['status'] ?? 'Active') ?></span></td>
                            <td><span class="status-badge status-<?= strtolower($bus['ride_status'] ?? 'pending') ?>"><i class="fas <?= ($bus['ride_status'] ?? 'pending') == 'pending' ? 'fa-clock' : (($bus['ride_status'] ?? 'pending') == 'active' ? 'fa-play' : 'fa-check-circle') ?>"></i> <?= ucfirst($bus['ride_status'] ?? 'Pending') ?></span></td>
                            <td>
                                <a href='update_bus.php?id=<?= $bus['id'] ?>' class="action-link edit" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href='#' onclick="confirmDelete(<?= $bus['id'] ?>)" class="action-link delete" title="Delete"><i class="fas fa-trash"></i></a>
                                <a href='#' onclick="openBlockModal(<?= $bus['id'] ?>)" class="action-link block" title="Block Dates"><i class="fas fa-calendar-times"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="padding: 30px; color: #888;">No buses registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="section-title"><span><i class="fas fa-bus"></i> Your Bus Details</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>ID</th><th>Bus Name</th><th>Destination</th><th>Number</th><th>Status</th><th>Ride Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($buses_result && $buses_result->num_rows > 0): ?>
                        <?php while($bus = $buses_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $bus['id'] ?></td>
                            <td><strong><?= htmlspecialchars($bus['bus_name']) ?></strong></td>
                            <td>
                                <?php if (!empty($bus['end_location'])): ?>
                                    <span class="status-badge status-active"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($bus['end_location']) ?></span>
                                <?php else: ?>
                                    <span style="color:#888;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($bus['status'] ?? 'active') ?>"><?= ucfirst($bus['status'] ?? 'Active') ?></span></td>
                            <td><span class="status-badge status-<?= strtolower($bus['ride_status'] ?? 'pending') ?>"><i class="fas <?= ($bus['ride_status'] ?? 'pending') == 'pending' ? 'fa-clock' : (($bus['ride_status'] ?? 'pending') == 'active' ? 'fa-play' : 'fa-check-circle') ?>"></i> <?= ucfirst($bus['ride_status'] ?? 'Pending') ?></span></td>
                            <td>
                                <a href='#' onclick="openBlockModal(<?= $bus['id'] ?>)" class="action-link block" title="Block Dates">
                                    <i class="fas fa-calendar-times"></i> Block Dates
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="padding: 30px; color: #888;">No bus found for your account.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============ BLOCKED DATES LIST ============ -->
    <?php if ($blocked_dates_result && $blocked_dates_result->num_rows > 0): ?>
        <div class="section-title" style="margin-top: 30px;">
            <span><i class="fas fa-calendar-times" style="color:#ff6b6b;"></i> Upcoming Blocked Dates</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>#</th><th>Bus</th><th>Date</th><th>Reason</th><th>Action</th></tr></thead>
                <tbody>
                    <?php $i = 1; while($bd = $blocked_dates_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($bd['bus_name']) ?></strong><br><small style="color:#888;"><?= htmlspecialchars($bd['bus_number']) ?></small></td>
                            <td><span class="status-badge status-blocked"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($bd['unavailable_date'])) ?></span></td>
                            <td><?= htmlspecialchars($bd['reason'] ?? 'N/A') ?></td>
                            <td><a href="unblock_date.php?id=<?= $bd['id'] ?>" class="action-link delete" onclick="return confirm('Unblock this date?')"><i class="fas fa-unlock"></i> Unblock</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============ PASSENGER SEAT BOOKINGS ============ -->
    <div class="section-title">
        <span><i class="fas fa-calendar-check"></i> Passenger Seat Bookings</span>
        <div><a href="#" class="btn btn-primary btn-sm"><i class="fas fa-file-excel"></i> Export</a></div>
    </div>

    <form method="GET" class="filter-bar" id="filterForm">
        <select name="route_category" id="destinationSelect">
            <option value="">-- All Destinations --</option>
            <?php if($routes_result) while($r = $routes_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($r['end_location']) ?>" <?= (isset($_GET['route_category']) && $_GET['route_category'] == $r['end_location']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['end_location']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        
        <select name="bus_id" id="busSelect">
            <option value="">-- All Buses --</option>
            <?php 
            $all_buses_result->data_seek(0);
            while($b = $all_buses_result->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>" <?= (isset($_GET['bus_id']) && $_GET['bus_id'] == $b['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['bus_name'] . ' (' . $b['bus_number'] . ')') ?>
                </option>
            <?php endwhile; ?>
        </select>
        
        <input type="date" name="journey_date" value="<?= isset($_GET['journey_date']) ? $_GET['journey_date'] : '' ?>" style="color-scheme: dark;">
        <input type="text" name="search" placeholder="Search customer..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
        <a href="admin_dashboard.php" class="reset-link"><i class="fas fa-undo"></i> Reset</a>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Ref Code</th><th>Bus</th><th>Destination</th><th>Date</th>
                    <th>Seats</th><th>Boarding</th><th>Dropping</th><th>Total Fare</th>
                    <th>Customer</th><th>Ride Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong style="color:#ffb700;"><?= htmlspecialchars($row['ref_code']) ?></strong></td>
                        <td><?= htmlspecialchars($row['bus_title']) ?></td>
                        <td><?= htmlspecialchars($row['route_category'] ?? 'N/A') ?></td>
                        <td><?= $row['journey_date'] ?></td>
                        <td><?= $row['seat_numbers'] ?></td>
                        <td><?= htmlspecialchars($row['boarding_point'] ?? $row['pickup_location'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['dropping_point'] ?? $row['dropoff_location'] ?? 'N/A') ?></td>
                        <td><?php if (isset($row['total_fare']) && $row['total_fare'] > 0): ?><strong style="color:#4ade80;">Rs. <?= number_format($row['total_fare'], 2) ?></strong><?php else: ?><span style="color:#888;">N/A</span><?php endif; ?></td>
                        <td><?= htmlspecialchars($row['full_name'] ?? $row['email'] ?? 'N/A') ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($row['ride_status'] ?? 'pending') ?>">
                                <i class="fas <?= ($row['ride_status'] ?? 'pending') == 'pending' ? 'fa-clock' : (($row['ride_status'] ?? 'pending') == 'active' ? 'fa-play' : 'fa-check-circle') ?>"></i>
                                <?= ucfirst($row['ride_status'] ?? 'Pending') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="padding: 30px; color: #888;">No bookings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============ CONTACT MESSAGES ============ -->
    <?php if ($role === 'super_admin'): ?>
        <div class="section-title" style="margin-top: 40px;">
            <span><i class="fas fa-envelope"></i> Customer Contact Messages</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    $msg_result = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 50");
                    if ($msg_result && $msg_result->num_rows > 0) {
                        while($msg = $msg_result->fetch_assoc()) {
                            echo "<tr>
                                    <td>" . htmlspecialchars($msg['name']) . "</td>
                                    <td>" . htmlspecialchars($msg['email']) . "</td>
                                    <td style='text-align:left;max-width:300px;word-wrap:break-word;'>" . htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : '') . "</td>
                                    <td>" . date('Y-m-d', strtotime($msg['created_at'])) . "</td>
                                    <td><a href='delete_message.php?id=" . $msg['id'] . "' onclick='return confirm(\"Delete this message?\")' style='color:#ff6b6b;text-decoration:none;'><i class='fas fa-trash'></i></a></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='padding:20px;color:#888;'>No messages found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- ============ BLOCK MODAL ============ -->
<div class="modal-overlay" id="blockModal">
    <div class="modal-box">
        <h3><i class="fas fa-calendar-times"></i> Block Bus Date Range</h3>
        <form action="block_bus_action.php" method="POST">
            <input type="hidden" name="bus_id" id="modal_bus_id">
            <label>Start Date:</label>
            <input type="date" name="start_date" id="start_date" required min="<?= date('Y-m-d') ?>">
            <label>End Date:</label>
            <input type="date" name="end_date" id="end_date" required min="<?= date('Y-m-d') ?>">
            <label>Reason (Optional):</label>
            <input type="text" name="reason" placeholder="Service, Holiday, Breakdown...">
            <button type="submit" name="block_bus" class="btn btn-primary"><i class="fas fa-lock"></i> Confirm Block</button>
            <button type="button" onclick="closeBlockModal()" class="btn btn-close"><i class="fas fa-times"></i> Cancel</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#filterRouteSelect').select2({
        placeholder: "-- All Destinations --",
        allowClear: true,
        width: '300px'
    });
    
    $('#destinationSelect').select2({
        placeholder: "-- All Destinations --",
        allowClear: true,
        width: '100%'
    });
    
    $('#busSelect').select2({
        placeholder: "-- All Buses --",
        allowClear: true,
        width: '100%'
    });
});

function openBlockModal(id) {
    document.getElementById('modal_bus_id').value = id;
    document.getElementById('blockModal').classList.add('active');
}
function closeBlockModal() {
    document.getElementById('blockModal').classList.remove('active');
}
document.getElementById('blockModal').addEventListener('click', function(e) {
    if (e.target === this) { closeBlockModal(); }
});
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this bus? All bookings will also be deleted!')) {
        window.location.href = 'admin_dashboard.php?delete_bus=' + id;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').min = today;
    document.getElementById('end_date').min = today;
});
</script>

</body>
</html>