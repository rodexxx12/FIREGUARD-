<?php require_once '../functions/functions.php'; ?>
<?php include('../../components/header.php'); ?>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-4">
                <!-- Combined Profile Card with Picture Upload -->
                <div class="card profile-card mb-4">
                    <div class="profile-header text-center py-4">
                        <div class="position-relative d-inline-block">
                            <div class="profile-image-container">
                                <img src="<?php echo getProfileImageUrl($user['profile_image']); ?>" 
                                     class="profile-avatar" alt="Profile" id="profileImageDisplay">
                                <button type="button" class="profile-image-upload-btn" id="uploadProfileBtn">
                                    <i class="bi bi-camera"></i>
                                </button>
                            </div>
                            <span class="status-badge <?php echo $user['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>"></span>
                        </div>
                        <h4 class="mt-3 mb-0"><?php echo htmlspecialchars($user['fullname']); ?></h4>
                        <p class="text-white-50"><?php echo htmlspecialchars($user['email_address']); ?></p>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-phone me-2"></i>Contact</span>
                                <span><?php echo htmlspecialchars($user['contact_number']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-calendar me-2"></i>Age</span>
                                <span><?php echo htmlspecialchars($user['age']); ?> years</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-device-hdd me-2"></i>Device</span>
                                <span><?php echo htmlspecialchars($user['device_number']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-circle-fill me-2"></i>Status</span>
                                <span class="badge bg-<?php echo $user['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-calendar-check me-2"></i>Member Since</span>
                                <span><?php echo date('M j, Y', strtotime($user['registration_date'])); ?></span>
                            </li>
                        </ul>
                    </div>
                    

                </div>
            </div>

            <div class="col-lg-8">
                <!-- Main Content with Tabs -->
                <div class="card profile-card">
                    <div class="card-header bg-white">
                        <ul class="nav nav-pills card-header-pills">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="pill" href="#profile">Profile</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#security">Security</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#activity">Activity</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Profile Tab -->
                            <div class="tab-pane fade show active" id="profile">
                                <!-- Multi-step Form -->
                                <form method="POST" id="profileForm">
                                    <input type="hidden" name="form_type" value="profile_update">
                                    <!-- Step Indicator -->
                                    <div class="step-indicator">
                                        <div class="step active" id="step1">
                                            <div class="step-number">1</div>
                                            <div class="step-title">Personal Info</div>
                                        </div>
                                        <div class="step" id="step2">
                                            <div class="step-number">2</div>
                                            <div class="step-title">Contact Details</div>
                                        </div>
                                        <div class="step" id="step3">
                                            <div class="step-number">3</div>
                                            <div class="step-title">Review</div>
                                        </div>
                                    </div>

                                    <!-- Step 1: Personal Info -->
                                    <div class="step-content" id="stepContent1">
                                        <h5 class="mb-4"><i class="bi bi-person me-2"></i>Personal Information</h5>
                                        <div class="mb-3">
                                            <label for="fullname" class="form-label">Full Name</label>
                                            <input type="text" class="form-control <?php echo isset($errors['fullname']) ? 'is-invalid' : ''; ?>" 
                                                   id="fullname" name="fullname" 
                                                   value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                                            <?php if (isset($errors['fullname'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['fullname']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="birthdate" class="form-label">Birthdate</label>
                                                <input type="date" class="form-control <?php echo isset($errors['birthdate']) ? 'is-invalid' : ''; ?>" 
                                                       id="birthdate" name="birthdate" 
                                                       value="<?php echo htmlspecialchars($user['birthdate']); ?>" required>
                                                <?php if (isset($errors['birthdate'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['birthdate']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Age</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['age']); ?>" disabled>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="button" class="btn btn-primary next-step" data-next="2">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 2: Contact Details -->
                                    <div class="step-content d-none" id="stepContent2">
                                        <h5 class="mb-4"><i class="bi bi-telephone me-2"></i>Contact Details</h5>
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                                                      id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                                            <?php if (isset($errors['address'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="contact_number" class="form-label">Contact Number</label>
                                                <input type="text" class="form-control <?php echo isset($errors['contact_number']) ? 'is-invalid' : ''; ?>" 
                                                       id="contact_number" name="contact_number" 
                                                       value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
                                                <?php if (isset($errors['contact_number'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['contact_number']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="email_address" class="form-label">Email Address</label>
                                                <input type="email" class="form-control <?php echo isset($errors['email_address']) ? 'is-invalid' : ''; ?>" 
                                                       id="email_address" name="email_address" 
                                                       value="<?php echo htmlspecialchars($user['email_address']); ?>" required>
                                                <?php if (isset($errors['email_address'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['email_address']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary prev-step" data-prev="1"><i class="bi bi-arrow-left"></i> Previous</button>
                                            <button type="button" class="btn btn-primary next-step" data-next="3">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 3: Review -->
                                    <div class="step-content d-none" id="stepContent3">
                                        <h5 class="mb-4"><i class="bi bi-check-circle me-2"></i>Review Changes</h5>
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-person me-2"></i>Personal Information</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <p><strong>Full Name:</strong> <span id="reviewFullname"><?php echo htmlspecialchars($user['fullname']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Birthdate:</strong> <span id="reviewBirthdate"><?php echo htmlspecialchars($user['birthdate']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Age:</strong> <span id="reviewAge"><?php echo htmlspecialchars($user['age']); ?></span></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-telephone me-2"></i>Contact Details</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-12 mb-3">
                                                    <p><strong>Address:</strong> <span id="reviewAddress"><?php echo htmlspecialchars($user['address']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Contact Number:</strong> <span id="reviewContact"><?php echo htmlspecialchars($user['contact_number']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Email Address:</strong> <span id="reviewEmail"><?php echo htmlspecialchars($user['email_address']); ?></span></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" id="confirmChanges" required>
                                            <label class="form-check-label" for="confirmChanges">
                                                I confirm that all the information provided is accurate
                                            </label>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary prev-step" data-prev="2"><i class="bi bi-arrow-left"></i> Previous</button>
                                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Save Changes</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security">
                                <h5 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Security Settings</h5>
                                
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="form_type" value="password_change">
                                    
                                    <div class="info-card mb-4">
                                        <h6><i class="bi bi-key me-2"></i>Change Password</h6>
                                        
                                        <div class="mb-3 mt-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="current_password" name="current_password" required>
                                            <?php if (isset($errors['current_password'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="new_password" name="new_password" required>
                                            <?php if (isset($errors['new_password'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                            <?php endif; ?>
                                            <div class="password-strength strength-0" id="passwordStrength"></div>
                                            <small class="text-muted">Password must be at least 8 characters with uppercase, lowercase, number, and special character</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="confirm_password" name="confirm_password" required>
                                            <?php if (isset($errors['confirm_password'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                                
                                <div class="info-card">
                                    <h6><i class="bi bi-device-hdd me-2"></i>Device Information</h6>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <p><strong>Device Number:</strong> <?php echo htmlspecialchars($user['device_number']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Status:</strong> 
                                                <span class="badge bg-<?php echo $user['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($user['status']); ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning mt-3">
                                        <i class="bi bi-exclamation-triangle"></i> Contact support to change your device number.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Activity Tab -->
                            <div class="tab-pane fade" id="activity">
                                <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                                
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong>Profile Updated</strong>
                                        <span class="activity-time">Just now</span>
                                    </div>
                                    <p class="mb-0">You updated your profile information</p>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong>Account Created</strong>
                                        <span class="activity-time"><?php echo date('M j, Y', strtotime($user['registration_date'])); ?></span>
                                    </div>
                                    <p class="mb-0">You joined our platform</p>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button class="btn btn-sm btn-outline-primary">View Full Activity Log</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
     

    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
                    document.getElementById('reviewFullname').textContent = document.getElementById('fullname').value;
                    document.getElementById('reviewBirthdate').textContent = document.getElementById('birthdate').value;
                    document.getElementById('reviewAge').textContent = document.querySelector('input[value="<?php echo $user['age']; ?>"]').value;
                    document.getElementById('reviewAddress').textContent = document.getElementById('address').value;
                    document.getElementById('reviewContact').textContent = document.getElementById('contact_number').value;
                    document.getElementById('reviewEmail').textContent = document.getElementById('email_address').value;
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

        // Calculate age from birthdate
        document.getElementById('birthdate').addEventListener('change', function() {
            const birthdate = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthdate.getFullYear();
            const monthDiff = today.getMonth() - birthdate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                age--;
            }
            
            document.querySelector('input[value="<?php echo $user['age']; ?>"]').value = age;
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

        // Profile picture upload modal handling - Custom implementation
        document.addEventListener('DOMContentLoaded', function() {
        const uploadBtn = document.getElementById('uploadProfileBtn');
            const profileImageModalElement = document.getElementById('profileImageModal');
        const profileImageInput = document.getElementById('profile_image');
        const profileImageDisplay = document.getElementById('profileImageDisplay');
            const profileImagePreview = document.getElementById('profileImagePreview');
            const imageOverlay = document.getElementById('imageOverlay');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
            
            // Check if all required elements exist
            if (!uploadBtn || !profileImageModalElement || !profileImageInput || 
                !profileImageDisplay || !profileImagePreview || !imageOverlay || 
                !uploadProgress || !progressBar || !uploadSubmitBtn) {
                console.warn('Some modal elements are missing');
                return;
            }
            
            // Custom modal functions
            window.showModal = function() {
                if (profileImageModalElement) {
                    profileImageModalElement.classList.add('show');
                    profileImageModalElement.style.display = 'block';
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'modalBackdrop';
                    document.body.appendChild(backdrop);
                    
                    // Focus trap for accessibility
                    profileImageModalElement.focus();
                }
            };
            
            window.hideModal = function() {
                if (profileImageModalElement) {
                    profileImageModalElement.classList.remove('show');
                    profileImageModalElement.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    
                    // Remove backdrop
                    const backdrop = document.getElementById('modalBackdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    
                    // Reset modal state
                    resetModal();
                }
            };
            
            // Open modal when upload button is clicked
        uploadBtn.addEventListener('click', function() {
                showModal();
            });
        
            // Handle file selection
        profileImageInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                    const file = this.files[0];
                    
                    // Validate file size (2MB limit)
                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            title: 'File Too Large',
                            text: 'Please select an image smaller than 2MB',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        this.value = '';
                        return;
                    }
                    
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        Swal.fire({
                            title: 'Invalid File Type',
                            text: 'Please select a JPG, PNG, or GIF image',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        this.value = '';
                        return;
                    }
                    
                const reader = new FileReader();
                
                reader.onload = function(e) {
                        profileImagePreview.src = e.target.result;
                    profileImageDisplay.src = e.target.result;
                        imageOverlay.style.display = 'none';
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle form submission
            document.getElementById('profilePictureForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!profileImageInput.files || !profileImageInput.files[0]) {
                    Swal.fire({
                        title: 'No Image Selected',
                        text: 'Please select an image to upload',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                // Show progress bar
                uploadProgress.classList.remove('d-none');
                uploadSubmitBtn.disabled = true;
                uploadSubmitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';
                
                // Simulate progress (you can replace this with actual upload progress)
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 10;
                    progressBar.style.width = progress + '%';
                    
                    if (progress >= 100) {
                        clearInterval(progressInterval);
                        // Submit the form
                        this.submit();
                    }
                }, 100);
            });
            
            // Handle backdrop clicks
            profileImageModalElement.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideModal();
                }
            });
            
            // Handle ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && profileImageModalElement.classList.contains('show')) {
                    hideModal();
                }
            });
            
            function resetModal() {
                profileImageInput.value = '';
                profileImagePreview.src = profileImageDisplay.src;
                imageOverlay.style.display = 'flex';
                uploadProgress.classList.add('d-none');
                progressBar.style.width = '0%';
                uploadSubmitBtn.disabled = false;
                uploadSubmitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Update Picture';
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

        // SweetAlert notifications
        <?php if (isset($_SESSION['swal'])): ?>
            Swal.fire({
                title: '<?php echo $_SESSION['swal']['title']; ?>',
                text: '<?php echo $_SESSION['swal']['text']; ?>',
                icon: '<?php echo $_SESSION['swal']['icon']; ?>',
                confirmButtonText: '<?php echo $_SESSION['swal']['confirmButtonText']; ?>',
                timer: 3000,
                timerProgressBar: true,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            });
            <?php unset($_SESSION['swal']); ?>
        <?php endif; ?>

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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


<!-- jQuery -->
<script src="../../../vendors/jquery/dist/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="../../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<!-- FastClick -->
<script src="../../../vendors/fastclick/lib/fastclick.js"></script>
<!-- NProgress -->
<script src="../../../vendors/nprogress/nprogress.js"></script>
<!-- Chart.js -->
<script src="../../../vendors/Chart.js/dist/Chart.min.js"></script>
<!-- gauge.js -->
<script src="../../../vendors/gauge.js/dist/gauge.min.js"></script>
<!-- bootstrap-progressbar -->
<script src="../../../vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
<!-- iCheck -->
<script src="../../../vendors/iCheck/icheck.min.js"></script>
<!-- Skycons -->
<script src="../../../vendors/skycons/skycons.js"></script>
<!-- Flot -->
<script src="../../../vendors/Flot/jquery.flot.js"></script>
<script src="../../../vendors/Flot/jquery.flot.pie.js"></script>
<script src="../../../vendors/Flot/jquery.flot.time.js"></script>
<script src="../../../vendors/Flot/jquery.flot.stack.js"></script>
<script src="../../../vendors/Flot/jquery.flot.resize.js"></script>
<!-- Flot plugins -->
<script src="../../../vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
<script src="../../../vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
<script src="../../../vendors/flot.curvedlines/curvedLines.js"></script>
<!-- DateJS -->
<script src="../../../vendors/DateJS/build/date.js"></script>
<!-- JQVMap -->
<script src="../../../vendors/jqvmap/dist/jquery.vmap.js"></script>
<script src="../../../vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
<script src="../../../vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>
<!-- bootstrap-daterangepicker -->
<script src="../../../vendors/moment/min/moment.min.js"></script>
<script src="../../../vendors/bootstrap-daterangepicker/daterangepicker.js"></script>

<!-- Custom Theme Scripts -->
<script src="../../../build/js/custom.min.js"></script>

<!-- Profile Picture Upload Modal -->
<div class="modal" id="profileImageModal" tabindex="-1" aria-labelledby="profileImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileImageModalLabel">
                    <i class="bi bi-camera me-2"></i>Update Profile Picture
                </h5>
                <button type="button" class="btn-close" onclick="hideModal()" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                    <input type="hidden" name="form_type" value="profile_picture">
                    
                    <!-- Image Preview Section -->
                    <div class="text-center mb-4">
                        <div class="profile-image-preview-container">
                            <img src="<?php echo getProfileImageUrl($user['profile_image']); ?>" 
                                 class="profile-image-preview" alt="Profile Preview" id="profileImagePreview">
                            <div class="profile-image-overlay" id="imageOverlay">
                                <i class="bi bi-camera-fill"></i>
                                <p class="mb-0">Click to upload</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Input -->
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Select New Profile Picture</label>
                        <input type="file" class="form-control" name="profile_image" id="profile_image" accept="image/*">
                        <?php if (isset($errors['profile_image'])): ?>
                            <div class="text-danger small mt-1"><?php echo $errors['profile_image']; ?></div>
                        <?php endif; ?>
                        <small class="text-muted">JPG, PNG or GIF (Max 2MB)</small>
                    </div>
                    
                    <!-- Upload Progress -->
                    <div class="upload-progress d-none" id="uploadProgress">
                        <div class="progress mb-2">
                            <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                        </div>
                        <small class="text-muted">Uploading...</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="hideModal()">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" form="profilePictureForm" class="btn btn-primary" id="uploadSubmitBtn">
                    <i class="bi bi-upload me-1"></i>Update Picture
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>