<div class="col-md-3 left_col">
  <div class="left_col scroll-view">
    <div class="navbar nav_title" style="border: 0;">
      <a href="../../mapping/php/map.php" class="site_title"> <span class="fireguard-gradient">FIREGUARD</span></a>
  </div>
<div class="clearfix"></div>
  <div class="profile clearfix">
    <div class="profile_pic">
    <img src="fireguardlogo.png" alt="Fire Guard Logo" class="img-circle profile_img">
    </div>
    <div class="profile_info">
      <span>Welcome,</span>
      <h2>Admin</h2>
    </div>
  </div>
<br />
<div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
  <div class="menu_section">
    <h3>General</h3>
    <ul class="nav side-menu">
      <li><a><i class="fa fa-home"></i> Home <span class="fa fa-chevron-down"></span></a>
        <ul class="nav child_menu">
          <li><a href="../../mapping/php/map.php">Locate Fire Incident</a></li>
          <li><a href="../../sensordata/php/fire_data_list.php">Sensor Data List</a></li>
          <li><a href="../../fireincidents/php/main.php">Fire Incidents</a></li>
          <li><a href="../../statistics/php/index.php">Statistics Dashboard</a></li>
        </ul>
      </li>
      <li><a><i class="fa fa-file"></i> Reports <span class="fa fa-chevron-down"></span></a>
        <ul class="nav child_menu">
          <li><a href="../../barangay_reports/php/reports.php">Barangay Reports Dashboard</a></li>
          <li><a href="../../spot/php/index.php">Create Reports</a></li>
          <li><a href="../../spot/php/final_reports.php">Final Reports</a></li>
        </ul>
      </li>
      <li><a><i class="fa fa-table"></i> Tables <span class="fa fa-chevron-down"></span></a>
        <ul class="nav child_menu">
          <li><a href="../../buildingtable/php/main.php">Building Table</a></li>
          <li><a href="../../alarmtable/php/index.php">Alarm Table</a></li>
          <li><a href="../../response/php/index.php">Response Table</a></li>
          <li><a href="../../usertable/php/index.php">User Table</a></li>
          <li><a href="../../firefightertable/php/index.php">Firefighter Table</a></li>
        </ul>
      </li>
    </ul>
</div>

<style>
/* Fix sidebar position - prevent scrolling */
.left_col {
    position: fixed !important;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1000;
}

.left_col.scroll-view {
    position: relative;
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Center the profile section */
.profile {
    display: flex;
    align-items: center;
    justify-content: center;  /* Center the profile items horizontally */
    gap: 5px;                /* Space between image and profile info */
}

/* Styling for the profile image container */
.profile_pic {
    display: flex;            /* Make the container a flexbox */
    justify-content: center;  /* Center the profile image horizontally */
    align-items: center;      /* Center the profile image vertically */
    margin-right: 5px;
}

/* Styling for the profile image */
.profile_img {
    width: 100px;  /* Increased size */
    height: 50px; /* Increased size */
    object-fit: cover;  /* Ensures the image is properly contained in the circle */
    border-radius: 50%;  /* Makes the image round */
    border: 1px solid #007bff;  /* Adds a blue border around the profile image */
    transition: transform 0.3s ease, box-shadow 0.3s ease;  /* Smooth hover transition */
}

.profile_img:hover {
    transform: scale(1.1);  /* Slight zoom-in effect on hover */
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.5);  /* Adds a soft shadow effect on hover */
}

/* Ensures the profile image is responsive */
@media (max-width: 768px) {
    .profile_img {
        width: 60px;  /* Smaller size for mobile devices */
        height: 60px;  /* Smaller size for mobile devices */
    }
}

/* Styling for profile info - bolder and smaller */
.profile_info {
    font-weight: 800;  /* Make text bolder */
    font-size: 1rem;  /* Decrease the size */
}

.profile_info span {
    font-weight: 800;  /* Make "Welcome," text bolder */
    font-size: 1rem;  /* Decrease the size */
}

.profile_info h2 {
    font-weight: 700;  /* Make "Admin" text bolder */
    font-size: 0.85em;  /* Decrease the size */
    margin: 0;  /* Remove default margin */
}

/* Dark to light orange gradient styling for FIREGUARD */
.fireguard-gradient {
    background: linear-gradient(135deg, #B84500 0%, #CC5500 20%, #FF6B00 40%, #FF8C42 60%, #FFA366 80%, #FFB380 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 900;
    font-size: 1.2em;
    letter-spacing: 2px;
    text-shadow: none;
    display: inline-block;
    animation: gradient-shift 3s ease infinite;
}

/* Animated gradient effect */
@keyframes gradient-shift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Fallback for browsers that don't support gradient text */
@supports not (-webkit-background-clip: text) {
    .fireguard-gradient {
        color: #FF8C42;
        font-weight: 900;
        font-size: 1.2em;
        letter-spacing: 2px;
    }
}

</style>
