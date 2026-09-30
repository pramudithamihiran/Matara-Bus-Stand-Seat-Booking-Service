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

// ============ UPDATE FARE RATE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
    $rate_per_km = floatval($_POST['rate_per_km']);
    
    if ($rate_per_km <= 0) {
        $error = "Rate must be greater than 0!";
    } else {
        $check = $conn->query("SELECT id FROM fare_settings LIMIT 1");
        
        if ($check->num_rows > 0) {
            $update = $conn->prepare("UPDATE fare_settings SET rate_per_km = ? WHERE id = (SELECT id FROM (SELECT id FROM fare_settings LIMIT 1) AS t)");
            $update->bind_param("d", $rate_per_km);
            if ($update->execute()) {
                $success = "Fare rate updated successfully!";
            } else {
                $error = "Error updating fare rate!";
            }
            $update->close();
        } else {
            $insert = $conn->prepare("INSERT INTO fare_settings (rate_per_km) VALUES (?)");
            $insert->bind_param("d", $rate_per_km);
            if ($insert->execute()) {
                $success = "Fare rate added successfully!";
            } else {
                $error = "Error adding fare rate!";
            }
            $insert->close();
        }
    }
}

// ============ GET CURRENT RATE ============
$rate_sql = "SELECT * FROM fare_settings LIMIT 1";
$rate_result = $conn->query($rate_sql);
$fare_settings = $rate_result->fetch_assoc();
$rate_per_km = $fare_settings['rate_per_km'] ?? 10.00;
$updated_at = $fare_settings['updated_at'] ?? null;

// ============ GET STATISTICS ============
$routes_count = $conn->query("SELECT COUNT(DISTINCT end_location) as total FROM buses WHERE end_location IS NOT NULL")->fetch_assoc()['total'] ?? 0;
$stops_count = $conn->query("SELECT COUNT(*) as total FROM route_stops")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fare Settings - Admin</title>
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
        
        .container { 
            max-width: 900px; 
            margin: 10px auto; 
            padding: 0 15px; 
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
        
        .btn-warning { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        .btn-warning:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
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
        
        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 22px 18px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .stat-card i {
            font-size: 28px;
            color: #ffb700;
            margin-bottom: 8px;
        }
        
        .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        
        .stat-card .label {
            font-size: 11px;
            color: #b0b0b0;
            font-weight: 500;
            margin-top: 4px;
        }
        
        .stat-card.green i { color: #4ade80; }
        .stat-card.orange i { color: #ffb700; }
        
        /* ===== MAIN CARD ===== */
        .main-card {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            margin-bottom: 20px;
        }
        
        .main-card h3 {
            color: #ffffff;
            font-size: 16px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px dashed rgba(255, 183, 0, 0.15);
            font-weight: 600;
        }
        
        .main-card h3 i {
            margin-right: 8px;
            color: #ffb700;
        }
        
        /* ===== CURRENT RATE DISPLAY ===== */
        .current-rate {
            background: linear-gradient(135deg, #1a0033, #2d1b4e);
            border: 1px solid rgba(255, 183, 0, 0.3);
            color: white;
            padding: 30px 25px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 40px rgba(255, 183, 0, 0.1) inset;
            position: relative;
            overflow: hidden;
        }
        
        .current-rate::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,183,0,0.15) 0%, transparent 70%);
            top: -150px;
            right: -100px;
            pointer-events: none;
        }
        
        .current-rate .label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
            color: #ffb700;
            font-weight: 600;
            position: relative;
        }
        
        .current-rate .rate-value {
            font-size: 48px;
            font-weight: 700;
            color: #ffb700;
            line-height: 1;
            text-shadow: 0 0 30px rgba(255, 183, 0, 0.5);
            position: relative;
        }
        
        .current-rate .rate-unit {
            font-size: 15px;
            opacity: 0.8;
            margin-top: 8px;
            color: #d0d0d0;
            position: relative;
        }
        
        .current-rate .updated {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 12px;
            color: #b0b0b0;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 183, 0, 0.2);
            position: relative;
        }
        
        .current-rate .updated i {
            color: #ffb700;
            margin-right: 5px;
        }
        
        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 18px;
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
            margin-right: 6px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #2a2a2a;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            outline: none;
            background: #0a0a0a;
            color: #e0e0e0;
            box-sizing: border-box;
            font-weight: 600;
            color-scheme: dark;
        }
        
        .form-group input:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        .form-group .hint {
            font-size: 11px;
            color: #888;
            margin-top: 6px;
            display: block;
        }
        
        /* ===== EXAMPLE BOX ===== */
        .example-box {
            background: rgba(255, 183, 0, 0.08);
            border-left: 4px solid #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.2);
            padding: 18px 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .example-box h4 {
            color: #ffb700;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
        
        .example-box h4 i {
            color: #ffb700;
            margin-right: 6px;
        }
        
        .example-box p {
            font-size: 13px;
            color: #d0d0d0;
            margin: 5px 0;
            line-height: 1.7;
        }
        
        .example-box strong {
            color: #ffffff;
        }
        
        .example-box hr {
            border: none;
            border-top: 1px dashed rgba(255, 183, 0, 0.2);
            margin: 10px 0;
        }
        
        .example-box .highlight {
            color: #4ade80;
            font-weight: 700;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .current-rate .rate-value {
                font-size: 38px;
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
        <h2><i class="fas fa-money-bill-wave"></i> Fare Settings</h2>
        <a href="manage_routes.php" class="btn btn-warning">
            <i class="fas fa-route"></i> Manage Routes
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
    
    <!-- ===== STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-route"></i>
            <div class="number"><?= $routes_count ?></div>
            <div class="label">Total Routes</div>
        </div>
        <div class="stat-card green">
            <i class="fas fa-map-marker-alt"></i>
            <div class="number"><?= $stops_count ?></div>
            <div class="label">Total Stops</div>
        </div>
        <div class="stat-card orange">
            <i class="fas fa-money-bill-wave"></i>
            <div class="number">Rs. <?= number_format($rate_per_km, 2) ?></div>
            <div class="label">Per KM Rate</div>
        </div>
    </div>
    
    <!-- ===== CURRENT RATE ===== -->
    <div class="current-rate">
        <div class="label">Current Rate</div>
        <div class="rate-value">Rs. <?= number_format($rate_per_km, 2) ?></div>
        <div class="rate-unit">per kilometer</div>
        <?php if ($updated_at): ?>
            <div class="updated">
                <i class="fas fa-clock"></i> Last updated: <?= date('M d, Y h:i A', strtotime($updated_at)) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ===== UPDATE FORM ===== -->
    <div class="main-card">
        <h3><i class="fas fa-edit"></i> Update Fare Rate</h3>
        
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-money-bill"></i> Rate per Kilometer (Rs.)</label>
                <input type="number" name="rate_per_km" step="0.01" min="0.01" 
                       value="<?= number_format($rate_per_km, 2, '.', '') ?>" 
                       placeholder="e.g. 10.00" required>
                <span class="hint">Enter the fare rate for 1 kilometer</span>
            </div>
            
            <button type="submit" name="update_rate" class="btn btn-success" style="width: 100%; padding: 14px; font-size: 14px;">
                <i class="fas fa-save"></i> Update Rate
            </button>
        </form>
        
        <!-- ===== EXAMPLE CALCULATION ===== -->
        <div class="example-box">
            <h4><i class="fas fa-calculator"></i> How Fare is Calculated</h4>
            <p><strong>Formula:</strong> Fare = Distance (km) × Rate per km × Number of Seats</p>
            <hr>
            <p><strong>Example:</strong> Customer travels from <strong>Matara</strong> to <strong>Galle</strong> (45 km)</p>
            <p>• Distance: <strong>45 km</strong></p>
            <p>• Rate: <strong>Rs. <?= number_format($rate_per_km, 2) ?>/km</strong></p>
            <p>• Fare per seat: 45 × <?= number_format($rate_per_km, 2) ?> = <span class="highlight">Rs. <?= number_format(45 * $rate_per_km, 2) ?></span></p>
            <p>• If booking <strong>2 seats</strong>: <?= number_format(45 * $rate_per_km, 2) ?> × 2 = <span class="highlight">Rs. <?= number_format(45 * $rate_per_km * 2, 2) ?></span></p>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="admin_dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
</div>

</body>
</html>