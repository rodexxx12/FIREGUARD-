<?php require_once '../functions/functions.php'; ?>
    <style>
    .profile-card .nav-pills .nav-link {
        border-radius: 20px;
        margin: 0 6px;
        font-weight: 500;
        color: #495057;
        background: #f8f9fa;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    }
    .profile-card .nav-pills .nav-link.active {
        background: linear-gradient(90deg, #ff6b6b 0%, #ff8e53 100%);
        color: #fff !important;
        box-shadow: 0 4px 16px -4px #ff8e53a0;
        font-weight: 700;
        border: none;
    }

    .profile-card .tab-content {
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 6px 32px -8px rgba(255,107,107,0.08), 0 1.5px 6px rgba(0,0,0,0.03);
        padding: 32px 24px 24px 24px;
        margin-top: 18px;
        min-height: 340px;
        animation: fadeInTab 0.5s cubic-bezier(.4,0,.2,1);
        color: #111 !important;
    }
    .profile-card .tab-content * {
        color: #111 !important;
    }
    @keyframes fadeInTab {
        from { opacity: 0; transform: translateY(24px); }
        to { opacity: 1; transform: none; }
    }
    @media (max-width: 767px) {
        .profile-card .tab-content {
            padding: 18px 6px 12px 6px;
            min-height: 220px;
        }
    }
</style>
</head>
 <!-- Include header with all necessary libraries -->
 <?php include '../../components/header.php'; ?>
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
                            <img src="<?php echo getProfileImageUrl($firefighter['profile_image']); ?>" 
                                     class="profile-avatar" alt="Profile" id="profileImageDisplay">
                                <button type="button" class="profile-image-upload-btn" id="uploadProfileBtn">
                                    <i class="bi bi-camera"></i>
                                </button>
                            </div>
                            <span class="status-badge <?php echo $firefighter['availability'] ? 'status-active' : 'status-inactive'; ?>"></span>
                        </div>
                        <h4 class="mt-3 mb-0"><?php echo htmlspecialchars($firefighter['name']); ?></h4>
                        <p class="text-white-50"><?php echo htmlspecialchars($firefighter['email']); ?></p>
                        <?php if ($firefighter['rank']): ?>
                            <span class="badge-rank"><?php echo htmlspecialchars($firefighter['rank']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-phone me-2"></i>Phone</span>
                                <span><?php echo htmlspecialchars($firefighter['phone']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-shield me-2"></i>Badge Number</span>
                                <span><?php echo htmlspecialchars($firefighter['badge_number'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-stars me-2"></i>Specialization</span>
                                <span><?php echo htmlspecialchars($firefighter['specialization'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-circle-fill me-2"></i>Status</span>
                                <span class="badge bg-<?php echo $firefighter['availability'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $firefighter['availability'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-calendar-check me-2"></i>Member Since</span>
                                <span><?php echo date('M j, Y', strtotime($firefighter['created_at'])); ?></span>
                            </li>
                            <?php if ($firefighter['updated_at']): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-clock-history me-2"></i>Last Updated</span>
                                <span><?php echo date('M j, Y g:i A', strtotime($firefighter['updated_at'])); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Profile Picture Upload Form (Hidden by default) -->
                    <!-- Add the modal after the profile card -->
                    <!-- Remove the Bootstrap modal for profile image upload -->
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
                                            <div class="step-title">Professional Details</div>
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
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                                   id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($firefighter['name']); ?>" required>
                                            <?php if (isset($errors['name'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                                   id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($firefighter['email']); ?>" required>
                                            <?php if (isset($errors['email'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                                   id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($firefighter['phone']); ?>" required>
                                            <?php if (isset($errors['phone'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="button" class="btn btn-primary next-step" data-next="2">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 2: Professional Details -->
                                    <div class="step-content d-none" id="stepContent2">
                                        <h5 class="mb-4"><i class="bi bi-briefcase me-2"></i>Professional Details</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="badge_number" class="form-label">Badge Number</label>
                                                <input type="text" class="form-control <?php echo isset($errors['badge_number']) ? 'is-invalid' : ''; ?>" 
                                                       id="badge_number" name="badge_number" 
                                                       value="<?php echo htmlspecialchars($firefighter['badge_number']); ?>">
                                                <?php if (isset($errors['badge_number'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['badge_number']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="rank" class="form-label">Rank</label>
                                                <input type="text" class="form-control <?php echo isset($errors['rank']) ? 'is-invalid' : ''; ?>" 
                                                       id="rank" name="rank" 
                                                       value="<?php echo htmlspecialchars($firefighter['rank']); ?>">
                                                <?php if (isset($errors['rank'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['rank']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="specialization" class="form-label">Specialization</label>
                                            <input type="text" class="form-control <?php echo isset($errors['specialization']) ? 'is-invalid' : ''; ?>" 
                                                   id="specialization" name="specialization" 
                                                   value="<?php echo htmlspecialchars($firefighter['specialization']); ?>">
                                            <?php if (isset($errors['specialization'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['specialization']; ?></div>
                                            <?php endif; ?>
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
                                                    <p><strong>Full Name:</strong> <span id="reviewName"><?php echo htmlspecialchars($firefighter['name']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Email:</strong> <span id="reviewEmail"><?php echo htmlspecialchars($firefighter['email']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Phone:</strong> <span id="reviewPhone"><?php echo htmlspecialchars($firefighter['phone']); ?></span></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-briefcase me-2"></i>Professional Details</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <p><strong>Badge Number:</strong> <span id="reviewBadge"><?php echo htmlspecialchars($firefighter['badge_number'] ?? 'N/A'); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Rank:</strong> <span id="reviewRank"><?php echo htmlspecialchars($firefighter['rank'] ?? 'N/A'); ?></span></p>
                                                </div>
                                                <div class="col-md-12">
                                                    <p><strong>Specialization:</strong> <span id="reviewSpecialization"><?php echo htmlspecialchars($firefighter['specialization'] ?? 'N/A'); ?></span></p>
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
                                    <h6><i class="bi bi-person-badge me-2"></i>Account Information</h6>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <p><strong>Username:</strong> <?php echo htmlspecialchars($firefighter['username']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Status:</strong> 
                                                <span class="badge bg-<?php echo $firefighter['availability'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $firefighter['availability'] ? 'Available' : 'Unavailable'; ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning mt-3">
                                        <i class="bi bi-exclamation-triangle"></i> Contact administrator to change your username or availability status.
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
                                        <strong>Login</strong>
                                        <span class="activity-time"><?php echo date('M j, g:i A', strtotime($firefighter['updated_at'])); ?></span>
                                    </div>
                                    <p class="mb-0">You logged in to your account</p>
                                </div>
                                
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong>Account Created</strong>
                                        <span class="activity-time"><?php echo date('M j, Y', strtotime($firefighter['created_at'])); ?></span>
                                    </div>
                                    <p class="mb-0">You joined the firefighting team</p>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button class="btn btn-sm btn-outline-primary">View Full Activity Log</button>
                                </div>
                            </div>
                        </div>
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
                    document.getElementById('reviewName').textContent = document.getElementById('name').value;
                    document.getElementById('reviewEmail').textContent = document.getElementById('email').value;
                    document.getElementById('reviewPhone').textContent = document.getElementById('phone').value;
                    document.getElementById('reviewBadge').textContent = document.getElementById('badge_number').value || 'N/A';
                    document.getElementById('reviewRank').textContent = document.getElementById('rank').value || 'N/A';
                    document.getElementById('reviewSpecialization').textContent = document.getElementById('specialization').value || 'N/A';
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
        const profileImageDisplay = document.getElementById('profileImageDisplay');
        
        uploadBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Upload Profile Picture',
                html: `
                    <div id='swal-drop-area' style='border:2px dashed #ccc; border-radius:12px; padding:24px; text-align:center; background:#fafbfc;'>
                        <i class='bi bi-cloud-arrow-up' style='font-size:2.5rem; color:#6c757d;'></i><br>
                        <span style='font-size:1.1rem;'>Drag & drop or click to select an image</span>
                        <input type='file' id='swal-profile-image' class='swal2-file' accept='image/*' style='display:none;'>
                        <div id='swal-preview' style='margin-top:16px;'></div>
                    </div>
                    <small class='text-muted d-block mt-2'>JPG, PNG or GIF (Max 2MB)</small>
                `,
                showCancelButton: true,
                confirmButtonText: 'Upload',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                willOpen: () => {
                    const dropArea = Swal.getPopup().querySelector('#swal-drop-area');
                    const fileInput = Swal.getPopup().querySelector('#swal-profile-image');
                    const preview = Swal.getPopup().querySelector('#swal-preview');
                    // Click to open file dialog
                    dropArea.addEventListener('click', () => fileInput.click());
                    // Drag & drop
                    dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.style.background='#f1f3f4'; });
                    dropArea.addEventListener('dragleave', e => { e.preventDefault(); dropArea.style.background='#fafbfc'; });
                    dropArea.addEventListener('drop', e => {
                        e.preventDefault();
                        dropArea.style.background='#fafbfc';
                        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                            fileInput.files = e.dataTransfer.files;
                            showPreview(fileInput.files[0], preview);
                        }
                    });
                    // File input change
                    fileInput.addEventListener('change', () => {
                        if (fileInput.files && fileInput.files[0]) {
                            showPreview(fileInput.files[0], preview);
                        }
                    });
                    // Helper to show preview
                    function showPreview(file, previewEl) {
                        if (!file.type.startsWith('image/')) {
                            previewEl.innerHTML = '<span class="text-danger">Not an image file.</span>';
                            return;
                        }
                const reader = new FileReader();
                        reader.onload = e => {
                            previewEl.innerHTML = `<img src='${e.target.result}' style='max-width:120px; max-height:120px; border-radius:50%; box-shadow:0 2px 8px #ccc; margin-bottom:8px;'><br><span style='font-size:0.95rem;'>Preview</span>`;
                        };
                        reader.readAsDataURL(file);
                    }
                },
                preConfirm: () => {
                    const fileInput = Swal.getPopup().querySelector('#swal-profile-image');
                    if (!fileInput.files || !fileInput.files[0]) {
                        Swal.showValidationMessage('Please select an image file');
                        return false;
                    }
                    const file = fileInput.files[0];
                    if (file.size > 2 * 1024 * 1024) {
                        Swal.showValidationMessage('File size must be less than 2MB');
                        return false;
                    }
                    return file;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const file = result.value;
                    const formData = new FormData();
                    formData.append('form_type', 'profile_picture');
                    formData.append('profile_image', file);
                    Swal.fire({
                        title: 'Uploading...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    fetch(window.location.pathname, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        window.location.reload();
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Upload failed: ' + error.message, 'error');
                    });
                }
            });
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
                confirmButtonColor: '#ff6b6b',
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
 <?php include('../../components/scripts.php'); ?>
    <!-- Profile Picture Upload Success Modal -->
    <div class="modal fade" id="profileUploadModal" tabindex="-1" aria-labelledby="profileUploadModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="profileUploadModalLabel">Profile Picture Updated</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Your profile picture has been successfully updated!
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Profile Picture Upload Success Modal -->
    <?php if (isset($_POST['form_type']) && $_POST['form_type'] === 'profile_picture' && empty($errors['profile_image'])): ?>
        <span id="profile-upload-success-flag" style="display:none;"></span>
    <?php endif; ?>
</body>
</html>