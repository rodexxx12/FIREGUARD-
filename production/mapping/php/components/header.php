<?php include '../../components/header.php'; ?>
  <!-- JavaScript Libraries -->
  <!-- Leaflet core with CDN fallback to ensure window.L exists before plugins -->
  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.js"></script>
  <script>
  if (!window.L) {
    var fallback = document.createElement('script');
    fallback.src = 'https://unpkg.com/leaflet@1.9.3/dist/leaflet.js';
    document.head.appendChild(fallback);
  }
  </script>
  <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
  <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
  <script src="https://unpkg.com/leaflet-heat@0.2.0/dist/leaflet-heat.js"></script>
  <script src="https://unpkg.com/mapbox-gl@2.15.0/dist/mapbox-gl.js"></script>
  <script src="https://unpkg.com/mapbox-gl-leaflet@0.0.15/leaflet-mapbox-gl.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/style.css">
