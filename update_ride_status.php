<!-- ===== BOOKINGS TABLE WITH RIDE STATUS ===== -->
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