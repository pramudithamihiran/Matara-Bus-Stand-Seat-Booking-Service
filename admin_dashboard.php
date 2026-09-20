<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); 
    exit();
}

$role = $_SESSION['role'] ?? 'bus_owner';
$owner_username = $_SESSION['admin_username'] ?? 'Unknown';

// ============ DELETE BUS ============
if (isset($_GET['delete_bus']) && $role === 'super_admin') {
    $bus_id = intval($_GET['delete_bus']);
    
    $delete_bookings_sql = "DELETE FROM bookings WHERE bus_id = ?";
    $delete_bookings_stmt = $conn->prepare($delete_bookings_sql);
    $delete_bookings_stmt->bind_param("i", $bus_id);
    $delete_bookings_stmt->execute();
    $delete_bookings_stmt->close();
    
    $delete_sql = "DELETE FROM buses WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $bus_id);
    if ($delete_stmt->execute()) {
        $success = "Bus and all its bookings deleted successfully!";
    } else {
        $error = "Error deleting bus!";
    }
    $delete_stmt->close();
}

// ============ STATISTICS ============
$stats_sql = "SELECT COUNT(*) as total FROM buses";
if ($role !== 'super_admin') {
    $stats_sql .= " WHERE owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$stats_result = $conn->query($stats_sql);
$total_buses = $stats_result->fetch_assoc()['total'];

$booking_stats_sql = "SELECT COUNT(*) as total FROM bookings bk 
                       JOIN buses b ON bk.bus_id = b.id";
if ($role !== 'super_admin') {
    $booking_stats_sql .= " WHERE b.owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$booking_stats_result = $conn->query($booking_stats_sql);
$total_bookings = $booking_stats_result->fetch_assoc()['total'];

$today = date('Y-m-d');
$today_sql = "SELECT COUNT(*) as total FROM bookings WHERE journey_date = '$today'";
$today_result = $conn->query($today_sql);
$today_bookings = $today_result->fetch_assoc()['total'];

// ============ BUS LIST ============
$bus_sql = "SELECT * FROM buses";
$bus_conditions = [];

if ($role !== 'super_admin') {
    $bus_conditions[] = "owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
if (!empty($_GET['filter_route'])) {
    $bus_conditions[] = "route_category = '" . mysqli_real_escape_string($conn, $_GET['filter_route']) . "'";
}

if (count($bus_conditions) > 0) {
    $bus_sql .= " WHERE " . implode(" AND ", $bus_conditions);
}
$bus_sql .= " ORDER BY id DESC";
$buses_result = $conn->query($bus_sql);

// ============ BOOKINGS ============
$where_clauses = [];

if ($role !== 'super_admin') {
    $where_clauses[] = "b.owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
if (!empty($_GET['route_category'])) {
    $where_clauses[] = "b.route_category = '" . mysqli_real_escape_string($conn, $_GET['route_category']) . "'";
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

$sql = "SELECT bk.*, b.bus_name as bus_title, b.route_category, b.ride_status, u.full_name, u.email 
        FROM bookings bk 
        JOIN buses b ON bk.bus_id = b.id 
        LEFT JOIN users u ON bk.customer_email = u.email" . $where_sql . " ORDER BY bk.id DESC LIMIT 100";

$result = $conn->query($sql);

// ============ ROUTES FOR FILTER ============
$route_query = "SELECT DISTINCT route_category FROM buses WHERE route_category IS NOT NULL";
if ($role !== 'super_admin') {
    $route_query .= " AND owner_username = '" . mysqli_real_escape_string($conn, $owner_username) . "'";
}
$routes_result = $conn->query($route_query);

$all_buses_sql = "SELECT id, bus_name, bus_number FROM buses ORDER BY bus_name ASC";
$all_buses_result = $conn->query($all_buses_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Matara Bus Service</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f4f8; padding-top: 80px; padding-bottom: 40px; }
        .container { max-width: 1300px; margin: 20px auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.08); }
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .dash-header h2 { font-size: 24px; font-weight: 700; color: #003580; }
        .dash-header h2 i { color: #28a745; margin-right: 10px; }
        .dash-header .user-badge { background: #e8f0fe; padding: 8px 18px; border-radius: 30px; font-size: 14px; color: #003580; font-weight: 500; }
        .dash-header .user-badge i { margin-right: 6px; }
        .dash-header .user-badge .role-badge { background: #003580; color: white; padding: 2px 10px; border-radius: 20px; font-size: 11px; margin-left: 8px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px 20px; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #eef2f7; text-align: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card i { font-size: 32px; color: #003580; margin-bottom: 10px; }
        .stat-card .number { font-size: 28px; font-weight: 700; color: #1a1a2e; }
        .stat-card .label { font-size: 13px; color: #888; font-weight: 500; }
        .stat-card.green i { color: #28a745; } .stat-card.blue i { color: #007bff; } .stat-card.orange i { color: #fd7e14; }
        
        .filter-bar { background: #f8f9fa; padding: 20px; border-radius: 14px; margin-bottom: 20px; display: flex; gap: 12px; align-items: center; border: 1px solid #e9ecef; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 10px 16px; border-radius: 10px; border: 1px solid #dde1e6; font-family: 'Poppins', sans-serif; font-size: 13px; background: white; outline: none; min-width: 140px; flex: 1 1 auto; }
        .filter-bar select:focus, .filter-bar input:focus { border-color: #003580; box-shadow: 0 0 0 3px rgba(0,53,128,0.1); }
        .filter-bar .btn-filter { background: #003580; color: white; padding: 10px 24px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 13px; white-space: nowrap; flex: 0 0 auto; transition: all 0.2s; font-family: 'Poppins', sans-serif; }
        .filter-bar .btn-filter:hover { background: #00255a; transform: translateY(-1px); }
        .filter-bar .btn-filter i { margin-right: 6px; }
        .filter-bar .reset-link { color: #888; font-size: 13px; text-decoration: none; white-space: nowrap; flex: 0 0 auto; padding: 10px 5px; font-weight: 500; }
        .filter-bar .reset-link:hover { color: #003580; text-decoration: underline; }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th { background: linear-gradient(135deg, #003580, #004d99); color: white; padding: 14px 12px; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; }
        td { padding: 12px; border-bottom: 1px solid #eef2f7; text-align: center; vertical-align: middle; }
        tr:hover td { background: #f8faff; }
        
        .status-badge { padding: 4px 14px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; }
        .status-blocked { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #cce5ff; color: #004085; }
        
        .btn { padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; display: inline-block; transition: all 0.2s; }
        .btn-primary { background: #003580; color: white; } .btn-primary:hover { background: #00255a; }
        .btn-success { background: #28a745; color: white; } .btn-success:hover { background: #1e7e34; }
        .btn-danger { background: #dc3545; color: white; } .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 6px 14px; font-size: 12px; }
        
        .action-link { margin: 0 4px; text-decoration: none; font-weight: 500; }
        .action-link.edit { color: #fd7e14; } .action-link.delete { color: #dc3545; } .action-link.block { color: #007bff; }
        .action-link:hover { text-decoration: underline; }
        
        .section-title { font-size: 18px; font-weight: 600; color: #1a1a2e; margin: 30px 0 15px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .section-title i { margin-right: 10px; color: #003580; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 999; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; padding: 35px; border-radius: 20px; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.3s ease; }
        @keyframes modalIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-box h3 { font-size: 20px; color: #003580; margin-bottom: 20px; text-align: center; }
        .modal-box h3 i { margin-right: 10px; }
        .modal-box label { font-size: 13px; font-weight: 500; display: block; margin-top: 12px; color: #333; }
        .modal-box input { width: 100%; padding: 10px 14px; border-radius: 10px; border: 2px solid #e0e0e0; font-family: 'Poppins', sans-serif; margin-top: 4px; box-sizing: border-box; }
        .modal-box input:focus { border-color: #003580; outline: none; }
        .modal-box .btn { width: 100%; margin-top: 15px; text-align: center; }
        .modal-box .btn-close { background: #e9ecef; color: #333; width: 100%; margin-top: 8px; }
        .modal-box .btn-close:hover { background: #dde1e6; }
        
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .filter-bar { flex-direction: column; align-items: stretch; } .filter-bar select, .filter-bar input, .filter-bar .btn-filter, .filter-bar .reset-link { width: 100%; flex: none; min-width: unset; } .filter-bar .btn-filter { text-align: center; } .dash-header { flex-direction: column; align-items: flex-start; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } .container { padding: 15px; } }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">

    <div class="dash-header">
        <h2><i class="fas fa-chart-pie"></i> Dashboard</h2>
        <div class="user-badge">
            <i class="fas fa-user-cog"></i> 
            <?= htmlspecialchars($owner_username); ?> 
            <span class="role-badge">
                <?= $role === 'super_admin' ? 'Super Admin' : 'Bus Owner' ?>
            </span>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card blue"><i class="fas fa-bus"></i><div class="number"><?= $total_buses ?></div><div class="label">Total Buses</div></div>
        <div class="stat-card green"><i class="fas fa-ticket-alt"></i><div class="number"><?= $total_bookings ?></div><div class="label">Total Bookings</div></div>
        <div class="stat-card orange"><i class="fas fa-calendar-day"></i><div class="number"><?= $today_bookings ?></div><div class="label">Today's Bookings</div></div>
    </div>

    <!-- ============ BUS MANAGEMENT ============ -->
    <?php if ($role === 'super_admin'): ?>
        <div class="section-title">
            <span><i class="fas fa-list"></i> Registered Buses Management</span>
            <a href="add_bus.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add New Bus</a>
        </div>
        
        <form method="GET" style="margin-bottom: 15px;">
            <select name="filter_route" onchange="this.form.submit()" style="padding:10px 16px;border-radius:10px;border:1px solid #dde1e6;font-family:'Poppins',sans-serif;background:white;min-width:200px;">
                <option value="">-- All Routes --</option>
                <?php 
                $route_list = $conn->query("SELECT DISTINCT route_category FROM buses");
                while($r = $route_list->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($r['route_category']) ?>" <?= (isset($_GET['filter_route']) && $_GET['filter_route'] == $r['route_category']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['route_category']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>ID</th><th>Bus Name</th><th>Route</th><th>Number</th><th>Owner</th><th>Status</th><th>Ride Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($buses_result && $buses_result->num_rows > 0): ?>
                        <?php while($bus = $buses_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $bus['id'] ?></td>
                            <td><strong><?= htmlspecialchars($bus['bus_name']) ?></strong></td>
                            <td><?= htmlspecialchars($bus['route_category'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                            <td><?= htmlspecialchars($bus['owner_username']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($bus['status'] ?? 'active') ?>"><?= ucfirst($bus['status'] ?? 'Active') ?></span></td>
                            <td><span class="status-badge status-<?= strtolower($bus['ride_status'] ?? 'pending') ?>"><i class="fas <?= ($bus['ride_status'] ?? 'pending') == 'pending' ? 'fa-clock' : (($bus['ride_status'] ?? 'pending') == 'active' ? 'fa-play' : 'fa-check-circle') ?>"></i> <?= ucfirst($bus['ride_status'] ?? 'Pending') ?></span></td>
                            <td>
                                <a href='update_bus.php?id=<?= $bus['id'] ?>' class="action-link edit"><i class="fas fa-edit"></i></a>
                                <a href='#' onclick="confirmDelete(<?= $bus['id'] ?>)" class="action-link delete"><i class="fas fa-trash"></i></a>
                                <a href='#' onclick="openBlockModal(<?= $bus['id'] ?>)" class="action-link block"><i class="fas fa-calendar-times"></i></a>
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
                <thead><tr><th>ID</th><th>Bus Name</th><th>Route</th><th>Number</th><th>Status</th><th>Ride Status</th></tr></thead>
                <tbody>
                    <?php if ($buses_result && $buses_result->num_rows > 0): ?>
                        <?php while($bus = $buses_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $bus['id'] ?></td>
                            <td><strong><?= htmlspecialchars($bus['bus_name']) ?></strong></td>
                            <td><?= htmlspecialchars($bus['route_category'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($bus['status'] ?? 'active') ?>"><?= ucfirst($bus['status'] ?? 'Active') ?></span></td>
                            <td><span class="status-badge status-<?= strtolower($bus['ride_status'] ?? 'pending') ?>"><i class="fas <?= ($bus['ride_status'] ?? 'pending') == 'pending' ? 'fa-clock' : (($bus['ride_status'] ?? 'pending') == 'active' ? 'fa-play' : 'fa-check-circle') ?>"></i> <?= ucfirst($bus['ride_status'] ?? 'Pending') ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 30px; color: #888;">No bus found for your account.</td></tr>
                    <?php endif; ?>
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
        <select name="route_category" id="route_category">
            <option value="">-- All Routes --</option>
            <?php if($routes_result) while($r = $routes_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($r['route_category']) ?>" <?= (isset($_GET['route_category']) && $_GET['route_category'] == $r['route_category']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['route_category']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <select name="bus_id" id="bus_id">
            <option value="">-- All Buses --</option>
            <?php 
            $all_buses_result->data_seek(0);
            while($b = $all_buses_result->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>" <?= (isset($_GET['bus_id']) && $_GET['bus_id'] == $b['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['bus_name'] . ' (' . $b['bus_number'] . ')') ?>
                </option>
            <?php endwhile; ?>
        </select>
        <input type="date" name="journey_date" value="<?= isset($_GET['journey_date']) ? $_GET['journey_date'] : '' ?>">
        <input type="text" name="search" placeholder="Search customer..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
        <a href="admin_dashboard.php" class="reset-link"><i class="fas fa-undo"></i> Reset</a>
    </form>

    <!-- ===== BOOKINGS TABLE ===== -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Ref Code</th>
                    <th>Bus</th>
                    <th>Route</th>
                    <th>Date</th>
                    <th>Seats</th>
                    <th>Customer</th>
                    <th>Ride Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['ref_code']) ?></strong></td>
                        <td><?= htmlspecialchars($row['bus_title']) ?></td>
                        <td><?= htmlspecialchars($row['route_category'] ?? 'N/A') ?></td>
                        <td><?= $row['journey_date'] ?></td>
                        <td><?= $row['seat_numbers'] ?></td>
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
                    <tr><td colspan="7" style="padding: 30px; color: #888;">No bookings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============ CONTACT MESSAGES ============ -->
    <?php if ($role === 'super_admin'): ?>
        <div class="section-title" style="margin-top: 40px;">
            <span><i class="fas fa-envelope"></i> Customer Contact Messages</span>
            <a href="#" onclick="return confirm('Delete all messages?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Clear All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    $msg_sql = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 50";
                    $msg_result = $conn->query($msg_sql);
                    if ($msg_result && $msg_result->num_rows > 0) {
                        while($msg = $msg_result->fetch_assoc()) {
                            echo "<tr>
                                    <td>" . htmlspecialchars($msg['name']) . "</td>
                                    <td>" . htmlspecialchars($msg['email']) . "</td>
                                    <td style='text-align:left;max-width:300px;word-wrap:break-word;'>" . htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : '') . "</td>
                                    <td>" . date('Y-m-d', strtotime($msg['created_at'])) . "</td>
                                    <td>
                                        <a href='delete_message.php?id=" . $msg['id'] . "' onclick='return confirm(\"Delete this message?\")' style='color:#dc3545;text-decoration:none;'>
                                            <i class='fas fa-trash'></i>
                                        </a>
                                    </td>
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
        <h3><i class="fas fa-calendar-times" style="color:#007bff;"></i> Block Bus Date Range</h3>
        <form action="block_bus_action.php" method="POST">
            <input type="hidden" name="bus_id" id="modal_bus_id">
            <label>Start Date:</label>
            <input type="date" name="start_date" id="start_date" required min="<?= date('Y-m-d') ?>">
            <label>End Date:</label>
            <input type="date" name="end_date" id="end_date" required min="<?= date('Y-m-d') ?>">
            <button type="submit" name="block_bus" class="btn btn-primary"><i class="fas fa-lock"></i> Confirm Block</button>
            <button type="button" onclick="closeBlockModal()" class="btn btn-close"><i class="fas fa-times"></i> Cancel</button>
        </form>
    </div>
</div>

<script>
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