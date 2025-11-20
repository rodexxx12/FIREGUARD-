<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <i class="fas fa-chart-bar me-2"></i>
            <span>Device Statistics</span>
        </div>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary" onclick="exportChartData()">
                <i class="fas fa-download"></i> Export Data
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5 class="chart-title">Monthly Device Additions</h5>
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="monthlyChart"></canvas>
                    <div id="monthlyChartError" class="chart-error" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Unable to load chart data</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <h5 class="chart-title">Status Changes Over Time</h5>
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="statusChart"></canvas>
                    <div id="statusChartError" class="chart-error" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Unable to load chart data</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-legend">
                    <h6>Legend:</h6>
                    <div class="legend-items">
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: rgb(75, 192, 192);"></span>
                            <span>Approved Devices</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: rgb(255, 205, 86);"></span>
                            <span>Pending Devices</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: rgb(255, 99, 132);"></span>
                            <span>Deactivated Devices</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chart-title {
    color: #333;
    font-weight: 600;
    margin-bottom: 15px;
    text-align: center;
}

.chart-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.chart-error {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #6c757d;
}

.chart-error i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #dc3545;
}

.chart-legend {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.chart-legend h6 {
    margin-bottom: 10px;
    color: #333;
    font-weight: 600;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #666;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid #ddd;
}

@media (max-width: 768px) {
    .chart-container {
        height: 250px !important;
    }
    
    .legend-items {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
function exportChartData() {
    // Create CSV content from chart data
    let csvContent = "Month,Device Additions,Approved,Pending,Deactivated\n";
    
    if (window.monthlyLabels && window.monthlyData) {
        for (let i = 0; i < window.monthlyLabels.length; i++) {
            const month = window.monthlyLabels[i];
            const additions = window.monthlyData[i] || 0;
            const approved = window.statusChangeData?.approved?.[i] || 0;
            const pending = window.statusChangeData?.pending?.[i] || 0;
            const deactivated = window.statusChangeData?.deactivated?.[i] || 0;
            
            csvContent += `"${month}",${additions},${approved},${pending},${deactivated}\n`;
        }
    }
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `device_statistics_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script> 