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
document.getElementById('new_password').addEventListener('input', function() {
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
    strengthMeter.className = 'password-strength strength-' + Math.min(strength, 4);
});

// Profile picture upload handling
const uploadBtn = document.getElementById('uploadProfileBtn');
const cancelUploadBtn = document.getElementById('cancelUploadBtn');
const profileImageForm = document.getElementById('profileImageForm');
const profileImageInput = document.getElementById('profile_image');
const profileImageDisplay = document.getElementById('profileImageDisplay');

uploadBtn.addEventListener('click', function() {
    profileImageForm.style.display = 'block';
});

cancelUploadBtn.addEventListener('click', function() {
    profileImageForm.style.display = 'none';
    profileImageInput.value = '';
});

profileImageInput.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            profileImageDisplay.src = e.target.result;
        }
        
        reader.readAsDataURL(this.files[0]);
    }
});

// Show modal if upload was successful (look for a flag in the DOM)
document.addEventListener('DOMContentLoaded', function() {
    const uploadSuccess = document.getElementById('profile-upload-success-flag');
    if (uploadSuccess) {
        const modal = new bootstrap.Modal(document.getElementById('profileUploadModal'));
        modal.show();
    }
});

// Form submission confirmation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    if (!document.getElementById('confirmChanges').checked) {
        e.preventDefault();
        Swal.fire({
            title: 'Confirmation Required',
            text: "Please confirm that the information is accurate",
            icon: 'warning',
            confirmButtonText: 'OK'
        });
    }
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: 'Change Password?',
        text: "You're about to change your password. Continue?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, change it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

// Bootstrap tab persistence
const tabElms = document.querySelectorAll('a[data-bs-toggle="pill"]');
tabElms.forEach(tabElm => {
    tabElm.addEventListener('shown.bs.tab', function (event) {
        localStorage.setItem('lastTab', event.target.getAttribute('href'));
    });
});

// Restore last active tab
const lastTab = localStorage.getItem('lastTab');
if (lastTab) {
    const tab = new bootstrap.Tab(document.querySelector(`a[href="${lastTab}"]`));
    tab.show();
}