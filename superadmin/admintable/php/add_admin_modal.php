<?php
// Add Admin Modal Component
?>
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="addAdminForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addAdminModalLabel">
            <i class="fas fa-user-plus me-2"></i>Add New Admin
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="insert">
          
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-user me-1"></i>Full Name <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control" name="full_name" id="add_full_name" 
                     placeholder="Enter full name" required>
              <div class="invalid-feedback">Please provide a valid full name.</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-envelope me-1"></i>Email <span class="text-danger">*</span>
              </label>
              <input type="email" class="form-control" name="email" id="add_email" 
                     placeholder="Enter email address" required>
              <div class="invalid-feedback">Please provide a valid email address.</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-user-tag me-1"></i>Username <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control" name="username" id="add_username" 
                     placeholder="Enter username" required>
              <div class="invalid-feedback">Please provide a username.</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-phone me-1"></i>Contact Number <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control" name="contact_number" id="add_contact_number" 
                     placeholder="Enter contact number (e.g., 09123456789)" maxlength="11" required>
              <div class="invalid-feedback">Please provide a valid 11-digit contact number starting with 09.</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-shield-alt me-1"></i>Role <span class="text-danger">*</span>
              </label>
              <select class="form-select" name="role" id="add_role" required>
                <option value="fire_officer" selected>Fire Officer Admin</option>
              </select>
              <div class="invalid-feedback">Please select a role.</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-toggle-on me-1"></i>Status <span class="text-danger">*</span>
              </label>
              <select class="form-select" name="status" id="add_status" required>
                <option value="">Select Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
              <div class="invalid-feedback">Please select a status.</div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-lock me-1"></i>Password <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="password" class="form-control" name="password" id="add_password" 
                       placeholder="Enter password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <div class="invalid-feedback">Please provide a password.</div>
              <div class="password-strength mt-1" id="passwordStrength"></div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">
                <i class="fas fa-lock me-1"></i>Confirm Password <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="password" class="form-control" name="confirm_password" id="add_confirm_password" 
                       placeholder="Confirm password" required>
                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <div class="invalid-feedback">Please confirm your password.</div>
              <div class="password-match mt-1" id="passwordMatch"></div>
            </div>
          </div>
          
          <div class="alert alert-info mt-3" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> All fields marked with <span class="text-danger">*</span> are required. 
            The system will automatically hash the password for security.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i>Add Admin
          </button>
        </div>
      </form>
    </div>
  </div>
</div> 