/**
 * Alert Functions for Fire Detection System Registration
 * 
 * This file contains all the alert functions used throughout the registration process.
 * Include this file in any registration-related pages that need these alert functions.
 */

// Check if SweetAlert2 is loaded
if (typeof Swal === 'undefined') {
    console.error('SweetAlert2 is not loaded. Please include SweetAlert2 before using these alert functions.');
}

/**
 * Shows a loading alert with a spinner
 * @param {string} title - The title of the loading alert
 * @returns {Promise} SweetAlert2 promise
 */
function showLoadingAlert(title = 'Processing...') {
    return Swal.fire({
        title: title,
        html: 'Please wait...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => Swal.showLoading()
    });
}

/**
 * Shows a success alert with green styling
 * @param {string} message - The message to display
 * @param {string} title - The title of the alert
 * @returns {Promise} SweetAlert2 promise
 */
function showSuccessAlert(message, title = 'Success!') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        confirmButtonText: 'OK',
        timer: 3000,
        timerProgressBar: true
    });
}

/**
 * Shows an error alert with red styling
 * @param {string} message - The message to display
 * @param {string} title - The title of the alert
 * @returns {Promise} SweetAlert2 promise
 */
function showErrorAlert(message, title = 'Error!') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        confirmButtonText: 'OK'
    });
}

/**
 * Shows a warning alert with yellow styling
 * @param {string} message - The message to display
 * @param {string} title - The title of the alert
 * @returns {Promise} SweetAlert2 promise
 */
function showWarningAlert(message, title = 'Warning!') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        confirmButtonText: 'OK',
        confirmButtonColor: '#ffc107'
    });
}

/**
 * Shows an informational alert with blue styling
 * @param {string} message - The message to display
 * @param {string} title - The title of the alert
 * @returns {Promise} SweetAlert2 promise
 */
function showInfoAlert(message, title = 'Information') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'info',
        confirmButtonText: 'OK',
        confirmButtonColor: '#17a2b8'
    });
}

/**
 * Shows a confirmation dialog with Yes/No buttons
 * @param {string} message - The message to display
 * @param {string} title - The title of the dialog
 * @param {string} confirmText - Text for the confirm button
 * @param {string} cancelText - Text for the cancel button
 * @returns {Promise} SweetAlert2 promise
 */
function showConfirmDialog(message, title = 'Confirm Action', confirmText = 'Yes', cancelText = 'No') {
    return Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#dc3545',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    });
}

/**
 * Shows validation errors in a formatted list
 * @param {Object|Array|string} errors - The validation errors to display
 * @returns {Promise} SweetAlert2 promise
 */
function showFormValidationError(errors) {
    let errorList = '';
    if (typeof errors === 'object') {
        if (Array.isArray(errors)) {
            errors.forEach(error => {
                errorList += `<li>${error}</li>`;
            });
        } else {
            Object.values(errors).forEach(error => {
                errorList += `<li>${error}</li>`;
            });
        }
    } else if (typeof errors === 'string') {
        errorList = `<li>${errors}</li>`;
    }
    
    return Swal.fire({
        title: 'Validation Errors',
        html: `<ul style="text-align: left; margin: 0; padding-left: 20px;">${errorList}</ul>`,
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#dc3545'
    });
}

/**
 * Shows a success message when a step is completed
 * @param {string} stepName - The name of the completed step
 * @returns {Promise} SweetAlert2 promise
 */
function showStepCompletionAlert(stepName) {
    return Swal.fire({
        title: 'Step Completed!',
        text: `${stepName} information has been saved successfully.`,
        icon: 'success',
        confirmButtonText: 'Continue',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
    });
}

/**
 * Shows a custom alert with specific styling
 * @param {string} message - The message to display
 * @param {string} title - The title of the alert
 * @param {string} icon - The icon to use (success, error, warning, info, question)
 * @param {Object} options - Additional SweetAlert2 options
 * @returns {Promise} SweetAlert2 promise
 */
function showCustomAlert(message, title = 'Alert', icon = 'info', options = {}) {
    const defaultOptions = {
        title: title,
        text: message,
        icon: icon,
        confirmButtonText: 'OK'
    };
    
    return Swal.fire({
        ...defaultOptions,
        ...options
    });
}

/**
 * Shows a network error alert
 * @param {string} message - Custom error message
 * @returns {Promise} SweetAlert2 promise
 */
function showNetworkError(message = 'Network error. Please check your connection and try again.') {
    return showErrorAlert(message, 'Connection Error');
}

/**
 * Shows a server error alert
 * @param {string} message - Custom error message
 * @returns {Promise} SweetAlert2 promise
 */
function showServerError(message = 'Server error. Please try again later.') {
    return showErrorAlert(message, 'Server Error');
}

/**
 * Shows a timeout error alert
 * @param {string} message - Custom error message
 * @returns {Promise} SweetAlert2 promise
 */
function showTimeoutError(message = 'Request timed out. Please try again.') {
    return showErrorAlert(message, 'Timeout Error');
}

// Export functions for use in other modules (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showLoadingAlert,
        showSuccessAlert,
        showErrorAlert,
        showWarningAlert,
        showInfoAlert,
        showConfirmDialog,
        showFormValidationError,
        showStepCompletionAlert,
        showCustomAlert,
        showNetworkError,
        showServerError,
        showTimeoutError
    };
} 