<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ GET PARAMETERS ============
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
$date_from_url = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$bus_name = "SUPER COACH";
$route_name = "";
$start_location = "";
$end_location = "";
$route_category = "";

// ============ GET BUS DETAILS ============
if ($bus_id > 0) {
    $bus_query = $conn->prepare("SELECT bus_name, route_category, start_location, end_location FROM buses WHERE id = ?");
    $bus_query->bind_param("i", $bus_id);
    $bus_query->execute();
    $bus_result = $bus_query->get_result();
    
    if ($bus_result && $bus_result->num_rows > 0) {
        $bus_data = $bus_result->fetch_assoc();
        $bus_name = $bus_data['bus_name'];
        $route_name = $bus_data['end_location'];
        $route_category = $bus_data['route_category'];
        $start_location = $bus_data['start_location'];
        $end_location = $bus_data['end_location'];
    }
    $bus_query->close();
}

// ============ GET STOPS FOR THIS ROUTE ============
$stops = [];
if (!empty($route_name)) {
    $stops_sql = "SELECT city_name, distance_km FROM route_stops WHERE route_category = ? ORDER BY distance_km ASC";
    $stops_stmt = $conn->prepare($stops_sql);
    $stops_stmt->bind_param("s", $route_name);
    $stops_stmt->execute();
    $stops_result = $stops_stmt->get_result();
    
    while ($stop = $stops_result->fetch_assoc()) {
        $stops[] = $stop;
    }
    $stops_stmt->close();
}

// ============ GET FARE RATE ============
$rate_sql = "SELECT rate_per_km FROM fare_settings LIMIT 1";
$rate_result = $conn->query($rate_sql);
$rate_per_km = $rate_result->fetch_assoc()['rate_per_km'] ?? 10.00;

// ============ GET BOOKED SEATS ============
$booked_seats = [];
if ($bus_id > 0 && !empty($date_from_url)) {
    $sql = "SELECT seat_numbers FROM bookings WHERE journey_date = '$date_from_url' AND bus_id = '$bus_id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $seats = explode(',', $row['seat_numbers']);
            foreach ($seats as $seat) {
                $clean = trim($seat);
                if ($clean !== '') {
                    $booked_seats[] = intval($clean);
                }
            }
        }
    }
}

// ============ CHECK IF BUS IS BLOCKED ============
$is_blocked = false;
if ($bus_id > 0 && !empty($date_from_url)) {
    $blocked_sql = "SELECT id FROM bus_unavailable_dates WHERE bus_id = '$bus_id' AND unavailable_date = '$date_from_url'";
    $blocked_result = $conn->query($blocked_sql);
    $is_blocked = $blocked_result->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($bus_name) ?> | Select Seats</title>
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
        
        /* ===== FULL WIDTH WRAPPER ===== */
        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 25px;
            position: relative;
            z-index: 5;
        }
        
        /* ===== HEADER ROW - BACK BUTTON + BUS NAME ===== */
        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            position: relative;
            z-index: 5;
        }
        
        /* ===== BACK BUTTON ===== */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 183, 0, 0.1);
            border: 2px solid rgba(255, 183, 0, 0.4);
            color: #ffb700;
            padding: 10px 22px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            white-space: nowrap;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            transform: translateX(-5px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .back-btn i {
            font-size: 14px;
        }
        
        /* ===== HEADER INFO (Bus Name + Subtitle) ===== */
        .header-info {
            text-align: right;
            flex: 1;
        }
        
        .header-info h2 {
            color: #ffffff;
            font-weight: 700;
            font-size: 24px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .header-info h2 i {
            color: #ffb700;
            font-size: 22px;
            filter: drop-shadow(0 0 10px rgba(255, 183, 0, 0.5));
        }
        
        .header-info p {
            color: #b0b0b0;
            font-size: 13px;
            margin: 4px 0 0;
        }
        
        /* ===== 2-COLUMN GRID LAYOUT ===== */
        .booking-layout {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 25px;
            align-items: start;
        }
        
        /* ===== LEFT PANEL ===== */
        .left-panel {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
            position: sticky;
            top: 110px;
        }
        
        .form-group { margin-bottom: 15px; }
        
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #c9c9c9;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group label i {
            margin-right: 5px;
            color: #ffb700;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #2a2a2a;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            background: #0a0a0a;
            color: #e0e0e0;
            box-sizing: border-box;
            color-scheme: dark;
        }
        
        .form-group input:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        .input-hint {
            color: #888;
            font-size: 11px;
            display: block;
            margin-top: 4px;
            font-weight: 400;
            text-transform: none;
        }
        
        /* ===== SEARCHABLE DROPDOWN ===== */
        .searchable-dropdown { position: relative; width: 100%; }
        
        .searchable-dropdown input {
            width: 100%;
            padding: 12px 40px 12px 15px;
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
        
        .searchable-dropdown input:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        .searchable-dropdown .dropdown-arrow-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffb700;
            cursor: pointer;
            transition: transform 0.3s;
            font-size: 13px;
            padding: 5px;
            z-index: 5;
        }
        
        .searchable-dropdown.open .dropdown-arrow-icon {
            transform: translateY(-50%) rotate(180deg);
        }
        
        .searchable-dropdown .dropdown-options {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: #161616;
            border: 2px solid rgba(255, 183, 0, 0.4);
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 999;
            display: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .searchable-dropdown.open .dropdown-options { display: block; }
        
        .searchable-dropdown .dropdown-option {
            padding: 10px 15px;
            cursor: pointer;
            font-size: 13px;
            color: #e0e0e0;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255, 183, 0, 0.05);
        }
        
        .searchable-dropdown .dropdown-option:last-child { border-bottom: none; }
        
        .searchable-dropdown .dropdown-option:hover {
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            padding-left: 20px;
        }
        
        .searchable-dropdown .dropdown-option i {
            color: #ffb700;
            font-size: 11px;
        }
        
        .searchable-dropdown .dropdown-option.selected {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            font-weight: 600;
        }
        
        .searchable-dropdown .dropdown-option.selected i {
            color: #0f0022;
        }
        
        .searchable-dropdown .dropdown-option .distance {
            margin-left: auto;
            font-size: 11px;
            opacity: 0.7;
        }
        
        /* ===== FARE DISPLAY ===== */
        .fare-display {
            display: none;
            background: rgba(255, 183, 0, 0.08);
            border-left: 4px solid #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.2);
            padding: 15px 18px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .fare-display.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fare-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .fare-row:last-child { margin-bottom: 0; }
        
        .fare-row .label { color: #b0b0b0; }
        
        .fare-row .label i {
            color: #ffb700;
            margin-right: 5px;
            width: 15px;
        }
        
        .fare-row .value {
            font-weight: 600;
            color: #ffb700;
        }
        
        .fare-divider {
            border: none;
            border-top: 1px dashed rgba(255, 183, 0, 0.3);
            margin: 8px 0;
        }
        
        .fare-total { font-size: 15px; }
        
        .fare-total .value {
            font-size: 20px;
            color: #4ade80;
        }
        
        /* ===== RIGHT PANEL - BUS FRAME ===== */
        .right-panel {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .bus-frame {
            background: rgba(15, 0, 34, 0.85);
            backdrop-filter: blur(20px);
            padding: 25px 20px;
            border-radius: 30px;
            border: 3px solid #ffb700;
            position: relative;
            overflow-x: auto;
            box-shadow: 0 0 40px rgba(255, 183, 0, 0.15);
        }
        
        .bus-title {
            text-align: center;
            font-size: 13px;
            color: #ffb700;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px dashed rgba(255, 183, 0, 0.2);
        }
        
        .driver-area {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            padding-right: 40px;
            align-items: center;
            gap: 12px;
        }
        
        .steering {
            width: 42px;
            height: 42px;
            border: 3px solid #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            font-size: 20px;
            background: rgba(220, 53, 69, 0.1);
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.4);
        }
        
        .driver-label {
            font-size: 11px;
            color: #ff6b6b;
            font-weight: 700;
            background: rgba(220, 53, 69, 0.15);
            padding: 3px 14px;
            border-radius: 12px;
            border: 1px solid rgba(220, 53, 69, 0.4);
            letter-spacing: 1px;
        }
        
        .bus-layout {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }
        
        .seat-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 50px;
        }
        
        .left-side { display: flex; gap: 8px; }
        .right-side { display: flex; gap: 8px; }
        
        .door-box {
            width: 80px;
            height: 38px;
            border: 2px dashed rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border-radius: 8px;
            font-size: 9px;
            background: rgba(220, 53, 69, 0.1);
        }
        
        .door-box i { margin-right: 4px; font-size: 12px; }
        
        .seat {
            width: 44px;
            height: 44px;
            border: 2px solid rgba(255, 183, 0, 0.3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #b0b0b0;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(22, 22, 22, 0.85);
            user-select: none;
        }
        
        .seat:hover:not(.booked):not(.selected) {
            transform: scale(1.08);
            border-color: #ffb700;
            color: #ffb700;
            box-shadow: 0 0 20px rgba(255, 183, 0, 0.4);
        }
        
        .seat.selected {
            background: linear-gradient(135deg, #ffb700, #f5a623) !important;
            color: #0f0022 !important;
            border-color: #ffb700 !important;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.6);
            transform: scale(1.08);
        }
        
        .seat.booked {
            background: rgba(220, 53, 69, 0.3) !important;
            color: #ff6b6b !important;
            border-color: rgba(220, 53, 69, 0.5) !important;
            cursor: not-allowed;
            opacity: 0.8;
        }
        
        .seat.booked::after {
            content: '\f00d';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 14px;
        }
        
        .seat.booked span { display: none; }
        
        .last-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px dashed rgba(255, 183, 0, 0.15);
        }
        
        /* ===== LEGEND ===== */
        .legend {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 20px;
            font-size: 13px;
            color: #b0b0b0;
            flex-wrap: wrap;
        }
        
        .legend-item { display: flex; align-items: center; gap: 8px; }
        
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 5px;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .legend-box.available { background: rgba(22, 22, 22, 0.85); }
        .legend-box.selected { background: linear-gradient(135deg, #ffb700, #f5a623); border-color: #ffb700; }
        .legend-box.booked { background: rgba(220, 53, 69, 0.5); border-color: rgba(220, 53, 69, 0.7); }
        
        /* ===== SUMMARY ===== */
        .summary {
            background: rgba(255, 183, 0, 0.1);
            border: 1px solid rgba(255, 183, 0, 0.3);
            padding: 16px 22px;
            border-radius: 12px;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .summary .seats-info {
            font-size: 15px;
            color: #ffb700;
        }
        
        .summary .seats-info strong {
            font-size: 22px;
        }
        
        /* ===== CONFIRM BUTTON ===== */
        .confirm-btn {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .confirm-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .confirm-btn:disabled {
            background: rgba(108, 117, 125, 0.3);
            color: #888;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none;
        }
        
        .confirm-btn i { margin-right: 10px; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) {
            .booking-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .left-panel {
                position: static;
            }
            .seat-row {
                gap: 30px;
            }
        }
        
        @media (max-width: 700px) {
            body { padding-top: 120px; }
            .main-wrapper { padding: 0 12px; }
            .left-panel, .right-panel { padding: 18px; }
            .seat { width: 34px; height: 34px; font-size: 10px; }
            .left-side, .right-side { gap: 5px; }
            .seat-row { gap: 15px; }
            .door-box { width: 60px; height: 30px; font-size: 7px; }
            .steering { width: 35px; height: 35px; font-size: 16px; }
            .driver-area { padding-right: 20px; }
            
            /* Mobile - Header stack */
            .header-row {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .header-info {
                text-align: center;
            }
            .header-info h2 {
                justify-content: center;
                font-size: 20px;
            }
        }
        
        @media (max-width: 480px) {
            body { padding-top: 110px; }
            .seat { width: 28px; height: 28px; font-size: 9px; border-radius: 6px; }
            .left-side, .right-side { gap: 4px; }
            .seat-row { gap: 10px; }
            .last-row { gap: 4px; }
            .door-box { width: 50px; height: 26px; font-size: 6px; }
            .header-info h2 { font-size: 18px; }
            .bus-title { font-size: 11px; letter-spacing: 2px; }
            .back-btn { padding: 8px 16px; font-size: 12px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-wrapper">
    
    <!-- ===== HEADER ROW - BACK BUTTON + BUS NAME ===== -->
    <div class="header-row">
        
        <!-- Back Button (Left) -->
        <a href="bus_list.php?route_name=<?= urlencode($route_category) ?>&journey_date=<?= $date_from_url ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Buses
        </a>
        
        <!-- Bus Name + Subtitle (Right) -->
        <div class="header-info">
            <h2><i class="fas fa-bus"></i> <?= htmlspecialchars($bus_name) ?></h2>
            <p>Select your preferred seats</p>
        </div>
        
    </div>

    <!-- ===== 2-COLUMN LAYOUT ===== -->
    <div class="booking-layout">
        
        <!-- ===== LEFT PANEL - FORM & FARE ===== -->
        <div class="left-panel">
            
            <div class="form-group">
                <label><i class="fas fa-calendar-day"></i> Travel Date</label>
                <form method="GET" action="">
                    <input type="hidden" name="bus_id" value="<?= $bus_id ?>">
                    <input type="date" name="date" value="<?= $date_from_url ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()" style="width:100%; padding:12px 15px; border:2px solid #2a2a2a; border-radius:10px; font-family:'Poppins', sans-serif; font-size:14px; outline:none; background:#0a0a0a; color:#e0e0e0; color-scheme:dark;">
                </form>
            </div>

            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Boarding Point</label>
                
                <div class="searchable-dropdown" id="boardingDropdown">
                    <input type="text" 
                           id="boardingInput" 
                           placeholder="Type or select boarding..."
                           value="<?= htmlspecialchars($start_location) ?>"
                           autocomplete="off"
                           onkeyup="filterBoarding()"
                           onfocus="openBoarding()">
                    <i class="fas fa-chevron-down dropdown-arrow-icon" onclick="toggleBoarding(event)"></i>
                    <div class="dropdown-options" id="boardingOptions">
                        <?php foreach ($stops as $stop): ?>
                            <div class="dropdown-option <?= ($stop['city_name'] === $start_location) ? 'selected' : '' ?>" 
                                 data-value="<?= htmlspecialchars($stop['city_name']) ?>" 
                                 data-distance="<?= $stop['distance_km'] ?>"
                                 onclick="selectBoarding('<?= htmlspecialchars($stop['city_name']) ?>', <?= $stop['distance_km'] ?>)">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?= htmlspecialchars($stop['city_name']) ?>
                                <span class="distance"><?= number_format($stop['distance_km'], 1) ?> km</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <span class="input-hint">Select your boarding location</span>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-flag-checkered"></i> Dropping Point</label>
                
                <div class="searchable-dropdown" id="droppingDropdown">
                    <input type="text" 
                           id="droppingInput" 
                           placeholder="Type or select dropping..."
                           value="<?= htmlspecialchars($end_location) ?>"
                           autocomplete="off"
                           onkeyup="filterDropping()"
                           onfocus="openDropping()">
                    <i class="fas fa-chevron-down dropdown-arrow-icon" onclick="toggleDropping(event)"></i>
                    <div class="dropdown-options" id="droppingOptions">
                        <?php foreach ($stops as $stop): ?>
                            <div class="dropdown-option <?= ($stop['city_name'] === $end_location) ? 'selected' : '' ?>" 
                                 data-value="<?= htmlspecialchars($stop['city_name']) ?>" 
                                 data-distance="<?= $stop['distance_km'] ?>"
                                 onclick="selectDropping('<?= htmlspecialchars($stop['city_name']) ?>', <?= $stop['distance_km'] ?>)">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?= htmlspecialchars($stop['city_name']) ?>
                                <span class="distance"><?= number_format($stop['distance_km'], 1) ?> km</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <span class="input-hint">Select your destination</span>
            </div>
            
            <input type="hidden" id="boardingValue" value="<?= htmlspecialchars($start_location) ?>">
            <input type="hidden" id="droppingValue" value="<?= htmlspecialchars($end_location) ?>">
            
            <!-- ===== FARE DISPLAY ===== -->
            <div class="fare-display" id="fareDisplay">
                <div class="fare-row">
                    <span class="label"><i class="fas fa-route"></i> Distance:</span>
                    <span class="value" id="fareDistance">0 km</span>
                </div>
                <div class="fare-row">
                    <span class="label"><i class="fas fa-money-bill"></i> Rate per km:</span>
                    <span class="value">Rs. <?= number_format($rate_per_km, 2) ?></span>
                </div>
                <div class="fare-row">
                    <span class="label"><i class="fas fa-chair"></i> Fare per seat:</span>
                    <span class="value" id="farePerSeat">Rs. 0.00</span>
                </div>
                <div class="fare-row">
                    <span class="label"><i class="fas fa-users"></i> Seats selected:</span>
                    <span class="value" id="fareSeats">0</span>
                </div>
                <hr class="fare-divider">
                <div class="fare-row fare-total">
                    <span class="label" style="font-weight:600;color:#ffb700;"><i class="fas fa-calculator"></i> Total:</span>
                    <span class="value" id="fareTotal">Rs. 0.00</span>
                </div>
            </div>

            <!-- ===== SUMMARY ===== -->
            <div class="summary">
                <div class="seats-info">
                    <i class="fas fa-chair"></i> Selected: <strong id="selectedCount">0</strong>
                </div>
            </div>

            <!-- ===== CONFIRM BUTTON ===== -->
            <button type="button" class="confirm-btn" id="confirmBtn" onclick="submitBooking()" disabled>
                <i class="fas fa-check-circle"></i> Confirm Booking
            </button>
        </div>
        
        <!-- ===== RIGHT PANEL - BUS SEAT MAP ===== -->
        <div class="right-panel">
            <div class="bus-frame">
                <div class="bus-title">
                    <i class="fas fa-bus"></i> <?= htmlspecialchars($bus_name) ?>
                </div>
                
                <div class="driver-area">
                    <span class="driver-label"><i class="fas fa-user"></i> DRIVER</span>
                    <div class="steering"><i class="fas fa-steering-wheel"></i></div>
                </div>

                <div class="bus-layout" id="busLayout">
                    <?php 
                    $seat_count = 1;
                    
                    for ($i = 1; $i <= 9; $i++) {
                        echo '<div class="seat-row">';
                        
                        echo '<div class="left-side">';
                        for ($j = 0; $j < 2; $j++) {
                            $is_booked = in_array($seat_count, $booked_seats);
                            $booked_class = $is_booked ? 'booked' : '';
                            echo '<div class="seat ' . $booked_class . '" id="seat_'.$seat_count.'" onclick="toggleSeat(this, '.$seat_count.')">'.$seat_count.'</div>';
                            $seat_count++;
                        }
                        echo '</div>';
                        
                        echo '<div class="right-side">';
                        for ($k = 0; $k < 3; $k++) {
                            $is_booked = in_array($seat_count, $booked_seats);
                            $booked_class = $is_booked ? 'booked' : '';
                            echo '<div class="seat ' . $booked_class . '" id="seat_'.$seat_count.'" onclick="toggleSeat(this, '.$seat_count.')">'.$seat_count.'</div>';
                            $seat_count++;
                        }
                        echo '</div>';
                        
                        echo '</div>';
                    }
                    
                    echo '<div class="seat-row">';
                    echo '<div class="door-box"><i class="fas fa-door-open"></i> REAR</div>';
                    
                    echo '<div class="right-side">';
                    for ($k = 0; $k < 3; $k++) {
                        $is_booked = in_array($seat_count, $booked_seats);
                        $booked_class = $is_booked ? 'booked' : '';
                        echo '<div class="seat ' . $booked_class . '" id="seat_'.$seat_count.'" onclick="toggleSeat(this, '.$seat_count.')">'.$seat_count.'</div>';
                        $seat_count++;
                    }
                    echo '</div>';
                    
                    echo '</div>';
                    
                    echo '<div class="last-row">';
                    for ($l = 0; $l < 6; $l++) {
                        $is_booked = in_array($seat_count, $booked_seats);
                        $booked_class = $is_booked ? 'booked' : '';
                        echo '<div class="seat ' . $booked_class . '" id="seat_'.$seat_count.'" onclick="toggleSeat(this, '.$seat_count.')">'.$seat_count.'</div>';
                        $seat_count++;
                    }
                    echo '</div>';
                    ?>
                </div>

                <div class="legend">
                    <div class="legend-item"><div class="legend-box available"></div> Available</div>
                    <div class="legend-item"><div class="legend-box selected"></div> Selected</div>
                    <div class="legend-item"><div class="legend-box booked"></div> Booked</div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
let selectedSeats = [];
let busId = <?= $bus_id ?>;
let ratePerKm = <?= $rate_per_km ?>;
let boardingDistance = 0;
let droppingDistance = 0;

document.addEventListener('DOMContentLoaded', function() {
    const boardingSelected = document.querySelector('#boardingOptions .dropdown-option.selected');
    const droppingSelected = document.querySelector('#droppingOptions .dropdown-option.selected');
    
    if (boardingSelected) {
        boardingDistance = parseFloat(boardingSelected.getAttribute('data-distance')) || 0;
    }
    if (droppingSelected) {
        droppingDistance = parseFloat(droppingSelected.getAttribute('data-distance')) || 0;
    }
    
    calculateFare();
});

function toggleBoarding(event) {
    if (event) event.stopPropagation();
    document.getElementById('boardingDropdown').classList.toggle('open');
    document.getElementById('droppingDropdown').classList.remove('open');
}

function openBoarding() {
    document.getElementById('boardingDropdown').classList.add('open');
    document.getElementById('droppingDropdown').classList.remove('open');
}

function filterBoarding() {
    const input = document.getElementById('boardingInput');
    const filter = input.value.toUpperCase();
    const options = document.querySelectorAll('#boardingOptions .dropdown-option');
    
    options.forEach(opt => {
        const text = opt.textContent || opt.innerText;
        if (text.toUpperCase().indexOf(filter) > -1) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    document.getElementById('boardingDropdown').classList.add('open');
}

function selectBoarding(cityName, distance) {
    document.getElementById('boardingInput').value = cityName;
    document.getElementById('boardingValue').value = cityName;
    boardingDistance = distance;
    
    document.querySelectorAll('#boardingOptions .dropdown-option').forEach(opt => {
        opt.classList.remove('selected');
        if (opt.getAttribute('data-value') === cityName) {
            opt.classList.add('selected');
        }
    });
    
    document.getElementById('boardingDropdown').classList.remove('open');
    calculateFare();
}

function toggleDropping(event) {
    if (event) event.stopPropagation();
    document.getElementById('droppingDropdown').classList.toggle('open');
    document.getElementById('boardingDropdown').classList.remove('open');
}

function openDropping() {
    document.getElementById('droppingDropdown').classList.add('open');
    document.getElementById('boardingDropdown').classList.remove('open');
}

function filterDropping() {
    const input = document.getElementById('droppingInput');
    const filter = input.value.toUpperCase();
    const options = document.querySelectorAll('#droppingOptions .dropdown-option');
    
    options.forEach(opt => {
        const text = opt.textContent || opt.innerText;
        if (text.toUpperCase().indexOf(filter) > -1) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    document.getElementById('droppingDropdown').classList.add('open');
}

function selectDropping(cityName, distance) {
    document.getElementById('droppingInput').value = cityName;
    document.getElementById('droppingValue').value = cityName;
    droppingDistance = distance;
    
    document.querySelectorAll('#droppingOptions .dropdown-option').forEach(opt => {
        opt.classList.remove('selected');
        if (opt.getAttribute('data-value') === cityName) {
            opt.classList.add('selected');
        }
    });
    
    document.getElementById('droppingDropdown').classList.remove('open');
    calculateFare();
}

document.addEventListener('click', function(e) {
    const boarding = document.getElementById('boardingDropdown');
    const dropping = document.getElementById('droppingDropdown');
    
    if (boarding && !boarding.contains(e.target)) {
        boarding.classList.remove('open');
    }
    if (dropping && !dropping.contains(e.target)) {
        dropping.classList.remove('open');
    }
});

function toggleSeat(element, seatNum) {
    if (element.classList.contains('booked')) {
        return;
    }
    
    if (selectedSeats.includes(seatNum)) {
        selectedSeats = selectedSeats.filter(s => s !== seatNum);
        element.classList.remove('selected');
    } else {
        selectedSeats.push(seatNum);
        element.classList.add('selected');
    }
    updateSummary();
    calculateFare();
}

function updateSummary() {
    document.getElementById('selectedCount').textContent = selectedSeats.length;
    document.getElementById('confirmBtn').disabled = selectedSeats.length === 0;
}

function calculateFare() {
    const fareDisplay = document.getElementById('fareDisplay');
    
    if (boardingDistance === 0 && droppingDistance === 0) {
        fareDisplay.classList.remove('show');
        return;
    }
    
    const distance = Math.abs(droppingDistance - boardingDistance);
    
    if (distance <= 0) {
        fareDisplay.classList.remove('show');
        return;
    }
    
    const farePerSeat = distance * ratePerKm;
    const seatCount = selectedSeats.length;
    const total = farePerSeat * seatCount;
    
    document.getElementById('fareDistance').textContent = distance.toFixed(1) + ' km';
    document.getElementById('farePerSeat').textContent = 'Rs. ' + farePerSeat.toFixed(2);
    document.getElementById('fareSeats').textContent = seatCount;
    document.getElementById('fareTotal').textContent = 'Rs. ' + total.toFixed(2);
    
    fareDisplay.classList.add('show');
}

function submitBooking() {
    const date = document.querySelector('input[name="date"]').value;
    const from = document.getElementById('boardingValue').value;
    const to = document.getElementById('droppingValue').value;

    if (!date) { alert("Please select a travel date!"); return; }
    if (!from) { alert("Please select your boarding point!"); return; }
    if (!to) { alert("Please select your dropping point!"); return; }
    if (selectedSeats.length === 0) { alert("Please select at least one seat!"); return; }

    const distance = Math.abs(droppingDistance - boardingDistance);
    
    if (distance <= 0) {
        alert("⚠️ Boarding and dropping points cannot be the same!");
        return;
    }
    
    const farePerSeat = distance * ratePerKm;
    const totalFare = farePerSeat * selectedSeats.length;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'confirm_booking.php';

    const data = {
        'bus_id': busId,
        'selected_seats': selectedSeats.join(','),
        'booking_date': date,
        'pickup': from,
        'dropoff': to,
        'route_name': '<?= htmlspecialchars($route_name) ?>',
        'distance_km': distance.toFixed(2),
        'fare_per_seat': farePerSeat.toFixed(2),
        'total_fare': totalFare.toFixed(2)
    };

    for (const key in data) {
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = key;
        hiddenField.value = data[key];
        form.appendChild(hiddenField);
    }

    document.body.appendChild(form);
    form.submit();
}
</script>

</body>
</html>