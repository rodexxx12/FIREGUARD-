// incident_reports_search.js

document.addEventListener('DOMContentLoaded', function () {
    const filterSelect = document.getElementById('incident-filter');
    const tableBody = document.getElementById('incidents-table-body');
    const dateStartInput = document.getElementById('incident-date-start');
    const dateEndInput = document.getElementById('incident-date-end');
    let debounceTimeout = null;

    function renderTable(incidents) {
        if (!incidents.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No incidents found.</td></tr>';
            return;
        }
        tableBody.innerHTML = incidents.map(incident => `
            <tr>
                <td><span class="fw-semibold">#${incident.id}</span></td>
                <td>${formatDate(incident.incident_time)}<br><small class="text-muted">${formatTime(incident.incident_time)}</small></td>
                <td><div class="fw-medium">${escapeHTML(incident.building_name || 'N/A')}</div><small class="text-muted">${escapeHTML(incident.building_address || '')}</small></td>
                <td><span class="badge severity-${incident.status ? incident.status.toLowerCase() : ''}" aria-label="Status: ${escapeHTML(incident.status)}">${incident.status ? escapeHTML(incident.status) : '<span class=\'text-muted\'>No status</span>'}</span></td>
                <td><div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-danger bg-opacity-10 text-white" aria-label="Temperature"><i class="fas fa-temperature-high"></i> ${incident.temp !== '' ? escapeHTML(incident.temp) + '°C' : '<span class=\'text-muted\'>N/A</span>'}</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning" aria-label="Smoke Level"><i class="fas fa-smog"></i> ${incident.smoke !== '' ? escapeHTML(incident.smoke) + ' ppm' : '<span class=\'text-muted\'>N/A</span>'}</span>
                    ${incident.flame_detected ? '<span class="badge bg-danger bg-opacity-10 text-white" aria-label="Flame Detected"><i class="fas fa-fire"></i> Flame</span>' : ''}
                </div></td>
                <td><div class="fw-medium">${escapeHTML(incident.admin_name || incident.acknowledged_by)}</div><small class="text-muted">${formatDateTime(incident.acknowledged_at)}</small></td>
                <td><a href="?incident_id=${incident.id}" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a></td>
            </tr>
        `).join('');
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;'}[c];
        });
    }
    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
    function formatTime(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true });
    }
    function formatDateTime(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function fetchIncidents() {
        const status = filterSelect.value;
        const startDate = dateStartInput.value;
        const endDate = dateEndInput.value;
        const formData = new FormData();
        formData.append('action', 'search');
        formData.append('status', status);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        fetch('reports.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => renderTable(data))
        .catch(() => {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading incidents.</td></tr>';
        });
    }

    filterSelect.addEventListener('change', fetchIncidents);
    dateStartInput.addEventListener('change', fetchIncidents);
    dateEndInput.addEventListener('change', fetchIncidents);
}); 