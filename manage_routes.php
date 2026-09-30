<?php
include 'config.php';
require_once 'tab_auth.php';

requireTabAuth('admin_login.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['role'] !== 'super_admin') {
    header("Location: admin_login.php");
    exit();
}

$success = '';
$error = '';

// ============ FUNCTION: REORDER STOPS ============
function reorderStops($conn, $route_name) {
    $sql = "SELECT id FROM route_stops WHERE route_category = ? ORDER BY distance_km ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $route_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $order = 1;
    $update = $conn->prepare("UPDATE route_stops SET stop_order = ? WHERE id = ?");
    
    while ($row = $result->fetch_assoc()) {
        $update->bind_param("ii", $order, $row['id']);
        $update->execute();
        $order++;
    }
    
    $update->close();
    $stmt->close();
}

// ============ DELETE STOP ============
if (isset($_GET['delete_stop'])) {
    $stop_id = intval($_GET['delete_stop']);
    $route_name = isset($_GET['route']) ? $_GET['route'] : '';
    
    $del = $conn->prepare("DELETE FROM route_stops WHERE id = ?");
    $del->bind_param("i", $stop_id);
    if ($del->execute()) {
        $success = "Stop deleted successfully!";
        if (!empty($route_name)) {
            reorderStops($conn, $route_name);
        }
    } else {
        $error = "Error deleting stop!";
    }
    $del->close();
}

// ============ ADD STOP ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stop'])) {
    $route_name = trim($_POST['route_category']);
    $city_name = trim($_POST['city_name']);
    $distance_km = floatval($_POST['distance_km']);
    
    if (empty($route_name) || empty($city_name)) {
        $error = "Please fill all fields!";
    } else {
        $check = $conn->prepare("SELECT id FROM route_stops WHERE route_category = ? AND city_name = ?");
        $check->bind_param("ss", $route_name, $city_name);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "This city already exists in this route!";
        } else {
            $insert = $conn->prepare("INSERT INTO route_stops (route_category, city_name, distance_km, stop_order) VALUES (?, ?, ?, 0)");
            $insert->bind_param("ssd", $route_name, $city_name, $distance_km);
            
            if ($insert->execute()) {
                reorderStops($conn, $route_name);
                $success = "Stop added successfully!";
            } else {
                $error = "Error adding stop!";
            }
            $insert->close();
        }
        $check->close();
    }
}

// ============ GET ALL ROUTES ============
$routes_sql = "SELECT DISTINCT end_location FROM buses WHERE end_location IS NOT NULL AND status = 'active' ORDER BY end_location ASC";
$routes_result = $conn->query($routes_sql);

$all_routes = [];
while ($r = $routes_result->fetch_assoc()) {
    $all_routes[] = $r['end_location'];
}

$selected_route = isset($_GET['route']) ? mysqli_real_escape_string($conn, $_GET['route']) : '';

$stops_result = false;
if (!empty($selected_route)) {
    $stops_sql = "SELECT * FROM route_stops WHERE route_category = ? ORDER BY distance_km ASC";
    $stops_stmt = $conn->prepare($stops_sql);
    $stops_stmt->bind_param("s", $selected_route);
    $stops_stmt->execute();
    $stops_result = $stops_stmt->get_result();
}

$rate_sql = "SELECT rate_per_km FROM fare_settings LIMIT 1";
$rate_result = $conn->query($rate_sql);
$rate_per_km = $rate_result->fetch_assoc()['rate_per_km'] ?? 10.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body {
            overflow-x: hidden;
        }
        
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
            max-width: 1200px; 
            margin: 10px auto; 
            padding: 0 15px; 
            position: relative;
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .page-header h2 {
            color: #ffffff;
            font-weight: 700;
            font-size: 22px;
        }
        
        .page-header h2 i {
            margin-right: 10px;
            color: #ffb700;
        }
        
        /* ===== BUTTONS ===== */
        .btn {
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, #003580, #004d99);
            color: white; 
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.3);
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(0, 53, 128, 0.5);
        }
        
        .btn-success { 
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white; 
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-success:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }
        
        .btn-danger { 
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white; 
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        .btn-danger:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.5);
        }
        
        .btn-warning { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        .btn-warning:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .btn-secondary { 
            background: rgba(108, 117, 125, 0.2);
            color: #b0b0b0; 
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        .btn-secondary:hover { 
            background: rgba(108, 117, 125, 0.35);
            transform: translateY(-2px);
        }
        
        .btn-sm { padding: 6px 12px; font-size: 11px; }
        
        /* ===== ALERTS ===== */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 500;
            font-size: 13px;
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
        
        /* ===== ROUTE SELECTOR ===== */
        .route-selector {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 20px 22px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            margin-bottom: 20px;
            position: relative;
            z-index: 1000; /* Important - high z-index */
            overflow: visible; /* Important - allow dropdown to show */
        }
        
        .route-selector > label {
            font-weight: 600;
            color: #c9c9c9;
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .route-selector > label i {
            color: #ffb700;
            margin-right: 6px;
        }
        
        /* ===== CUSTOM DROPDOWN ===== */
        .custom-dropdown {
            position: relative;
            width: 100%;
            z-index: 2000;
        }
        
        .dropdown-input-wrapper {
            position: relative;
        }
        
        .dropdown-input-wrapper input {
            width: 100%;
            padding: 12px 42px 12px 16px;
            border: 2px solid #2a2a2a;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            background: #0a0a0a;
            color: #e0e0e0;
            box-sizing: border-box;
            cursor: text;
            transition: all 0.3s;
        }
        
        .dropdown-input-wrapper input:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        .dropdown-arrow {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffb700;
            cursor: pointer;
            transition: transform 0.3s;
            padding: 5px;
            font-size: 14px;
            z-index: 10;
        }
        
        .custom-dropdown.open .dropdown-arrow {
            transform: translateY(-50%) rotate(180deg);
        }
        
        .dropdown-list {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: #161616;
            border: 2px solid rgba(255, 183, 0, 0.4);
            border-radius: 10px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 9999; /* Very high z-index */
            display: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
        }
        
        .custom-dropdown.open .dropdown-list {
            display: block;
            animation: dropdownFadeIn 0.2s ease;
        }
        
        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-item {
            padding: 12px 16px;
            cursor: pointer;
            font-size: 13px;
            color: #e0e0e0;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 183, 0, 0.05);
        }
        
        .dropdown-item:last-child { border-bottom: none; }
        
        .dropdown-item:hover {
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            padding-left: 20px;
        }
        
        .dropdown-item i {
            color: #ffb700;
            font-size: 12px;
        }
        
        .dropdown-item.selected {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            font-weight: 600;
        }
        
        .dropdown-item.selected i {
            color: #0f0022;
        }
        
        .dropdown-item.no-results {
            text-align: center;
            color: #888;
            cursor: default;
            padding: 20px;
            justify-content: center;
        }
        
        .dropdown-item.no-results:hover {
            background: transparent;
            padding-left: 20px;
        }
        
        /* ===== ROUTE ACTIONS ===== */
        .route-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
        }
        
        .route-actions .btn {
            flex: 1;
            text-align: center;
            padding: 12px;
            font-size: 13px;
        }
        
        .route-hint {
            display: block;
            margin-top: 10px;
            font-size: 11px;
            color: #888;
        }
        
        .route-hint i {
            color: #ffb700;
        }
        
        /* ===== MAIN GRID ===== */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
            position: relative;
            z-index: 1; /* Lower z-index than route-selector */
        }
        
        /* ===== ADD STOP FORM ===== */
        .add-stop-form {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            height: fit-content;
        }
        
        .add-stop-form h3 {
            color: #ffffff;
            font-size: 15px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px dashed rgba(255, 183, 0, 0.15);
            font-weight: 600;
        }
        
        .add-stop-form h3 i {
            margin-right: 8px;
            color: #4ade80;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: #c9c9c9;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group label i {
            color: #ffb700;
            margin-right: 5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #2a2a2a;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            background: #0a0a0a;
            color: #e0e0e0;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        .form-group .hint {
            font-size: 10px;
            color: #888;
            margin-top: 4px;
            display: block;
            text-transform: none;
        }
        
        /* ===== STOPS TABLE ===== */
        .stops-table-wrapper {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .stops-table-wrapper h3 {
            color: #ffffff;
            font-size: 15px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px dashed rgba(255, 183, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-weight: 600;
        }
        
        .stops-table-wrapper h3 i {
            margin-right: 8px;
            color: #ffb700;
        }
        
        .badge-rate {
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: linear-gradient(135deg, #1a0033, #2d1b4e);
            color: #ffb700;
            padding: 12px 8px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255, 183, 0, 0.3);
        }
        
        td {
            padding: 12px 8px;
            border-bottom: 1px solid rgba(255, 183, 0, 0.08);
            text-align: center;
            color: #e0e0e0;
        }
        
        tr:hover td {
            background: rgba(255, 183, 0, 0.03);
        }
        
        .order-badge {
            display: inline-block;
            width: 26px;
            height: 26px;
            line-height: 26px;
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            border-radius: 50%;
            font-weight: 700;
            font-size: 11px;
            box-shadow: 0 4px 10px rgba(255, 183, 0, 0.3);
        }
        
        .km-badge {
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            font-size: 11px;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .fare-preview {
            color: #4ade80;
            font-weight: 700;
            font-size: 12px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #b0b0b0;
            font-size: 13px;
        }
        
        .no-data i {
            font-size: 45px;
            color: rgba(255, 183, 0, 0.3);
            margin-bottom: 12px;
            display: block;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            .route-actions {
                flex-direction: column;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    
    <div class="page-header">
        <h2><i class="fas fa-route"></i> Manage Routes & Stops</h2>
        <a href="fare_settings.php" class="btn btn-warning">
            <i class="fas fa-money-bill-wave"></i> Fare Settings
        </a>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <!-- ===== ROUTE SELECTOR ===== -->
    <div class="route-selector">
        <label><i class="fas fa-road"></i> Select Route (Bus Destination)</label>
        
        <div class="custom-dropdown" id="routeDropdown">
            <div class="dropdown-input-wrapper">
                <input type="text" 
                       id="routeInput" 
                       placeholder="Type or click to select a route..." 
                       value="<?= htmlspecialchars($selected_route) ?>"
                       autocomplete="off"
                       onkeyup="filterRouteList()"
                       onfocus="openRouteDropdown()">
                <i class="fas fa-chevron-down dropdown-arrow" id="dropdownArrow" onclick="toggleRouteDropdown(event)"></i>
            </div>
            
            <div class="dropdown-list" id="routeDropdownList">
                <div class="dropdown-item" data-value="" onclick="selectRoute('')">
                    <i class="fas fa-times-circle" style="color:#888;"></i> -- Clear Selection --
                </div>
                <?php foreach ($all_routes as $route): ?>
                    <div class="dropdown-item <?= ($selected_route === $route) ? 'selected' : '' ?>" 
                         data-value="<?= htmlspecialchars($route) ?>" 
                         onclick="selectRoute('<?= htmlspecialchars($route, ENT_QUOTES) ?>')">
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($route) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="route-actions">
            <button type="button" onclick="goToRoute()" class="btn btn-primary">
                <i class="fas fa-search"></i> Load Route
            </button>
            <button type="button" onclick="clearRoute()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>
        
        <span class="route-hint">
            <i class="fas fa-info-circle"></i> Type to search, or click the arrow to see all routes
        </span>
    </div>
    
    <?php if (!empty($selected_route)): ?>
    
    <div class="main-grid">
        
        <!-- ===== ADD STOP FORM ===== -->
        <div class="add-stop-form">
            <h3><i class="fas fa-plus-circle"></i> Add New Stop</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="route_category" value="<?= htmlspecialchars($selected_route) ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-city"></i> City / Stop Name</label>
                    <input type="text" name="city_name" placeholder="e.g. Galle" required>
                    <span class="hint">Enter the city or stop name</span>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-road"></i> Distance from Matara (km)</label>
                    <input type="number" name="distance_km" step="0.1" min="0" placeholder="e.g. 45" required>
                    <span class="hint">Distance in kilometers from Matara</span>
                </div>
                
                <button type="submit" name="add_stop" class="btn btn-success" style="width: 100%; padding: 13px;">
                    <i class="fas fa-plus"></i> Add Stop
                </button>
            </form>
        </div>
        
        <!-- ===== STOPS TABLE ===== -->
        <div class="stops-table-wrapper">
            <h3>
                <span><i class="fas fa-list"></i> Stops for "<?= htmlspecialchars($selected_route) ?>"</span>
                <span class="badge-rate">Rate: Rs. <?= number_format($rate_per_km, 2) ?>/km</span>
            </h3>
            
            <?php if ($stops_result && $stops_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>City</th>
                            <th>Distance</th>
                            <th>Fare from Matara</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($stop = $stops_result->fetch_assoc()): 
                            $fare_from_matara = $stop['distance_km'] * $rate_per_km;
                        ?>
                            <tr>
                                <td><span class="order-badge"><?= $stop['stop_order'] ?></span></td>
                                <td><strong><?= htmlspecialchars($stop['city_name']) ?></strong></td>
                                <td><span class="km-badge"><?= number_format($stop['distance_km'], 1) ?> km</span></td>
                                <td class="fare-preview">Rs. <?= number_format($fare_from_matara, 2) ?></td>
                                <td>
                                    <a href="manage_routes.php?route=<?= urlencode($selected_route) ?>&delete_stop=<?= $stop['id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this stop?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>No stops added yet for this route.</p>
                    <p style="font-size: 12px; margin-top: 5px;">Add the first stop using the form.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <?php else: ?>
        <div class="no-data" style="background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(20px); border-radius: 16px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 183, 0, 0.15); padding: 60px 20px;">
            <i class="fas fa-route" style="font-size: 60px;"></i>
            <p style="font-size: 15px; color: #ffffff;">Please select a route to manage its stops.</p>
            <p style="font-size: 12px; margin-top: 8px;">Use the dropdown above to select a route.</p>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 25px;">
        <a href="admin_dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
</div>

<script>
function openRouteDropdown() {
    document.getElementById('routeDropdown').classList.add('open');
}

function toggleRouteDropdown(event) {
    if (event) event.stopPropagation();
    const dropdown = document.getElementById('routeDropdown');
    dropdown.classList.toggle('open');
    
    if (dropdown.classList.contains('open')) {
        document.getElementById('routeInput').focus();
    }
}

function filterRouteList() {
    const input = document.getElementById('routeInput');
    const filter = input.value.toUpperCase();
    const list = document.getElementById('routeDropdownList');
    const items = list.getElementsByClassName('dropdown-item');
    
    let visibleCount = 0;
    
    // Remove existing no-results
    const oldNoResults = list.querySelector('.no-results');
    if (oldNoResults) oldNoResults.remove();
    
    for (let i = 0; i < items.length; i++) {
        if (items[i].getAttribute('data-value') === '') {
            items[i].style.display = '';
            continue;
        }
        
        const txtValue = items[i].textContent || items[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            items[i].style.display = '';
            visibleCount++;
        } else {
            items[i].style.display = 'none';
        }
    }
    
    if (visibleCount === 0 && filter !== '') {
        const noResults = document.createElement('div');
        noResults.className = 'dropdown-item no-results';
        noResults.innerHTML = '<i class="fas fa-search"></i> No routes found';
        list.appendChild(noResults);
    }
    
    document.getElementById('routeDropdown').classList.add('open');
}

function selectRoute(value) {
    const input = document.getElementById('routeInput');
    input.value = value;
    
    const items = document.querySelectorAll('.dropdown-item');
    items.forEach(item => {
        item.classList.remove('selected');
        if (item.getAttribute('data-value') === value) {
            item.classList.add('selected');
        }
    });
    
    document.getElementById('routeDropdown').classList.remove('open');
}

function goToRoute() {
    const input = document.getElementById('routeInput');
    const value = input.value.trim();
    
    if (!value) {
        alert('⚠️ Please select or type a route first!');
        input.focus();
        return;
    }
    
    const items = document.querySelectorAll('.dropdown-item');
    let found = false;
    
    items.forEach(item => {
        if (item.getAttribute('data-value') && 
            item.getAttribute('data-value').toLowerCase() === value.toLowerCase()) {
            found = true;
            window.location.href = 'manage_routes.php?route=' + encodeURIComponent(item.getAttribute('data-value'));
        }
    });
    
    if (!found) {
        alert('⚠️ Route "' + value + '" not found!\n\nPlease select from the dropdown list.');
        input.focus();
    }
}

function clearRoute() {
    window.location.href = 'manage_routes.php';
}

document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('routeDropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
    }
});

document.getElementById('routeInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        goToRoute();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('routeDropdown').classList.remove('open');
    }
});
</script>

</body>
</html>