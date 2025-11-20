    <div class="list-group legend-list-group">
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend active legend-3d" data-status="all" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #6c757d 60%, #adb5bd 100%); box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10);"></span>
                <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i>All Incidents</span>
            </div>
            <!-- <span id="all-count" class="badge bg-dark rounded-pill shadow-sm px-3 py-2 fs-6"><?= array_sum($counts) ?></span> -->
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="SAFE" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(40,167,69,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #28a745 60%, #d4edda 100%); box-shadow: 0 2px 8px 0 rgba(40,167,69,0.10);"></span>
                <span class="fw-semibold"><i class="bi bi-shield-check me-1 text-success"></i>Safe</span>
            </div>
            <span class="badge bg-success rounded-pill shadow-sm px-3 py-2 fs-6"><?= $counts['SAFE'] ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="MONITORING" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(255,193,7,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #ffc107 60%, #fffbe0 100%); box-shadow: 0 2px 8px 0 rgba(255,193,7,0.10);"></span>
                <span class="fw-semibold"><i class="bi bi-eye me-1 text-warning"></i>Monitoring</span>
            </div>
            <span class="badge bg-warning text-dark rounded-pill shadow-sm px-3 py-2 fs-6"><?= $counts['MONITORING'] ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="ACKNOWLEDGED" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(253,126,20,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #fd7e14 60%, #ffe0b2 100%); box-shadow: 0 2px 8px 0 rgba(253,126,20,0.10);"></span>
                <span class="fw-semibold"><i class="bi bi-check2-circle me-1 text-orange"></i>Acknowledged</span>
            </div>
            <span class="badge bg-orange rounded-pill shadow-sm px-3 py-2 fs-6"><?= $counts['ACKNOWLEDGED'] ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="EMERGENCY" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(220,53,69,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #dc3545 60%, #ffb3b3 100%); box-shadow: 0 2px 8px 0 rgba(220,53,69,0.10);"></span>
                <span class="fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>Emergency</span>
            </div>
            <span class="badge bg-danger rounded-pill shadow-sm px-3 py-2 fs-6"><?= $counts['EMERGENCY'] ?></span>
        </a>
    </div>
</div> 

<style>
/* Modern 3D Glassmorphism Legend Panel */
.legend-panel-glass {
    background: rgba(255,255,255,0.7);
    border-radius: 1.5rem;
    box-shadow: 0 8px 32px 0 rgba(31,38,135,0.18), 0 1.5px 6px 0 rgba(0,0,0,0.08);
    backdrop-filter: blur(8px);
    border: 1.5px solid rgba(200,200,200,0.18);
    transition: box-shadow 0.3s, border 0.3s;
}

.legend-panel-glass .legend-settings-btn {
    background: rgba(255,255,255,0.8);
    border: none;
    box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10);
    transition: background 0.2s;
}

.legend-panel-glass .legend-settings-btn:active {
    background: #ffe5e5;
}

.legend-list-group .legend-3d {
    background: rgba(255,255,255,0.85);
    border: none;
    border-radius: 1rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10);
    transition: box-shadow 0.2s, transform 0.2s, background 0.2s;
    position: relative;
    z-index: 1;
}

/* Light red background on active and hover */
.legend-list-group .legend-3d.active,
.legend-list-group .legend-3d:hover {
    background: red;
    box-shadow: 0 8px 24px 0 rgba(220, 53, 69, 0.18), 0 2px 8px 0 rgba(0,0,0,0.10);
    transform: translateY(-2px) scale(1.03);
    border: 1px solid rgba(220, 53, 69, 0.25);
}

/* Indicator */
.legend-3d-indicator {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 10px;
    border: 2.5px solid #fff;
    box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10);
    transition: box-shadow 0.2s, border 0.2s;
}

.legend-list-group .legend-3d.active .legend-3d-indicator,
.legend-list-group .legend-3d:hover .legend-3d-indicator {
    border-color: #dc3545;
    box-shadow: 0 4px 16px 0 rgba(220, 53, 69, 0.25);
}

.legend-list-group .badge {
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    box-shadow: 0 1px 4px 0 rgba(44,62,80,0.08);
    transition: box-shadow 0.2s;
}

.legend-list-group .legend-3d .fw-semibold {
    font-size: 1.08rem;
    letter-spacing: 0.01em;
}

/* Custom badge colors */
.badge.bg-orange {
    background-color: #fd7e14 !important;
}

.badge.bg-success {
    background-color: #28a745 !important;
}

.badge.bg-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

.badge.bg-danger {
    background-color: #dc3545 !important;
}
</style>