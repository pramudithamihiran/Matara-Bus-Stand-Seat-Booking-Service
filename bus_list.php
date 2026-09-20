<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ GET PARAMETERS ============
$route_name = isset($_GET['route_name']) ? mysqli_real_escape_string($conn, $_GET['route_name']) : '';
$selected_date = isset($_GET['journey_date']) ? mysqli_real_escape_string($conn, $_GET['journey_date']) : date('Y-m-d');
$search_destination = isset($_GET['search_destination']) ? mysqli_real_escape_string($conn, $_GET['search_destination']) : '';

if(empty($route_name)) {
    header("Location: dashboard.php");
    exit();
}

// ============ BUILD QUERY ============
$sql = "SELECT * FROM buses 
        WHERE route_category = '$route_name' 
        AND status = 'active'
        AND id NOT IN (
            SELECT bus_id FROM bus_unavailable_dates 
            WHERE unavailable_date = '$selected_date'
        )";

// ============ ADD DESTINATION SEARCH ============
if (!empty($search_destination)) {
    $sql .= " AND end_location LIKE '%$search_destination%'";
}

$sql .= " ORDER BY departure_time ASC";

$result = $conn->query($sql);

// ============ GET ROUTE DETAILS ============
$route_sql = "SELECT DISTINCT start_location, end_location FROM buses WHERE route_category = '$route_name' LIMIT 1";
$route_result = $conn->query($route_sql);
$route_data = $route_result->fetch_assoc();

// ============ GET UNIQUE DESTINATIONS ============
$dest_sql = "SELECT DISTINCT end_location FROM buses WHERE route_category = '$route_name' AND status = 'active' ORDER BY end_location ASC";
$dest_result = $conn->query($dest_sql);

$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
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
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); padding-top: 80px; padding-bottom: 40px; min-height: 100vh; }
        .main-wrapper { max-width: 1000px; margin: 0 auto; padding: 20px 15px; }
        
        .page-header { background: linear-gradient(135deg, #003580, #004d99); color: white; padding: 30px 35px; border-radius: 20px; margin-bottom: 25px; text-align: center; }
        .page-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .page-header h1 i { color: #ffb700; margin-right: 10px; }
        .page-header .back-link { display: inline-block; color: #ffb700; text-decoration: none; font-size: 14px; font-weight: 500; margin-top: 10px; transition: 0.3s; }
        .page-header .back-link:hover { color: white; }
        .page-header .back-link i { margin-right: 5px; }
        
        .filter-bar { background: white; padding: 20px 25px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar label { font-weight: 600; color: #333; font-size: 14px; }
        .filter-bar label i { color: #003580; margin-right: 5px; }
        .filter-bar input[type="date"] { padding: 10px 16px; border-radius: 10px; border: 2px solid #e0e0e0; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; flex: 1; min-width: 180px; background: #fafafa; }
        .filter-bar input[type="date"]:focus { border-color: #003580; box-shadow: 0 0 0 3px rgba(0,53,128,0.08); }
        
        .filter-bar .search-dest { padding: 10px 16px; border-radius: 10px; border: 2px solid #e0e0e0; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; flex: 1; min-width: 180px; background: #fafafa; }
        .filter-bar .search-dest:focus { border-color: #003580; box-shadow: 0 0 0 3px rgba(0,53,128,0.08); }
        
        .filter-bar .btn-check { background: #003580; color: white; padding: 10px 25px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-family: 'Poppins', sans-serif; white-space: nowrap; }
        .filter-bar .btn-check:hover { background: #00255a; transform: translateY(-2px); }
        .filter-bar .btn-check i { margin-right: 6px; }
        .filter-bar .clear-link { color: #888; text-decoration: none; font-size: 13px; font-weight: 500; }
        .filter-bar .clear-link:hover { color: #dc3545; text-decoration: underline; }
        
        /* Destination Dropdown */
        .dest-dropdown { position: relative; flex: 1; min-width: 180px; }
        .dest-dropdown input { width: 100%; padding: 10px 16px; border-radius: 10px; border: 2px solid #e0e0e0; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; background: #fafafa; }
        .dest-dropdown input:focus { border-color: #003580; box-shadow: 0 0 0 3px rgba(0,53,128,0.08); }
        .dest-dropdown .dropdown-list { display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 10px; max-height: 200px; overflow-y: auto; z-index: 100; }
        .dest-dropdown .dropdown-list.show { display: block; }
        .dest-dropdown .dropdown-list .item { padding: 10px 16px; cursor: pointer; border-bottom: 1px solid #eee; }
        .dest-dropdown .dropdown-list .item:hover { background: #e8f0fe; }
        
        .bus-card { background: white; padding: 25px 30px; border-radius: 16px; margin-bottom: 18px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center; border-left: 6px solid #ffb700; transition: all 0.3s ease; }
        .bus-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .bus-card .bus-info { flex: 1; }
        .bus-card .bus-info h3 { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 5px; }
        .bus-card .bus-info h3 i { color: #003580; margin-right: 8px; }
        .bus-card .bus-info .details { font-size: 14px; color: #666; margin: 3px 0; }
        .bus-card .bus-info .details i { width: 20px; color: #003580; }
        .bus-card .bus-info .badges { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
        .bus-card .bus-info .badge { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-ac { background: #d4edda; color: #155724; }
        .badge-luxury { background: #ffd700; color: #856404; }
        .badge-seats { background: #e8f0fe; color: #003580; }
        
        .bus-card .bus-status { text-align: right; }
        .bus-card .bus-status .btn-book { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s; font-family: 'Poppins', sans-serif; text-decoration: none; display: inline-block; }
        .bus-card .bus-status .btn-book:hover { background: #1e7e34; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(40,167,69,0.3); }
        .bus-card .bus-status .btn-book i { margin-right: 6px; }
        .bus-card .bus-status .btn-login { background: #003580; color: white; padding: 12px 30px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .bus-card .bus-status .btn-login:hover { background: #00255a; transform: translateY(-2px); }
        .bus-card .bus-status .btn-login i { margin-right: 6px; }
        
        .no-buses { text-align: center; padding: 60px 20px; background: white; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .no-buses i { font-size: 50px; color: #ddd; margin-bottom: 15px; }
        .no-buses h3 { color: #333; margin-bottom: 5px; }
        .no-buses p { color: #888; font-size: 14px; }
        
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar input[type="date"] { min-width: unset; }
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
        <!-- Route Detail එක ඉවත් කරලා -->
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Routes
        </a>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <div class="filter-bar">
        <form method="GET" action="bus_list.php" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap; width:100%;">
            <input type="hidden" name="route_name" value="<?= htmlspecialchars($route_name) ?>">
            
            <label><i class="fas fa-calendar-day"></i> Travel Date:</label>
            <input type="date" name="journey_date" id="journey_date" value="<?= $selected_date ?>" min="<?= date('Y-m-d') ?>">
            
            <!-- Destination Search -->
            <div class="dest-dropdown">
                <input type="text" id="dest_search" placeholder="Search Destination..." value="<?= htmlspecialchars($search_destination) ?>" onkeyup="filterDestinations()" onfocus="showDropdown()">
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
            
            <button type="submit" class="btn-check"><i class="fas fa-search"></i> Filter</button>
            <a href="bus_list.php?route_name=<?= urlencode($route_name) ?>" class="clear-link"><i class="fas fa-times-circle"></i> Clear</a>
        </form>
    </div>

    <!-- ===== BUS LIST ===== -->
    <?php if ($result && $result->num_rows > 0): ?>
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
                        <?php if ($row['bus_type'] == 'AC'): ?>
                            <span class="badge badge-ac"><i class="fas fa-snowflake"></i> AC</span>
                        <?php elseif ($row['bus_type'] == 'Luxury'): ?>
                            <span class="badge badge-luxury"><i class="fas fa-star"></i> Luxury</span>
                        <?php elseif ($row['bus_type'] == 'Semi-Luxury'): ?>
                            <span class="badge badge-luxury"><i class="fas fa-star-half-alt"></i> Semi-Luxury</span>
                        <?php endif; ?>
                        <span class="badge badge-seats"><i class="fas fa-chair"></i> <?= $row['seat_capacity'] ?? 40 ?> Seats</span>
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
                <?php else: ?>
                    No buses found for this route on the selected date.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
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
    // Auto submit form
    document.querySelector('.filter-bar form').submit();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('destDropdown');
    var input = document.getElementById('dest_search');
    if (!dropdown.contains(e.target) && e.target !== input) {
        dropdown.classList.remove('show');
    }
});
</script>

</body>
</html>