<!-- Forgot Password Form -->
<div class="forgot-container" id="forgotContainer">
    <div class="close-btn" id="closeForgot">×</div>
    <form id="forgotPasswordForm" method="post" action="">
        <input type="hidden" name="action" value="forgot_password">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <h2>Reset Password</h2>
        
        <div class="input-group">
            <input type="email" id="forgotEmail" name="email" required autocomplete="email" placeholder="Email Address">
        </div>

        <button type="submit" class="login-btn" id="forgotButton">
            <span id="forgotButtonText">SEND RESET LINK</span>
        </button>

        <div class="register-link">
            Remember your password? <a href="#" id="backToLoginLink">Back to login</a>
        </div>
    </form>
</div>

<!-- Reset Password Form -->
<div class="reset-container" id="resetContainer">
    <div class="close-btn" id="closeReset">×</div>
    <form id="resetPasswordForm" method="post" action="">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($reset_token ?? '') ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($reset_email ?? '') ?>">
        <h2>Set New Password</h2>
        
        <div class="input-group">
            <input type="password" id="newPassword" name="password" required autocomplete="new-password" placeholder="New Password">
        </div>

        <div class="input-group">
            <input type="password" id="confirmPassword" name="confirm_password" required autocomplete="new-password" placeholder="Confirm New Password">
        </div>

        <button type="submit" class="login-btn" id="resetButton">
            <span id="resetButtonText">UPDATE PASSWORD</span>
        </button>

        <div class="register-link">
            Remember your password? <a href="#" id="backToLoginLink2">Back to login</a>
        </div>
    </form>
</div> 