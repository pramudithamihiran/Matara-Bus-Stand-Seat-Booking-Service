<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ GET STATISTICS ============
$total_buses_sql = "SELECT COUNT(*) as total FROM buses WHERE status = 'active'";
$total_buses_result = $conn->query($total_buses_sql);
$total_buses = $total_buses_result->fetch_assoc()['total'] ?? 0;

$total_routes_sql = "SELECT COUNT(DISTINCT route_category) as total FROM buses WHERE status = 'active'";
$total_routes_result = $conn->query($total_routes_sql);
$total_routes = $total_routes_result->fetch_assoc()['total'] ?? 0;

$total_bookings_sql = "SELECT COUNT(*) as total FROM bookings";
$total_bookings_result = $conn->query($total_bookings_sql);
$total_bookings = $total_bookings_result->fetch_assoc()['total'] ?? 0;

// ============ GET ROUTE BOOKING STATISTICS ============
$route_stats_sql = "SELECT b.route_category, COUNT(bk.id) as booking_count 
                    FROM bookings bk 
                    JOIN buses b ON bk.bus_id = b.id 
                    GROUP BY b.route_category 
                    ORDER BY booking_count DESC";
$route_stats_result = $conn->query($route_stats_sql);

$route_labels = [];
$route_data = [];

while($row = $route_stats_result->fetch_assoc()) {
    $route_labels[] = $row['route_category'];
    $route_data[] = $row['booking_count'];
}

// ============ GET TOP 4 ROUTES ============
$top_routes = [];
if ($route_stats_result && $route_stats_result->num_rows > 0) {
    $route_stats_result->data_seek(0);
    $i = 0;
    while($row = $route_stats_result->fetch_assoc()) {
        if ($i >= 4) break;
        $top_routes[] = $row;
        $i++;
    }
}

// ============ GET LATEST BOOKINGS ============
$latest_sql = "SELECT bk.*, b.bus_name FROM bookings bk JOIN buses b ON bk.bus_id = b.id ORDER BY bk.booking_time DESC LIMIT 3";
$latest_result = $conn->query($latest_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matara Bus Service | Online Seat Booking</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #003580;
            --accent: #ffb700;
            --dark: #00255a;
            --text-muted: #6c757d;
            --light-bg: #f8f9fa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: #333;
            padding-top: 70px;
        }

        /* ===== HERO SECTION ===== */
        .hero-section {
            position: relative;
            min-height: 80vh;
            background: linear-gradient(135deg, rgba(0,37,90,0.75), rgba(0,25,90,0.85)), 
                        url('images/parevi_duwa.jpg') no-repeat center center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 60px 20px;
        }

        .hero-content {
            max-width: 850px;
            animation: fadeIn 1.2s ease-out;
        }

        .hero-content .hero-badge {
            display: inline-block;
            background: rgba(255,183,0,0.2);
            color: var(--accent);
            padding: 6px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid rgba(255,183,0,0.3);
        }

        .hero-content .hero-badge i {
            margin-right: 6px;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: 1px;
            text-shadow: 0 3px 15px rgba(0,0,0,0.4);
        }

        .hero-content h1 .highlight {
            color: var(--accent);
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 35px;
            opacity: 0.9;
            line-height: 1.8;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--dark);
            padding: 16px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 50px;
            box-shadow: 0 8px 30px rgba(255, 183, 0, 0.4);
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(255, 255, 255, 0.3);
        }

        /* ===== STATS SECTION ===== */
        .stats-section {
            background: white;
            padding: 40px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border-bottom: 1px solid #eee;
        }

        .stats-container {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 30px;
            text-align: center;
        }

        .stat-item .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-item .number i {
            color: var(--accent);
            font-size: 1.8rem;
            margin-right: 5px;
        }

        .stat-item .label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ===== FEATURES SECTION ===== */
        .features-section {
            padding: 80px 20px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .section-title {
            color: var(--dark);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .section-title .highlight {
            color: var(--accent);
        }

        .section-subtitle {
            color: var(--text-muted);
            margin-bottom: 50px;
            font-size: 1.1rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.04);
            transition: all 0.4s ease;
            border: 1px solid #f0f0f0;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 53, 128, 0.08);
            border-color: rgba(0, 53, 128, 0.1);
        }

        .feature-card .icon-wrapper {
            width: 70px;
            height: 70px;
            background: rgba(0, 53, 128, 0.06);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
            transition: 0.3s;
        }

        .feature-card:hover .icon-wrapper {
            background: var(--primary);
            color: white;
        }

        .feature-card h3 {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.7;
        }

        /* ===== STATISTICS & CHART SECTION ===== */
        .stats-chart-section {
            background: white;
            padding: 60px 20px;
        }

        .stats-chart-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .chart-box {
            background: #f8faff;
            padding: 30px;
            border-radius: 20px;
            border: 1px solid #eef2f7;
        }

        .chart-box h3 {
            color: var(--primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }

        .chart-box h3 i {
            margin-right: 8px;
            color: var(--accent);
        }

        .chart-container {
            max-width: 350px;
            margin: 0 auto;
        }

        .stats-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stats-info .stat-card {
            background: #f8faff;
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            border: 1px solid #eef2f7;
        }

        .stats-info .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stats-info .stat-card .label {
            font-size: 12px;
            color: var(--text-muted);
        }

        .stats-info .stat-card .route-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            margin-top: 5px;
        }

        /* ===== LATEST BOOKINGS ===== */
        .latest-section {
            background: var(--light-bg);
            padding: 60px 20px;
        }

        .latest-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .latest-container .section-title {
            text-align: center;
        }

        .latest-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .latest-card {
            background: white;
            padding: 20px 25px;
            border-radius: 14px;
            border-left: 4px solid var(--accent);
            transition: 0.3s;
        }

        .latest-card:hover {
            background: #f0f4f8;
            transform: translateY(-3px);
        }

        .latest-card .ref {
            font-size: 12px;
            color: var(--text-muted);
        }

        .latest-card .ref strong {
            color: var(--primary);
        }

        .latest-card h4 {
            font-size: 16px;
            color: var(--dark);
            margin: 5px 0;
        }

        .latest-card p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 3px 0;
        }

        .latest-card .seat-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            color: var(--text-muted);
            padding: 40px 0;
        }

        /* ===== TESTIMONIALS ===== */
        .testimonials-section {
            padding: 60px 20px;
            max-width: 1100px;
            margin: 0 auto;
            text-align: center;
        }

        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .testimonial-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.04);
            text-align: left;
            border: 1px solid #f0f0f0;
        }

        .testimonial-card .stars {
            color: var(--accent);
            margin-bottom: 10px;
        }

        .testimonial-card p {
            font-size: 14px;
            color: #555;
            line-height: 1.7;
        }

        .testimonial-card .author {
            margin-top: 12px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .testimonial-card .author span {
            font-weight: 400;
            color: var(--text-muted);
            font-size: 12px;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .stats-chart-container {
                grid-template-columns: 1fr;
            }
            .stats-info {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.2rem;
            }
            .hero-content p {
                font-size: 1rem;
                padding: 0 15px;
            }
            .hero-section {
                min-height: 70vh;
                padding: 40px 15px;
            }
            .hero-content .hero-badge {
                font-size: 11px;
            }
            .btn-primary {
                padding: 14px 28px;
                font-size: 0.95rem;
                width: 100%;
                justify-content: center;
            }
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            .stat-item .number {
                font-size: 2rem;
            }
            .section-title {
                font-size: 1.8rem;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .latest-grid {
                grid-template-columns: 1fr;
            }
            .testimonial-grid {
                grid-template-columns: 1fr;
            }
            .stats-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 1.8rem;
            }
            .stat-item .number {
                font-size: 1.6rem;
            }
            .stats-container {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<!-- ===== HERO SECTION ===== -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-bus"></i> Sri Lanka's Trusted Bus Service
        </div>
        <h1>Book Your <span class="highlight">Bus Seat</span> Online</h1>
        <p>The safest, fastest and most convenient way to reserve your highway and long-distance journey seats in Sri Lanka.</p>
        <div class="hero-buttons">
            <a href="dashboard.php" class="btn-primary">
                <i class="fas fa-compass"></i> Explore Routes
            </a>
        </div>
    </div>
</section>

<!-- ===== STATS SECTION ===== -->
<section class="stats-section">
    <div class="stats-container">
        <div class="stat-item">
            <div class="number"><i class="fas fa-bus"></i><?= number_format($total_buses) ?></div>
            <div class="label">Active Buses</div>
        </div>
        <div class="stat-item">
            <div class="number"><i class="fas fa-route"></i><?= number_format($total_routes) ?></div>
            <div class="label">Routes</div>
        </div>
        <div class="stat-item">
            <div class="number"><i class="fas fa-ticket-alt"></i><?= number_format($total_bookings) ?></div>
            <div class="label">Total Bookings</div>
        </div>
        <div class="stat-item">
            <div class="number"><i class="fas fa-smile"></i>100%</div>
            <div class="label">Satisfaction</div>
        </div>
    </div>
</section>

<!-- ===== STATISTICS & CHART SECTION ===== -->
<section class="stats-chart-section">
    <div class="stats-chart-container">
        
        <!-- Chart -->
        <div class="chart-box">
            <h3><i class="fas fa-chart-pie"></i> Most Booked Routes</h3>
            <div class="chart-container">
                <canvas id="routeChart"></canvas>
            </div>
        </div>
        
        <!-- Stats Info -->
        <div class="stats-info">
            <?php if (!empty($top_routes)): ?>
                <?php 
                $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12'];
                foreach($top_routes as $index => $route): 
                ?>
                    <div class="stat-card">
                        <div class="number"><?= $route['booking_count'] ?></div>
                        <div class="label">Bookings</div>
                        <div class="route-name">
                            <i class="fas fa-circle" style="color:<?= $colors[$index % count($colors)] ?? '#888' ?>;font-size:10px;"></i> 
                            <?= htmlspecialchars($route['route_category'] ?? 'N/A') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="stat-card" style="grid-column:1/-1;">
                    <div class="number">0</div>
                    <div class="label">No route data available</div>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</section>

<!-- ===== FEATURES SECTION ===== -->
<section class="features-section">
    <h2 class="section-title">Why Book With <span class="highlight">Us?</span></h2>
    <p class="section-subtitle">Experience a seamless and modern passenger booking platform</p>

    <div class="features-grid">
        <div class="feature-card">
            <div class="icon-wrapper">
                <i class="fas fa-bolt"></i>
            </div>
            <h3>Easy & Fast Booking</h3>
            <p>Select your route, find available schedules, and reserve your seat in less than a minute.</p>
        </div>

        <div class="feature-card">
            <div class="icon-wrapper">
                <i class="fas fa-couch"></i>
            </div>
            <h3>Select Preferred Seats</h3>
            <p>View the real-time interactive bus seat layout map and choose your favorite, secure window seat.</p>
        </div>

        <div class="feature-card">
            <div class="icon-wrapper">
                <i class="fas fa-headset"></i>
            </div>
            <h3>24/7 Support Service</h3>
            <p>Our dedicated Matara Bus Station operations support desk is always here to assist you anytime.</p>
        </div>
    </div>
</section>

<!-- ===== LATEST BOOKINGS ===== -->
<section class="latest-section">
    <div class="latest-container">
        <h2 class="section-title">Latest <span class="highlight">Bookings</span></h2>
        <p class="section-subtitle">Recent seat reservations made by our passengers</p>

        <?php if ($latest_result && $latest_result->num_rows > 0): ?>
            <div class="latest-grid">
                <?php while($row = $latest_result->fetch_assoc()): ?>
                    <div class="latest-card">
                        <div class="ref">
                            <i class="fas fa-qrcode"></i> Ref: <strong><?= htmlspecialchars($row['ref_code'] ?? 'N/A') ?></strong>
                        </div>
                        <h4><i class="fas fa-bus" style="color:var(--primary);"></i> <?= htmlspecialchars($row['bus_name'] ?? 'N/A') ?></h4>
                        <p><i class="fas fa-calendar-day"></i> <?= isset($row['journey_date']) ? date('d M Y', strtotime($row['journey_date'])) : 'N/A' ?></p>
                        <p>
                            <i class="fas fa-chair"></i> 
                            <?php 
                            if (isset($row['seat_numbers']) && !empty($row['seat_numbers'])):
                                $seats = explode(',', $row['seat_numbers']);
                                foreach($seats as $seat): 
                            ?>
                                <span class="seat-badge"><?= trim($seat) ?></span>
                            <?php endforeach; 
                            endif; 
                            ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-ticket-alt" style="font-size:40px; color:#ddd;"></i>
                <p>No bookings yet. Be the first to book!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="testimonials-section">
    <h2 class="section-title">What Our <span class="highlight">Customers Say</span></h2>
    <p class="section-subtitle">Real experiences from real passengers</p>

    <div class="testimonial-grid">
        <div class="testimonial-card">
            <div class="stars">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
            </div>
            <p>"Amazing service! Booked my seat in just 2 minutes. The seat selection feature is very user-friendly."</p>
            <div class="author">Nuwan Perera <span>Colombo</span></div>
        </div>

        <div class="testimonial-card">
            <div class="stars">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
            </div>
            <p>"Very reliable bus service. The online booking system is smooth and hassle-free. Highly recommended!"</p>
            <div class="author">Kumari Silva <span>Matara</span></div>
        </div>

        <div class="testimonial-card">
            <div class="stars">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star-half-alt"></i>
            </div>
            <p>"Best bus service in the southern province. Comfortable seats and punctual departures. Will book again!"</p>
            <div class="author">Chamara Rathnayake <span>Tangalle</span></div>
        </div>
    </div>
</section>

<!-- ===== CHART.JS SCRIPT ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('routeChart').getContext('2d');
    
    const routeLabels = <?= json_encode($route_labels) ?>;
    const routeData = <?= json_encode($route_data) ?>;
    const routeColors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c'];
    
    if (routeLabels.length > 0) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: routeLabels,
                datasets: [{
                    data: routeData,
                    backgroundColor: routeColors.slice(0, routeLabels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: 'Poppins',
                                size: 11
                            },
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '60%'
            }
        });
    } else {
        document.querySelector('.chart-container').innerHTML = `
            <div style="text-align:center;padding:40px;color:#888;">
                <i class="fas fa-chart-pie" style="font-size:40px;color:#ddd;"></i>
                <p style="margin-top:10px;">No booking data available</p>
            </div>
        `;
    }
});
</script>

</body>
</html>