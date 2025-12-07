<?php if (isset($statusStats) && is_array($statusStats)): ?>
    <?php foreach ($statusStats as $stat): ?>
        <div class="col-md-3 mb-3 d-flex">
            <div class="stat-card <?= htmlspecialchars($stat['status']) ?> w-100">
                <div class="stat-icon">
                    <i class="fas fa-<?= $stat['status'] === 'approved' ? 'check-circle' : ($stat['status'] === 'pending' ? 'clock' : 'times-circle') ?>"></i>
                </div>
                <div class="stat-content">
                    <h5 class="stat-number"><?= htmlspecialchars($stat['count']) ?></h5>
                    <p class="stat-label"><?= ucfirst(htmlspecialchars($stat['status'])) ?> Devices</p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (isset($totalDevices)): ?>
    <div class="col-md-3 mb-3 d-flex">
        <div class="stat-card total w-100">
            <div class="stat-icon">
                <i class="fas fa-server"></i>
            </div>
            <div class="stat-content">
                <h5 class="stat-number"><?= htmlspecialchars($totalDevices) ?></h5>
                <p class="stat-label">Total Devices</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.stat-card {
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    min-height: 120px;
    height: 100%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.stat-card.approved {
    background-color: #28a745;
}

.stat-card.pending {
    background-color: #ff9800;
}

.stat-card.deactivated {
    background-color: #dc3545;
}

.stat-card.total {
    background-color: #007bff;
}

.stat-icon {
    font-size: 2.6rem;
    width: 72px;
    text-align: center;
    color: #ffffff;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2.6rem;
    font-weight: bold;
    margin: 0;
    color: #ffffff;
}

.stat-label {
    margin: 0;
    color: #ffffff;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.refresh-statistics {
    transition: all 0.3s;
}

.refresh-statistics:hover {
    transform: rotate(180deg);
}

.refresh-statistics:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style> 