<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Required - Profile Picture Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
        }
        .icon-large {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .step-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .step-number {
            background: #667eea;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-card text-center">
                    <div class="icon-large">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    
                    <h2 class="mb-4">Login Required</h2>
                    <p class="text-muted mb-4">
                        To upload a profile picture, you need to be logged in as an administrator.
                    </p>
                    
                    <div class="text-start mb-4">
                        <h5 class="mb-3">How to Log In:</h5>
                        
                        <div class="step-item">
                            <span class="step-number">1</span>
                            <strong>Go to the Login Page</strong>
                            <p class="mb-0 mt-2">Click the button below to access the login page.</p>
                        </div>
                        
                        <div class="step-item">
                            <span class="step-number">2</span>
                            <strong>Enter Admin Credentials</strong>
                            <p class="mb-0 mt-2">Use your admin username and password to log in.</p>
                        </div>
                        
                        <div class="step-item">
                            <span class="step-number">3</span>
                            <strong>Access Profile Page</strong>
                            <p class="mb-0 mt-2">After login, navigate to the profile page to upload your picture.</p>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="../../../login/php/login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Go to Login Page
                        </a>
                        
                        <a href="auth_test.php" class="btn btn-outline-secondary">
                            <i class="bi bi-info-circle me-2"></i>
                            Check Authentication Status
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            If you don't have admin credentials, please contact your system administrator.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 