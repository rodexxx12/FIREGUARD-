// Spot Report Scripts
// This file contains JavaScript functionality for the spot report module

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Spot report scripts initialized
    
    // Add any spot-specific JavaScript functionality here
    // This file is included to prevent 404 errors
});

/**
 * Get CSRF token from page
 * @returns {string|null} CSRF token or null
 */
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        return metaTag.getAttribute('content');
    }
    
    const hiddenInput = document.querySelector('input[name="csrf_token"]');
    if (hiddenInput) {
        return hiddenInput.value;
    }
    
    return null;
}

