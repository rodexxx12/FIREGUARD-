document.addEventListener('DOMContentLoaded', function() {
    // Validation patterns for new structured format
    const deviceNumberPattern = /^[A-Z0-9]{2,4}-[A-Z0-9]{2,4}-\d{6}$/;
    const serialNumberPattern = /^SEN-\d{4}-\d{6}$/;



    // Function to reset add form
    function resetAddForm() {
        const form = document.getElementById('addDeviceForm');
        form.reset();
        document.getElementById('device_number').classList.remove('is-invalid', 'is-valid');
        document.getElementById('serial_number').classList.remove('is-invalid', 'is-valid');
        document.getElementById('device_number_feedback').style.display = 'none';
        document.getElementById('serial_number_feedback').style.display = 'none';
    }

    // Reset form when modal is closed
    const addDeviceModal = document.getElementById('addDeviceModal');
    if (addDeviceModal) {
        addDeviceModal.addEventListener('hidden.bs.modal', function () {
            resetAddForm();
        });
    }
    






    // Handle delete device button clicks
    const deleteButtons = document.querySelectorAll('.delete-device');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const deviceId = this.dataset.deviceId;
            const deviceNumber = this.dataset.deviceNumber;
            
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to delete device ${deviceNumber}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait while we delete the device.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Send delete request
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `action=delete&id=${deviceId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting device:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while deleting the device. Please try again.'
                        });
                    });
                }
            });
        });
    });

    // Initialize charts and other functionality
    // ... rest of the existing code for charts and other features ...
});
