/**
 * Real-time Form Validation for Fire Detection System Registration
 * 
 * This file provides comprehensive validation for all form fields with real-time feedback.
 * It includes visual indicators, error messages, and validation states.
 */

class FormValidator {
    constructor() {
        this.validationStates = {};
        this.debounceTimers = {};
        this.init();
    }

    init() {
        this.setupValidationListeners();
        this.setupPasswordStrengthMeter();
        this.setupConfirmPasswordFeedback();
        this.setupDeviceFormatValidation();
        this.setupLocationValidation();
    }

    /**
     * Setup validation listeners for all form fields
     */
    setupValidationListeners() {
        // Personal Information Fields
        this.setupFieldValidation('fullname', this.validateFullName.bind(this));
        this.setupFieldValidation('email', this.validateEmail.bind(this));
        this.setupFieldValidation('contact', this.validateContact.bind(this));
        this.setupFieldValidation('birthdate', this.validateBirthdate.bind(this));

        // Location Fields
        this.setupFieldValidation('address', this.validateAddress.bind(this));
        this.setupFieldValidation('barangay', this.validateBarangay.bind(this));
        this.setupFieldValidation('building_name', this.validateBuildingName.bind(this));
        this.setupFieldValidation('building_type', this.validateBuildingType.bind(this));

        // Device Fields
        this.setupFieldValidation('device_number', this.validateDeviceNumber.bind(this));
        this.setupFieldValidation('serial_number', this.validateSerialNumber.bind(this));

        // Credential Fields
        this.setupFieldValidation('username', this.validateUsername.bind(this));
        this.setupFieldValidation('password', this.validatePassword.bind(this));
        this.setupFieldValidation('confirm_password', this.validateConfirmPassword.bind(this));

        // Add validation status indicators (disabled - icons removed)
        // this.addValidationStatusIndicators();
    }

    /**
     * Setup validation for a specific field
     */
    setupFieldValidation(fieldName, validationFunction) {
        const field = document.getElementById(fieldName);
        if (!field) return;

        // Add validation on input for real-time feedback
        field.addEventListener('input', (e) => {
            this.debounceValidation(fieldName, () => {
                this.validateField(fieldName, validationFunction);
            });
        });

        // Add validation on blur
        field.addEventListener('blur', (e) => {
            this.validateField(fieldName, validationFunction);
        });

        // Add validation on focus (clear previous errors)
        field.addEventListener('focus', (e) => {
            this.clearFieldError(fieldName);
        });

        // Add validation on change for select fields
        field.addEventListener('change', (e) => {
            this.validateField(fieldName, validationFunction);
        });

        // Initial validation
        if (field.value) {
            this.validateField(fieldName, validationFunction);
        }
    }

    /**
     * Debounce validation to avoid too many API calls
     */
    debounceValidation(fieldName, callback) {
        if (this.debounceTimers[fieldName]) {
            clearTimeout(this.debounceTimers[fieldName]);
        }
        this.debounceTimers[fieldName] = setTimeout(callback, 300);
    }

    /**
     * Validate a field and update UI
     */
    async validateField(fieldName, validationFunction) {
        const field = document.getElementById(fieldName);
        if (!field) return;

        const value = field.value.trim();
        const result = await validationFunction(value, field);

        this.updateFieldValidationState(fieldName, result);
    }

    /**
     * Update field validation state and UI
     */
    updateFieldValidationState(fieldName, result) {
        const field = document.getElementById(fieldName);
        if (!field) return;

        this.validationStates[fieldName] = result;

        // Remove existing validation classes
        field.classList.remove('is-valid', 'is-invalid', 'is-warning');

        // Add appropriate validation class
        if (result.valid) {
            field.classList.add('is-valid');
        } else if (result.warning) {
            field.classList.add('is-warning');
        } else {
            field.classList.add('is-invalid');
        }

        // Update validation indicator (disabled - icons removed)
        // const indicator = field.parentNode.querySelector('.validation-indicator');
        // if (indicator) {
        //     this.updateValidationIndicator(indicator, result);
        // }

        // Remove any existing text feedback to avoid duplicates
        const existingFeedback = field.parentNode.querySelector('.validation-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        // Create real-time feedback message
        if (result.message && result.message.trim() !== '') {
            this.createFeedbackMessage(fieldName, result);
        }

        // Update form submit button state
        this.updateSubmitButtonState();
    }



    /**
     * Create real-time feedback message
     */
    createFeedbackMessage(fieldName, result) {
        const field = document.getElementById(fieldName);
        if (!field) return;

        // Create feedback element
        const feedback = document.createElement('div');
        feedback.className = 'validation-feedback';
        
        // Set appropriate class based on validation result
        if (result.valid) {
            feedback.classList.add('valid-feedback');
        } else if (result.warning) {
            feedback.classList.add('warning-feedback');
        } else {
            feedback.classList.add('invalid-feedback');
        }

        // Add icon and message
        const icon = document.createElement('span');
        icon.className = 'feedback-icon';
        
        if (result.valid) {
            icon.innerHTML = '✓';
            icon.style.color = '#28a745';
        } else if (result.warning) {
            icon.innerHTML = '⚠';
            icon.style.color = '#ffc107';
        } else {
            icon.innerHTML = '✗';
            icon.style.color = '#dc3545';
        }

        feedback.appendChild(icon);
        
        const message = document.createElement('span');
        message.textContent = result.message;
        feedback.appendChild(message);

        // Insert after the field
        field.parentNode.appendChild(feedback);
    }

    /**
     * Clear field error
     */
    clearFieldError(fieldName) {
        const field = document.getElementById(fieldName);
        if (!field) return;

        field.classList.remove('is-invalid', 'is-valid', 'is-warning');
        
        // Remove any text feedback
        const feedback = field.parentNode.querySelector('.validation-feedback');
        if (feedback) {
            feedback.remove();
        }

        // Hide validation indicator (disabled - icons removed)
        // const indicator = field.parentNode.querySelector('.validation-indicator');
        // if (indicator) {
        //     indicator.style.display = 'none';
        // }
    }

    /**
     * Update submit button state based on form validity
     */
    updateSubmitButtonState() {
        const submitButtons = document.querySelectorAll('button[type="submit"]');
        const isFormValid = this.isFormValid();

        submitButtons.forEach(button => {
            button.disabled = !isFormValid;
            if (!isFormValid) {
                button.classList.add('btn-disabled');
            } else {
                button.classList.remove('btn-disabled');
            }
        });

        // Update validation summary
        this.updateValidationSummary();
    }

    /**
     * Update validation summary display
     */
    updateValidationSummary() {
        const currentStep = this.getCurrentStep();
        const requiredFields = this.getRequiredFieldsForStep(currentStep);
        
        let validCount = 0;
        let totalCount = requiredFields.length;
        
        requiredFields.forEach(fieldName => {
            if (this.validationStates[fieldName] && this.validationStates[fieldName].valid) {
                validCount++;
            }
        });

        // Create or update validation summary
        let summaryElement = document.getElementById('validation-summary');
        if (!summaryElement) {
            summaryElement = document.createElement('div');
            summaryElement.id = 'validation-summary';
            summaryElement.className = 'validation-summary';
            summaryElement.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: rgba(10, 14, 39, 0.95);
                border-radius: 16px;
                padding: 18px 20px;
                box-shadow: 0 15px 40px rgba(0,0,0,0.35);
                z-index: 1000;
                font-size: 14px;
                font-weight: 500;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-left: 4px solid #28a745;
                transition: all 0.3s ease;
                color: #f3f6fb;
                backdrop-filter: blur(14px);
            `;
            document.body.appendChild(summaryElement);
        }

        const progress = totalCount > 0 ? (validCount / totalCount) * 100 : 0;
        const progressColor = progress === 100 ? '#28a745' : progress >= 50 ? '#ffc107' : '#dc3545';
        
        summaryElement.innerHTML = `
            <div style="margin-bottom: 10px; font-weight: 700; color: #ffffff; font-size: 15px;">
                Step ${this.getStepNumber(currentStep)} Validation
            </div>
            <div style="margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: rgba(255,255,255,0.85); font-size: 13px;">
                    <span>Progress</span>
                    <span style="font-weight: 700; letter-spacing: 0.5px;">${validCount}/${totalCount} fields</span>
                </div>
                <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.15); border-radius: 999px; overflow: hidden;">
                    <div style="width: ${progress}%; height: 100%; background: linear-gradient(90deg, #ff9b42, ${progressColor}); transition: width 0.3s ease;"></div>
                </div>
            </div>
            <div style="font-size: 12px; color: rgba(255,255,255,0.95); font-weight: 600;">
                ${progress === 100 ? '✅ All fields valid' : `${totalCount - validCount} field(s) need attention`}
            </div>
        `;

        summaryElement.style.borderLeftColor = progressColor;
    }

    /**
     * Get step number
     */
    getStepNumber(step) {
        const stepNumbers = {
            'personal': 1,
            'location': 2,
            'device': 3,
            'credentials': 4
        };
        return stepNumbers[step] || 1;
    }

    /**
     * Check if entire form is valid
     */
    isFormValid() {
        const currentStep = this.getCurrentStep();
        const requiredFields = this.getRequiredFieldsForStep(currentStep);
        
        return requiredFields.every(fieldName => {
            return this.validationStates[fieldName] && this.validationStates[fieldName].valid;
        });
    }

    /**
     * Get current step
     */
    getCurrentStep() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('step') || 'personal';
    }

    /**
     * Get required fields for current step
     */
    getRequiredFieldsForStep(step) {
        const fieldMap = {
            'personal': ['fullname', 'birthdate', 'email', 'contact'],
            'location': ['address', 'barangay', 'building_name', 'building_type'],
            'device': ['device_number', 'serial_number'],
            'credentials': ['username', 'password', 'confirm_password']
        };
        return fieldMap[step] || [];
    }

    // ===== VALIDATION FUNCTIONS =====

    /**
     * Validate full name
     */
    async validateFullName(value) {
        if (!value) {
            return { valid: false, message: 'Please enter your full name' };
        }

        if (value.length < 2) {
            return { valid: false, message: 'Name must be at least 2 characters long' };
        }

        if (value.length > 100) {
            return { valid: false, message: 'Name is too long (maximum 100 characters)' };
        }

        if (!/^[a-zA-Z\s\.\'-]+$/.test(value)) {
            return { valid: false, message: 'Name can only contain letters, spaces, dots, apostrophes, and hyphens' };
        }

        return { valid: true, message: 'Full name looks good!' };
    }

    /**
     * Validate email
     */
    async validateEmail(value) {
        if (!value) {
            return { valid: false, message: 'Email is required' };
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return { valid: false, message: 'Please enter a valid email address' };
        }

        // Check if email is verified
        const emailInput = document.getElementById('email');
        if (emailInput && emailInput.getAttribute('data-verified') !== 'true') {
            return { valid: false, message: 'Please verify your email address first' };
        }

        // If email is verified, return success immediately
        if (emailInput && emailInput.getAttribute('data-verified') === 'true') {
            return { valid: true, message: 'Email verified successfully!' };
        }

        // Only check email availability if not verified (fallback)
        try {
            const response = await this.ajaxValidation('email', value);
            return response;
        } catch (error) {
            return { valid: false, message: 'Unable to verify email availability' };
        }
    }

    /**
     * Validate contact number
     */
    async validateContact(value) {
        if (!value) {
            return { valid: false, message: 'Please enter your contact number' };
        }

        if (!/^09\d{9}$/.test(value)) {
            return { valid: false, message: 'Contact number must start with 09 and be exactly 11 digits' };
        }

        // Check if contact number is already registered
        try {
            const response = await this.ajaxValidation('contact', value);
            return response;
        } catch (error) {
            return { valid: false, message: 'Unable to verify contact number availability' };
        }
    }

    /**
     * Validate birthdate
     */
    async validateBirthdate(value) {
        if (!value) {
            return { valid: false, message: 'Please select your birthdate' };
        }

        const birth = new Date(value);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }

        if (age < 18) {
            return { valid: false, message: 'You must be at least 18 years old to register' };
        }

        if (age > 120) {
            return { valid: false, message: 'Please enter a valid birthdate' };
        }

        return { valid: true, message: `Age: ${age} years old` };
    }

    /**
     * Validate address
     */
    async validateAddress(value) {
        if (!value) {
            return { valid: false, message: 'Address is required' };
        }

        if (value.length < 10) {
            return { valid: false, message: 'Address is too short (minimum 10 characters)' };
        }

        if (value.length > 255) {
            return { valid: false, message: 'Address is too long (maximum 255 characters)' };
        }

        return { valid: true, message: 'Address is valid' };
    }

    /**
     * Validate barangay
     */
    async validateBarangay(value, fieldEl) {
        if (!value) {
            return { valid: false, message: 'Barangay is required.' };
        }

        const addressField = document.getElementById('address');
        const addr = (addressField?.value || '').toLowerCase().replace(/[\s,]+/g, ' ');
        const brgy = (value || '').toLowerCase().trim();

        if (addr && brgy) {
            const re = new RegExp('(^|\\s)' + brgy.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&') + '(?=\\s|$)');
            if (!re.test(addr)) {
                return { valid: false, message: 'Barangay must match your full address.' };
            }
        }

        return { valid: true, message: 'Barangay is valid' };
    }

    /**
     * Validate building name
     */
    async validateBuildingName(value) {
        if (!value) {
            return { valid: false, message: 'Building name is required' };
        }

        if (value.length < 2) {
            return { valid: false, message: 'Building name must be at least 2 characters' };
        }

        if (value.length > 100) {
            return { valid: false, message: 'Building name is too long (maximum 100 characters)' };
        }

        if (!/^[a-zA-Z0-9\s\.\'-]+$/.test(value)) {
            return { valid: false, message: 'Building name contains invalid characters' };
        }

        return { valid: true, message: 'Building name is valid' };
    }

    /**
     * Validate building type
     */
    async validateBuildingType(value) {
        if (!value) {
            return { valid: false, message: 'Building type is required' };
        }

        const validTypes = ['Residential', 'Commercial', 'Institutional', 'Industrial'];
        if (!validTypes.includes(value)) {
            return { valid: false, message: 'Please select a valid building type' };
        }

        return { valid: true, message: 'Building type is valid' };
    }

    /**
     * Validate device number
     */
    async validateDeviceNumber(value) {
        if (!value) {
            return { valid: false, message: 'Device number is required' };
        }

        // Check if device exists in admin_devices table and is available
        try {
            console.log('Validating device number:', value);
            const response = await this.ajaxValidation('device', value);
            console.log('Device validation response:', response);
            return response;
        } catch (error) {
            console.error('Device validation error:', error);
            return { valid: false, message: 'Unable to verify device availability' };
        }
    }

    /**
     * Validate serial number
     */
    async validateSerialNumber(value) {
        if (!value) {
            return { valid: false, message: 'Serial number is required' };
        }

        // Check if serial number exists in admin_devices table and is available
        try {
            console.log('Validating serial number:', value);
            const response = await this.ajaxValidation('device', value);
            console.log('Serial validation response:', response);
            return response;
        } catch (error) {
            console.error('Serial validation error:', error);
            return { valid: false, message: 'Unable to verify serial number availability' };
        }
    }

    /**
     * Validate username
     */
    async validateUsername(value) {
        if (!value) {
            return { valid: false, message: 'Please enter a username' };
        }

        if (value.length < 5) {
            return { valid: false, message: 'Username must be at least 5 characters long' };
        }

        if (!/^[a-zA-Z0-9_]+$/.test(value)) {
            return { valid: false, message: 'Username can only contain letters, numbers, and underscores' };
        }

        // Check if username is already taken
        try {
            const response = await this.ajaxValidation('username', value);
            return response;
        } catch (error) {
            return { valid: false, message: 'Unable to verify username availability' };
        }
    }

    /**
     * Validate password
     */
    async validatePassword(value) {
        if (!value) {
            return { valid: false, message: 'Please enter a password' };
        }

        if (value.length < 8) {
            return { valid: false, message: 'Password must be at least 8 characters long' };
        }

        // Check password strength
        try {
            const response = await this.ajaxValidation('password_strength', value);
            return response;
        } catch (error) {
            return { valid: false, message: 'Unable to check password strength' };
        }
    }

    /**
     * Validate confirm password
     */
    async validateConfirmPassword(value) {
        const password = document.getElementById('password')?.value;
        
        if (!value) {
            return { valid: false, message: 'Please confirm your password' };
        }

        if (password !== value) {
            return { valid: false, message: 'Passwords do not match' };
        }

        return { valid: true, message: 'Passwords match!' };
    }

    /**
     * Make AJAX validation request
     */
    async ajaxValidation(type, value) {
        const formData = new FormData();
        formData.append('type', type);
        
        // For device validation, send as 'value' parameter
        if (type === 'device') {
            formData.append('value', value);
        } else {
            formData.append(type === 'password_strength' ? 'password' : type, value);
        }

        console.log('AJAX request - Type:', type, 'Value:', value);
        console.log('FormData entries:');
        for (let [key, val] of formData.entries()) {
            console.log(key, val);
        }

        try {
            const response = await fetch('./ajax_validate.php', {
                method: 'POST',
                body: formData
            });

            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            console.log('Response data:', data);
            return data;
        } catch (error) {
            console.error('Validation error:', error);
            throw error;
        }
    }

    // ===== SPECIAL VALIDATION SETUPS =====

    /**
     * Setup password strength meter with real-time feedback
     */
    setupPasswordStrengthMeter() {
        const passwordField = document.getElementById('password');
        const strengthMeter = document.getElementById('password-strength');
        
        if (!passwordField || !strengthMeter) return;

        passwordField.addEventListener('input', async (e) => {
            const value = e.target.value;
            if (!value) {
                strengthMeter.innerHTML = '';
                return;
            }

            try {
                const response = await this.ajaxValidation('password_strength', value);
                this.updatePasswordStrengthMeter(strengthMeter, response);
            } catch (error) {
                console.error('Password strength check failed:', error);
            }
        });
    }

    /**
     * Update password strength meter with real-time feedback
     */
    updatePasswordStrengthMeter(meter, data) {
        const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#198754'];
        const strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];

        const strength = data.strength || 0;
        const color = strengthColors[strength] || '#dc3545';
        const label = strengthLabels[strength] || 'Very Weak';

        let feedbackHtml = '';
        if (data.feedback) {
            feedbackHtml = '<ul class="password-feedback">';
            data.feedback.forEach(item => {
                const isPositive = item.includes('✓');
                feedbackHtml += `<li class="${isPositive ? 'positive' : 'negative'}">${item}</li>`;
            });
            feedbackHtml += '</ul>';
        }

        meter.innerHTML = `
            <div class="password-strength-bar">
                <div class="strength-fill" style="width: ${(strength / 5) * 100}%; background-color: ${color};"></div>
            </div>
            <div class="strength-label" style="color: ${color};">${label}</div>
            ${feedbackHtml}
        `;
    }

    /**
     * Setup confirm password real-time feedback
     */
    setupConfirmPasswordFeedback() {
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const feedbackElement = document.getElementById('confirm-password-feedback');
        
        if (!passwordField || !confirmPasswordField || !feedbackElement) return;

        const updateFeedback = () => {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (!confirmPassword) {
                feedbackElement.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                feedbackElement.innerHTML = `
                    <div class="valid-feedback" style="display: block;">
                        <i class="fas fa-check-circle"></i> Passwords match!
                    </div>
                `;
            } else {
                feedbackElement.innerHTML = `
                    <div class="invalid-feedback" style="display: block;">
                        <i class="fas fa-times-circle"></i> Passwords do not match
                    </div>
                `;
            }
        };

        // Listen for changes in both password fields
        passwordField.addEventListener('input', updateFeedback);
        confirmPasswordField.addEventListener('input', updateFeedback);
    }

    /**
     * Setup device validation (no format restrictions)
     */
    setupDeviceFormatValidation() {
        const deviceNumberField = document.getElementById('device_number');
        const serialNumberField = document.getElementById('serial_number');

        if (deviceNumberField) {
            deviceNumberField.addEventListener('input', (e) => {
                // Convert to uppercase for consistency
                e.target.value = e.target.value.toUpperCase();
            });
        }

        if (serialNumberField) {
            serialNumberField.addEventListener('input', (e) => {
                // Convert to uppercase for consistency
                e.target.value = e.target.value.toUpperCase();
            });
        }
    }

    /**
     * Setup location validation
     */
    setupLocationValidation() {
        const latitudeField = document.getElementById('latitude');
        const longitudeField = document.getElementById('longitude');
        const addressField = document.getElementById('address');

        if (latitudeField && longitudeField && addressField) {
            // Validate coordinates when they change
            const validateLocation = () => {
                const lat = parseFloat(latitudeField.value);
                const lng = parseFloat(longitudeField.value);

                if (isNaN(lat) || isNaN(lng)) {
                    this.updateFieldValidationState('latitude', {
                        valid: false,
                        message: 'Invalid coordinates'
                    });
                    return;
                }

                if (lat < -90 || lat > 90) {
                    this.updateFieldValidationState('latitude', {
                        valid: false,
                        message: 'Latitude must be between -90 and 90'
                    });
                    return;
                }

                if (lng < -180 || lng > 180) {
                    this.updateFieldValidationState('longitude', {
                        valid: false,
                        message: 'Longitude must be between -180 and 180'
                    });
                    return;
                }

                this.updateFieldValidationState('latitude', {
                    valid: true,
                    message: 'Coordinates are valid'
                });
            };

            latitudeField.addEventListener('change', validateLocation);
            longitudeField.addEventListener('change', validateLocation);
        }
    }

    /**
     * Add validation status indicators to form fields
     */
    addValidationStatusIndicators() {
        const formFields = document.querySelectorAll('input, select, textarea');
        
        formFields.forEach(field => {
            const fieldName = field.id;
            if (!fieldName) return;

            // Add validation status indicator inside the field
            const indicator = document.createElement('div');
            indicator.className = 'validation-indicator';
            indicator.innerHTML = '<span class="indicator-icon"></span>';
            indicator.style.cssText = `
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 16px;
                pointer-events: none;
                z-index: 10;
                display: none;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
            `;

            // Make field container relative for absolute positioning
            const fieldContainer = field.parentNode;
            if (fieldContainer) {
                fieldContainer.style.position = 'relative';
                fieldContainer.appendChild(indicator);
            }

            // Update indicator based on validation state
            field.addEventListener('input', () => {
                if (this.validationStates[fieldName]) {
                    this.updateValidationIndicator(indicator, this.validationStates[fieldName]);
                }
            });
        });
    }

    /**
     * Update validation indicator
     */
    updateValidationIndicator(indicator, validationState) {
        const icon = indicator.querySelector('.indicator-icon');
        
        if (validationState.valid) {
            icon.textContent = '✓';
            icon.style.color = '#28a745';
            indicator.style.display = 'flex';
            indicator.style.alignItems = 'center';
            indicator.style.justifyContent = 'center';
        } else if (validationState.warning) {
            icon.textContent = '⚠';
            icon.style.color = '#ffc107';
            indicator.style.display = 'flex';
            indicator.style.alignItems = 'center';
            indicator.style.justifyContent = 'center';
        } else if (validationState.message && !validationState.valid) {
            icon.textContent = '✗';
            icon.style.color = '#dc3545';
            indicator.style.display = 'flex';
            indicator.style.alignItems = 'center';
            indicator.style.justifyContent = 'center';
        } else {
            indicator.style.display = 'none';
        }
    }

    /**
     * Validate entire form before submission
     */
    validateForm() {
        const currentStep = this.getCurrentStep();
        const requiredFields = this.getRequiredFieldsForStep(currentStep);
        const errors = [];

        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field && field.value.trim() === '') {
                errors.push(`${this.getFieldLabel(fieldName)} is required`);
            } else if (this.validationStates[fieldName] && !this.validationStates[fieldName].valid) {
                errors.push(this.validationStates[fieldName].message);
            }
        });

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Get field label
     */
    getFieldLabel(fieldName) {
        const field = document.getElementById(fieldName);
        if (!field) return fieldName;

        const label = field.parentNode.querySelector('label');
        return label ? label.textContent.replace('*', '').trim() : fieldName;
    }
}

// Initialize validation when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.formValidator = new FormValidator();
});

// Global function for form submission validation
function validateFormSubmission(step) {
    if (!window.formValidator) {
        console.error('Form validator not initialized');
        return true;
    }

    // Check email verification for personal step
    if (step === 'personal') {
        const emailInput = document.getElementById('email');
        if (emailInput && emailInput.getAttribute('data-verified') !== 'true') {
            if (typeof showErrorAlert === 'function') {
                showErrorAlert('Please verify your email address before proceeding.');
            } else {
                alert('Please verify your email address before proceeding.');
            }
            return false;
        }
    }

    const validation = window.formValidator.validateForm();
    
    if (!validation.valid) {
        // Show validation errors using SweetAlert2
        if (typeof showFormValidationError === 'function') {
            showFormValidationError(validation.errors);
        } else {
            // Fallback to alert if SweetAlert2 is not available
            alert('Please fix the following errors:\n' + validation.errors.join('\n'));
        }
        return false;
    }

    return true;
}

// Global function to show validation report
function showValidationReport() {
    if (!window.formValidator) {
        console.error('Form validator not initialized');
        return;
    }

    const currentStep = window.formValidator.getCurrentStep();
    const requiredFields = window.formValidator.getRequiredFieldsForStep(currentStep);
    
    let reportHtml = `
        <div style="text-align: left; max-height: 400px; overflow-y: auto;">
            <h4 style="margin-bottom: 15px; color: #333;">Validation Report - Step ${window.formValidator.getStepNumber(currentStep)}</h4>
            <div style="margin-bottom: 10px;">
                <strong>Current Step:</strong> ${currentStep.charAt(0).toUpperCase() + currentStep.slice(1)} Information
            </div>
            <hr style="margin: 10px 0;">
    `;

    let validCount = 0;
    let totalCount = requiredFields.length;

    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        const validationState = window.formValidator.validationStates[fieldName];
        const fieldLabel = field ? (field.parentNode.querySelector('label')?.textContent.replace('*', '').trim() || fieldName) : fieldName;
        
        if (validationState && validationState.valid) {
            validCount++;
            reportHtml += `
                <div style="margin-bottom: 8px; padding: 8px; background: #d4edda; border-radius: 5px; border-left: 4px solid #28a745;">
                    <span style="color: #28a745; font-weight: bold;">✓</span> ${fieldLabel}: ${validationState.message}
                </div>
            `;
        } else if (validationState) {
            reportHtml += `
                <div style="margin-bottom: 8px; padding: 8px; background: #f8d7da; border-radius: 5px; border-left: 4px solid #dc3545;">
                    <span style="color: #dc3545; font-weight: bold;">✗</span> ${fieldLabel}: ${validationState.message}
                </div>
            `;
        } else {
            reportHtml += `
                <div style="margin-bottom: 8px; padding: 8px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
                    <span style="color: #ffc107; font-weight: bold;">⚠</span> ${fieldLabel}: Not validated yet
                </div>
            `;
        }
    });

    const progress = totalCount > 0 ? (validCount / totalCount) * 100 : 0;
    const progressColor = progress === 100 ? '#28a745' : progress >= 50 ? '#ffc107' : '#dc3545';

    reportHtml += `
        <hr style="margin: 15px 0;">
        <div style="text-align: center;">
            <div style="margin-bottom: 8px;">
                <strong>Overall Progress:</strong> ${validCount}/${totalCount} fields valid (${Math.round(progress)}%)
            </div>
            <div style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                <div style="width: ${progress}%; height: 100%; background: ${progressColor}; transition: width 0.3s ease;"></div>
            </div>
            <div style="margin-top: 8px; font-size: 14px; color: #666;">
                ${progress === 100 ? '🎉 All fields are valid! You can proceed.' : `${totalCount - validCount} field(s) still need attention.`}
            </div>
        </div>
    </div>
    `;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Validation Report',
            html: reportHtml,
            icon: progress === 100 ? 'success' : progress >= 50 ? 'warning' : 'error',
            confirmButtonText: 'Close',
            width: '600px'
        });
    } else {
        alert('Validation Report:\n' + reportHtml.replace(/<[^>]*>/g, ''));
    }
}

// Add validation report button to each form
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[id$="-form"]');
    forms.forEach(form => {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            const reportButton = document.createElement('button');
            reportButton.type = 'button';
            reportButton.className = 'btn btn-outline-info btn-sm ms-2';
            reportButton.innerHTML = '📊 Validation Report';
            reportButton.onclick = showValidationReport;
            submitButton.parentNode.appendChild(reportButton);
        }
    });
});

// Add CSS for validation styling
const validationStyles = `
    .validation-feedback {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .valid-feedback {
        color: #28a745;
    }

    .warning-feedback {
        color: #ffc107;
    }

    .invalid-feedback {
        color: #dc3545;
    }

    .validation-icon {
        margin-right: 0.5rem;
        font-weight: bold;
    }

    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
    }

    .is-warning {
        border-color: #ffc107 !important;
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
    }

    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    /* Password strength meter styles */
    .password-strength-bar {
        width: 100%;
        height: 8px;
        background-color: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin: 10px 0;
    }

    .strength-fill {
        height: 100%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }

    .strength-label {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .password-feedback {
        list-style: none;
        padding: 0;
        margin: 10px 0;
    }

    .password-feedback li {
        font-size: 0.8rem;
        margin-bottom: 2px;
    }

    .password-feedback .positive {
        color: #28a745;
    }

    .password-feedback .negative {
        color: #dc3545;
    }

    /* Confirm password feedback styles */
    #confirm-password-feedback .valid-feedback {
        color: #28a745;
        font-size: 0.875rem;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    #confirm-password-feedback .invalid-feedback {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    #confirm-password-feedback i {
        font-size: 1rem;
    }

    .btn-disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-control:focus.is-valid {
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
    }

    .form-control:focus.is-warning {
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
    }

    .form-control:focus.is-invalid {
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    .validation-indicator {
        transition: all 0.3s ease;
        display: none !important;
        align-items: center !important;
        justify-content: center !important;
        visibility: hidden !important;
    }

    .indicator-icon {
        font-weight: bold;
        transition: color 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .form-control:focus + .validation-indicator .indicator-icon {
        transform: scale(1.1);
    }

    .form-group {
        position: relative;
    }

    .form-control {
        padding-right: 40px !important;
    }

    .validation-feedback {
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-control.is-valid,
    .form-control.is-warning,
    .form-control.is-invalid {
        transition: all 0.3s ease;
    }

    .btn:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .btn:disabled:hover {
        transform: none !important;
    }

    /* Email Verification Styles */
    .email-verification-container {
        position: relative;
    }

    .verification-status {
        margin-top: 10px;
        padding: 10px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
    }

    .verification-code-input {
        border: 2px solid #007bff;
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        letter-spacing: 4px;
    }

    .verification-code-input:focus {
        border-color: #0056b3;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .btn-verify {
        transition: all 0.3s ease;
    }

    .btn-verify:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }

    .btn-verify:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    .verification-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .verification-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        border: 1px solid #bee5eb;
        color: #0c5460;
    }

    .verification-error {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        border: 1px solid #f5c6cb;
        color: #721c24;
    }



    /* Animation for verification code input */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .verification-code-input:focus {
        animation: pulse 0.3s ease;
    }

    /* Success checkmark animation */
    @keyframes checkmark {
        0% { transform: scale(0); opacity: 0; }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); opacity: 1; }
    }

    .verification-success .checkmark {
        animation: checkmark 0.5s ease;
    }
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = validationStyles;
document.head.appendChild(styleSheet); 