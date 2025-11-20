<?php
// Simple navigation file for barangay reports
// This can be included in your main navigation or used as a standalone entry point
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Reports Navigation</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <i class="fas fa-chart-pie text-primary" style="font-size: 3rem;"></i>
                        </div>
                        
                        <h1 class="h3 text-primary mb-3">Barangay Reports System</h1>
                        
                        <p class="text-muted mb-4">
                            Access comprehensive fire incident reports and statistics for all barangays in the system.
                        </p>
                        
                        <div class="d-grid gap-3">
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-chart-pie me-2"></i>
                                View Barangay Overview
                            </a>
                            
                            <a href="../" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>
                                Back to Main System
                            </a>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Navigate through barangay reports and filter data by various criteria
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
