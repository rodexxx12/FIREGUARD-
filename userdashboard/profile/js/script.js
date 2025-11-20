  // Step navigation
  document.querySelectorAll('.next-step').forEach(button => {
    button.addEventListener('click', function() {
        const nextStep = this.dataset.next;
        const currentStep = nextStep - 1;
        
        // Validate current step before proceeding
        let isValid = true;
        document.querySelectorAll(`#stepContent${currentStep} [required]`).forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        if (!isValid) {
            Swal.fire({
                title: 'Validation Error',
                text: 'Please fill in all required fields',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Update review fields
        if (nextStep === 3) {
            document.getElementById('reviewFullname').textContent = document.getElementById('full_name').value;
            document.getElementById('reviewEmail').textContent = document.getElementById('email').value;
            document.getElementById('reviewContact').textContent = document.getElementById('contact_number').value;
        }
        
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById('stepContent' + nextStep).classList.remove('d-none');
        
        // Update step indicator
        document.querySelectorAll('.step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('step' + nextStep).classList.add('active');
    });
});

document.querySelectorAll('.prev-step').forEach(button => {
    button.addEventListener('click', function() {
        const prevStep = this.dataset.prev;
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById('stepContent' + prevStep).classList.remove('d-none');
        
        // Update step indicator
        document.querySelectorAll('.step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('step' + prevStep).classList.add('active');
    });
});

// Password strength meter
const newPasswordField = document.getElementById('new_password');
if (newPasswordField) {
    newPasswordField.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength++;
        
        // Contains uppercase
        if (/[A-Z]/.test(password)) strength++;
        
        // Contains lowercase
        if (/[a-z]/.test(password)) strength++;
        
        // Contains number
        if (/[0-9]/.test(password)) strength++;
        
        // Contains special character
        if (/[\W]/.test(password)) strength++;
        
        // Update strength meter
        const strengthMeter = document.getElementById('passwordStrength');
        if (strengthMeter) {
            strengthMeter.className = 'password-strength strength-' + Math.min(strength, 4);
        }
    });
}

// Profile picture modal handling
const profilePictureModal = document.getElementById('profilePictureModal');
const modalProfileImage = document.getElementById('modalProfileImage');
const modalProfileImageDisplay = document.getElementById('modalProfileImageDisplay');
const imagePreview = document.getElementById('imagePreview');
const previewSection = document.getElementById('previewSection');
const updateProfilePictureBtn = document.getElementById('updateProfilePictureBtn');
const modalErrorDisplay = document.getElementById('modalErrorDisplay');
const modalErrorText = document.getElementById('modalErrorText');
const profilePictureModalForm = document.getElementById('profilePictureModalForm');

// Check if user is logged in before opening modal
const uploadProfileBtn = document.getElementById('uploadProfileBtn');
if (uploadProfileBtn) {
    uploadProfileBtn.addEventListener('click', function(e) {
        // Check if we have admin_id in session (this will be set by PHP)
        const isLoggedIn = document.body.dataset.adminId !== undefined && document.body.dataset.adminId !== '';
        
        if (!isLoggedIn) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Login Required',
                    text: 'You must be logged in as an admin to upload a profile picture.',
                    icon: 'warning',
                    confirmButtonText: 'Go to Login',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../../../login/php/login.php';
                    }
                });
            } else {
                // Fallback if SweetAlert is not available
                if (confirm('You must be logged in as an admin to upload a profile picture. Go to login page?')) {
                    window.location.href = '../../../login/php/login.php';
                }
            }
            return;
        }
    });
}

// Modal event listeners
if (profilePictureModal) {
    profilePictureModal.addEventListener('show.bs.modal', function() {
        resetModal();
    });

    profilePictureModal.addEventListener('hidden.bs.modal', function() {
        resetModal();
    });
}

// File input change handler
if (modalProfileImage) {
    modalProfileImage.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            handleFileSelect(this.files[0]);
        }
    });
}

// Handle file selection
function handleFileSelect(file) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        showModalError('Please select a valid image file (JPG, PNG, or GIF)');
        return;
    }
    
    // Validate file size (2MB)
    const maxSize = 2 * 1024 * 1024; // 2MB in bytes
    if (file.size > maxSize) {
        showModalError('File size must be less than 2MB');
        return;
    }
    
    // Clear any previous errors
    hideModalError();
    
    // Create preview
    const reader = new FileReader();
    reader.onload = function(e) {
        if (imagePreview) {
            imagePreview.src = e.target.result;
        }
        if (previewSection) {
            previewSection.classList.remove('d-none');
        }
        if (updateProfilePictureBtn) {
            updateProfilePictureBtn.disabled = false;
        }
    };
    reader.readAsDataURL(file);
}

// Reset modal state
function resetModal() {
    if (modalProfileImage) {
        modalProfileImage.value = '';
    }
    if (previewSection) {
        previewSection.classList.add('d-none');
    }
    if (updateProfilePictureBtn) {
        updateProfilePictureBtn.disabled = true;
    }
    hideModalError();
}

// Show modal error
function showModalError(message) {
    if (modalErrorText) {
        modalErrorText.textContent = message;
    }
    if (modalErrorDisplay) {
        modalErrorDisplay.classList.remove('d-none');
    }
    if (updateProfilePictureBtn) {
        updateProfilePictureBtn.disabled = true;
    }
}

// Hide modal error
function hideModalError() {
    if (modalErrorDisplay) {
        modalErrorDisplay.classList.add('d-none');
    }
}

// Form submission handling
if (profilePictureModalForm) {
    profilePictureModalForm.addEventListener('submit', function(e) {
        if (!modalProfileImage || !modalProfileImage.files || !modalProfileImage.files[0]) {
            e.preventDefault();
            showModalError('Please select an image file');
            return;
        }
        
        // Show loading state
        if (updateProfilePictureBtn) {
            updateProfilePictureBtn.disabled = true;
            updateProfilePictureBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Uploading...';
        }
    });
}

// Profile form submission
const profileForm = document.getElementById('profileForm');
if (profileForm) {
    profileForm.addEventListener('submit', function(e) {
        const confirmCheckbox = document.getElementById('confirmChanges');
        if (confirmCheckbox && !confirmCheckbox.checked) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirmation Required',
                    text: 'Please confirm that all information is accurate before proceeding.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Please confirm that all information is accurate before proceeding.');
            }
        }
    });
}

// Password form submission
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        const newPasswordField = document.getElementById('new_password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        if (newPasswordField && confirmPasswordField) {
            const newPassword = newPasswordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Password Mismatch',
                        text: 'New password and confirm password do not match.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('New password and confirm password do not match.');
                }
            }
        }
    });
}

// Success message handling
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alertElement => {
            try {
                const bsAlert = new bootstrap.Alert(alertElement);
                bsAlert.close();
            } catch (error) {
                // Fallback if Bootstrap Alert is not available
                if (alertElement && alertElement.style) {
                    alertElement.style.display = 'none';
                }
            }
        });
    }, 5000);
});