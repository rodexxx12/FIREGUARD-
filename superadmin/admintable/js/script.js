$(document).ready(function () {
    // Initialize search and filter functionality
    initializeSearchAndFilter();
    
    // Edit button
    $(document).on('click', '.edit-btn', function() {
        const adminId = $(this).data('id');
        handleEditAdmin(adminId);
    });

    

    function initializeSearchAndFilter() {
        const $searchInput = $('#searchInput');
        const $statusFilter = $('#statusFilter');
        const $clearFilters = $('#clearFilters');
        const $adminTable = $('#adminTable');
        const $showingCount = $('#showingCount');
        const $totalCount = $('#totalCount');
        const $filterInfo = $('#filterInfo');
        const $paginationControls = $('#paginationControls');
        const $prevPage = $('#prevPage');
        const $nextPage = $('#nextPage');
        const $currentPage = $('#currentPage');
        const $totalPages = $('#totalPages');

        // Pagination state
        let currentPage = 1;
        const rowsPerPage = 10;
        let filteredRows = [];
        let totalPageCount = 1;

        // Set initial count and pagination
        paginateTable();
        updateResultsCount();

        // Real-time search functionality with debouncing
        let searchTimeout;
        $searchInput.on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                currentPage = 1;
                paginateTable();
            }, 300);
        });

        // Status filter
        $statusFilter.on('change', function() {
            currentPage = 1;
            paginateTable();
        });

        // Clear filters
        $clearFilters.on('click', function() {
            $searchInput.val('');
            $statusFilter.val('');
            currentPage = 1;
            paginateTable();
            $searchInput.focus();
        });

        // Pagination button events
        $prevPage.on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                paginateTable();
            }
        });
        $nextPage.on('click', function() {
            if (currentPage < totalPageCount) {
                currentPage++;
                paginateTable();
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                $searchInput.focus();
            }
            if (e.key === 'Escape') {
                $clearFilters.click();
            }
        });

        $searchInput.focus();

        function paginateTable() {
            // Filter rows
            const searchTerm = $searchInput.val().toLowerCase();
            const statusFilter = $statusFilter.val().toLowerCase();
            filteredRows = [];
            $adminTable.find('tbody tr').each(function() {
                const $row = $(this);
                const searchData = $row.data('search');
                const status = $row.data('status');
                let showRow = true;
                if (searchTerm && !searchData.includes(searchTerm)) showRow = false;
                if (statusFilter && status !== statusFilter) showRow = false;
                if (showRow) filteredRows.push($row);
                $row.hide(); // Hide all rows initially
            });
            // Pagination logic
            const totalRows = filteredRows.length;
            totalPageCount = Math.max(1, Math.ceil(totalRows / rowsPerPage));
            if (currentPage > totalPageCount) currentPage = totalPageCount;
            const startIdx = (currentPage - 1) * rowsPerPage;
            const endIdx = startIdx + rowsPerPage;
            for (let i = startIdx; i < endIdx && i < filteredRows.length; i++) {
                filteredRows[i].fadeIn(200);
            }
            // Update controls
            $currentPage.text(currentPage);
            $totalPages.text(totalPageCount);
            $prevPage.prop('disabled', currentPage === 1);
            $nextPage.prop('disabled', currentPage === totalPageCount);
            updateResultsCount();
            updateFilterInfo();
        }

        function updateResultsCount() {
            const visibleRows = $adminTable.find('tbody tr:visible').length;
            const totalRows = $adminTable.find('tbody tr').length;
            $showingCount.text(visibleRows);
            $totalCount.text(totalRows);
        }

        function updateFilterInfo() {
            const searchTerm = $searchInput.val();
            const statusFilter = $statusFilter.val();
            let filterText = '';
            const filters = [];
            if (searchTerm) filters.push(`Search: "${searchTerm}"`);
            if (statusFilter) filters.push(`Status: ${statusFilter}`);
            if (filters.length > 0) filterText = `Filtered by: ${filters.join(', ')}`;
            $filterInfo.text(filterText);
        }
    }

    function showNotification(message, type = 'info') {
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'error' ? 'exclamation-triangle' : 'info-circle';
        Swal.fire({
            icon: type,
            title: type.charAt(0).toUpperCase() + type.slice(1),
            text: message,
            timer: type === 'success' ? 2000 : 4000,
            showConfirmButton: false,
            position: 'top-end',
            toast: true
        });
    }

    function handleEditAdmin(adminId) {
        $.ajax({
            url: '../functions/ajax_handlers.php',
            method: 'POST',
            data: {
                action: 'get_user',
                admin_id: adminId
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const admin = response.data;
                    $('#edit_admin_id').val(admin.admin_id);
                    $('#edit_full_name').val(admin.full_name);
                    $('#edit_email').val(admin.email);
                    $('#edit_username').val(admin.username);
                    $('#edit_contact_number').val(admin.contact_number);
                    $('#edit_role').val(admin.role);
                    $('#edit_status').val(admin.status);

                    $('#editAdminModal').modal('show');
                } else {
                    showNotification(response.message || 'Admin not found.', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                showNotification('An error occurred while fetching admin data.', 'error');
            }
        });
    }



    // Handle Edit Form Submit
    $('#editAdminForm').submit(function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        $.ajax({
            url: '../functions/ajax_handlers.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showNotification('Admin updated successfully.', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(response.message || 'Failed to update admin.', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                showNotification('An error occurred while updating admin.', 'error');
            }
        });
    });
});