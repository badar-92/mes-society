<?php
// public/access-denied.php
// This page is shown when a user tries to access a restricted area without proper permissions.
// It can be linked directly or included via redirect.
$page_title = "Access Denied";
// If you have a public header/footer, you can include them instead of the standalone HTML.
// For consistency, you might want to use the site's header and footer.
// Example:
//require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Access Denied | MES Society</title>
    <!-- Bootstrap 5 CDN for quick styling (you can replace with local assets) -->
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .denied-container {
            text-align: center;
            background: white;
            padding: 3rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .denied-icon {
            font-size: 5rem;
            line-height: 1;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 2.5rem;
            font-weight: 600;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .btn-group-custom {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            border-radius: 50px;
            transition: all 0.2s;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
    </style>
</head>
<body>
    <div class="denied-container">
        <div class="denied-icon">⛔</div>
        <h1>Access Denied</h1>
        <p>You are not authorized to view or perform this operation.</p>
        <p class="text-muted">If you believe this is an error, please contact the administrator.</p>
        <div class="btn-group-custom">
            <button onclick="history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Go Back
            </button>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
        </div>
    </div>

    <!-- Optional: Font Awesome for icons (if you want to use them) -->
    <script src="https://kit.fontawesome.com/your-kit-id.js" crossorigin="anonymous"></script>
    <!-- If you don't have Font Awesome, you can remove the <i> tags or use plain text. -->
</body>
</html>
<?php
// If you included a footer, uncomment the line below:
 //require_once '../includes/footer.php';
?>