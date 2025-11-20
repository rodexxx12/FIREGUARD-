  // Step navigation
// Utility functions for managing required fields in multi-step forms
function initializeWizardRequirements(stepSelector) {
    document.querySelectorAll(stepSelector).forEach(content => {
        content.querySelectorAll('[required]').forEach(field => {
            field.dataset.originallyRequired = 'true';
        });
        if (content.classList.contains('d-none')) {
            toggleRequiredFields(content, false);
        }
    });
}

function toggleRequiredFields(content, enable) {
    content.querySelectorAll('[data-originally-required="true"]').forEach(field => {
        if (enable) {
            field.setAttribute('required', 'required');
        } else {
            field.removeAttribute('required');
        }
    });
}

function setActiveWizardContent(stepSelector, activeId) {
    document.querySelectorAll(stepSelector).forEach(content => {
        const isActive = content.id === activeId;
        content.classList.toggle('d-none', !isActive);
        toggleRequiredFields(content, isActive);
    });
}

// Initialize wizard required field tracking
initializeWizardRequirements('#profileForm .step-content');
initializeWizardRequirements('[id^="securityStepContent"]');

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
        
        setActiveWizardContent('#profileForm .step-content', 'stepContent' + nextStep);
        
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
        setActiveWizardContent('#profileForm .step-content', 'stepContent' + prevStep);
        
        // Update step indicator
        document.querySelectorAll('.step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('step' + prevStep).classList.add('active');
    });
});

// Security Wizard Navigation
document.querySelectorAll('.next-step-security').forEach(button => {
    button.addEventListener('click', function() {
        const nextStep = this.dataset.next;
        const currentStep = nextStep - 1;
        
        // Validate current step before proceeding
        let isValid = true;
        document.querySelectorAll(`#securityStepContent${currentStep} [required]`).forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        // Special validation for step 2 (password confirmation)
        if (currentStep === 2) {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                confirmPassword.classList.add('is-invalid');
                isValid = false;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Password Mismatch',
                        text: 'New password and confirm password do not match.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        }
        
        if (!isValid) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please fill in all required fields correctly',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            }
            return;
        }
        
        // Update review fields for step 3
        if (nextStep === 3) {
            const passwordStrength = document.getElementById('passwordStrength');
            const reviewPasswordStrength = document.getElementById('reviewPasswordStrength');
            if (passwordStrength && reviewPasswordStrength) {
                const strengthClass = passwordStrength.className;
                let strengthText = 'Weak';
                if (strengthClass.includes('strength-4')) strengthText = 'Very Strong';
                else if (strengthClass.includes('strength-3')) strengthText = 'Strong';
                else if (strengthClass.includes('strength-2')) strengthText = 'Medium';
                else if (strengthClass.includes('strength-1')) strengthText = 'Weak';
                reviewPasswordStrength.textContent = strengthText;
            }
        }
        
        // Hide all security step contents
        setActiveWizardContent('[id^="securityStepContent"]', 'securityStepContent' + nextStep);
        
        // Update security step indicator
        document.querySelectorAll('[id^="securityStep"]').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('securityStep' + nextStep).classList.add('active');
    });
});

document.querySelectorAll('.prev-step-security').forEach(button => {
    button.addEventListener('click', function() {
        const prevStep = this.dataset.prev;
        
        // Hide all security step contents
        setActiveWizardContent('[id^="securityStepContent"]', 'securityStepContent' + prevStep);
        
        // Update security step indicator
        document.querySelectorAll('[id^="securityStep"]').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('securityStep' + prevStep).classList.add('active');
    });
});

// Activity Wizard Navigation
document.querySelectorAll('.next-step-activity').forEach(button => {
    button.addEventListener('click', function() {
        const nextStep = this.dataset.next;
        
        // Hide all activity step contents
        document.querySelectorAll('[id^="activityStepContent"]').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById('activityStepContent' + nextStep).classList.remove('d-none');
        
        // Update activity step indicator
        document.querySelectorAll('[id^="activityStep"]').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('activityStep' + nextStep).classList.add('active');
    });
});

document.querySelectorAll('.prev-step-activity').forEach(button => {
    button.addEventListener('click', function() {
        const prevStep = this.dataset.prev;
        
        // Hide all activity step contents
        document.querySelectorAll('[id^="activityStepContent"]').forEach(content => {
            content.classList.add('d-none');
        });
        document.getElementById('activityStepContent' + prevStep).classList.remove('d-none');
        
        // Update activity step indicator
        document.querySelectorAll('[id^="activityStep"]').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('activityStep' + prevStep).classList.add('active');
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
        const confirmCheckbox = document.getElementById('confirmPasswordChange');
        
        // Check confirmation checkbox
        if (confirmCheckbox && !confirmCheckbox.checked) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirmation Required',
                    text: 'Please confirm that you want to change your password.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Please confirm that you want to change your password.');
            }
            return;
        }
        
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

// Reset wizards when switching tabs
document.addEventListener('DOMContentLoaded', function() {
    // Get all tab links
    const tabLinks = document.querySelectorAll('a[data-bs-toggle="pill"]');
    
    tabLinks.forEach(link => {
        link.addEventListener('shown.bs.tab', function(e) {
            const targetTab = e.target.getAttribute('href');
            
            // Reset Security wizard to step 1
            if (targetTab === '#security') {
                document.querySelectorAll('[id^="securityStepContent"]').forEach(content => {
                    content.classList.add('d-none');
                });
                document.getElementById('securityStepContent1').classList.remove('d-none');
                
                document.querySelectorAll('[id^="securityStep"]').forEach(step => {
                    step.classList.remove('active');
                });
                document.getElementById('securityStep1').classList.add('active');
            }
            
            // Reset Activity wizard to step 1
            if (targetTab === '#activity') {
                document.querySelectorAll('[id^="activityStepContent"]').forEach(content => {
                    content.classList.add('d-none');
                });
                document.getElementById('activityStepContent1').classList.remove('d-none');
                
                document.querySelectorAll('[id^="activityStep"]').forEach(step => {
                    step.classList.remove('active');
                });
                document.getElementById('activityStep1').classList.add('active');
            }
        });
    });
    
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