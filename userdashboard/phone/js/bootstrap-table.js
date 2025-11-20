/**
 * Bootstrap Table Implementation for Phone Numbers
 * Features: Search, Pagination, Sorting, Filtering
 */

class PhoneTableManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalItems = 0;
        this.filteredData = [];
        this.allData = [];
        this.searchTerm = '';
        this.statusFilter = 'all';
        this.sortColumn = 'created_at';
        this.sortDirection = 'desc';
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadData();
    }

    bindEvents() {
        // Search functionality
        $('#phoneSearchInput').on('input', this.debounce((e) => {
            this.searchTerm = e.target.value.toLowerCase().trim();
            this.filterAndPaginate();
        }, 300));

        // Clear search
        $('#clearSearchBtn').on('click', () => {
            $('#phoneSearchInput').val('');
            this.searchTerm = '';
            this.filterAndPaginate();
        });

        // Status filter buttons
        $('.status-filter-btn').on('click', (e) => {
            const filter = $(e.target).data('filter');
            this.statusFilter = filter;
            this.updateStatusFilterButtons();
            this.filterAndPaginate();
        });

        // Clear all filters
        $('#clearAllFiltersBtn').on('click', () => {
            this.searchTerm = '';
            this.statusFilter = 'all';
            $('#phoneSearchInput').val('');
            this.updateStatusFilterButtons();
            this.filterAndPaginate();
        });

        // Pagination
        $(document).on('click', '.pagination-btn', (e) => {
            e.preventDefault();
            const page = parseInt($(e.target).data('page'));
            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.renderTable();
                this.renderPagination();
            }
        });

        // Items per page change
        $('#itemsPerPage').on('change', (e) => {
            this.itemsPerPage = parseInt(e.target.value);
            this.currentPage = 1;
            this.renderTable();
            this.renderPagination();
        });

        // Column sorting
        $(document).on('click', '.sortable-header', (e) => {
            const column = $(e.target).data('column');
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.updateSortIndicators();
            this.filterAndPaginate();
        });

        // Keyboard shortcuts
        $(document).on('keydown', (e) => {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                $('#phoneSearchInput').focus();
            }
            if (e.key === 'Escape') {
                $('#phoneSearchInput').val('');
                this.searchTerm = '';
                this.filterAndPaginate();
            }
        });
    }

    async loadData() {
        try {
            const response = await fetch('UserPhoneApi.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch phone numbers');
            }

            const result = await response.json();
            this.allData = result.data || [];
            this.totalItems = this.allData.length;
            
            this.filterAndPaginate();
        } catch (error) {
            console.error('Error loading phone data:', error);
            this.showError('Failed to load phone numbers. Please refresh the page.');
        }
    }

    filterAndPaginate() {
        // Apply search filter
        this.filteredData = this.allData.filter(item => {
            const phoneNumber = item.phone_number || '';
            const label = item.label || '';
            const status = item.verified ? 'verified' : 'unverified';
            const isPrimary = item.is_primary ? 'primary' : '';
            
            const searchMatch = this.searchTerm === '' || 
                phoneNumber.toLowerCase().includes(this.searchTerm) ||
                label.toLowerCase().includes(this.searchTerm) ||
                status.includes(this.searchTerm) ||
                isPrimary.includes(this.searchTerm);

            const statusMatch = this.statusFilter === 'all' ||
                (this.statusFilter === 'verified' && item.verified) ||
                (this.statusFilter === 'unverified' && !item.verified) ||
                (this.statusFilter === 'primary' && item.is_primary);

            return searchMatch && statusMatch;
        });

        // Apply sorting
        this.filteredData.sort((a, b) => {
            let aVal = a[this.sortColumn];
            let bVal = b[this.sortColumn];

            // Handle different data types
            if (this.sortColumn === 'created_at') {
                aVal = new Date(aVal);
                bVal = new Date(bVal);
            } else if (typeof aVal === 'string') {
                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
            }

            if (this.sortDirection === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });

        this.totalItems = this.filteredData.length;
        this.currentPage = 1; // Reset to first page when filtering
        this.renderTable();
        this.renderPagination();
        this.updateSearchResultsCount();
    }

    renderTable() {
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const pageData = this.filteredData.slice(startIndex, endIndex);

        const tbody = $('#phoneTableBody');
        tbody.empty();

        if (pageData.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bi bi-search text-muted mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted mb-0">No phone numbers found</p>
                            <small class="text-muted">Try adjusting your search terms or filters</small>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        pageData.forEach(item => {
            const row = this.createTableRow(item);
            tbody.append(row);
        });
    }

    createTableRow(item) {
        const statusBadge = item.verified ? 
            '<span class="badge bg-success">Verified</span>' : 
            '<span class="badge bg-warning text-dark">Unverified</span>';

        const primaryBadge = item.is_primary ? 
            '<span class="badge bg-primary">Primary</span>' : '';

        const label = item.label ? 
            `<small class="text-muted">${this.escapeHtml(item.label)}</small>` : 
            '<small class="text-muted">No label</small>';

        const createdDate = new Date(item.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <tr class="phone-item ${item.is_primary ? 'primary' : ''}">
                <td class="phone-number-display">${this.escapeHtml(item.phone_number)}</td>
                <td>${label}</td>
                <td>
                    <div class="d-flex gap-1">
                        ${statusBadge}
                        ${primaryBadge}
                    </div>
                </td>
                <td>${createdDate}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        ${this.createActionButtons(item)}
                    </div>
                </td>
            </tr>
        `;
    }

    createActionButtons(item) {
        let buttons = '';

        if (!item.verified) {
            buttons += `
                <button class="btn btn-outline-info btn-sm" 
                        onclick="verifyPhone(${item.phone_id})" 
                        title="Verify Phone">
                    <i class="bi bi-check-circle"></i>
                </button>
            `;
        }

        if (!item.is_primary) {
            buttons += `
                <button class="btn btn-outline-primary btn-sm" 
                        onclick="setPrimaryPhone(${item.phone_id})" 
                        title="Set as Primary">
                    <i class="bi bi-star"></i>
                </button>
            `;
        }

        buttons += `
            <button class="btn btn-outline-warning btn-sm" 
                    onclick="editPhoneLabel(${item.phone_id}, '${this.escapeHtml(item.label || '')}')" 
                    title="Edit Label">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm" 
                    onclick="deletePhone(${item.phone_id})" 
                    title="Delete Phone">
                <i class="bi bi-trash"></i>
            </button>
        `;

        return buttons;
    }

    renderPagination() {
        const totalPages = Math.ceil(this.totalItems / this.itemsPerPage);
        const pagination = $('#pagination');
        
        if (totalPages <= 1) {
            pagination.empty();
            return;
        }

        let paginationHtml = '<nav><ul class="pagination justify-content-center">';
        
        // Previous button
        const prevDisabled = this.currentPage === 1 ? 'disabled' : '';
        paginationHtml += `
            <li class="page-item ${prevDisabled}">
                <a class="page-link pagination-btn" href="#" data-page="${this.currentPage - 1}">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, this.currentPage + 2);

        if (startPage > 1) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-btn" href="#" data-page="1">1</a>
                </li>
            `;
            if (startPage > 2) {
                paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === this.currentPage ? 'active' : '';
            paginationHtml += `
                <li class="page-item ${activeClass}">
                    <a class="page-link pagination-btn" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link pagination-btn" href="#" data-page="${totalPages}">${totalPages}</a>
                </li>
            `;
        }

        // Next button
        const nextDisabled = this.currentPage === totalPages ? 'disabled' : '';
        paginationHtml += `
            <li class="page-item ${nextDisabled}">
                <a class="page-link pagination-btn" href="#" data-page="${this.currentPage + 1}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `;

        paginationHtml += '</ul></nav>';
        pagination.html(paginationHtml);
    }

    updateStatusFilterButtons() {
        $('.status-filter-btn').removeClass('active');
        $(`.status-filter-btn[data-filter="${this.statusFilter}"]`).addClass('active');
    }

    updateSortIndicators() {
        $('.sortable-header').removeClass('sort-asc sort-desc');
        $(`.sortable-header[data-column="${this.sortColumn}"]`)
            .addClass(this.sortDirection === 'asc' ? 'sort-asc' : 'sort-desc');
    }

    updateSearchResultsCount() {
        const countElement = $('#searchResultsCount');
        const total = this.allData.length;
        const filtered = this.filteredData.length;
        
        if (this.searchTerm || this.statusFilter !== 'all') {
            countElement.html(`Showing ${filtered} of ${total} numbers`);
        } else {
            countElement.html(`Showing ${total} numbers`);
        }
    }

    showError(message) {
        // You can implement toast notifications here
        console.error(message);
        alert(message); // Fallback for now
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize the table manager when DOM is ready
$(document).ready(function() {
    window.phoneTableManager = new PhoneTableManager();
});

// Global functions for action buttons
async function verifyPhone(phoneId) {
    try {
        const response = await fetch('UserPhoneApi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'verify',
                phone_id: phoneId
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showToast('Phone verification initiated', 'success');
            window.phoneTableManager.loadData(); // Refresh table
        } else {
            showToast(result.error || 'Failed to verify phone', 'error');
        }
    } catch (error) {
        console.error('Error verifying phone:', error);
        showToast('Failed to verify phone', 'error');
    }
}

async function setPrimaryPhone(phoneId) {
    try {
        const response = await fetch('UserPhoneApi.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                phone_id: phoneId
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showToast('Primary phone updated successfully', 'success');
            window.phoneTableManager.loadData(); // Refresh table
        } else {
            showToast(result.error || 'Failed to set primary phone', 'error');
        }
    } catch (error) {
        console.error('Error setting primary phone:', error);
        showToast('Failed to set primary phone', 'error');
    }
}

function editPhoneLabel(phoneId, currentLabel) {
    const newLabel = prompt('Enter new label for this phone number:', currentLabel);
    
    if (newLabel !== null && newLabel !== currentLabel) {
        updatePhoneLabel(phoneId, newLabel);
    }
}

async function updatePhoneLabel(phoneId, newLabel) {
    try {
        const response = await fetch('UserPhoneApi.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_label',
                phone_id: phoneId,
                label: newLabel
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showToast('Phone label updated successfully', 'success');
            window.phoneTableManager.loadData(); // Refresh table
        } else {
            showToast(result.error || 'Failed to update phone label', 'error');
        }
    } catch (error) {
        console.error('Error updating phone label:', error);
        showToast('Failed to update phone label', 'error');
    }
}

async function deletePhone(phoneId) {
    if (!confirm('Are you sure you want to delete this phone number? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('UserPhoneApi.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                phone_id: phoneId
            })
        });

        const result = await response.json();
        
        if (result.success) {
            showToast('Phone number deleted successfully', 'success');
            window.phoneTableManager.loadData(); // Refresh table
        } else {
            showToast(result.error || 'Failed to delete phone number', 'error');
        }
    } catch (error) {
        console.error('Error deleting phone:', error);
        showToast('Failed to delete phone number', 'error');
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    // Create toast element
    const toast = $(`
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `);

    // Add to toast container
    if ($('.toast-container').length === 0) {
        $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3"></div>');
    }
    
    $('.toast-container').append(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast[0]);
    bsToast.show();
    
    // Remove toast element after it's hidden
    toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}
