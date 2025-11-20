$(document).ready(function() {
    // Initialize SweetAlert configuration
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    // Add Admin Button Click
    $('#addAdminBtn').on('click', function() {
        $('#addAdminModal').modal('show');
        resetAddForm();
    });

    // Toggle Password Visibility for Add Form
    $('#togglePassword').on('click', function() {
        const passwordField = $('#add_password');
        const icon = $(this).find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Toggle Confirm Password Visibility for Add Form
    $('#toggleConfirmPassword').on('click', function() {
        const confirmPasswordField = $('#add_confirm_password');
        const icon = $(this).find('i');
        
        if (confirmPasswordField.attr('type') === 'password') {
            confirmPasswordField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            confirmPasswordField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Real-time Password Validation
    $('#add_password').on('input', function() {
        validatePassword($(this).val());
        validatePasswordMatch();
    });

    $('#add_confirm_password').on('input', function() {
        validatePasswordMatch();
    });

    // Real-time phone number validation
    $('#add_contact_number').on('input', function() {
        validatePhoneNumber($(this));
    });

    $('#edit_contact_number').on('input', function() {
        validatePhoneNumber($(this));
    });

    // Restrict phone number input to numbers only and max length
    $('#add_contact_number, #edit_contact_number').on('keypress', function(e) {
        // Allow: backspace, delete, tab, escape, enter, and numbers
        if (e.which === 8 || e.which === 9 || e.which === 27 || e.which === 13 || 
            (e.which >= 48 && e.which <= 57)) {
            // Check if adding this character would exceed 11 digits
            if (e.which >= 48 && e.which <= 57 && $(this).val().length >= 11) {
                e.preventDefault();
                return;
            }
            return;
        }
        e.preventDefault();
    });

    // Clean pasted content to numbers only
    $('#add_contact_number, #edit_contact_number').on('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.originalEvent || e).clipboardData.getData('text/plain');
        const numbersOnly = pastedText.replace(/\D/g, '').substring(0, 11);
        $(this).val(numbersOnly);
        validatePhoneNumber($(this));
    });

    // Phone number validation function
    function validatePhoneNumber(field) {
        const phone = field.val().trim();
        field.removeClass('is-invalid is-valid');
        
        if (phone === '') {
            return;
        }
        
        if (isValidPhoneNumber(phone)) {
            field.addClass('is-valid');
        } else {
            field.addClass('is-invalid');
        }
    }

    // Password Strength Validation
    function validatePassword(password) {
        const strengthDiv = $('#passwordStrength');
        let strength = 0;
        let feedback = '';

        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        switch (strength) {
            case 0:
            case 1:
                feedback = '<small class="text-danger"><i class="fas fa-times"></i> Very Weak</small>';
                break;
            case 2:
                feedback = '<small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Weak</small>';
                break;
            case 3:
                feedback = '<small class="text-info"><i class="fas fa-minus"></i> Fair</small>';
                break;
            case 4:
                feedback = '<small class="text-primary"><i class="fas fa-check"></i> Good</small>';
                break;
            case 5:
                feedback = '<small class="text-success"><i class="fas fa-check-double"></i> Strong</small>';
                break;
        }

        strengthDiv.html(feedback);
    }

    // Password Match Validation
    function validatePasswordMatch() {
        const password = $('#add_password').val();
        const confirmPassword = $('#add_confirm_password').val();
        const matchDiv = $('#passwordMatch');

        if (confirmPassword === '') {
            matchDiv.html('');
            return;
        }

        if (password === confirmPassword) {
            matchDiv.html('<small class="text-success"><i class="fas fa-check"></i> Passwords match</small>');
            $('#add_confirm_password').removeClass('is-invalid').addClass('is-valid');
        } else {
            matchDiv.html('<small class="text-danger"><i class="fas fa-times"></i> Passwords do not match</small>');
            $('#add_confirm_password').removeClass('is-valid').addClass('is-invalid');
        }
    }



    // Add Admin Form Submit
    $('#addAdminForm').on('submit', function(e) {
        e.preventDefault();
        
        if (validateAddForm()) {
            const formData = new FormData(this);
            
            $.ajax({
                url: '../functions/admin_crud.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    // Show loading state
                    $('#addAdminForm button[type="submit"]').prop('disabled', true)
                        .html('<i class="fas fa-spinner fa-spin me-1"></i>Adding...');
                },
                success: function(response) {
                    if (response.success) {
                        Toast.fire({
                            icon: 'success',
                            title: response.message
                        });
                        
                        $('#addAdminModal').modal('hide');
                        resetAddForm();
                        
                        // Refresh the table
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message,
                            confirmButtonColor: '#d33'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred while processing your request.',
                        confirmButtonColor: '#d33'
                    });
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    // Reset button state
                    $('#addAdminForm button[type="submit"]').prop('disabled', false)
                        .html('<i class="fas fa-save me-1"></i>Add Admin');
                }
            });
        }
    });

    // Edit Admin Button Click
    $(document).on('click', '.edit-btn', function() {
        const adminId = $(this).data('id');
        
        // Fetch admin data
        $.ajax({
            url: '../functions/admin_crud.php',
            type: 'POST',
            data: {
                action: 'get',
                admin_id: adminId
            },
            dataType: 'json',
            beforeSend: function() {
                // Show loading state
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            },
            success: function(response) {
                if (response.success) {
                    populateEditForm(response.data);
                    $('#editAdminModal').modal('show');
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error fetching admin data'
                });
                console.error('AJAX Error:', error);
            },
            complete: function() {
                // Reset button state
                $('.edit-btn').prop('disabled', false).html('<i class="fas fa-edit"></i>');
            }
        });
    });

    // Edit Admin Form Submit
    $('#editAdminForm').on('submit', function(e) {
        e.preventDefault();
        
        if (validateEditForm()) {
            const formData = new FormData(this);
            
            $.ajax({
                url: '../functions/admin_crud.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    // Show loading state
                    $('#editAdminForm button[type="submit"]').prop('disabled', true)
                        .html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        Toast.fire({
                            icon: 'success',
                            title: response.message
                        });
                        
                        $('#editAdminModal').modal('hide');
                        
                        // Refresh the table
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message,
                            confirmButtonColor: '#d33'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred while processing your request.',
                        confirmButtonColor: '#d33'
                    });
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    // Reset button state
                    $('#editAdminForm button[type="submit"]').prop('disabled', false)
                        .html('<i class="fas fa-save me-1"></i>Save Changes');
                }
            });
        }
    });



    // Form Validation Functions
    function validateAddForm() {
        let isValid = true;
        const form = $('#addAdminForm')[0];
        
        // Remove previous validation classes
        $(form).find('.is-invalid').removeClass('is-invalid');
        
        // Check required fields
        $(form).find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                isValid = false;
            }
        });
        
        // Email validation
        const email = $('#add_email').val();
        if (email && !isValidEmail(email)) {
            $('#add_email').addClass('is-invalid');
            isValid = false;
        }
        
        // Contact number validation
        const contact = $('#add_contact_number').val();
        if (contact && !isValidPhoneNumber(contact)) {
            $('#add_contact_number').addClass('is-invalid');
            isValid = false;
        }
        
        // Password validation
        const password = $('#add_password').val();
        if (!password || password.length < 8) {
            $('#add_password').addClass('is-invalid');
            isValid = false;
        }
        
        // Password confirmation validation
        const confirmPassword = $('#add_confirm_password').val();
        if (!confirmPassword || password !== confirmPassword) {
            $('#add_confirm_password').addClass('is-invalid');
            isValid = false;
        }
        
        return isValid;
    }

    function validateEditForm() {
        let isValid = true;
        const form = $('#editAdminForm')[0];
        
        // Remove previous validation classes
        $(form).find('.is-invalid').removeClass('is-invalid');
        
        // Check required fields
        $(form).find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                isValid = false;
            }
        });
        
        // Email validation
        const email = $('#edit_email').val();
        if (email && !isValidEmail(email)) {
            $('#edit_email').addClass('is-invalid');
            isValid = false;
        }
        
        // Contact number validation
        const contact = $('#edit_contact_number').val();
        if (contact && !isValidPhoneNumber(contact)) {
            $('#edit_contact_number').addClass('is-invalid');
            isValid = false;
        }
        
        return isValid;
    }

    // Email validation helper
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Phone number validation helper
    function isValidPhoneNumber(phone) {
        // Must be exactly 11 digits and start with 09 only
        const phoneRegex = /^09\d{9}$/;
        return phoneRegex.test(phone);
    }

    // Populate Edit Form
    function populateEditForm(adminData) {
        $('#edit_admin_id').val(adminData.admin_id);
        $('#edit_full_name').val(adminData.full_name);
        $('#edit_email').val(adminData.email);
        $('#edit_username').val(adminData.username);
        $('#edit_contact_number').val(adminData.contact_number);
        $('#edit_role').val(adminData.role);
        $('#edit_status').val(adminData.status);

    }

    // Reset Add Form
    function resetAddForm() {
        $('#addAdminForm')[0].reset();
        $('#addAdminForm .is-invalid').removeClass('is-invalid');
        $('#togglePassword i').removeClass('fa-eye-slash').addClass('fa-eye');
        $('#add_password').attr('type', 'password');
        $('#toggleConfirmPassword i').removeClass('fa-eye-slash').addClass('fa-eye');
        $('#add_confirm_password').attr('type', 'password');
        $('#passwordStrength').html('');
        $('#passwordMatch').html('');
        // Set default role to Fire Officer
        $('#add_role').val('fire_officer');
    }

    // Update Table Counters
    function updateTableCounters() {
        const totalRows = $('#adminTable tbody tr').length;
        $('#totalCount').text(totalRows);
        $('#showingCount').text(totalRows);
    }

    // Modal Events
    $('#addAdminModal').on('hidden.bs.modal', function() {
        resetAddForm();
    });

    // Real-time validation
    $('#addAdminForm input, #addAdminForm select').on('blur', function() {
        validateField($(this));
    });

    $('#editAdminForm input, #editAdminForm select').on('blur', function() {
        validateField($(this));
    });

    function validateField(field) {
        const value = field.val().trim();
        const fieldName = field.attr('name');
        
        // Remove previous validation
        field.removeClass('is-invalid is-valid');
        
        // Check if required field is empty
        if (field.prop('required') && !value) {
            field.addClass('is-invalid');
            return false;
        }
        
        // Specific field validations
        if (value) {
            switch (fieldName) {
                case 'email':
                    if (!isValidEmail(value)) {
                        field.addClass('is-invalid');
                        return false;
                    }
                    break;
                case 'contact_number':
                    if (!isValidPhoneNumber(value)) {
                        field.addClass('is-invalid');
                        return false;
                    }
                    break;
            }
        }
        
        // If we get here, field is valid
        if (value) {
            field.addClass('is-valid');
        }
        
        return true;
    }
}); 