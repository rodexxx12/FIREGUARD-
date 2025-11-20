/**
 * Prevent Forward Navigation After Login
 * This script disables the browser's forward button after a user logs in
 * and then clicks the back button. It shows a toast notification when
 * forward navigation is attempted.
 */

(function() {
    'use strict';

    // Toast Notification System
    function createToastContainer() {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    function showToast(message, type = 'info', title = null) {
        const container = createToastContainer();
        
        // Remove existing toast if any
        const existingToast = container.querySelector('.toast-notification');
        if (existingToast) {
            existingToast.classList.add('hiding');
            setTimeout(() => existingToast.remove(), 300);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        
        // Set icon based on type
        let icon = 'ℹ️';
        if (type === 'warning') icon = '⚠️';
        else if (type === 'error') icon = '❌';
        else if (type === 'success') icon = '✅';
        else if (type === 'info') icon = 'ℹ️';

        // Set default title if not provided
        if (!title) {
            if (type === 'warning') title = 'Warning';
            else if (type === 'error') title = 'Error';
            else if (type === 'success') title = 'Success';
            else title = 'Information';
        }

        // Add toast styles if not already added
        if (!document.getElementById('toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.textContent = `
                .toast-notification {
                    background: rgba(13, 16, 40, 0.95);
                    backdrop-filter: blur(18px);
                    border: 1px solid rgba(255, 255, 255, 0.15);
                    border-left: 4px solid #ff5a4d;
                    border-radius: 12px;
                    padding: 1rem 1.25rem;
                    min-width: 300px;
                    max-width: 400px;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    animation: slideInRight 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                    pointer-events: auto;
                    position: relative;
                }
                .toast-notification.warning { border-left-color: #ffbf40; }
                .toast-notification.error { border-left-color: #ff6b6b; }
                .toast-notification.success { border-left-color: #51cf66; }
                .toast-notification.info { border-left-color: #74c0fc; }
                .toast-icon { font-size: 1.5rem; flex-shrink: 0; }
                .toast-content { flex: 1; display: flex; flex-direction: column; gap: 0.25rem; }
                .toast-title { font-size: 0.95rem; font-weight: 600; color: #f3f6fb; margin: 0; }
                .toast-message { font-size: 0.85rem; color: #c9cfdb; margin: 0; line-height: 1.4; }
                .toast-close {
                    background: transparent;
                    border: none;
                    color: #c9cfdb;
                    font-size: 1.2rem;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    transition: all 0.2s ease;
                    flex-shrink: 0;
                }
                .toast-close:hover { background: rgba(255, 255, 255, 0.1); color: #f3f6fb; }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                .toast-notification.hiding {
                    animation: slideOutRight 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
                }
                @media (max-width: 768px) {
                    .toast-container {
                        top: 10px;
                        right: 10px;
                        left: 10px;
                    }
                    .toast-notification {
                        min-width: auto;
                        max-width: 100%;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" aria-label="Close">×</button>
        `;

        // Add close button functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        });

        container.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    // Initialize forward navigation prevention
    function initForwardPrevention() {
        // Track navigation state
        let navigationState = {
            hasNavigatedBack: false,
            forwardBlocked: false,
            historyIndex: 0
        };

        // Store initial state
        const initialState = {
            url: window.location.href,
            timestamp: Date.now()
        };

        // Replace current history entry to prevent forward navigation
        window.history.replaceState(initialState, null, window.location.href);

        // On page load, set up forward prevention
        function setupBarrier() {
            // Push a barrier state to prevent forward navigation
            const barrierState = {
                url: window.location.href,
                timestamp: Date.now(),
                isBarrier: true
            };
            window.history.pushState(barrierState, null, window.location.href);
            navigationState.historyIndex = window.history.length - 1;
        }

        // Set up barrier immediately if DOM is ready, otherwise wait for load
        if (document.readyState === 'loading') {
            window.addEventListener('load', setupBarrier);
        } else {
            setupBarrier();
        }

        // Listen for popstate (back/forward button)
        window.addEventListener('popstate', function(event) {
            const currentState = event.state;
            
            // If we have a state and it's our barrier state, we're trying to go forward
            if (currentState && currentState.isBarrier) {
                // User is trying to go forward - prevent it
                event.preventDefault();
                // Push the barrier state back
                window.history.pushState(currentState, null, window.location.href);
                showToast('Forward navigation is disabled for security reasons. Please use the navigation menu instead.', 'warning', 'Navigation Disabled');
                return;
            }

            // If we don't have a state or it's the initial state, user clicked back
            if (!currentState || (currentState.url === initialState.url && !currentState.isBarrier)) {
                // User clicked back - allow it but mark that we've navigated back
                navigationState.hasNavigatedBack = true;
                
                // Immediately push a new barrier state to prevent forward
                setTimeout(() => {
                    const newBarrierState = {
                        url: window.location.href,
                        timestamp: Date.now(),
                        isBarrier: true
                    };
                    window.history.pushState(newBarrierState, null, window.location.href);
                    navigationState.forwardBlocked = true;
                }, 0);
            } else if (navigationState.hasNavigatedBack) {
                // User is trying to go forward after going back - prevent it
                event.preventDefault();
                const barrierState = {
                    url: window.location.href,
                    timestamp: Date.now(),
                    isBarrier: true
                };
                window.history.pushState(barrierState, null, window.location.href);
                showToast('Forward navigation is disabled for security reasons. Please use the navigation menu instead.', 'warning', 'Navigation Disabled');
            }
        });

        // Additional protection: Override history.forward() if it exists
        if (window.history.forward) {
            const originalForward = window.history.forward;
            window.history.forward = function() {
                showToast('Forward navigation is disabled for security reasons. Please use the navigation menu instead.', 'warning', 'Navigation Disabled');
                // Don't call original forward
            };
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForwardPrevention);
    } else {
        initForwardPrevention();
    }

    // Export showToast for use in other scripts if needed
    window.showToast = showToast;
})();

