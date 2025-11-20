// Announcements Management
class AnnouncementsManager {
    constructor() {
        this.announcementsContainer = document.querySelector('#announcementsOverlay .alert-content');
        this.loadingElement = null;
        this.init();
    }

    init() {
        this.createLoadingElement();
        this.loadAnnouncements();
        
        // Refresh announcements every 5 minutes
        setInterval(() => {
            this.loadAnnouncements();
        }, 300000); // 5 minutes
    }

    createLoadingElement() {
        this.loadingElement = document.createElement('div');
        this.loadingElement.className = 'announcement-loading';
        this.loadingElement.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading announcements...</span>
            </div>
            <p>Loading announcements...</p>
        `;
        this.loadingElement.style.textAlign = 'center';
        this.loadingElement.style.padding = '20px';
    }

    async loadAnnouncements() {
        try {
            // Show loading
            this.showLoading();

            const response = await fetch('login/functions/get_announcements.php');
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response received:', text);
                throw new Error('Server returned non-JSON response');
            }
            
            const data = await response.json();

            if (data.success) {
                this.renderAnnouncements(data.announcements);
            } else {
                this.showError(data.message || 'Failed to load announcements');
            }
        } catch (error) {
            console.error('Error loading announcements:', error);
            this.showError('Network error while loading announcements');
        }
    }

    showLoading() {
        if (this.announcementsContainer) {
            // Keep the description paragraph
            const description = this.announcementsContainer.querySelector('p:first-child');
            this.announcementsContainer.innerHTML = '';
            if (description) {
                this.announcementsContainer.appendChild(description);
            }
            this.announcementsContainer.appendChild(this.loadingElement);
        }
    }

    renderAnnouncements(announcements) {
        if (!this.announcementsContainer) return;

        // Keep the description paragraph
        const description = this.announcementsContainer.querySelector('p:first-child');
        this.announcementsContainer.innerHTML = '';
        if (description) {
            this.announcementsContainer.appendChild(description);
        }

        if (announcements.length === 0) {
            const noAnnouncements = document.createElement('div');
            noAnnouncements.className = 'announcement-item';
            noAnnouncements.innerHTML = `
                <div class="announcement-date">${new Date().toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}</div>
                <h3 class="announcement-title">No Current Announcements</h3>
                <p>There are no active announcements at this time. Please check back later for updates.</p>
            `;
            this.announcementsContainer.appendChild(noAnnouncements);
            return;
        }

        announcements.forEach(announcement => {
            const announcementElement = this.createAnnouncementElement(announcement);
            this.announcementsContainer.appendChild(announcementElement);
        });
    }

    createAnnouncementElement(announcement) {
        const element = document.createElement('div');
        element.className = 'announcement-item';
        
        const date = new Date(announcement.created_at).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });

        let priorityBadge = '';
        if (announcement.priority === 'high') {
            priorityBadge = '<span class="priority-badge high">High Priority</span>';
        } else if (announcement.priority === 'medium') {
            priorityBadge = '<span class="priority-badge medium">Medium Priority</span>';
        }

        element.innerHTML = `
            <div class="announcement-date">
                ${date}
                ${priorityBadge}
            </div>
            <h3 class="announcement-title">${this.escapeHtml(announcement.title)}</h3>
            <p>${this.escapeHtml(announcement.content).replace(/\n/g, '<br>')}</p>
            <div class="announcement-meta">
                <small>Posted by: ${this.escapeHtml(announcement.author_name)} 
                (${announcement.source.charAt(0).toUpperCase() + announcement.source.slice(1)})</small>
            </div>
        `;

        return element;
    }

    showError(message) {
        if (this.announcementsContainer) {
            const description = this.announcementsContainer.querySelector('p:first-child');
            this.announcementsContainer.innerHTML = '';
            if (description) {
                this.announcementsContainer.appendChild(description);
            }

            const errorElement = document.createElement('div');
            errorElement.className = 'announcement-item';
            errorElement.innerHTML = `
                <div class="announcement-date">${new Date().toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}</div>
                <h3 class="announcement-title">Error Loading Announcements</h3>
                <p>${this.escapeHtml(message)}. Please try refreshing the page.</p>
            `;
            this.announcementsContainer.appendChild(errorElement);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize announcements when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize announcements manager
    const announcementsManager = new AnnouncementsManager();
    
    // Add refresh button functionality
    const refreshButton = document.createElement('button');
    refreshButton.className = 'btn btn-sm btn-outline-primary';
    refreshButton.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
    refreshButton.style.marginBottom = '15px';
    refreshButton.onclick = () => announcementsManager.loadAnnouncements();
    
    const announcementsOverlay = document.getElementById('announcementsOverlay');
    if (announcementsOverlay) {
        const alertContent = announcementsOverlay.querySelector('.alert-content');
        if (alertContent) {
            alertContent.insertBefore(refreshButton, alertContent.firstChild);
        }
    }
}); 