// Mobile Menu Toggle with smooth animations
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const navLinks = document.getElementById('navLinks');

mobileMenuBtn.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    if (navLinks.classList.contains('active')) {
        mobileMenuBtn.innerHTML = '<i class="fas fa-times"></i>';
        // Add smooth slide animation
        navLinks.style.animation = 'smoothSlideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
    } else {
        mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        // Add smooth slide out animation
        navLinks.style.animation = 'smoothSlideOut 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
    }
});

// Close mobile menu when clicking a link
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('active');
        mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    });
});

// Header scroll effect
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
    if (window.scrollY > 100) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
});

// Carousel functionality
const carouselItems = document.querySelectorAll('.carousel-item');
const carouselDots = document.querySelectorAll('.carousel-dot');
const prevBtn = document.querySelector('.carousel-prev');
const nextBtn = document.querySelector('.carousel-next');
let currentSlide = 0;
let slideInterval;
const slideDuration = 5000; // 5 seconds

// Initialize carousel with smooth transitions
function showSlide(index) {
    // Remove all classes from slides
    carouselItems.forEach(item => {
        item.classList.remove('active', 'next', 'prev');
    });
    carouselDots.forEach(dot => dot.classList.remove('active'));
    
    // Calculate next and previous indices
    const nextIndex = (index + 1) % carouselItems.length;
    const prevIndex = (index - 1 + carouselItems.length) % carouselItems.length;
    
    // Add appropriate classes with smooth transition
    carouselItems[index].classList.add('active');
    carouselItems[nextIndex].classList.add('next');
    carouselItems[prevIndex].classList.add('prev');
    
    // Add smooth animation to active slide
    carouselItems[index].style.animation = 'smoothFadeIn 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
    
    // Update dots
    carouselDots[index].classList.add('active');
    currentSlide = index;
}

// Next slide
function nextSlide() {
    let nextIndex = (currentSlide + 1) % carouselItems.length;
    showSlide(nextIndex);
}

// Previous slide
function prevSlide() {
    let prevIndex = (currentSlide - 1 + carouselItems.length) % carouselItems.length;
    showSlide(prevIndex);
}

// Start auto slide
function startCarousel() {
    slideInterval = setInterval(nextSlide, slideDuration);
}

// Stop auto slide
function stopCarousel() {
    clearInterval(slideInterval);
}

// Toast notification function
function showToast(message, type = 'info') {
    // Remove existing toast if any
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span class="toast-message">${message}</span>
        </div>
    `;
    
    // Add to body
    document.body.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// Event listeners
nextBtn.addEventListener('click', () => {
    nextSlide();
    stopCarousel();
    startCarousel();
    // Show toast message when forward arrow is clicked
    showToast('Moving to next slide', 'info');
});

prevBtn.addEventListener('click', () => {
    prevSlide();
    stopCarousel();
    startCarousel();
});

carouselDots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
        showSlide(index);
        stopCarousel();
        startCarousel();
    });
});

// Pause carousel on hover
const carousel = document.querySelector('.carousel');
carousel.addEventListener('mouseenter', stopCarousel);
carousel.addEventListener('mouseleave', startCarousel);

// Initialize
showSlide(0);
startCarousel();

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    });
});

// Form submission
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        alert('Thank you for your message! We will get back to you soon.');
        this.reset();
    });
}

// 3D hover effects for service cards
const serviceCards = document.querySelectorAll('.service-card');
serviceCards.forEach(card => {
    card.addEventListener('mousemove', (e) => {
        const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
        const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
        card.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
    });
    
    card.addEventListener('mouseenter', () => {
        card.style.transition = 'none';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.transition = 'all 0.5s ease';
        card.style.transform = 'rotateY(0deg) rotateX(0deg)';
    });
});

// Login Form Functionality
const loginContainer = document.getElementById('loginContainer');
const loginToggle = document.getElementById('loginToggle');
const navLoginBtn = document.getElementById('navLoginBtn');
const footerLoginBtn = document.getElementById('footerLoginBtn');
const forgotContainer = document.getElementById('forgotContainer');
const resetContainer = document.getElementById('resetContainer');

// Toggle login form with smooth animation
function toggleLoginForm() {
    loginContainer.classList.toggle('active');
    if (loginContainer.classList.contains('active')) {
        loginToggle.innerHTML = '<i class="fas fa-times"></i><span>Close</span>';
        // Add smooth entrance animation
        loginContainer.style.animation = 'smoothSlideIn 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
    } else {
        loginToggle.innerHTML = '<i class="fas fa-sign-in-alt"></i><span>Login</span>';
        // Add smooth exit animation
        loginContainer.style.animation = 'smoothSlideOut 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
    }
}

// Event listeners for login buttons
loginToggle.addEventListener('click', toggleLoginForm);
if (navLoginBtn) {
    navLoginBtn.addEventListener('click', function(e) {
        e.preventDefault();
        toggleLoginForm();
    });
}
footerLoginBtn.addEventListener('click', function(e) {
    e.preventDefault();
    toggleLoginForm();
});

// Toggle password visibility
const togglePassword = document.getElementById('togglePassword');
if (togglePassword) {
    togglePassword.addEventListener('click', function() {
        const password = document.getElementById('password');
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.textContent = type === 'password' ? '👁️' : '🙈';
    });
}

// Forgot password form switching
const forgotPasswordLink = document.getElementById('forgotPasswordLink');
if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener('click', function(e) {
        e.preventDefault();
        loginContainer.classList.remove('active');
        forgotContainer.style.display = 'block';
    });
}

const backToLoginLink = document.getElementById('backToLoginLink');
if (backToLoginLink) {
    backToLoginLink.addEventListener('click', function(e) {
        e.preventDefault();
        forgotContainer.style.display = 'none';
        loginContainer.classList.add('active');
    });
}

const backToLoginLink2 = document.getElementById('backToLoginLink2');
if (backToLoginLink2) {
    backToLoginLink2.addEventListener('click', function(e) {
        e.preventDefault();
        resetContainer.style.display = 'none';
        loginContainer.classList.add('active');
    });
}

const closeForgot = document.getElementById('closeForgot');
if (closeForgot) {
    closeForgot.addEventListener('click', function(e) {
        e.preventDefault();
        forgotContainer.style.display = 'none';
    });
}

const closeReset = document.getElementById('closeReset');
if (closeReset) {
    closeReset.addEventListener('click', function(e) {
        e.preventDefault();
        resetContainer.style.display = 'none';
    });
}

// Inline form feedback helpers
function getInlineFeedbackElements(form, createIfMissing = false) {
    if (!form) return null;

    let container = form.querySelector('[data-feedback="container"]');

    if (!container && createIfMissing) {
        container = document.createElement('div');
        container.className = 'alert alert-dismissible fade form-feedback-alert d-none mt-3';
        container.setAttribute('role', 'alert');
        container.dataset.feedback = 'container';
        container.innerHTML = `
            <div class="d-flex align-items-start gap-2">
                <span class="form-feedback-icon flex-shrink-0" data-feedback="icon"></span>
                <div class="form-feedback-message flex-grow-1" data-feedback="message"></div>
            </div>
            <button type="button" class="btn-close" aria-label="Close"></button>
        `;

        const wrapper = form.querySelector('.form-feedback-wrapper');
        if (wrapper) {
            wrapper.innerHTML = '';
            wrapper.appendChild(container);
        } else if (form.firstElementChild) {
            form.insertBefore(container, form.firstElementChild);
        } else {
            form.appendChild(container);
        }
    }

    if (!container) {
        return null;
    }

    const closeBtn = container.querySelector('.btn-close');
    if (closeBtn && !closeBtn.dataset.feedbackBound) {
        closeBtn.dataset.feedbackBound = 'true';
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            hideInlineFormAlert(form);
        });
    }

    return {
        container,
        icon: container.querySelector('[data-feedback="icon"]'),
        message: container.querySelector('[data-feedback="message"]')
    };
}

function hideInlineFormAlert(form) {
    const elements = getInlineFeedbackElements(form);
    if (!elements) return;

    const { container, icon, message } = elements;
    container.classList.add('d-none');
    container.classList.remove('alert-success', 'alert-danger', 'alert-info', 'show');

    if (icon) {
        icon.innerHTML = '';
    }
    if (message) {
        message.textContent = '';
    }
}

function showInlineFormAlert(form, feedbackMessage, type = 'info') {
    const elements = getInlineFeedbackElements(form, true);
    if (!elements) return false;

    const { container, icon, message } = elements;
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const iconClass = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';

    container.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info');
    container.classList.add(alertClass, 'show');

    if (icon) {
        icon.innerHTML = `<i class="fas ${iconClass}"></i>`;
    }
    if (message) {
        message.textContent = feedbackMessage;
    } else {
        container.textContent = feedbackMessage;
    }

    return true;
}

// Bootstrap Feedback Modal helpers
const feedbackModalElement = document.getElementById('feedbackModal');
const feedbackModalTitle = document.getElementById('feedbackModalTitle');
const feedbackModalBody = document.getElementById('feedbackModalBody');
const feedbackModalIcon = document.getElementById('feedbackModalIcon');
let feedbackModalInstance = null;
let feedbackModalResolver = null;
let feedbackModalTimer = null;

function getFeedbackModalInstance() {
    if (!feedbackModalElement || typeof bootstrap === 'undefined') {
        return null;
    }

    if (!feedbackModalInstance) {
        feedbackModalInstance = new bootstrap.Modal(feedbackModalElement);

        feedbackModalElement.addEventListener('hidden.bs.modal', () => {
            if (feedbackModalTimer) {
                clearTimeout(feedbackModalTimer);
                feedbackModalTimer = null;
            }
            if (feedbackModalResolver) {
                feedbackModalResolver();
                feedbackModalResolver = null;
            }
        });
    }

    return feedbackModalInstance;
}

function showFeedbackModal({ title, message, type = 'success', autoHide = false, autoHideDelay = 2500 }) {
    return new Promise((resolve) => {
        const modalInstance = getFeedbackModalInstance();

        if (!modalInstance) {
            // Fallback to alert if Bootstrap modal is not available
            window.alert(`${title}\n\n${message}`);
            resolve();
            return;
        }

        if (feedbackModalTimer) {
            clearTimeout(feedbackModalTimer);
            feedbackModalTimer = null;
        }

        const header = feedbackModalElement.querySelector('.modal-header');
        if (header) {
            header.classList.remove('bg-success', 'bg-danger', 'text-white');
        }

        const isError = type === 'error';
        if (header) {
            header.classList.add(isError ? 'bg-danger' : 'bg-success', 'text-white');
        }

        const iconClass = isError ? 'fa-exclamation-circle' : 'fa-check-circle';
        if (feedbackModalIcon) {
            feedbackModalIcon.innerHTML = `<i class="fas ${iconClass}"></i>`;
            feedbackModalIcon.classList.toggle('text-danger', isError);
            feedbackModalIcon.classList.toggle('text-success', !isError);
        }

        if (feedbackModalTitle) {
            feedbackModalTitle.textContent = title;
        }
        if (feedbackModalBody) {
            feedbackModalBody.textContent = message;
        }

        modalInstance.show();
        feedbackModalResolver = resolve;

        if (autoHide) {
            feedbackModalTimer = setTimeout(() => {
                modalInstance.hide();
            }, autoHideDelay);
        }
    });
}

// Show reset form if token is valid
if (typeof showResetForm !== 'undefined' && showResetForm) {
    resetContainer.style.display = 'block';
}

// Alert functions
function showLoadingAlert(title = 'Processing...') {
    return Swal.fire({
        title: title,
        html: 'Please wait...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => Swal.showLoading()
    });
}

function showSuccessAlert(message, title = 'Success!', form = null) {
    if (form && showInlineFormAlert(form, message, 'success')) {
        return Promise.resolve();
    }

    return showFeedbackModal({
        title,
        message,
        type: 'success',
        autoHide: true,
        autoHideDelay: 2500
    });
}

function showErrorAlert(message, title = 'Error!', form = null) {
    if (form && showInlineFormAlert(form, message, 'error')) {
        return Promise.resolve();
    }

    return showFeedbackModal({
        title,
        message,
        type: 'error',
        autoHide: false
    });
}

// Emergency Overlays
const emergencyOverlay = document.getElementById('emergencyOverlay');
const contactsOverlay = document.getElementById('contactsOverlay');
const announcementsOverlay = document.getElementById('announcementsOverlay');
const tipsOverlay = document.getElementById('tipsOverlay');

// Buttons to show overlays
const emergencyBtn = document.getElementById('emergencyBtn');
const contactsBtn = document.getElementById('contactsBtn');
const announcementsBtn = document.getElementById('announcementsBtn');
const tipsBtn = document.getElementById('tipsBtn');
const emergencyBtnHero = document.getElementById('emergencyBtnHero');
const footerEmergencyBtn = document.getElementById('footerEmergencyBtn');
const footerContactsBtn = document.getElementById('footerContactsBtn');
const footerAnnouncementsBtn = document.getElementById('footerAnnouncementsBtn');
const footerTipsBtn = document.getElementById('footerTipsBtn');

// Buttons to close overlays
const closeEmergency = document.getElementById('closeEmergency');
const closeContacts = document.getElementById('closeContacts');
const closeAnnouncements = document.getElementById('closeAnnouncements');
const closeTips = document.getElementById('closeTips');

// Function to toggle overlay with smooth animations
function toggleOverlay(overlay) {
    document.querySelectorAll('.alert-overlay').forEach(ov => {
        if (ov === overlay) {
            ov.classList.toggle('active');
            if (ov.classList.contains('active')) {
                // Add smooth entrance animation
                const container = ov.querySelector('.alert-container');
                if (container) {
                    container.style.animation = 'smoothScaleIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                }
            }
        } else {
            ov.classList.remove('active');
        }
    });
}

// Event listeners for overlay buttons
emergencyBtn.addEventListener('click', () => toggleOverlay(emergencyOverlay));
contactsBtn.addEventListener('click', () => toggleOverlay(contactsOverlay));
announcementsBtn.addEventListener('click', () => toggleOverlay(announcementsOverlay));
tipsBtn.addEventListener('click', () => toggleOverlay(tipsOverlay));
emergencyBtnHero.addEventListener('click', (e) => {
    e.preventDefault();
    toggleOverlay(emergencyOverlay);
});
footerEmergencyBtn.addEventListener('click', (e) => {
    e.preventDefault();
    toggleOverlay(emergencyOverlay);
});
footerContactsBtn.addEventListener('click', (e) => {
    e.preventDefault();
    toggleOverlay(contactsOverlay);
});
footerAnnouncementsBtn.addEventListener('click', (e) => {
    e.preventDefault();
    toggleOverlay(announcementsOverlay);
});
footerTipsBtn.addEventListener('click', (e) => {
    e.preventDefault();
    toggleOverlay(tipsOverlay);
});

// Event listeners for close buttons
closeEmergency.addEventListener('click', () => toggleOverlay(emergencyOverlay));
closeContacts.addEventListener('click', () => toggleOverlay(contactsOverlay));
closeAnnouncements.addEventListener('click', () => toggleOverlay(announcementsOverlay));
closeTips.addEventListener('click', () => toggleOverlay(tipsOverlay));

// Close overlays when clicking outside content
document.querySelectorAll('.alert-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            toggleOverlay(overlay);
        }
    });
});

// Make phone numbers clickable
document.querySelectorAll('.emergency-number').forEach(el => {
    const phoneNumber = el.textContent.trim().split(' ').pop();
    el.innerHTML = el.innerHTML.replace(phoneNumber, `<a href="tel:${phoneNumber}">${phoneNumber}</a>`);
});

// Enhanced form submission with aggressive back button prevention
async function handleFormSubmit(form, submitButton) {
    const originalText = submitButton.innerHTML;
    
    hideInlineFormAlert(form);

    try {
        // Disable button and show spinner
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner"></span> Processing...';
        
        // Show appropriate alert based on form type
        let alert;
        if (form.id === 'loginForm') {
            alert = showLoadingAlert('Verifying credentials...');
        } else {
            alert = showLoadingAlert('Processing your request...');
        }
        
        // Submit form data
        const formData = new FormData(form);
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const result = await response.json();
        
        // Close alert
        alert.close();
        
        if (result.success) {
            // SECURITY MEASURES FOR LOGIN FORM - Only after successful login
            if (form.id === 'loginForm') {
                // 1. Clear form data after successful login
                form.reset();
                const inputs = form.querySelectorAll('input[type="text"], input[type="password"]');
                inputs.forEach(input => {
                    input.value = '';
                    input.setAttribute('autocomplete', 'off');
                });
                
                // 2. Clear browser cache
                if (window.caches) {
                    caches.keys().then(names => {
                        names.forEach(name => caches.delete(name));
                    });
                }
                
                // 3. Clear storage (preserve captcha verification)
                if (window.sessionStorage) {
                    const captchaVerified = sessionStorage.getItem('captchaVerified');
                    sessionStorage.clear();
                    if (captchaVerified) {
                        sessionStorage.setItem('captchaVerified', captchaVerified);
                    }
                }
                if (window.localStorage) {
                    localStorage.removeItem('loginFormData');
                    localStorage.removeItem('userCredentials');
                }
                
                // 4. Prevent back button after login
                window.history.replaceState(null, null, window.location.href);
                window.history.pushState(null, null, window.location.href);
                
                // 5. Add popstate listener to prevent back navigation
                const preventBack = function(e) {
                    window.history.forward();
                };
                window.addEventListener('popstate', preventBack);
                
                // 6. Show success and redirect with replace
                await showSuccessAlert('Login successful! Redirecting...', 'Success!', form);
                
                setTimeout(() => {
                    // Use replace instead of href to prevent back navigation
                    window.location.replace(result.redirect);
                }, 1000);
                
            } else {
                await showSuccessAlert(result.message, 'Success!', form);
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
            }
        } else {
            await showErrorAlert(result.message, 'Error!', form);
            
            // Clear form on error too
            if (form.id === 'loginForm') {
                form.reset();
                const inputs = form.querySelectorAll('input[type="text"], input[type="password"]');
                inputs.forEach(input => input.value = '');
            }
        }
        
    } catch (error) {
        console.error('Form submission error:', error);
        await showErrorAlert('An error occurred. Please try again.', 'Error!', form);
        
        // Clear form on error
        if (form.id === 'loginForm') {
            form.reset();
            const inputs = form.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => input.value = '');
        }
    } finally {
        // Restore button state
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
}

// Set up form submissions (now including login form with enhanced security)
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Special handling for login form - show captcha first
        if (form.id === 'loginForm') {
            // Check if captcha verification exists
            const isVerified = sessionStorage.getItem('captchaVerified');
            
            if (isVerified !== 'true') {
                // Show captcha modal and pass callback to submit form after verification
                showCaptchaModal(async () => {
                    // Callback will execute after captcha is verified
                    await handleFormSubmit(form, form.querySelector('button[type="submit"]'));
                });
                return;
            }
        }
        
        // For other forms or if already verified, submit normally
        await handleFormSubmit(form, form.querySelector('button[type="submit"]'));
    });
});

// Initialize tooltips for emergency buttons
document.querySelectorAll('.emergency-btn').forEach(btn => {
    if (btn) {
        btn.addEventListener('mouseenter', function() {
            const tooltip = this.querySelector('.emergency-btn-tooltip');
            if (tooltip) {
                tooltip.style.opacity = '1';
                tooltip.style.right = '80px';
            }
        });
        
        btn.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.emergency-btn-tooltip');
            if (tooltip) {
                tooltip.style.opacity = '0';
                tooltip.style.right = '70px';
            }
        });
    }
});

// SMART BACK BUTTON PREVENTION - Only prevents back navigation, allows normal typing
(function() {
    'use strict';
    
    // Prevent back button navigation (but don't clear form while typing)
    function preventBackNavigation() {
        // Method 1: Replace current history entry
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Method 2: Add new history entry
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
        }
        
        // Method 3: Listen for popstate and prevent back
        window.addEventListener('popstate', function(event) {
            // Force forward navigation
            window.history.forward();
        });
    }
    
    // Apply back button prevention
    preventBackNavigation();
    
})();

// Contact Form Submission (Enhanced)
const contactFormEnhanced = document.getElementById('contactForm');
if (contactFormEnhanced) {
    contactFormEnhanced.addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    try {
        // Disable button and show spinner
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner"></span> Sending...';
        
        // Show loading alert
        const alert = Swal.fire({
            title: 'Sending your message',
            html: 'Please wait...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
        
        // Submit form data
        const formData = new FormData(form);
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const result = await response.json();
        
        // Close loading alert
        await alert.close();
        
        if (result.success) {
            await Swal.fire({
                title: 'Success!',
                text: result.message,
                icon: 'success',
                confirmButtonText: 'OK',
                timer: 3000,
                timerProgressBar: true
            });
            form.reset();
        } else {
            await Swal.fire({
                title: 'Error!',
                text: result.message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    } catch (error) {
        console.error('Form submission error:', error);
        await Swal.fire({
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } finally {
        // Restore button state
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
    });
}