<style>
/* Improved Navigation Styles */
.top_nav {
    background: #fff;
    border-bottom: 1px solid #e5e5e5;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.nav_menu {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 14px;
    height: 52px;
}

.nav.toggle {
    display: flex;
    align-items: center;
}

.nav.toggle a {
    color: #333;
    font-size: 18px;
    padding: 8px 12px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.nav.toggle a:hover {
    background-color: #f8f9fa;
    color: #007bff;
}

.navbar-nav {
    display: flex;
    align-items: center;
    margin: 0;
    padding: 0;
}

.navbar-right {
    display: flex;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 12px;
}

.nav-item {
    display: flex;
    align-items: center;
    position: relative;
}

/* Profile Icon Styling */
.user-profile {
    display: flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    background: #ff7a18;
    color: #ffffff !important;
    text-decoration: none;
    transition: all 0.25s ease;
    border: 1px solid #ff7a18;
    border-radius: 30px;
    font-weight: 600;
    font-size: 13px;
    letter-spacing: 0.3px;
    text-shadow: none;
}
.user-profile i {
    font-size: 16px;
}
.user-profile .logout-label {
    font-size: 12px;
    margin-left: 3px;
}
.user-profile span,
.user-profile i,
.user-profile:visited,
.user-profile:active {
    color: #ffffff !important;
}

.user-profile:hover {
    transform: translateY(-1px);
    color: #ffffff !important;
    text-decoration: none;
    background: #ff6a00;
    border-color: #ff6a00;
}

.profile-btn {
    background: #007bff !important;
    color: #ffffff !important;
    border: 1px solid #007bff;
    text-shadow: none;
    border-radius: 5px !important;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3), 0 1px 2px rgba(0, 0, 0, 0.1);
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-btn i {
    color: #ffffff !important;
}
.profile-btn:hover {
    background: #0056b3 !important;
    border-color: #0056b3;
    color: #ffffff !important;
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4), 0 2px 4px rgba(0, 0, 0, 0.15);
    transform: translateY(-1px);
}

.logout-btn {
    background: #28a745 !important;
    border: 1px solid #28a745;
    color: #ffffff !important;
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 5px !important;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.35), 0 1px 2px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}
.logout-btn i {
    color: #ffffff !important;
}
.logout-btn:hover {
    background: #1e7e34 !important;
    border-color: #1e7e34;
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.45), 0 2px 4px rgba(0, 0, 0, 0.15);
    transform: translateY(-1px);
}

/* Hide dropdown arrow */
.user-profile::after {
    display: none !important;
}

.dropdown-toggle::after {
    display: none !important;
}

/* Removed user-profile img styles since image is now in dropdown */


/* Hide dropdown arrow */
.user-profile::after {
    display: none !important;
}

.dropdown-toggle::after {
    display: none !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .nav_menu {
        padding: 0 10px;
        height: 42px;
    }
    
    .navbar-right {
        gap: 10px;
    }
    
    .user-profile {
        padding: 5px 10px;
        font-size: 0;
    }
    
    
}

@media (max-width: 576px) {
    .navbar-right {
        gap: 8px;
    }
    
}
</style>

<div class="top_nav">
  <div class="nav_menu">
    <div class="nav toggle">
      <a id="menu_toggle"><i class="fa fa-bars"></i></a>
    </div>
    <nav class="nav navbar-nav">
      <ul class="navbar-right">
            
        
        <!-- Profile Button -->
        <li class="nav-item">
          <a href="../../profile/php/main.php" class="user-profile profile-btn" id="profileBtn" aria-label="Profile" title="Profile">
            <i class="bi bi-person" aria-hidden="true"></i>
          </a>
        </li>

        <!-- Logout Button -->
        <li class="nav-item">
          <a href="javascript:;" class="user-profile logout-btn" id="logoutBtn" aria-label="Logout" title="Logout">
            <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
          </a>
        </li>

      </ul>
    </nav>
  </div>
</div>


<!-- SweetAlert JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Enhanced logout functionality
document.addEventListener('DOMContentLoaded', function() {

  // Function to stop all text-to-speech
  const stopAllSpeech = () => {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
      console.log('All text-to-speech stopped');
    }
  };

  // Function to clear all application data
  const clearAppData = () => {
    // Stop any ongoing text-to-speech
    stopAllSpeech();
    
    // Preserve captcha verification flag before clearing
    const captchaVerified = sessionStorage.getItem('captchaVerified');
    
    // Clear localStorage and sessionStorage
    localStorage.clear();
    sessionStorage.clear();
    
    // Restore captcha verification if it existed
    if (captchaVerified) {
      sessionStorage.setItem('captchaVerified', captchaVerified);
    }
    
    // Clear all cookies
    document.cookie.split(";").forEach(cookie => {
      const eqPos = cookie.indexOf("=");
      const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
      document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
    });
    
    // Clear cache and force reload
    if ('caches' in window) {
      caches.keys().then(names => {
        names.forEach(name => caches.delete(name));
      });
    }
  };

  // Logout functionality
  document.getElementById('logoutBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Stop any ongoing text-to-speech
    stopAllSpeech();
    
    Swal.fire({
      title: 'Confirm Logout',
      html: 'Are you sure you want to log out?<br><small>This will end your current session.</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: '<i class="fa fa-sign-out"></i> Log Out',
      cancelButtonText: 'Cancel',
      reverseButtons: true,
      backdrop: 'rgba(0,0,0,0.7)',
      allowOutsideClick: false,
      showLoaderOnConfirm: true,
      preConfirm: () => {
        return fetch('../../logout/php/logout.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Cache-Control': 'no-cache, no-store',
            'Pragma': 'no-cache',
            'Content-Type': 'application/json'
          }
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .catch(error => {
          Swal.showValidationMessage(`Request failed: ${error}`);
        });
      }
    }).then((result) => {
      if (result.isConfirmed) {
        clearAppData();
        
        // Final logout confirmation
        Swal.fire({
          title: 'Session Terminated',
          html: 'You have been securely logged out.<br>Redirecting to login page...',
          icon: 'success',
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false,
          allowOutsideClick: false,
          willClose: () => {
            // Nuclear option for back button prevention
            window.location.replace('../../../index.php?logout=success');
            window.history.pushState(null, '', '../../../index.php');
            window.addEventListener('popstate', () => {
              window.history.pushState(null, '', '../../../index.php');
              window.location.replace('../../../index.php');
            });
          }
        });
      }
    });
  });

  // Strict back button prevention
  (function() {
    window.history.pushState(null, '', window.location.href);
    window.onpopstate = function(event) {
      window.history.pushState(null, '', window.location.href);
      if (!window.location.href.includes('../../../index.php')) {
        window.location.replace('../../../index.php');
      }
    };
    
    // Additional protection against cached pages
    window.onpageshow = function(event) {
      if (event.persisted) {
        window.location.reload();
      }
    };
  })();

  // Location permission modal (show only once)
  if (navigator.geolocation && !localStorage.getItem('locationModalShown')) {
    navigator.geolocation.getCurrentPosition(
      function(position) {
        // Location access granted, do nothing
      },
      function(error) {
        if (error.code === error.PERMISSION_DENIED) {
          Swal.fire({
            title: 'Enable Location',
            html: 'This application requires your location to provide full functionality. Please enable location services in your browser.',
            icon: 'info',
            confirmButtonText: 'Enable Location',
            allowOutsideClick: false,
            allowEscapeKey: false
          }).then(() => {
            // Try to request location again
            navigator.geolocation.getCurrentPosition(function() {}, function() {});
          });
          localStorage.setItem('locationModalShown', 'true');
        }
      }
    );
  } else if (!navigator.geolocation && !localStorage.getItem('locationModalShown')) {
    Swal.fire({
      title: 'Geolocation Not Supported',
      html: 'Your browser does not support geolocation. Some features may not work properly.',
      icon: 'warning',
      confirmButtonText: 'OK',
      allowOutsideClick: false,
      allowEscapeKey: false
    });
    localStorage.setItem('locationModalShown', 'true');
  }
});
</script>