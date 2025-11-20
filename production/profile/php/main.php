<?php
require_once __DIR__ . '/../functions/functions.php';

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
  <?php include('../../components/header.php'); ?>
  
  <!-- Profile Page CSS -->
  <link rel="stylesheet" href="../css/style.css">

<body class="nav-md" data-admin-id="<?php echo isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : ''; ?>">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main">        
            <main class="main-content">
    <div class="container py-5">
        <!-- Display any errors from form processing -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Error:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-4">
                <!-- Combined Profile Card with Picture Upload -->
                <div class="card profile-card mb-4">
                    <div class="profile-header text-center py-4">
                        <div class="position-relative d-inline-block">
                            <div class="profile-image-container">
                                <img src="<?php echo getProfileImageUrl($admin['profile_image']); ?>" 
                                     class="profile-avatar" alt="Profile" id="profileImageDisplay">
                                <button type="button" class="profile-image-upload-btn" id="uploadProfileBtn" data-bs-toggle="modal" data-bs-target="#profilePictureModal">
                                    <i class="bi bi-camera"></i>
                                </button>
                            </div>
                            <span class="status-badge <?php echo $admin['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>"></span>
                        </div>
                        <h4 class="mt-3 mb-0"><?php echo htmlspecialchars($admin['full_name']); ?></h4>
                        <p class="text-white-50"><?php echo htmlspecialchars($admin['email']); ?></p>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-badge me-2"></i>Username</span>
                                <span><?php echo htmlspecialchars($admin['username']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-telephone me-2"></i>Contact</span>
                                <span><?php echo htmlspecialchars($admin['contact_number']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-shield me-2"></i>Role</span>
                                <span class="badge role-<?php echo str_replace(' ', '_', strtolower($admin['role'])); ?> role-badge">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $admin['role']))); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-circle-fill me-2"></i>Status</span>
                                <span class="badge bg-<?php echo $admin['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($admin['status']); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-calendar-check me-2"></i>Member Since</span>
                                <span><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-clock-history me-2"></i>Last Updated</span>
                                <span><?php echo date('M j, Y g:i A', strtotime($admin['updated_at'])); ?></span>
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
                                            <label for="full_name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                                   id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                                            <?php if (isset($errors['full_name'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                                            <small class="text-muted">Username cannot be changed</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $admin['role']))); ?>" disabled>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="button" class="btn btn-primary next-step" data-next="2">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 2: Contact Details -->
                                    <div class="step-content d-none" id="stepContent2">
                                        <h5 class="mb-4"><i class="bi bi-telephone me-2"></i>Contact Details</h5>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                                   id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                            <?php if (isset($errors['email'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="contact_number" class="form-label">Contact Number</label>
                                            <input type="text" class="form-control <?php echo isset($errors['contact_number']) ? 'is-invalid' : ''; ?>" 
                                                   id="contact_number" name="contact_number" 
                                                   value="<?php echo htmlspecialchars($admin['contact_number']); ?>" required>
                                            <?php if (isset($errors['contact_number'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['contact_number']; ?></div>
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
                                                    <p><strong>Full Name:</strong> <span id="reviewFullname"><?php echo htmlspecialchars($admin['full_name']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Username:</strong> <?php echo htmlspecialchars($admin['username']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $admin['role']))); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-telephone me-2"></i>Contact Details</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <p><strong>Email Address:</strong> <span id="reviewEmail"><?php echo htmlspecialchars($admin['email']); ?></span></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Contact Number:</strong> <span id="reviewContact"><?php echo htmlspecialchars($admin['contact_number']); ?></span></p>
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
                                <!-- Multi-step Security Form -->
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="form_type" value="password_change">
                                    
                                    <!-- Step Indicator -->
                                    <div class="step-indicator">
                                        <div class="step active" id="securityStep1">
                                            <div class="step-number">1</div>
                                            <div class="step-title">Current Password</div>
                                        </div>
                                        <div class="step" id="securityStep2">
                                            <div class="step-number">2</div>
                                            <div class="step-title">New Password</div>
                                        </div>
                                        <div class="step" id="securityStep3">
                                            <div class="step-number">3</div>
                                            <div class="step-title">Review & Confirm</div>
                                        </div>
                                    </div>

                                    <!-- Step 1: Current Password -->
                                    <div class="step-content" id="securityStepContent1">
                                        <h5 class="mb-4"><i class="bi bi-key me-2"></i>Verify Current Password</h5>
                                        <div class="info-card mb-4">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                                       id="current_password" name="current_password" required>
                                                <?php if (isset($errors['current_password'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                                <?php endif; ?>
                                                <small class="text-muted">Enter your current password to proceed with the change</small>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="button" class="btn btn-primary next-step-security" data-next="2">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 2: New Password -->
                                    <div class="step-content d-none" id="securityStepContent2">
                                        <h5 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Set New Password</h5>
                                        <div class="info-card mb-4">
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
                                                <small class="text-muted">Re-enter your new password to confirm</small>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary prev-step-security" data-prev="1"><i class="bi bi-arrow-left"></i> Previous</button>
                                            <button type="button" class="btn btn-primary next-step-security" data-next="3">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 3: Review & Confirm -->
                                    <div class="step-content d-none" id="securityStepContent3">
                                        <h5 class="mb-4"><i class="bi bi-check-circle me-2"></i>Review Password Change</h5>
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-shield-check me-2"></i>Password Change Summary</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <p><strong>Current Password:</strong> <span id="reviewCurrentPassword">••••••••</span></p>
                                                </div>
                                                <div class="col-md-12">
                                                    <p><strong>New Password:</strong> <span id="reviewNewPassword">••••••••</span></p>
                                                </div>
                                                <div class="col-md-12">
                                                    <p><strong>Password Strength:</strong> <span id="reviewPasswordStrength">-</span></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-shield-exclamation me-2"></i>Security Information</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <p><strong>Last Password Change:</strong> 
                                                        <?php echo $admin['updated_at'] ? date('M j, Y g:i A', strtotime($admin['updated_at'])) : 'Never changed'; ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Account Status:</strong> 
                                                        <span class="badge bg-<?php echo $admin['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($admin['status']); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" id="confirmPasswordChange" required>
                                            <label class="form-check-label" for="confirmPasswordChange">
                                                I confirm that I want to change my password and understand the security implications
                                            </label>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> For security reasons, some account settings can only be changed by a system administrator.
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary prev-step-security" data-prev="2"><i class="bi bi-arrow-left"></i> Previous</button>
                                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Change Password</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Activity Tab -->
                            <div class="tab-pane fade" id="activity">
                                <!-- Multi-step Activity Form -->
                                <div id="activityWizard">
                                    <!-- Step Indicator -->
                                    <div class="step-indicator">
                                        <div class="step active" id="activityStep1">
                                            <div class="step-number">1</div>
                                            <div class="step-title">Recent Activity</div>
                                        </div>
                                        <div class="step" id="activityStep2">
                                            <div class="step-number">2</div>
                                            <div class="step-title">Activity Details</div>
                                        </div>
                                        <div class="step" id="activityStep3">
                                            <div class="step-number">3</div>
                                            <div class="step-title">Activity Summary</div>
                                        </div>
                                    </div>

                                    <!-- Step 1: Recent Activity -->
                                    <div class="step-content" id="activityStepContent1">
                                        <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>Recent Activity Overview</h5>
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-list-ul me-2"></i>Latest Activities</h6>
                                            <div class="activity-item mt-3">
                                                <div class="d-flex justify-content-between">
                                                    <strong><i class="bi bi-person-check me-2"></i>Profile Updated</strong>
                                                    <span class="activity-time">Just now</span>
                                                </div>
                                                <p class="mb-0">You updated your profile information</p>
                                            </div>
                                            
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <strong><i class="bi bi-box-arrow-in-right me-2"></i>Login</strong>
                                                    <span class="activity-time"><?php echo date('M j, g:i A', strtotime($admin['updated_at'])); ?></span>
                                                </div>
                                                <p class="mb-0">You logged in to your account</p>
                                            </div>
                                            
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <strong><i class="bi bi-person-plus me-2"></i>Account Created</strong>
                                                    <span class="activity-time"><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></span>
                                                </div>
                                                <p class="mb-0">Your admin account was created</p>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="button" class="btn btn-primary next-step-activity" data-next="2">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 2: Activity Details -->
                                    <div class="step-content d-none" id="activityStepContent2">
                                        <h5 class="mb-4"><i class="bi bi-info-circle me-2"></i>Activity Details</h5>
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-calendar-event me-2"></i>Activity Timeline</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <p><strong>Account Created:</strong></p>
                                                    <p class="text-muted"><?php echo date('F j, Y', strtotime($admin['created_at'])); ?></p>
                                                    <p class="text-muted"><?php echo date('g:i A', strtotime($admin['created_at'])); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Last Updated:</strong></p>
                                                    <p class="text-muted"><?php echo date('F j, Y', strtotime($admin['updated_at'])); ?></p>
                                                    <p class="text-muted"><?php echo date('g:i A', strtotime($admin['updated_at'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-shield-check me-2"></i>Account Status</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <p><strong>Current Status:</strong> 
                                                        <span class="badge bg-<?php echo $admin['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($admin['status']); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Role:</strong> 
                                                        <span class="badge role-<?php echo str_replace(' ', '_', strtolower($admin['role'])); ?> role-badge">
                                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $admin['role']))); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary prev-step-activity" data-prev="1"><i class="bi bi-arrow-left"></i> Previous</button>
                                            <button type="button" class="btn btn-primary next-step-activity" data-next="3">Next <i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    </div>

                                    <!-- Step 3: Activity Summary -->
                                    <div class="step-content d-none" id="activityStepContent3">
                                        <h5 class="mb-4"><i class="bi bi-file-text me-2"></i>Activity Summary</h5>
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-graph-up me-2"></i>Activity Overview</h6>
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <div class="text-center p-3 border rounded">
                                                        <h4 class="mb-0"><?php echo date('Y') - date('Y', strtotime($admin['created_at'])); ?></h4>
                                                        <small class="text-muted">Years Active</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="text-center p-3 border rounded">
                                                        <h4 class="mb-0"><?php echo $admin['status'] === 'Active' ? 'Active' : 'Inactive'; ?></h4>
                                                        <small class="text-muted">Account Status</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="text-center p-3 border rounded">
                                                        <h4 class="mb-0"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $admin['role']))); ?></h4>
                                                        <small class="text-muted">User Role</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="info-card mb-4">
                                            <h6><i class="bi bi-clock-history me-2"></i>Recent Activity Log</h6>
                                            <div class="mt-3">
                                                <p><strong>Profile Updated:</strong> <span class="text-muted">Just now</span></p>
                                                <p><strong>Last Login:</strong> <span class="text-muted"><?php echo date('M j, Y g:i A', strtotime($admin['updated_at'])); ?></span></p>
                                                <p><strong>Account Created:</strong> <span class="text-muted"><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></span></p>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary prev-step-activity" data-prev="2"><i class="bi bi-arrow-left"></i> Previous</button>
                                            <button type="button" class="btn btn-primary" onclick="window.location.reload();">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Refresh Activity
                                            </button>
                                        </div>
                                        
                                        <div class="text-center mt-4">
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark-text me-2"></i>View Full Activity Log
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    <!-- Profile Picture Upload Modal -->
    <div class="modal fade" id="profilePictureModal" tabindex="-1" aria-labelledby="profilePictureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profilePictureModalLabel">
                        <i class="bi bi-camera me-2"></i>Update Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="profilePictureModalForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="form_type" value="profile_picture">
                        
                        <!-- Error Display for Profile Picture Upload -->
                        <?php if (isset($errors['profile_image'])): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Upload Error:</strong> <?php echo htmlspecialchars($errors['profile_image']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Current Profile Picture Display -->
                        <div class="text-center mb-4">
                            <div class="position-relative d-inline-block">
                                <img src="<?php echo getProfileImageUrl($admin['profile_image']); ?>" 
                                     class="profile-avatar-modal" alt="Current Profile" id="modalProfileImageDisplay">
                                <div class="profile-image-overlay">
                                    <i class="bi bi-camera"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Upload Section -->
                        <div class="upload-section mb-4">
                            <div class="mb-3">
                                <label for="modalProfileImage" class="form-label">Select Profile Picture</label>
                                <input type="file" class="form-control" name="profile_image" id="modalProfileImage" accept="image/*" required>
                                <small class="text-muted">JPG, PNG or GIF (Max 2MB)</small>
                            </div>
                        </div>
                        
                        <!-- Preview Section (Hidden by default) -->
                        <div class="preview-section d-none" id="previewSection">
                            <h6 class="mb-3"><i class="bi bi-eye me-2"></i>Preview</h6>
                            <div class="preview-container">
                                <img src="" alt="Preview" id="imagePreview" class="preview-image">
                            </div>
                        </div>
                        
                        <!-- Error Display -->
                        <div id="modalErrorDisplay" class="alert alert-danger d-none">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <span id="modalErrorText"></span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-2"></i>Cancel
                    </button>
                    <button type="submit" form="profilePictureModalForm" class="btn btn-primary" id="updateProfilePictureBtn" disabled>
                        <i class="bi bi-check-lg me-2"></i>Update Picture
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // SweetAlert notifications
    <?php if (isset($_SESSION['swal'])): ?>
        Swal.fire({
            title: '<?php echo htmlspecialchars($_SESSION['swal']['title'], ENT_QUOTES, 'UTF-8'); ?>',
            text: '<?php echo htmlspecialchars($_SESSION['swal']['text'], ENT_QUOTES, 'UTF-8'); ?>',
            icon: '<?php echo htmlspecialchars($_SESSION['swal']['icon'], ENT_QUOTES, 'UTF-8'); ?>',
            confirmButtonText: '<?php echo htmlspecialchars($_SESSION['swal']['confirmButtonText'], ENT_QUOTES, 'UTF-8'); ?>',
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
    </script>

<?php include '../../../../components/scripts.php'; ?>
<script src="../js/script.js"></script>
</body>
</html>