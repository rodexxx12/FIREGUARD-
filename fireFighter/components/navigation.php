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
    padding: 0 18px;
    height: 64px;
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
    gap: 8px;
    padding: 8px 16px;
    background: linear-gradient(90deg, #ff4d4f, #d9363e);
    color: #ffffff !important;
    text-decoration: none;
    transition: all 0.25s ease;
    box-shadow: 0 3px 10px rgba(217, 54, 62, 0.28);
    border: 1px solid rgba(217, 54, 62, 0.4);
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    letter-spacing: 0.3px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
.user-profile span,
.user-profile i,
.user-profile:visited,
.user-profile:active {
    color: #ffffff !important;
}

.user-profile:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(217, 54, 62, 0.35);
    color: #ffffff !important;
    text-decoration: none;
    background: linear-gradient(90deg, #ff4a4d, #c62c34);
    border-color: #c62c34;
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
        padding: 0 12px;
        height: 45px;
    }
    
    .navbar-right {
        gap: 10px;
    }
    
    .user-profile {
        padding: 5px 10px;
        font-size: 13px;
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
            
        
        <!-- Logout Button -->
        <li class="nav-item">
          <a href="javascript:;" class="user-profile" id="logoutBtn">
            <span>Logout</span>
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