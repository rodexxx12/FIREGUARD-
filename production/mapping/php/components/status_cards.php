<div class="col-lg-4">
                <div class="control-panel">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="card status-card status-safe h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Users</h5>
                                    <h2 id="user-count" class="text-primary"><?= $counts['total_users'] ?? 0 ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="card status-card status-monitoring h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Buildings</h5>
                                    <h2 id="building-count" class="text-success"><?= $counts['total_buildings'] ?? 0 ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="card status-card status-fire h-100">
                                <div class="card-body">
                                    <h5 class="card-title">All Fire Incidents</h5>
                                    <h2 id="fire-events-count" class="text-danger"><?= $counts['total_fire_incidents'] ?? 0 ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
