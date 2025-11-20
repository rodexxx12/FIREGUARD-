/**
 * Device Display JavaScript Functions
 * Handles device information display, downloads, and user interactions
 */

class DeviceDisplayManager {
    constructor() {
        this.initializeEventListeners();
    }

    /**
     * Initialize event listeners for device display functionality
     */
    initializeEventListeners() {
        // Add event listeners for download buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('download-btn') || e.target.closest('.download-btn')) {
                e.preventDefault();
                const btn = e.target.classList.contains('download-btn') ? e.target : e.target.closest('.download-btn');
                this.handleDownload(btn);
            }

            if (e.target.classList.contains('print-btn') || e.target.closest('.print-btn')) {
                e.preventDefault();
                const btn = e.target.classList.contains('print-btn') ? e.target : e.target.closest('.print-btn');
                this.handlePrint(btn);
            }

            if (e.target.classList.contains('copy-btn') || e.target.closest('.copy-btn')) {
                e.preventDefault();
                const btn = e.target.classList.contains('copy-btn') ? e.target : e.target.closest('.copy-btn');
                this.handleCopy(btn);
            }
        });
    }

    /**
     * Handle device information download
     * @param {HTMLElement} button - The download button element
     */
    handleDownload(button) {
        const deviceNumber = button.getAttribute('data-device-number') || 
                           button.closest('.device-info-card').querySelector('.device-number').textContent;
        const serialNumber = button.getAttribute('data-serial-number') || 
                           button.closest('.device-info-card').querySelector('.serial-number').textContent;
        const format = button.getAttribute('data-format') || 'txt';

        this.showLoadingState(button);

        // Create download URL
        const downloadUrl = `download_handler.php?action=download_single&device_number=${encodeURIComponent(deviceNumber)}&serial_number=${encodeURIComponent(serialNumber)}&format=${format}`;

        // Trigger download
        fetch(downloadUrl)
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Download failed');
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `device_${deviceNumber}_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                this.showSuccessMessage('Download completed successfully!');
            })
            .catch(error => {
                console.error('Download error:', error);
                this.showErrorMessage('Download failed. Please try again.');
            })
            .finally(() => {
                this.hideLoadingState(button);
            });
    }

    /**
     * Handle device information printing
     * @param {HTMLElement} button - The print button element
     */
    handlePrint(button) {
        const deviceCard = button.closest('.device-info-card');
        const deviceNumber = deviceCard.querySelector('.device-number').textContent;
        const serialNumber = deviceCard.querySelector('.serial-number').textContent;
        const deviceType = deviceCard.querySelector('.device-type').textContent;
        const generatedDate = deviceCard.querySelector('.generated-date').textContent;

        // Create print window content
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Device Information - ${deviceNumber}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    .info-row { margin: 10px 0; }
                    .label { font-weight: bold; display: inline-block; width: 150px; }
                    .value { font-family: monospace; }
                    .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Fire Detection System</h1>
                    <h2>Device Information</h2>
                </div>
                <div class="info-row">
                    <span class="label">Device Number:</span>
                    <span class="value">${deviceNumber}</span>
                </div>
                <div class="info-row">
                    <span class="label">Serial Number:</span>
                    <span class="value">${serialNumber}</span>
                </div>
                <div class="info-row">
                    <span class="label">Device Type:</span>
                    <span class="value">${deviceType}</span>
                </div>
                <div class="info-row">
                    <span class="label">Generated Date:</span>
                    <span class="value">${generatedDate}</span>
                </div>
                <div class="footer">
                    <p>This document was generated by the Fire Detection System</p>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
            </body>
            </html>
        `;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();
        
        // Wait for content to load then print
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    }

    /**
     * Handle device information copy to clipboard
     * @param {HTMLElement} button - The copy button element
     */
    handleCopy(button) {
        const deviceCard = button.closest('.device-info-card');
        const deviceNumber = deviceCard.querySelector('.device-number').textContent;
        const serialNumber = deviceCard.querySelector('.serial-number').textContent;
        const deviceType = deviceCard.querySelector('.device-type').textContent;
        const generatedDate = deviceCard.querySelector('.generated-date').textContent;

        const textToCopy = `Device Number: ${deviceNumber}\nSerial Number: ${serialNumber}\nDevice Type: ${deviceType}\nGenerated Date: ${generatedDate}`;

        if (navigator.clipboard && window.isSecureContext) {
            // Use modern clipboard API
            navigator.clipboard.writeText(textToCopy)
                .then(() => {
                    this.showCopyFeedback('Device information copied to clipboard!');
                })
                .catch(err => {
                    console.error('Failed to copy: ', err);
                    this.fallbackCopyTextToClipboard(textToCopy);
                });
        } else {
            // Fallback for older browsers
            this.fallbackCopyTextToClipboard(textToCopy);
        }
    }

    /**
     * Fallback copy method for older browsers
     * @param {string} text - Text to copy
     */
    fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            this.showCopyFeedback('Device information copied to clipboard!');
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            this.showErrorMessage('Failed to copy device information');
        }

        document.body.removeChild(textArea);
    }

    /**
     * Show loading state on button
     * @param {HTMLElement} button - The button element
     */
    showLoadingState(button) {
        button.classList.add('loading');
        button.disabled = true;
        const originalText = button.innerHTML;
        button.setAttribute('data-original-text', originalText);
        button.innerHTML = '<span>Processing...</span>';
    }

    /**
     * Hide loading state on button
     * @param {HTMLElement} button - The button element
     */
    hideLoadingState(button) {
        button.classList.remove('loading');
        button.disabled = false;
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
            button.removeAttribute('data-original-text');
        }
    }

    /**
     * Show success message
     * @param {string} message - Success message
     */
    showSuccessMessage(message) {
        this.showMessage(message, 'success');
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showErrorMessage(message) {
        this.showMessage(message, 'error');
    }

    /**
     * Show copy feedback message
     * @param {string} message - Feedback message
     */
    showCopyFeedback(message) {
        this.showMessage(message, 'copy-feedback');
    }

    /**
     * Show message with specified type
     * @param {string} message - Message text
     * @param {string} type - Message type (success, error, copy-feedback)
     */
    showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.textContent = message;

        // Remove existing messages of the same type
        const existingMessages = document.querySelectorAll(`.message.${type}`);
        existingMessages.forEach(msg => msg.remove());

        document.body.appendChild(messageDiv);

        // Auto-remove message after 3 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 3000);
    }

    /**
     * Generate device display HTML programmatically
     * @param {Object} deviceData - Device data object
     * @returns {string} HTML string
     */
    static generateDeviceHTML(deviceData) {
        const { device_number, serial_number, device_type = 'Fire Detection Device', status = 'approved' } = deviceData;
        
        return `
            <div class="device-info-card">
                <div class="device-header">
                    <h3>Device Information</h3>
                    <span class="status-badge status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                </div>
                <div class="device-details">
                    <div class="detail-row">
                        <label>Device Number:</label>
                        <span class="device-number">${device_number}</span>
                    </div>
                    <div class="detail-row">
                        <label>Serial Number:</label>
                        <span class="serial-number">${serial_number}</span>
                    </div>
                    <div class="detail-row">
                        <label>Device Type:</label>
                        <span class="device-type">${device_type}</span>
                    </div>
                    <div class="detail-row">
                        <label>Generated Date:</label>
                        <span class="generated-date">${new Date().toLocaleString()}</span>
                    </div>
                </div>
                <div class="device-actions">
                    <button class="btn btn-primary download-btn" data-device-number="${device_number}" data-serial-number="${serial_number}" data-format="txt">
                        <i class="fa fa-download"></i> Download Info
                    </button>
                    <button class="btn btn-secondary print-btn">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <button class="btn btn-info copy-btn">
                        <i class="fa fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        `;
    }
}

// Initialize device display manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new DeviceDisplayManager();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DeviceDisplayManager;
} 