                <div class="control-panel px-3 py-4" style="background: linear-gradient(135deg, #f8fafc 60%, #e9ecef 100%); border-radius: 1.25rem; box-shadow: 0 6px 32px 0 rgba(44,62,80,0.10);">
            <!-- Recent Alerts Section -->
            <div class="row mt-2 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center py-4 rounded-top" style="background: linear-gradient(90deg, #e53935 0%, #e35d5b 100%); box-shadow: 0 2px 8px 0 rgba(229,57,53,0.10);">
                    <h5 class="mb-0 text-white d-flex align-items-center">
                        <i class="bi bi-bell-fill me-3 fs-3"></i>
                        <span class="fw-bold">Recent Alerts</span>
                    </h5>
                    <div class="position-relative">
                        <span class="badge bg-light text-primary fw-semibold px-4 py-2 shadow-sm border border-2 border-white">
                            Alerts
                        </span>
                        <span id="alert-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-white text-primary border border-primary shadow-sm">
                            <?= count($alerts) ?>
                        </span>
                    </div>
                </div>

                <div id="alerts-container" class="list-group list-group-flush border rounded-bottom" style="max-height: 340px; overflow-y: auto; background: #fff; box-shadow: 0 2px 8px 0 rgba(44,62,80,0.04);">
                    <?php if (empty($alerts)): ?>
                        <div class="list-group-item text-center text-muted py-5 d-flex flex-column align-items-center justify-content-center" style="min-height: 180px;">
                            <i class="bi bi-info-circle display-3 mb-3" style="color: #e53935;"></i>
                            <span class="fs-5">No recent alerts</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="list-group-item alert-item emergency py-4 px-3 border-bottom bg-light position-relative" style="transition: box-shadow 0.2s, background 0.2s; border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 1px 4px 0 rgba(229,57,53,0.04); cursor: pointer;" onmouseover="this.style.background='#fff';this.style.boxShadow='0 4px 16px 0 rgba(229,57,53,0.10)';" onmouseout="this.style.background='#f8f9fa';this.style.boxShadow='0 1px 4px 0 rgba(229,57,53,0.04)';">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">Alert ID: <span class="text-danger">#<?= $alert['id'] ?></span></h6>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($alert['timestamp'])) ?></small>
                                    </div>
                                    <span class="badge bg-<?= $alert['status'] === 'active' ? 'danger' : 'secondary' ?> text-white px-3 py-2 rounded-pill shadow-sm align-self-center">
                                        <i class="bi bi-<?= $alert['status'] === 'active' ? 'exclamation-triangle-fill' : 'check-circle-fill' ?> me-1"></i>
                                        <?= ucfirst($alert['status']) ?>
                                    </span>
                                </div>
                                <p class="mb-2"><strong>Building:</strong> <span class="text-primary-emphasis"><?= $alert['building_type'] ?></span></p>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-thermometer-half me-1"></i>Temp: <?= $alert['temp'] ?>°C</span>
                                    <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-cloud-fog2 me-1"></i>Smoke: <?= $alert['smoke'] ?></span>
                                    <span class="badge bg-secondary-subtle text-dark px-3 py-2 rounded-pill shadow-sm">
                                        <?= $alert['flame_detected'] ? '<i class="bi bi-fire text-danger me-1"></i>Flame Detected' : '<i class="bi bi-shield-check text-success me-1"></i>No Flame' ?>
                                    </span>
                                </div>
                                <button class="btn btn-sm btn-gradient-primary view-alert-details px-4 py-2 fw-semibold shadow-sm border-0"
                                    style="background: linear-gradient(90deg, #e53935 0%, #e35d5b 100%); color: #fff; border-radius: 2rem; letter-spacing: 0.5px; transition: background 0.2s;"
                                    onmouseover="this.style.background='linear-gradient(90deg, #d32f2f 0%, #b71c1c 100%)';" onmouseout="this.style.background='linear-gradient(90deg, #e53935 0%, #e35d5b 100%)';"
                                    data-id="<?= $alert['id'] ?>"
                                    data-status="<?= $alert['status'] ?>"
                                    data-building="<?= $alert['building_type'] ?>"
                                    data-smoke="<?= $alert['smoke'] ?>"
                                    data-temp="<?= $alert['temp'] ?>"
                                    data-heat="<?= $alert['heat'] ?>"
                                    data-flame="<?= $alert['flame_detected'] ?>"
                                    data-timestamp="<?= $alert['timestamp'] ?>">
                                    <i class="bi bi-eye me-2"></i>View Details
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div> 