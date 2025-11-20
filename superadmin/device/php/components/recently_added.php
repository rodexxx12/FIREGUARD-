<div class="card mt-4">
    <div class="card-header d-flex align-items-center">
        <i class="fas fa-clock me-2"></i>
        Recently Added Devices
    </div>
    <div class="card-body">
        <?php if (empty($recentlyAdded)): ?>
            <div class="alert alert-info">No recently added devices found.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($recentlyAdded as $device): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars($device['device_number']) ?></strong>
                            <span><?= htmlspecialchars($device['serial_number']) ?></span>
                        </div>
                        <small class="text-muted">
                            <i class="far fa-clock"></i> <?= date('M j, Y', strtotime($device['created_at'])) ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div> 