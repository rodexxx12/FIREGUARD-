<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FIREGUARD</title>
    <link rel="icon" type="image/png" sizes="32x32" href="fireguardlogo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="fireguardlogo.png?v=1">
    <link rel="shortcut icon" type="image/png" href="fireguardlogo.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="fireguardlogo.png?v=1">
    <link rel="apple-touch-icon" href="fireguardlogo.png?v=1">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../../vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="../../../vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
  
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
        crossorigin=""/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
  

  <link href="../../../vendors/iCheck/skins/flat/green.css" rel="stylesheet">
  <link href="../../../vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">  
  <link href="../../../vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">  
  <link href="../../../build/css/custom.min.css" rel="stylesheet">
  
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Mapbox GL -->
  <link rel="stylesheet" href="https://unpkg.com/mapbox-gl@2.15.0/dist/mapbox-gl.css" />
  
  <!-- JavaScript Libraries -->
  <!-- jQuery - Required for DataTables -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  
  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
          crossorigin=""></script>
  
  <!-- html2canvas for screenshot functionality -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  
  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
  // Ensure Leaflet is loaded before any plugins
  window.addEventListener('load', function() {
    if (typeof L === 'undefined') {
      console.error('Leaflet failed to load');
    }
  });
  </script>
  
  <!-- Leaflet plugins - load after core -->
  <script>
  // Load Leaflet plugins only after L is available
  function loadLeafletPlugins() {
    if (typeof L !== 'undefined') {
      // Load marker cluster
      var script1 = document.createElement('script');
      script1.src = 'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js';
      document.head.appendChild(script1);
      
      // Load routing machine
      var script2 = document.createElement('script');
      script2.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
      document.head.appendChild(script2);
      
      // Load heat map
      var script3 = document.createElement('script');
      script3.src = 'https://unpkg.com/leaflet-heat@0.2.0/dist/leaflet-heat.js';
      document.head.appendChild(script3);
      
      // Load Mapbox GL
      var script4 = document.createElement('script');
      script4.src = 'https://unpkg.com/mapbox-gl@2.15.0/dist/mapbox-gl.js';
      document.head.appendChild(script4);
      
      // Load Mapbox GL Leaflet integration
      script4.onload = function() {
        var script5 = document.createElement('script');
        script5.src = 'https://unpkg.com/mapbox-gl-leaflet@0.0.15/leaflet-mapbox-gl.js';
        document.head.appendChild(script5);
      };
    } else {
      // Retry after a short delay
      setTimeout(loadLeafletPlugins, 100);
    }
  }
  
  // Start loading plugins
  loadLeafletPlugins();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>