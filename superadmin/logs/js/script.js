$(document).ready(function () {
    // Initialize search and filter functionality
    initializeSearchAndFilter();
    
    // Fetch addresses from database
    fetchAddresses();
    
    // Edit and delete buttons
    $(document).on('click', '.edit-btn', function() {
        const userId = $(this).data('id');
        handleEditUser(userId);
    });

    $(document).on('click', '.delete-btn', function() {
        const userId = $(this).data('id');
        handleDeleteUser(userId);
    });

    function initializeSearchAndFilter() {
        const $searchInput = $('#searchInput');
        const $statusFilter = $('#statusFilter');
        const $addressFilter = $('#addressFilter');
        const $clearFilters = $('#clearFilters');
        const $userTable = $('#userTable');
        const $showingCount = $('#showingCount');
        const $totalCount = $('#totalCount');
        const $filterInfo = $('#filterInfo');

        // Set initial count
        updateResultsCount();

        // Real-time search functionality with debouncing
        let searchTimeout;
        $searchInput.on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filterTable();
            }, 300); // 300ms delay for better performance
        });

        // Status filter
        $statusFilter.on('change', function() {
            filterTable();
        });

        // Address filter
        $addressFilter.on('change', function() {
            filterTable();
        });

        // Refresh addresses button
        $('#refreshAddresses').on('click', function() {
            const $btn = $(this);
            const $icon = $btn.find('i');
            
            // Show loading state
            $icon.removeClass('fa-sync-alt').addClass('fa-spinner fa-spin');
            $btn.prop('disabled', true);
            
            // Fetch addresses again
            fetchAddresses();
            
            // Reset button after a delay
            setTimeout(function() {
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
                $btn.prop('disabled', false);
            }, 2000);
        });

        // Clear filters
        $clearFilters.on('click', function() {
            $searchInput.val('');
            $statusFilter.val('');
            $addressFilter.val('');
            filterTable();
            $searchInput.focus(); // Focus back to search input
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                $searchInput.focus();
            }
            
            // Escape to clear filters
            if (e.key === 'Escape') {
                $clearFilters.click();
            }
        });

        // Focus search input on page load
        $searchInput.focus();

        function filterTable() {
            const searchTerm = $searchInput.val().toLowerCase();
            const statusFilter = $statusFilter.val().toLowerCase();
            const addressFilter = $addressFilter.val().toLowerCase();

            $userTable.find('tbody tr').each(function() {
                const $row = $(this);
                const searchData = $row.data('search');
                const status = $row.data('status');
                const address = $row.data('address');

                let showRow = true;

                // Search filter
                if (searchTerm && !searchData.includes(searchTerm)) {
                    showRow = false;
                }

                // Status filter
                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }

                // Address filter - using data-city attribute for better accuracy
                if (addressFilter) {
                    const city = $row.data('city');
                    const address = $row.data('address');
                    
                    // Check if the selected filter matches either the city key or is part of the address
                    // Since we're now showing full addresses, we can also match by the full address
                    if (!city || (city !== addressFilter && !address.includes(addressFilter))) {
                        showRow = false;
                    }
                }

                // Show/hide row with animation
                if (showRow) {
                    $row.fadeIn(200);
                } else {
                    $row.fadeOut(200);
                }
            });

            updateResultsCount();
            updateFilterInfo();
        }

        function updateResultsCount() {
            const visibleRows = $userTable.find('tbody tr:visible').length;
            const totalRows = $userTable.find('tbody tr').length;
            
            $showingCount.text(visibleRows);
            $totalCount.text(totalRows);
        }

        function updateFilterInfo() {
            const searchTerm = $searchInput.val();
            const statusFilter = $statusFilter.val();
            const addressFilter = $addressFilter.val();

            let filterText = '';
            const filters = [];

            if (searchTerm) {
                filters.push(`Search: "${searchTerm}"`);
            }
            if (statusFilter) {
                filters.push(`Status: ${statusFilter}`);
            }
            if (addressFilter) {
                filters.push(`Address: ${addressFilter}`);
            }

            if (filters.length > 0) {
                filterText = `Filtered by: ${filters.join(', ')}`;
            }

            $filterInfo.text(filterText);
        }
    }

    // Function to fetch addresses from database
    function fetchAddresses() {
        const $addressFilter = $('#addressFilter');
        const $addressFilterLoading = $('#addressFilterLoading');
        const $refreshBtn = $('#refreshAddresses');
        
        // Show loading state
        $addressFilterLoading.show();
        $addressFilterLoading.html(`
            <small class="text-muted">
                <i class="fas fa-spinner fa-spin me-1"></i>Fetching addresses from database...
            </small>
        `);
        
        $.ajax({
            url: '../functions/get_addresses.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.addresses) {
                    // Clear loading option
                    $addressFilter.empty();
                    
                    // Add default option
                    $addressFilter.append('<option value="">All Addresses</option>');
                    
                    // Add address options
                    response.addresses.forEach(function(address) {
                        $addressFilter.append(`<option value="${address.key}">${address.value}</option>`);
                    });
                    
                    // Hide loading indicator
                    $addressFilterLoading.hide();
                    
                    // Show success message briefly
                    if (response.addresses.length > 0) {
                        showNotification(`Loaded ${response.addresses.length} unique addresses!`, 'success');
                    } else {
                        showNotification('No addresses found in the database.', 'info');
                    }
                } else {
                    handleAddressFetchError(response.message || 'Failed to load addresses');
                }
            },
            error: function(xhr, status, error) {
                handleAddressFetchError('Network error while loading addresses');
                console.error('Address fetch error:', status, error);
            }
        });
    }

    function handleAddressFetchError(message) {
        const $addressFilter = $('#addressFilter');
        const $addressFilterLoading = $('#addressFilterLoading');
        
        // Clear and show error state
        $addressFilter.empty();
        $addressFilter.append('<option value="">Error loading addresses</option>');
        
        // Update loading message
        $addressFilterLoading.html(`
            <small class="text-danger">
                <i class="fas fa-exclamation-triangle me-1"></i>${message}
            </small>
        `);
        
        // Remove the notification about failed address loading
        // showNotification('Failed to load addresses. Using basic filtering.', 'warning');
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

    function handleEditUser(userId) {
        $.ajax({
            url: '',
            method: 'POST',
            data: {
                action: 'get_user',
                user_id: userId
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const user = response.data;
                    $('#edit_user_id').val(user.user_id);
                    $('#edit_fullname').val(user.fullname);
                    $('#edit_birthdate').val(user.birthdate);
                    $('#edit_address').val(user.address);
                    $('#edit_contact_number').val(user.contact_number);
                    $('#edit_email_address').val(user.email_address);
                    $('#edit_device_number').val(user.device_number);
                    $('#edit_username').val(user.username);
                    $('#edit_status').val(user.status);
                    
                    $('#editUserModal').modal('show');
                } else {
                    showNotification(response.message || 'User not found.', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                showNotification('An error occurred while fetching user data.', 'error');
            }
        });
    }

    function handleDeleteUser(userId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This user will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('', {action: 'delete', user_id: userId}, function (res) {
                    if (res.success) {
                        showNotification('User has been deleted.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(res.message || 'Failed to delete user.', 'error');
                    }
                }, 'json').fail(function() {
                    showNotification('An error occurred while deleting the user.', 'error');
                });
            }
        });
    }

    // Handle Edit Form Submit
    $('#editUserForm').submit(function (e) {
        e.preventDefault();
        
        let formData = $(this).serialize();
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showNotification('User updated successfully.', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(response.message || 'Failed to update user.', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);
                showNotification('An error occurred while updating user.', 'error');
            }
        });
    });
});