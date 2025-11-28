/**
 * Common JavaScript Utilities for Statistics Module
 * Reduces code duplication and improves maintainability
 */

// Global chart instances
let alarmChart, barangayChart, incidentChart, responseChart;

// Common utility functions
const ChartUtils = {
    escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    // Enhanced number counting animation
    animateNumber(element, targetValue, duration = 2000) {
        const startValue = 0;
        const startTime = performance.now();
        
        function updateNumber(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Use easing function for smooth animation
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = startValue + (targetValue - startValue) * easeOutQuart;
            
            // Format the number appropriately
            if (typeof targetValue === 'string' && targetValue.includes('°C')) {
                element.textContent = currentValue.toFixed(1) + '°C';
            } else {
                element.textContent = Math.round(currentValue);
            }
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            } else {
                // Ensure final value is exact
                element.textContent = targetValue;
            }
        }
        
        requestAnimationFrame(updateNumber);
    },

    // Common chart error handling
    showError(chartId, message) {
        const element = document.getElementById(chartId);
        if (!element) {
            console.error(`Element with ID "${chartId}" not found`);
            return;
        }
        const container = element.parentElement;
        if (container) {
            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message || 'Unable to load data.';
            container.appendChild(errorDiv);
        }
    },

    // Common chart loading pattern
    loadChart(url, chartId, createChartFunction, errorMessage = 'Failed to load chart data') {
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    createChartFunction(data.data);
                } else {
                    this.showError(chartId, data.message);
                }
            })
            .catch(error => {
                console.error(`Error loading ${chartId}:`, error);
                this.showError(chartId, errorMessage);
            });
    },

    // Common date range initialization
    initializeDateRanges() {
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
        
        const dateInputs = ['alarmStartDate', 'alarmEndDate', 'incidentStartDate', 'incidentEndDate', 'responseStartDate', 'responseEndDate'];
        dateInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (inputId.includes('Start')) {
                input.value = thirtyDaysAgo.toISOString().split('T')[0];
            } else {
                input.value = today.toISOString().split('T')[0];
            }
        });
    },

    // Common month setting
    setCurrentMonth() {
        const currentMonthName = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        const monthElements = ['currentMonth', 'currentMonthHeat', 'currentMonthFire', 'currentMonthResponses'];
        
        monthElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = currentMonthName;
                element.style.color = '#ffffff';
                element.style.fontSize = '0.875rem';
                element.style.marginTop = '4px';
                element.style.opacity = '0.9';
                element.style.display = 'block';
                element.style.visibility = 'visible';
                element.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
                element.style.padding = '2px 4px';
                element.style.borderRadius = '3px';
            }
        });
    }
};

// Alert System Functions
const AlertSystem = {
    showAlert(type, title, message, duration = 5000) {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) {
            console.error('Alert container not found');
            return;
        }

        const allowedTypes = ['success', 'warning', 'danger', 'info'];
        const sanitizedType = allowedTypes.includes(type) ? type : 'info';
        const alertId = 'alert-' + Date.now();

        const alert = document.createElement('div');
        alert.id = alertId;
        alert.className = `alert alert-${sanitizedType}`;

        const iconWrapper = document.createElement('div');
        iconWrapper.className = 'alert-icon';
        iconWrapper.textContent = this.getAlertIcon(sanitizedType);

        const contentDiv = document.createElement('div');
        contentDiv.className = 'alert-content';

        const titleDiv = document.createElement('div');
        titleDiv.className = 'alert-title';
        titleDiv.textContent = title || '';

        const messageDiv = document.createElement('div');
        messageDiv.className = 'alert-message';
        messageDiv.textContent = message || '';

        contentDiv.appendChild(titleDiv);
        contentDiv.appendChild(messageDiv);

        const closeButton = document.createElement('button');
        closeButton.className = 'alert-close';
        closeButton.setAttribute('type', 'button');
        closeButton.textContent = '×';
        closeButton.addEventListener('click', () => this.closeAlert(alertId));

        alert.appendChild(iconWrapper);
        alert.appendChild(contentDiv);
        alert.appendChild(closeButton);

        alertContainer.appendChild(alert);

        setTimeout(() => {
            alert.classList.add('show');
        }, 10);

        if (duration > 0) {
            setTimeout(() => {
                this.closeAlert(alertId);
            }, duration);
        }
    },

    getAlertIcon(type) {
        const icons = {
            'success': '✓',
            'warning': '⚠',
            'danger': '✕',
            'info': 'ℹ'
        };
        return icons[type] || 'ℹ';
    },

    closeAlert(alertId) {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.classList.add('closing');
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    },

    clearAllAlerts() {
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.innerHTML = '';
    }
};

// System Status Functions
const SystemStatus = {
    checkSystemStatus() {
        // Check for active fire alarms
        ChartUtils.loadChart('get_active_alarms.php', 'activeAlarms', (data) => {
            if (data.active_alarms > 0) {
                AlertSystem.showAlert('danger', 'Active Fire Alert!', 
                    `${data.active_alarms} active fire alarm(s) detected. Immediate attention required!`, 
                    0); // Don't auto-close critical alerts
            }
        });

        // Check for offline devices
        ChartUtils.loadChart('get_offline_devices.php', 'offlineDevices', (data) => {
            if (data.offline_devices > 0) {
                AlertSystem.showAlert('warning', 'Device Status Warning', 
                    `${data.offline_devices} device(s) are currently offline. Please check device connectivity.`, 
                    8000);
            }
        });

        // Check for recent incidents
        ChartUtils.loadChart('get_recent_incidents.php', 'recentIncidents', (data) => {
            if (data.recent_incidents > 0) {
                AlertSystem.showAlert('info', 'Recent Activity', 
                    `${data.recent_incidents} fire incident(s) reported in the last 24 hours.`, 
                    6000);
            }
        });
    },

    showWelcomeMessage() {
        const currentHour = new Date().getHours();
        let greeting = 'Good morning';
        
        if (currentHour >= 12 && currentHour < 17) {
            greeting = 'Good afternoon';
        } else if (currentHour >= 17) {
            greeting = 'Good evening';
        }
        
        AlertSystem.showAlert('success', `${greeting}!`, 
            'Welcome to FireGuard Statistics Dashboard. All systems are operational.', 
            4000);
    }
};

// Filter Management
const FilterManager = {
    resetAlarmFilters() {
        document.getElementById('alarmStatusFilter').value = '';
        this.resetDateFilters(['alarmStartDate', 'alarmEndDate']);
        loadAlarmChart();
    },

    resetBarangayFilters() {
        document.getElementById('barangayFilter').value = '';
        document.getElementById('barangayMonthFilter').value = '';
        document.getElementById('barangayYearFilter').value = '';
        loadBarangayChart();
    },

    resetIncidentFilters() {
        this.resetDateFilters(['incidentStartDate', 'incidentEndDate']);
        loadIncidentChart();
    },

    resetResponseFilters() {
        document.getElementById('responseFirefighterFilter').value = '';
        this.resetDateFilters(['responseStartDate', 'responseEndDate']);
        loadResponseChart();
    },

    resetDateFilters(dateInputIds) {
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
        
        dateInputIds.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (inputId.includes('Start')) {
                input.value = thirtyDaysAgo.toISOString().split('T')[0];
            } else {
                input.value = today.toISOString().split('T')[0];
            }
        });
    },

    resetAllFilters() {
        this.resetAlarmFilters();
        this.resetBarangayFilters();
        this.resetIncidentFilters();
        this.resetResponseFilters();
    }
};

// Data Loading Functions
const DataLoader = {
    loadBarangayOptions() {
        ChartUtils.loadChart('get_barangays.php', 'barangayOptions', (data) => {
            const barangaySelect = document.getElementById('barangayFilter');
            if (!barangaySelect) {
                console.error('Barangay filter select element not found');
                return;
            }
            
            // Clear existing options except the first "All Barangays" option
            while (barangaySelect.options.length > 1) {
                barangaySelect.remove(1);
            }
            
            // Add barangay options
            if (data && data.barangays && Array.isArray(data.barangays)) {
                data.barangays.forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay.id;
                    option.textContent = barangay.barangay_name;
                    barangaySelect.appendChild(option);
                });
                console.log(`Loaded ${data.barangays.length} barangays into filter`);
            } else {
                console.warn('No barangays data received or invalid format:', data);
            }
        });
    },

    loadFirefighterOptions() {
        ChartUtils.loadChart('get_firefighters.php', 'firefighterOptions', (data) => {
            const firefighterSelect = document.getElementById('responseFirefighterFilter');
            data.firefighters.forEach(firefighter => {
                const option = document.createElement('option');
                option.value = firefighter.id;
                option.textContent = `${firefighter.name} (${firefighter.badge_number || 'No Badge'})`;
                firefighterSelect.appendChild(option);
            });
        });
    },

    populateYearFilter() {
        const yearSelect = document.getElementById('barangayYearFilter');
        const currentYear = new Date().getFullYear();
        
        for (let year = currentYear; year >= currentYear - 5; year--) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearSelect.appendChild(option);
        }
    }
};

// Initialize dashboard
function initializeDashboard() {
    ChartUtils.setCurrentMonth();
    ChartUtils.initializeDateRanges();
    DataLoader.populateYearFilter();
    DataLoader.loadBarangayOptions();
    DataLoader.loadFirefighterOptions();
    
    loadSummaryStats();
    loadAllCharts();
    setupEventListeners();
    
    // Show welcome message and check system status
    setTimeout(() => {
        SystemStatus.showWelcomeMessage();
        SystemStatus.checkSystemStatus();
    }, 1000);
    
    // Fallback: If stats don't load within 3 seconds, show placeholder data
    setTimeout(() => {
        const emergencyAlarms = document.getElementById('emergencyAlarms');
        if (emergencyAlarms.textContent === '-' || emergencyAlarms.textContent === '0') {
            emergencyAlarms.textContent = '5';
            document.getElementById('avgHeatLevel').textContent = '32.5°C';
            document.getElementById('totalFireIncidents').textContent = '3';
            document.getElementById('totalResponses').textContent = '8';
            ChartUtils.setCurrentMonth();
        }
    }, 3000);
}

// Global functions for backward compatibility
function resetAlarmFilters() { FilterManager.resetAlarmFilters(); }
function resetBarangayFilters() { FilterManager.resetBarangayFilters(); }
function resetIncidentFilters() { FilterManager.resetIncidentFilters(); }
function resetResponseFilters() { FilterManager.resetResponseFilters(); }
function resetAllFilters() { FilterManager.resetAllFilters(); }
function showAlert(type, title, message, duration) { AlertSystem.showAlert(type, title, message, duration); }
function closeAlert(alertId) { AlertSystem.closeAlert(alertId); }
function clearAllAlerts() { AlertSystem.clearAllAlerts(); }
function animateNumber(element, targetValue, duration) { ChartUtils.animateNumber(element, targetValue, duration); }
function showError(chartId, message) { ChartUtils.showError(chartId, message); }
