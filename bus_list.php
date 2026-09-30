<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ GET PARAMETERS ============
$route_name = isset($_GET['route_name']) ? mysqli_real_escape_string($conn, $_GET['route_name']) : '';
$selected_date = isset($_GET['journey_date']) ? mysqli_real_escape_string($conn, $_GET['journey_date']) : '';
$search_destination = isset($_GET['search_destination']) ? mysqli_real_escape_string($conn, $_GET['search_destination']) : '';

if(empty($route_name)) {
    header("Location: dashboard.php");
    exit();
}

// ============ GET ROUTE DETAILS ============
$route_sql = "SELECT DISTINCT start_location, end_location FROM buses WHERE route_category = '$route_name' LIMIT 1";
$route_result = $conn->query($route_sql);
$route_data = $route_result->fetch_assoc();

// ============ GET UNIQUE DESTINATIONS ============
$dest_sql = "SELECT DISTINCT end_location FROM buses WHERE route_category = '$route_name' AND status = 'active' ORDER BY end_location ASC";
$dest_result = $conn->query($dest_sql);

// ============ CHECK IF USER IS LOGGED IN ============
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if ($is_logged_in) {
    if (!isTabAuthenticated()) {
        $is_logged_in = false;
    }
}

// ============ BUILD QUERY ============
$result = false;

if (!empty($selected_date)) {
    $sql = "SELECT * FROM buses 
            WHERE route_category = '$route_name' 
            AND status = 'active'
            AND id NOT IN (
                SELECT bus_id FROM bus_unavailable_dates 
                WHERE unavailable_date = '$selected_date'
            )";
    
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    if ($selected_date === $today) {
        $sql .= " AND departure_time > '$current_time'";
    }

    if (!empty($search_destination)) {
        $sql .= " AND end_location LIKE '%$search_destination%'";
    }

    $sql .= " ORDER BY departure_time ASC";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buses - <?= htmlspecialchars($route_name) ?> Road</title>
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
        
        .main-wrapper { max-width: 1000px; margin: 0 auto; padding: 20px 15px; }
        
        /* ===== PAGE HEADER ===== */
        .page-header { 
            background: rgba(15, 0, 34, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.2);
            color: white; 
            padding: 30px 35px; 
            border-radius: 20px; 
            margin-bottom: 25px; 
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .page-header h1 { 
            font-size: 28px; 
            font-weight: 700; 
            margin-bottom: 5px;
            color: #ffffff;
        }
        
        .page-header h1 i { color: #ffb700; margin-right: 10px; }
        
        .page-header .back-link { 
            display: inline-block; 
            color: #ffb700; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500; 
            margin-top: 10px; 
            transition: 0.3s; 
        }
        
        .page-header .back-link:hover { color: white; }
        .page-header .back-link i { margin-right: 5px; }
        
        /* ===== FILTER BAR ===== */
        .filter-bar { 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            padding: 20px 25px; 
            border-radius: 16px; 
            margin-bottom: 25px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            display: flex; 
            gap: 15px; 
            align-items: center; 
            flex-wrap: wrap; 
            position: relative;
            z-index: 100; /* Important for dropdown to appear above other content */
            overflow: visible; /* Important */
        }
        
        .filter-bar label { 
            font-weight: 600; 
            color: #c9c9c9; 
            font-size: 14px; 
        }
        
        .filter-bar label i { color: #ffb700; margin-right: 5px; }
        
        /* ===== DATE INPUT WITH CUSTOM GOLD ICON ===== */
        .date-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        
        .date-input-wrapper input[type="date"] { 
            width: 100%;
            padding: 10px 45px 10px 16px; 
            border-radius: 10px; 
            border: 2px solid #2a2a2a; 
            font-family: 'Poppins', sans-serif; 
            font-size: 14px; 
            font-weight: 500;
            outline: none; 
            background: #0a0a0a;
            color: #e0e0e0;
            transition: all 0.3s;
        }
        
        .date-input-wrapper input[type="date"]:focus { 
            border-color: #ffb700; 
            box-shadow: 0 0 0 3px rgba(255, 183, 0, 0.15); 
        }
        
        .date-input-wrapper input[type="date"].error { 
            border-color: #dc3545; 
            background: #2a0a0a; 
        }
        
        /* Gold Calendar Icon */
        .date-input-wrapper .custom-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffb700;
            font-size: 18px;
            pointer-events: none;
            text-shadow: 0 0 10px rgba(255, 183, 0, 0.5);
            animation: glow 2s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { text-shadow: 0 0 10px rgba(255, 183, 0, 0.5); }
            50% { text-shadow: 0 0 20px rgba(255, 183, 0, 0.8); }
        }
        
        /* Browser default calendar icon hide කරන්න */
        .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            right: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        /* ===== DESTINATION DROPDOWN ===== */
        .dest-dropdown { 
            position: relative; 
            flex: 1; 
            min-width: 180px;
            z-index: 1000; /* High z-index */
        }
        
        .dest-dropdown input { 
            width: 100%; 
            padding: 10px 16px; 
            border-radius: 10px; 
            border: 2px solid #2a2a2a; 
            font-family: 'Poppins', sans-serif; 
            font-size: 14px; 
            outline: none; 
            background: #0a0a0a;
            color: #e0e0e0;
        }
        
        .dest-dropdown input:focus { 
            border-color: #ffb700; 
            box-shadow: 0 0 0 3px rgba(255, 183, 0, 0.1); 
        }
        
        .dest-dropdown input::placeholder { color: #666; }
        
        .dest-dropdown .dropdown-list { 
            display: none; 
            position: absolute; 
            top: calc(100% + 5px); 
            left: 0; 
            right: 0; 
            background: #161616;
            border: 1px solid rgba(255, 183, 0, 0.2); 
            border-radius: 10px; 
            max-height: 200px; 
            overflow-y: auto; 
            z-index: 9999; /* Very high z-index */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
        }
        
        .dest-dropdown .dropdown-list.show { display: block; }
        
        .dest-dropdown .dropdown-list .item { 
            padding: 10px 16px; 
            cursor: pointer; 
            border-bottom: 1px solid rgba(255, 183, 0, 0.05);
            color: #e0e0e0;
            transition: 0.2s;
        }
        
        .dest-dropdown .dropdown-list .item:hover { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
        }
        
        .dest-dropdown .dropdown-list .item:last-child {
            border-bottom: none;
        }
        
        /* ===== FILTER BUTTON ===== */
        .filter-bar .btn-check { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            padding: 10px 25px; 
            border: none; 
            border-radius: 10px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s; 
            font-family: 'Poppins', sans-serif; 
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .filter-bar .btn-check:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .filter-bar .btn-check i { margin-right: 6px; }
        
        .filter-bar .clear-link { 
            color: #888; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500; 
            transition: 0.3s;
        }
        
        .filter-bar .clear-link:hover { color: #ffb700; text-decoration: underline; }
        
        /* ===== BUS CARD ===== */
        .bus-card { 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            padding: 25px 30px; 
            border-radius: 16px; 
            margin-bottom: 18px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-left: 6px solid #ffb700; 
            transition: all 0.3s ease; 
        }
        
        .bus-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 40px rgba(255, 183, 0, 0.15);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .bus-card .bus-info { flex: 1; }
        
        .bus-card .bus-info h3 { 
            font-size: 18px; 
            font-weight: 700; 
            color: #ffffff; 
            margin-bottom: 5px; 
        }
        
        .bus-card .bus-info h3 i { color: #ffb700; margin-right: 8px; }
        
        .bus-card .bus-info .details { 
            font-size: 14px; 
            color: #b0b0b0; 
            margin: 3px 0; 
        }
        
        .bus-card .bus-info .details i { 
            width: 20px; 
            color: #ffb700; 
        }
        
        .bus-card .bus-info .badges { 
            margin-top: 10px; 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap; 
        }
        
        .bus-card .bus-info .badge { 
            padding: 4px 14px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 600; 
            display: inline-block; 
        }
        
        .badge-seats { 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .badge-active { 
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .bus-card .bus-status { text-align: right; }
        
        .bus-card .bus-status .btn-book { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 10px; 
            font-weight: 700; 
            font-size: 14px; 
            cursor: pointer; 
            transition: all 0.3s; 
            font-family: 'Poppins', sans-serif; 
            text-decoration: none; 
            display: inline-block;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .bus-card .bus-status .btn-book:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .bus-card .bus-status .btn-book i { margin-right: 6px; }
        
        .bus-card .bus-status .btn-login { 
            background: linear-gradient(135deg, #003580, #004d99);
            color: white; 
            padding: 12px 30px; 
            border-radius: 10px; 
            font-weight: 600; 
            font-size: 14px; 
            text-decoration: none; 
            display: inline-block; 
            transition: all 0.3s; 
        }
        
        .bus-card .bus-status .btn-login:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 53, 128, 0.4);
        }
        
        .bus-card .bus-status .btn-login i { margin-right: 6px; }
        
        /* ===== NO BUSES ===== */
        .no-buses { 
            text-align: center; 
            padding: 60px 20px; 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            border-radius: 16px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .no-buses i { 
            font-size: 50px; 
            color: rgba(255, 183, 0, 0.3); 
            margin-bottom: 15px; 
        }
        
        .no-buses h3 { color: #ffffff; margin-bottom: 5px; }
        .no-buses p { color: #b0b0b0; font-size: 14px; }
        
        /* ===== SELECT DATE PROMPT ===== */
        .select-date-prompt {
            text-align: center;
            padding: 60px 20px;
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .select-date-prompt i {
            font-size: 60px;
            color: #ffb700;
            margin-bottom: 20px;
        }
        
        .select-date-prompt h3 {
            color: #ffffff;
            margin-bottom: 10px;
            font-size: 22px;
        }
        
        .select-date-prompt p {
            color: #b0b0b0;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .select-date-prompt .arrow-icon {
            display: inline-block;
            animation: bounceUp 1.5s infinite;
            color: #ffb700;
            font-size: 24px;
        }
        
        @keyframes bounceUp {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .date-input-wrapper { min-width: unset; }
            .dest-dropdown { min-width: unset; }
            .bus-card { flex-direction: column; text-align: center; }
            .bus-card .bus-status { text-align: center; margin-top: 15px; width: 100%; }
            .bus-card .bus-status .btn-book { width: 100%; }
            .bus-card .bus-status .btn-login { width: 100%; }
            .page-header h1 { font-size: 22px; }
            .bus-card .bus-info .badges { justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .page-header { padding: 20px; }
            .bus-card { padding: 18px 15px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-wrapper">
    
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header">
        <h1><i class="fas fa-route"></i> <?= htmlspecialchars($route_name) ?> Road</h1>
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Routes
        </a>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <div class="filter-bar">
        <form method="GET" action="bus_list.php" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap; width:100%;" id="filterForm">
            <input type="hidden" name="route_name" value="<?= htmlspecialchars($route_name) ?>">
            
            <label><i class="fas fa-calendar-day"></i> Travel Date: <span style="color:#dc3545;">*</span></label>
            
            <!-- Custom Date Input with Gold Icon -->
            <div class="date-input-wrapper">
                <input type="date" name="journey_date" id="journey_date" value="<?= $selected_date ?>" min="<?= date('Y-m-d') ?>" required>
                <i class="fas fa-calendar-alt custom-icon"></i>
            </div>
            
            <!-- Destination Search -->
            <div class="dest-dropdown">
                <input type="text" id="dest_search" placeholder="Search Destination..." value="<?= htmlspecialchars($search_destination) ?>" onkeyup="filterDestinations()" onfocus="showDropdown()" autocomplete="off">
                <input type="hidden" name="search_destination" id="dest_hidden" value="<?= htmlspecialchars($search_destination) ?>">
                <div class="dropdown-list" id="destDropdown">
                    <div class="item" onclick="selectDestination('')">-- All Destinations --</div>
                    <?php if ($dest_result && $dest_result->num_rows > 0): ?>
                        <?php while($d = $dest_result->fetch_assoc()): ?>
                            <div class="item" onclick="selectDestination('<?= htmlspecialchars($d['end_location']) ?>')">
                                <?= htmlspecialchars($d['end_location']) ?>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-check" onclick="return validateDate()"><i class="fas fa-search"></i> Filter</button>
            <a href="bus_list.php?route_name=<?= urlencode($route_name) ?>" class="clear-link"><i class="fas fa-times-circle"></i> Clear</a>
        </form>
    </div>

    <!-- ===== BUS LIST ===== -->
    <?php if (empty($selected_date)): ?>
        <div class="select-date-prompt">
            <i class="fas fa-calendar-day"></i>
            <h3>Please Select a Travel Date</h3>
            <p>Choose a date from the filter above to see available buses for this route.</p>
            <div class="arrow-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
        </div>
    
    <?php elseif ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="bus-card">
                <div class="bus-info">
                    <h3><i class="fas fa-bus"></i> <?= htmlspecialchars($row['bus_name']) ?></h3>
                    <div class="details">
                        <i class="fas fa-route"></i> <?= htmlspecialchars($row['start_location']) ?> to <?= htmlspecialchars($row['end_location']) ?>
                    </div>
                    <div class="details">
                        <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($row['departure_time'])) ?> | 
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($row['bus_number']) ?>
                    </div>
                    <div class="badges">
                        <span class="badge badge-seats"><i class="fas fa-chair"></i> <?= $row['seat_capacity'] ?? 54 ?> Seats</span>
                        <span class="badge badge-active"><i class="fas fa-check-circle"></i> Active</span>
                    </div>
                </div>
                <div class="bus-status">
                    <?php if ($is_logged_in): ?>
                        <a href="select_seats.php?bus_id=<?= $row['id'] ?>&date=<?= $selected_date ?>" class="btn-book">
                            <i class="fas fa-chair"></i> BOOK SEAT
                        </a>
                    <?php else: ?>
                        <a href="login.php?redirect_bus_id=<?= $row['id'] ?>&date=<?= $selected_date ?>" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login to Book
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-buses">
            <i class="fas fa-bus"></i>
            <h3>No Buses Available</h3>
            <p>
                <?php if (!empty($search_destination)): ?>
                    No buses found for destination: <strong><?= htmlspecialchars($search_destination) ?></strong>
                <?php elseif ($selected_date === date('Y-m-d')): ?>
                    No more buses available for today. All buses have already departed.<br>
                    <small style="color:#888;">Please try tomorrow or another date.</small>
                <?php else: ?>
                    No buses found for this route on the selected date.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
function validateDate() {
    const dateInput = document.getElementById('journey_date');
    
    if (!dateInput.value || dateInput.value === '') {
        alert('⚠️ Please select a travel date first!\n\nYou need to choose a date to check bus availability.');
        dateInput.classList.add('error');
        dateInput.focus();
        dateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    
    const today = new Date().toISOString().split('T')[0];
    if (dateInput.value < today) {
        alert('⚠️ Invalid date!\n\nPlease select a future date.');
        dateInput.classList.add('error');
        dateInput.focus();
        return false;
    }
    
    dateInput.classList.remove('error');
    return true;
}

document.getElementById('journey_date').addEventListener('change', function() {
    if (this.value && this.value !== '') {
        this.classList.remove('error');
    }
});

function showDropdown() {
    document.getElementById('destDropdown').classList.add('show');
}

function filterDestinations() {
    var input = document.getElementById('dest_search');
    var filter = input.value.toUpperCase();
    var dropdown = document.getElementById('destDropdown');
    var items = dropdown.getElementsByClassName('item');
    
    for (var i = 0; i < items.length; i++) {
        var txtValue = items[i].textContent || items[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            items[i].style.display = '';
        } else {
            items[i].style.display = 'none';
        }
    }
    dropdown.classList.add('show');
}

function selectDestination(value) {
    document.getElementById('dest_search').value = value;
    document.getElementById('dest_hidden').value = value;
    document.getElementById('destDropdown').classList.remove('show');
    
    if (validateDate()) {
        document.getElementById('filterForm').submit();
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('destDropdown');
    var input = document.getElementById('dest_search');
    var destWrapper = document.querySelector('.dest-dropdown');
    
    if (destWrapper && !destWrapper.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Also close dropdown on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('destDropdown').classList.remove('show');
    }
});
</script>

</body>
</html>