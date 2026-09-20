<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ GET PARAMETERS ============
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
$date_from_url = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$bus_name = "SUPER COACH";

// ============ GET BUS DETAILS ============
if ($bus_id > 0) {
    $bus_query = $conn->prepare("SELECT bus_name FROM buses WHERE id = ?");
    $bus_query->bind_param("i", $bus_id);
    $bus_query->execute();
    $bus_result = $bus_query->get_result();
    
    if ($bus_result && $bus_result->num_rows > 0) {
        $bus_data = $bus_result->fetch_assoc();
        $bus_name = $bus_data['bus_name'];
    }
    $bus_query->close();
}

// ============ GET BOOKED SEATS FOR THIS DATE (status නැතිව) ============
$booked_seats = [];
if ($bus_id > 0 && !empty($date_from_url)) {
    // status column එක නැති නිසා ඒක ඉවත් කරලා
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
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            padding-top: 80px;
            padding-bottom: 40px;
            min-height: 100vh;
        }
        
        .main-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px 15px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header h2 {
            color: #003580;
            font-weight: 700;
            font-size: 22px;
            margin: 0;
        }
        
        .header h2 i {
            color: #ffb700;
            margin-right: 8px;
        }
        
        .header p {
            color: #888;
            font-size: 13px;
            margin: 5px 0 0;
        }
        
        .booking-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.03);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group label i {
            margin-right: 5px;
            color: #003580;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        .form-group input:focus {
            border-color: #003580;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0,53,128,0.08);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .input-hint {
            color: #888;
            font-size: 11px;
            display: block;
            margin-top: 4px;
            font-weight: 400;
            text-transform: none;
        }
        
        .bus-frame {
            background: #f8faff;
            padding: 20px 15px;
            border-radius: 30px;
            border: 3px solid #003580;
            position: relative;
            margin-top: 10px;
            overflow-x: auto;
        }
        
        .bus-title {
            text-align: center;
            font-size: 11px;
            color: #003580;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px dashed #e8f0fe;
        }
        
        .driver-area {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 12px;
            padding-right: 20px;
            align-items: center;
            gap: 10px;
        }
        
        .steering {
            width: 35px;
            height: 35px;
            border: 3px solid #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            font-size: 16px;
            background: #fff5f5;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.2);
        }
        
        .driver-label {
            font-size: 10px;
            color: #dc3545;
            font-weight: 700;
            background: #fff5f5;
            padding: 2px 12px;
            border-radius: 12px;
            border: 1px solid #dc3545;
        }
        
        .bus-layout {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
        }
        
        .seat-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .left-side {
            display: flex;
            gap: 5px;
        }
        
        .right-side {
            display: flex;
            gap: 5px;
        }
        
        .door-box {
            width: 65px;
            height: 30px;
            border: 2px dashed #dc3545;
            color: #dc3545;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border-radius: 6px;
            font-size: 7px;
            background: #fff5f5;
        }
        
        .door-box i {
            margin-right: 3px;
            font-size: 10px;
        }
        
        .seat {
            width: 32px;
            height: 32px;
            border: 2px solid #d1d9e6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 600;
            color: #555;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fdfdfd;
            user-select: none;
        }
        
        .seat:hover:not(.booked):not(.selected) {
            transform: scale(1.08);
            border-color: #003580;
        }
        
        .seat.selected {
            background: #28a745 !important;
            color: white !important;
            border-color: #28a745 !important;
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
            transform: scale(1.05);
        }
        
        .seat.booked {
            background: #dc3545 !important;
            color: white !important;
            border-color: #dc3545 !important;
            cursor: not-allowed;
            opacity: 0.8;
        }
        
        .seat.booked::after {
            content: '\f00d';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 11px;
        }
        
        .seat.booked span {
            display: none;
        }
        
        .last-row {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 2px dashed #e8f0fe;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            font-size: 12px;
            color: #777;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .legend-box {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid #d1d9e6;
        }
        
        .legend-box.available {
            background: #fdfdfd;
        }
        
        .legend-box.selected {
            background: #28a745;
            border-color: #28a745;
        }
        
        .legend-box.booked {
            background: #dc3545;
            border-color: #dc3545;
        }
        
        .summary {
            background: #e8f0fe;
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .summary .seats-info {
            font-size: 14px;
            color: #003580;
        }
        
        .summary .seats-info strong {
            font-size: 18px;
        }
        
        .confirm-btn {
            background: linear-gradient(135deg, #003580, #004d99);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
            width: 100%;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .confirm-btn:hover {
            background: linear-gradient(135deg, #00255a, #003580);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,53,128,0.3);
        }
        
        .confirm-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none;
        }
        
        .confirm-btn i {
            margin-right: 8px;
        }
        
        @media (max-width: 600px) {
            .main-wrapper {
                padding: 15px 10px;
            }
            .booking-card {
                padding: 15px;
            }
            .seat {
                width: 26px;
                height: 26px;
                font-size: 7px;
            }
            .left-side {
                gap: 3px;
            }
            .right-side {
                gap: 3px;
            }
            .door-box {
                width: 46px;
                height: 24px;
                font-size: 6px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .bus-frame {
                padding: 10px 6px;
                border-radius: 16px;
            }
            .summary {
                flex-direction: column;
                text-align: center;
            }
            .seat-row {
                gap: 5px;
            }
        }
        
        @media (max-width: 400px) {
            .seat {
                width: 20px;
                height: 20px;
                font-size: 6px;
                border-width: 1.5px;
            }
            .left-side {
                gap: 2px;
            }
            .right-side {
                gap: 2px;
            }
            .door-box {
                width: 35px;
                height: 18px;
                font-size: 5px;
            }
            .last-row {
                gap: 2px;
            }
            .seat-row {
                gap: 3px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-wrapper">
    
    <div class="header">
        <h2><i class="fas fa-bus"></i> <?= htmlspecialchars($bus_name) ?></h2>
        <p>Select your preferred seats</p>
    </div>

    <div class="booking-card">
        
        <div class="form-group">
            <label><i class="fas fa-calendar-day"></i> Travel Date</label>
            <form method="GET" action="">
                <input type="hidden" name="bus_id" value="<?= $bus_id ?>">
                <input type="date" name="date" value="<?= $date_from_url ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()" style="width:100%; padding:12px 15px; border:2px solid #e0e0e0; border-radius:10px; font-family:'Poppins', sans-serif; font-size:14px; outline:none; background:#fafafa;">
            </form>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Boarding Point</label>
                <input type="text" id="pickup" placeholder="e.g. Matara">
                <span class="input-hint">Enter your boarding location</span>
            </div>
            <div class="form-group">
                <label><i class="fas fa-flag-checkered"></i> Dropping Point</label>
                <input type="text" id="dropoff" placeholder="e.g. Colombo">
                <span class="input-hint">Enter your destination</span>
            </div>
        </div>

        <!-- ===== BUS SEATS ===== -->
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
                
                // Rows 1-9: 2 left + 3 right = 5 seats per row (45 seats)
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
                
                // Row 10: REAR DOOR + 3 seats (46, 47, 48)
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
                
                // Row 11: Last row - 6 seats (49, 50, 51, 52, 53, 54)
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

            <!-- ===== LEGEND ===== -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-box available"></div>
                    Available
                </div>
                <div class="legend-item">
                    <div class="legend-box selected"></div>
                    Selected
                </div>
                <div class="legend-item">
                    <div class="legend-box booked"></div>
                    Booked
                </div>
            </div>
            
            <!-- ===== SUMMARY ===== -->
            <div class="summary">
                <div class="seats-info">
                    <i class="fas fa-chair"></i> Selected: <strong id="selectedCount">0</strong>
                </div>
            </div>

            <button type="button" class="confirm-btn" id="confirmBtn" onclick="submitBooking()">
                <i class="fas fa-check-circle"></i> Confirm Booking
            </button>
        </div>
    </div>
</div>

<script>
let selectedSeats = [];
let busId = <?= $bus_id ?>;

// ============ TOGGLE SEAT ============
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
}

// ============ UPDATE SUMMARY ============
function updateSummary() {
    document.getElementById('selectedCount').textContent = selectedSeats.length;
    document.getElementById('confirmBtn').disabled = selectedSeats.length === 0;
}

// ============ SUBMIT BOOKING ============
function submitBooking() {
    const date = document.querySelector('input[name="date"]').value;
    const from = document.getElementById('pickup').value.trim();
    const to = document.getElementById('dropoff').value.trim();

    if (!date) { alert("Please select a travel date!"); return; }
    if (!from) { alert("Please enter your boarding point!"); return; }
    if (!to) { alert("Please enter your dropping point!"); return; }
    if (selectedSeats.length === 0) { alert("Please select at least one seat!"); return; }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'confirm_booking.php';

    const data = {
        'bus_id': busId,
        'selected_seats': selectedSeats.join(','),
        'booking_date': date,
        'pickup': from,
        'dropoff': to
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