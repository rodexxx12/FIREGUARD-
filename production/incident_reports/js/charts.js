// Chart.js configuration and real-time updates
let monthlyChart = null;
let buildingTypeChart = null;
let currentPeriod = 'monthly'; // 'daily' | 'monthly' | 'yearly'
let currentTimeFilter = null;  // { start: ISOString, end: ISOString, label: string }
let currentIncidentTypeFilter = ''; // '', 'flame', 'smoke', 'high_temp'
let monthlyAbortController = null;
let buildingAbortController = null;

// Colors for charts
const chartColors = {
    primary: '#007bff',
    success: '#28a745',
    warning: '#ffc107',
    danger: '#dc3545',
    info: '#17a2b8',
    secondary: '#6c757d',
    light: '#f8f9fa',
    dark: '#343a40'
};

// Subtle 3D effect via drop shadow (no gradients)
const customShadowPlugin = {
    id: 'customShadow',
    beforeDatasetDraw(chart, args, pluginOptions) {
        const ctx = chart.ctx;
        ctx.save();
        ctx.shadowColor = pluginOptions?.color || 'rgba(0,0,0,0.18)';
        ctx.shadowBlur = pluginOptions?.blur ?? 10;
        ctx.shadowOffsetX = pluginOptions?.offsetX ?? 0;
        ctx.shadowOffsetY = pluginOptions?.offsetY ?? 4;
    },
    afterDatasetDraw(chart) {
        chart.ctx.restore();
    }
};

// Palette helpers for building chart colors (no gradients)
// Stable color mapping by building type, with incident-type-specific hues
const buildingTypeColorMap = {
    Residential: {
        default: '#ff66b2',
        flame: '#ff66b2',
        smoke: '#ff66b2',
        high_temp: '#ff66b2'
    },
    Commercial: {
        default: '#007bff',
        flame: '#007bff',
        smoke: '#007bff',
        high_temp: '#007bff'
    },
    Institutional: {
        default: '#6f42c1',
        flame: '#6f42c1',
        smoke: '#6f42c1',
        high_temp: '#6f42c1'
    },
    Industrial: {
        default: '#28a745',
        flame: '#28a745',
        smoke: '#28a745',
        high_temp: '#28a745'
    },
    Unknown: {
        default: '#bab0ab',
        flame: '#bab0ab',
        smoke: '#bab0ab',
        high_temp: '#bab0ab'
    }
};

function normalizeBuildingType(label) {
    if (!label) return 'Unknown';
    const lower = String(label).toLowerCase();
    if (lower.includes('residential')) return 'Residential';
    if (lower.includes('commercial') || lower.includes('commericial')) return 'Commercial';
    if (lower.includes('institution')) return 'Institutional';
    if (lower.includes('industrial')) return 'Industrial';
    return label.charAt(0).toUpperCase() + label.slice(1);
}

function getColorsForBuildingLabels(labels) {
    const fallback = [chartColors.primary, chartColors.success, chartColors.warning, chartColors.danger, chartColors.info, chartColors.secondary];
    const colors = [];
    for (let i = 0; i < labels.length; i++) {
        const normalized = normalizeBuildingType(labels[i]);
        const palette = buildingTypeColorMap[normalized];
        if (!palette) {
            colors.push(fallback[i % fallback.length]);
            continue;
        }
        const key = currentIncidentTypeFilter === 'flame' || currentIncidentTypeFilter === 'smoke' || currentIncidentTypeFilter === 'high_temp'
            ? currentIncidentTypeFilter
            : 'default';
        colors.push(palette[key] || palette.default);
    }
    return colors;
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Charts.js: DOM loaded, initializing charts...');
    // Restore persisted filters
    try {
        const savedPeriod = sessionStorage.getItem('ir_period');
        const savedTime = sessionStorage.getItem('ir_time_filter');
        const savedIncidentType = sessionStorage.getItem('ir_incident_type');
        if (savedPeriod) currentPeriod = savedPeriod;
        if (savedTime) currentTimeFilter = JSON.parse(savedTime);
        if (typeof savedIncidentType === 'string') {
            currentIncidentTypeFilter = savedIncidentType;
        }
    } catch (e) {}
    // Delay initialization to avoid conflicts with other chart libraries
    setTimeout(() => {
        initializeCharts();
        startRealTimeUpdates();
        syncFilterUI();
    }, 1000);
});

// Also initialize when window loads to ensure all scripts are loaded
window.addEventListener('load', function() {
    console.log('Charts.js: Window loaded, checking charts...');
    // Only initialize if charts haven't been initialized yet
    if (!monthlyChart && !buildingTypeChart) {
        setTimeout(() => {
            initializeCharts();
            startRealTimeUpdates();
        }, 500);
    }
});

function initializeCharts() {
    console.log('Charts.js: Initializing charts...');
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Charts.js: Chart.js library not found!');
        return;
    }

    // Register shadow plugin once
    try { Chart.register(customShadowPlugin); } catch (e) { /* ignore if already registered */ }
    
    // Check if charts are already initialized
    if (monthlyChart || buildingTypeChart) {
        console.log('Charts.js: Charts already initialized, skipping...');
        return;
    }
    
    // Initialize Monthly Incidents Bar Chart
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        console.log('Charts.js: Creating monthly chart...');
        // Destroy existing chart if it exists
        if (monthlyCtx.chart) {
            monthlyCtx.chart.destroy();
        }
        monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Total Incidents',
                    data: [],
                    backgroundColor: chartColors.primary,
                    borderColor: chartColors.dark,
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6
                }, {
                    label: 'Flame Incidents',
                    data: [],
                    backgroundColor: chartColors.danger,
                    borderColor: '#8a1f26',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6
                }, {
                    label: 'Smoke Incidents',
                    data: [],
                    backgroundColor: chartColors.warning,
                    borderColor: '#b38600',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 900, easing: 'easeOutQuart' },
                layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } },
                plugins: {
                    title: {
                        display: true,
                        text: 'Incident Trends',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const datasetLabel = context.dataset?.label || '';
                                const value = context.parsed?.y ?? 0;
                                const chart = context.chart;
                                const index = context.dataIndex;
                                const total = (chart?.data?.datasets?.[0]?.data?.[index]) || 0;
                                if (context.datasetIndex === 0) {
                                    return `${datasetLabel}: ${value}`;
                                }
                                const pct = total ? ((value / total) * 100).toFixed(1) : '0.0';
                                return `${datasetLabel}: ${value} (${pct}%)`;
                            },
                            footer: (items) => {
                                const dsIndex = items?.[0]?.datasetIndex ?? 0;
                                return dsIndex === 1 ? 'Click bar to filter buildings: Flame'
                                     : dsIndex === 2 ? 'Click bar to filter buildings: Smoke'
                                     : 'Click bar to filter buildings: All incidents';
                            }
                        }
                    },
                    customShadow: { color: 'rgba(0,0,0,0.15)', blur: 12, offsetY: 6 }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Incidents'
                        },
                        grid: { color: 'rgba(0,0,0,0.06)' }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Time'
                        },
                        grid: { display: false }
                    }
                },
                onClick: (evt, elements) => {
                    if (!elements || !elements.length) return;
                    const el = elements[0];
                    const index = el.index;
                    const datasetIndex = el.datasetIndex;
                    const range = getTimeRangeForIndex(index);
                    if (!range) return;
                    currentTimeFilter = range;
                    if (datasetIndex === 1) currentIncidentTypeFilter = 'flame';
                    else if (datasetIndex === 2) currentIncidentTypeFilter = 'smoke';
                    else currentIncidentTypeFilter = '';
                    if (currentIncidentTypeFilter) {
                        sessionStorage.setItem('ir_incident_type', currentIncidentTypeFilter);
                    } else {
                        sessionStorage.removeItem('ir_incident_type');
                    }
                    loadBuildingTypeData();
                    syncFilterUI();
                }
            }
        });

        // Double-click to clear all filters
        monthlyCtx.addEventListener('dblclick', () => {
            currentTimeFilter = null;
            currentIncidentTypeFilter = '';
            sessionStorage.removeItem('ir_time_filter');
            sessionStorage.removeItem('ir_incident_type');
            loadChartData();
            syncFilterUI();
        });
    }

    // Initialize Building Type Pie Chart
    const buildingTypeCtx = document.getElementById('buildingTypeChart');
    if (buildingTypeCtx) {
        console.log('Charts.js: Creating building type chart...');
        // Destroy existing chart if it exists
        if (buildingTypeCtx.chart) {
            buildingTypeCtx.chart.destroy();
        }
        buildingTypeChart = new Chart(buildingTypeCtx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        chartColors.primary,
                        chartColors.success,
                        chartColors.warning,
                        chartColors.danger,
                        chartColors.info,
                        chartColors.secondary
                    ],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 900, easing: 'easeOutQuart' },
                plugins: {
                    title: {
                        display: true,
                        text: 'Incidents by Building Type',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        position: 'right',
                        labels: { usePointStyle: true }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total ? ((value / total) * 100).toFixed(1) : '0.0';
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    },
                    customShadow: { color: 'rgba(0,0,0,0.15)', blur: 12, offsetY: 6 }
                },
                cutout: '55%'
            }
        });
    }

    // Period filter events
    const periodSelect = document.getElementById('monthly-period-filter');
    if (periodSelect) {
        // Apply saved period to UI
        try { if (currentPeriod) periodSelect.value = currentPeriod; } catch (e) {}
        periodSelect.addEventListener('change', () => {
            currentPeriod = periodSelect.value || 'monthly';
            sessionStorage.setItem('ir_period', currentPeriod);
            currentTimeFilter = null; // reset time link on period change
            sessionStorage.removeItem('ir_time_filter');
            loadChartData();
            syncFilterUI();
        });
    }

    // Clear time filter button
    const clearBtn = document.getElementById('clear-building-time-filter');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            currentTimeFilter = null;
            sessionStorage.removeItem('ir_time_filter');
            loadBuildingTypeData();
            syncFilterUI();
        });
    }

    // Clear incident-type filter button
    const clearIncidentBtn = document.getElementById('clear-building-incident-type');
    if (clearIncidentBtn) {
        clearIncidentBtn.addEventListener('click', () => {
            currentIncidentTypeFilter = '';
            sessionStorage.removeItem('ir_incident_type');
            loadBuildingTypeData();
            syncFilterUI();
        });
    }

    // Load initial data
    loadChartData();
}

function setLoading(chartId, isLoading) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    const parent = canvas.closest('.chart-container') || canvas.parentElement;
    if (!parent) return;
    let overlay = parent.querySelector('.chart-loading');
    if (isLoading) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'chart-loading';
            overlay.innerText = 'Loading...';
            overlay.style.position = 'absolute';
            overlay.style.inset = '0';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.background = 'rgba(255,255,255,0.6)';
            parent.appendChild(overlay);
        }
    } else if (overlay) {
        overlay.remove();
    }
}

function loadChartData() {
    console.log('Charts.js: Loading chart data...');
    
    // Cancel any in-flight requests
    try { monthlyAbortController?.abort(); } catch (e) {}
    monthlyAbortController = new AbortController();

    // Helper to robustly parse JSON and surface HTML error bodies
    const parseJsonOrThrow = async (response) => {
        if (!response.ok) {
            const text = await response.text().catch(() => '');
            throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
        }
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text().catch(() => '');
            throw new Error(`Invalid JSON (Content-Type: ${contentType}): ${text.substring(0, 200)}`);
        }
        return response.json();
    };

    setLoading('monthlyChart', true);
    
    // Load trends data with period and optional start/end
    const trendsUrl = new URL('../php/chart_data.php', window.location.href);
    trendsUrl.searchParams.set('action', 'trends');
    trendsUrl.searchParams.set('period', currentPeriod);
    if (currentTimeFilter && currentTimeFilter.start && currentTimeFilter.end) {
        trendsUrl.searchParams.set('start', currentTimeFilter.start);
        trendsUrl.searchParams.set('end', currentTimeFilter.end);
    }

    fetch(trendsUrl.toString(), { signal: monthlyAbortController.signal })
        .then(parseJsonOrThrow)
        .then(data => { window.__trendsRawData = Array.isArray(data) ? data : []; updateMonthlyChart(data); })
        .catch(error => {
            if (error.name === 'AbortError') return;
            console.error('Error loading trends data:', error);
            showChartError('monthlyChart', 'Failed to load trends data');
        })
        .finally(() => setLoading('monthlyChart', false));

    // Load building type data (respect currentTimeFilter, severity filter, and incident-type filter)
    loadBuildingTypeData();
}

function loadBuildingTypeData() {
    // Cancel in-flight
    try { buildingAbortController?.abort(); } catch (e) {}
    buildingAbortController = new AbortController();

    // Helper to robustly parse JSON and surface HTML error bodies
    const parseJsonOrThrow = async (response) => {
        if (!response.ok) {
            const text = await response.text().catch(() => '');
            throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
        }
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text().catch(() => '');
            throw new Error(`Invalid JSON (Content-Type: ${contentType}): ${text.substring(0, 200)}`);
        }
        return response.json();
    };

    setLoading('buildingTypeChart', true);

    const buildingUrl = new URL('../php/chart_data.php', window.location.href);
    buildingUrl.searchParams.set('action', 'building_type_data');
    if (currentTimeFilter && currentTimeFilter.start && currentTimeFilter.end) {
        buildingUrl.searchParams.set('start', currentTimeFilter.start);
        buildingUrl.searchParams.set('end', currentTimeFilter.end);
    }
    if (currentIncidentTypeFilter) {
        buildingUrl.searchParams.set('incident_type', currentIncidentTypeFilter);
        sessionStorage.setItem('ir_incident_type', currentIncidentTypeFilter);
    } else {
        sessionStorage.removeItem('ir_incident_type');
    }

    fetch(buildingUrl.toString(), { signal: buildingAbortController.signal })
        .then(parseJsonOrThrow)
        .then(data => updateBuildingTypeChart(data))
        .catch(error => {
            if (error.name === 'AbortError') return;
            console.error('Error loading building type data:', error);
            showChartError('buildingTypeChart', 'Failed to load building type data');
        })
        .finally(() => setLoading('buildingTypeChart', false));

    syncFilterUI();
}

function showChartError(chartId, message) {
    const canvas = document.getElementById(chartId);
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        ctx.fillStyle = '#dc3545';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
    }
}

function updateMonthlyChart(data) {
    if (!monthlyChart) return;

    // Choose label format based on currentPeriod
    const labels = data.map(item => {
        if (currentPeriod === 'daily') {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        } else if (currentPeriod === 'yearly') {
            return String(item.year);
        } else {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }
    }).reverse();

    const totalData = data.map(item => parseInt(item.incident_count)).reverse();
    const flameData = data.map(item => parseInt(item.flame_incidents)).reverse();
    const smokeData = data.map(item => parseInt(item.smoke_incidents)).reverse();

    monthlyChart.data.labels = labels;
    monthlyChart.data.datasets[0].data = totalData;
    monthlyChart.data.datasets[1].data = flameData;
    monthlyChart.data.datasets[2].data = smokeData;
    monthlyChart.update();

    // Update chart statistics (use most recent bucket if available)
    const totalMonthlyEl = document.getElementById('total-monthly');
    const flameMonthlyEl = document.getElementById('flame-monthly');
    const smokeMonthlyEl = document.getElementById('smoke-monthly');

    if (data.length > 0) {
        const latest = data[0];
        if (totalMonthlyEl) totalMonthlyEl.textContent = parseInt(latest.incident_count || 0).toLocaleString();
        if (flameMonthlyEl) flameMonthlyEl.textContent = parseInt(latest.flame_incidents || 0).toLocaleString();
        if (smokeMonthlyEl) smokeMonthlyEl.textContent = parseInt(latest.smoke_incidents || 0).toLocaleString();
    }
}

function updateBuildingTypeChart(data) {
    if (!buildingTypeChart) return;

    // Normalize labels and ensure stable preferred order
    const preferredOrder = ['Residential', 'Commercial', 'Institutional', 'Industrial', 'Unknown'];
    const normalized = data.map(item => ({
        building_type: normalizeBuildingType(item.building_type),
        incident_count: parseInt(item.incident_count)
    }));
    // Group by type in case of label variants from DB
    const aggregated = normalized.reduce((acc, { building_type, incident_count }) => {
        acc[building_type] = (acc[building_type] || 0) + (Number.isFinite(incident_count) ? incident_count : 0);
        return acc;
    }, {});
    const labels = Object.keys(aggregated).sort((a, b) => {
        const ai = preferredOrder.indexOf(a);
        const bi = preferredOrder.indexOf(b);
        if (ai === -1 && bi === -1) return a.localeCompare(b);
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
    });
    const values = labels.map(l => aggregated[l]);

    buildingTypeChart.data.labels = labels;
    buildingTypeChart.data.datasets[0].data = values;
    const bgColors = getColorsForBuildingLabels(labels);
    buildingTypeChart.data.datasets[0].backgroundColor = bgColors;
    if (buildingTypeChart.options?.plugins?.title) {
        const filterSuffix = currentIncidentTypeFilter === 'flame' ? ' (Flame)'
            : currentIncidentTypeFilter === 'smoke' ? ' (Smoke)'
            : currentIncidentTypeFilter === 'high_temp' ? ' (High Temp)'
            : '';
        buildingTypeChart.options.plugins.title.text = 'Incidents by Building Type' + filterSuffix;
    }
    buildingTypeChart.update();

    const buildingTypesCountEl = document.getElementById('building-types-count');
    if (buildingTypesCountEl) {
        buildingTypesCountEl.textContent = labels.length;
    }
}

function syncFilterUI() {
    // Time chip
    const chip = document.getElementById('building-time-filter-chip');
    const label = document.getElementById('building-time-filter-label');
    if (chip && label) {
        if (currentTimeFilter && currentTimeFilter.start && currentTimeFilter.end) {
            const s = new Date(currentTimeFilter.start);
            const e = new Date(currentTimeFilter.end);
            const fmt = (d) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            label.textContent = `${fmt(s)} - ${fmt(e)}`;
            chip.style.display = '';
        } else {
            chip.style.display = 'none';
        }
    }

    // Incident-type chip
    const itChip = document.getElementById('building-incident-type-chip');
    const itLabel = document.getElementById('building-incident-type-label');
    if (itChip && itLabel) {
        if (currentIncidentTypeFilter) {
            const pretty = currentIncidentTypeFilter === 'flame' ? 'Flame'
                          : currentIncidentTypeFilter === 'smoke' ? 'Smoke'
                          : currentIncidentTypeFilter === 'high_temp' ? 'High Temp'
                          : currentIncidentTypeFilter;
            itLabel.textContent = `Incident Type: ${pretty}`;
            itChip.style.display = '';
        } else {
            itChip.style.display = 'none';
        }
    }
}

function startRealTimeUpdates() {
    // Helper to robustly parse JSON and surface HTML error bodies
    const parseJsonOrThrow = async (response) => {
        if (!response.ok) {
            const text = await response.text().catch(() => '');
            throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
        }
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text().catch(() => '');
            throw new Error(`Invalid JSON (Content-Type: ${contentType}): ${text.substring(0, 200)}`);
        }
        return response.json();
    };

    // Update charts every 30 seconds
    setInterval(() => {
        loadChartData();
    }, 30000);

    // Severity filter removed
}

function getTimeRangeForIndex(index) {
    // Compute start/end ISO strings for the selected bar index given currentPeriod and current dataset order
    if (!monthlyChart || !monthlyChart.data || !monthlyChart.data.labels) return null;

    const data = window.__trendsRawData || [];
    if (!data.length) return null;

    // Because we reversed when setting chart, the chart index maps to reversed order
    const dataIndex = (data.length - 1) - index;
    const item = data[dataIndex];

    if (!item) return null;

    if (currentPeriod === 'daily' && item.date) {
        const start = new Date(item.date);
        const end = new Date(item.date);
        end.setHours(23,59,59,999);
        const filter = { start: start.toISOString(), end: end.toISOString(), label: item.date };
        sessionStorage.setItem('ir_time_filter', JSON.stringify(filter));
        return filter;
    }
    if (currentPeriod === 'monthly' && item.month) {
        const start = new Date(item.month + '-01T00:00:00');
        const end = new Date(start);
        end.setMonth(end.getMonth() + 1);
        end.setMilliseconds(end.getMilliseconds() - 1);
        const filter = { start: start.toISOString(), end: end.toISOString(), label: item.month };
        sessionStorage.setItem('ir_time_filter', JSON.stringify(filter));
        return filter;
    }
    if (currentPeriod === 'yearly' && item.year) {
        const start = new Date(`${item.year}-01-01T00:00:00`);
        const end = new Date(`${item.year}-12-31T23:59:59.999`);
        const filter = { start: start.toISOString(), end: end.toISOString(), label: String(item.year) };
        sessionStorage.setItem('ir_time_filter', JSON.stringify(filter));
        return filter;
    }
    return null;
}

// Export functions for global access
window.chartFunctions = {
    loadChartData,
    updateMonthlyChart,
    updateBuildingTypeChart
}; 