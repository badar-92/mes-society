<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
$userRole = $isLoggedIn ? $_SESSION['user_role'] : '';

// Get unread notifications count AND list of latest 5 unread notifications
$unreadNotifications = 0;
$recentNotifications = [];
if ($isLoggedIn) {
    try {
        $db = Database::getInstance()->getConnection();
        // Count unread
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifications = $stmt->fetchColumn();
        
        // Fetch latest 5 unread notifications (no action_url column)
        $stmt = $db->prepare("SELECT id, title, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $recentNotifications = $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}

// Fixed relative time function – no dynamic properties, PHP 8.2+ compatible
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    // Calculate weeks from days
    $weeks = floor($diff->d / 7);
    $days = $diff->d % 7;
    
    $parts = [];
    if ($weeks > 0) {
        $parts[] = $weeks . ' week' . ($weeks > 1 ? 's' : '');
    }
    if ($days > 0) {
        $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
    }
    if ($diff->h > 0) {
        $parts[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
    }
    if ($diff->i > 0) {
        $parts[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
    }
    if ($diff->s > 0 && empty($parts)) {
        $parts[] = $diff->s . ' second' . ($diff->s > 1 ? 's' : '');
    }
    
    if (empty($parts)) {
        return 'just now';
    }
    
    if (!$full) {
        $parts = array_slice($parts, 0, 1);
    }
    
    return implode(', ', $parts) . ' ago';
}

// Set default meta values if not defined
if (!isset($page_description)) {
    $page_description = "Mechanical Engineering Society at University of Lahore - Join student events, competitions, workshops, and connect with engineering community.";
}

if (!isset($page_keywords)) {
    $page_keywords = "MES UOL, MES Society, University of Lahore, mechanical engineering, engineering society, student activities, engineering events, competitions Lahore, workshops, engineering students Pakistan, MES University of Lahore, mes uol society";
}

// Set current URL for canonical tag
$current_url = SITE_URL . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- DYNAMIC TITLE -->
    <title><?php echo isset($page_title) ? $page_title . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- CORE SEO META TAGS -->
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <meta name="author" content="MES Society UOL">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="language" content="English">
    <meta name="geo.region" content="PK-PB">
    <meta name="geo.placename" content="Lahore">
    <meta name="geo.position" content="31.5204;74.3587">
    <meta name="application-name" content="MES Society UOL">
    <meta name="apple-mobile-web-app-title" content="MES UOL">
    <meta name="theme-color" content="#000000">
    
    <!-- OPEN GRAPH META TAGS (Facebook, LinkedIn) -->
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr($page_description, 0, 200)); ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/logo-mes.png">
    <meta property="og:url" content="<?php echo $current_url; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:locale" content="en_US">
    
    <!-- TWITTER CARD META TAGS -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars(substr($page_description, 0, 200)); ?>">
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>/assets/images/logo-mes.png">
    <meta name="twitter:site" content="@MES_UOL">
    
    <!-- CANONICAL URL -->
    <link rel="canonical" href="<?php echo $current_url; ?>">
    
    <!-- STRUCTURED DATA (Schema.org) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Mechanical Engineering Society",
        "alternateName": "MES Society UOL",
        "url": "<?php echo SITE_URL; ?>",
        "logo": "<?php echo SITE_URL; ?>/assets/images/logo-mes.png",
        "sameAs": [
            "https://www.facebook.com/MESUOL",
            "https://www.instagram.com/mes_uol",
            "https://twitter.com/MES_UOL"
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "email": "contact@mesuol.xo.je",
            "contactType": "customer service",
            "availableLanguage": "English"
        },
        "founder": {
            "@type": "Organization",
            "name": "University of Lahore"
        },
        "foundingDate": "2023",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Lahore",
            "addressRegion": "Punjab",
            "addressCountry": "PK"
        }
    }
    </script>
    
    <!-- WEBSITE SCHEMA -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "MES Society Portal",
        "url": "<?php echo SITE_URL; ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo SITE_URL; ?>/search?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <!-- WEBPAGE SCHEMA -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "MES Society - Mechanical Engineering Society",
        "description": "Official website of Mechanical Engineering Society at University of Lahore. Join events, competitions, workshops and connect with engineering community.",
        "url": "<?php echo SITE_URL; ?>",
        "primaryImageOfPage": "<?php echo SITE_URL; ?>/assets/images/logo-mes.png",
        "isPartOf": {
            "@type": "WebSite",
            "name": "MES Society Portal",
            "url": "<?php echo SITE_URL; ?>",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "<?php echo SITE_URL; ?>/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        },
        "breadcrumb": {
            "@type": "BreadcrumbList",
            "itemListElement": [{
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "<?php echo SITE_URL; ?>"
            }]
        }
    }
    </script>
    
    <!-- COLLEGE/UNIVERSITY SCHEMA -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollegeOrUniversity",
        "name": "University of Lahore",
        "url": "https://uol.edu.pk",
        "logo": "<?php echo SITE_URL; ?>/assets/images/logo-university.png",
        "sameAs": [
            "https://www.facebook.com/uolofficial",
            "https://twitter.com/UOLahore"
        ],
        "department": {
            "@type": "Organization",
            "name": "Mechanical Engineering Society",
            "alternateName": "MES Society",
            "url": "<?php echo SITE_URL; ?>",
            "logo": "<?php echo SITE_URL; ?>/assets/images/logo-mes.png",
            "parentOrganization": {
                "@type": "CollegeOrUniversity",
                "name": "University of Lahore"
            }
        }
    }
    </script>
    
    <!-- GOOGLE SITE VERIFICATION (Add your actual verification code) -->
    <!-- <meta name="google-site-verification" content="YOUR_ACTUAL_CODE_HERE" /> -->
    
    <!-- FAVICONS -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL; ?>/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL; ?>/assets/images/favicon-16x16.png">
    <link rel="manifest" href='data:application/manifest+json,{"name":"MES%20UOL%20Society","short_name":"MES%20UOL","description":"Official%20portal%20of%20Mechanical%20Engineering%20Society%2C%20University%20of%20Lahore","start_url":"https://mesuol.xo.je/mes-society/public/","display":"standalone","background_color":"%23ffffff","theme_color":"%23f57c00","icons":[{"src":"https://mesuol.xo.je/mes-society/assets/images/android-chrome-192x192.png","sizes":"192x192","type":"image/png","purpose":"any%20maskable"},{"src":"https://mesuol.xo.je/mes-society/assets/images/android-chrome-512x512.png","sizes":"512x512","type":"image/png","purpose":"any%20maskable"}],"scope":"https://mesuol.xo.je/mes-society/"}'>
    <meta name="msapplication-TileColor" content="#000000">
    <meta name="theme-color" content="#000000">
    
    <!-- EXTERNAL CSS & JS -->
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- CUSTOM CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/custom.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/responsive.css" rel="stylesheet">
    
    <style>
        /* RESET DEFAULT MARGIN/PADDING TO REMOVE EMPTY SPACE ABOVE HEADER */
        html, body {
            margin: 0;
            padding: 0;
        }
        
        :root {
            --primary-color: #000000;
            --secondary-color: #FFFFFF;
            --accent-color: #FF6600;
            --light-gray: #f8f9fa;
            --dark-gray: #6c757d;
        }
        
        /* Enhanced Mobile Header */
        .mobile-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .brand-text {
            flex: 1;
        }
        
        .brand-text h1 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary-color);
            line-height: 1.2;
        }
        
        .brand-text p {
            font-size: 0.8rem;
            margin: 0;
            color: var(--dark-gray);
            line-height: 1.2;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo {
            height: 35px;
            width: auto;
            object-fit: contain;
        }
        
        /* Enhanced Mobile Navigation */
        .mobile-nav-toggler {
            border: none;
            background: transparent;
            font-size: 1.4rem;
            color: var(--primary-color);
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .mobile-nav-toggler .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-nav-toggler:hover {
            background: var(--accent-color);
            color: white;
        }
        
        .mobile-nav-menu {
            position: fixed;
            top: 0;
            left: -320px;
            width: 320px;
            height: 100vh;
            background: white;
            z-index: 1080;
            transition: left 0.3s ease;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .mobile-nav-menu.show {
            left: 0;
        }
        
        .mobile-nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1070;
        }
        
        .mobile-nav-overlay.show {
            display: block;
        }
        
        .mobile-nav-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            background: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .mobile-nav-body {
            padding: 1rem 0;
        }
        
        .mobile-nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--dark-gray);
            text-decoration: none;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .mobile-nav-link:hover,
        .mobile-nav-link.active {
            background: var(--accent-color);
            color: white;
            padding-left: 2rem;
        }
        
        .mobile-nav-link i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .mobile-nav-link .badge {
            margin-left: auto;
        }
        
        /* Enhanced User Section */
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-welcome {
            text-align: right;
        }
        
        .user-welcome .name {
            color: var(--accent-color);
            font-weight: 600;
            font-size: 0.9rem;
            line-height: 1.2;
        }
        
        .user-welcome .role {
            color: var(--dark-gray);
            font-size: 0.8rem;
            line-height: 1.2;
        }
        
        /* Enhanced Desktop Navigation */
        .desktop-nav {
            background: #f8f9fa;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .desktop-nav-link {
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 0 2px;
            position: relative;
        }
        
        .desktop-nav-link:hover {
            background: var(--accent-color);
            color: white;
            transform: translateY(-1px);
        }
        
        .desktop-nav-link .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.6rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .desktop-nav {
                display: none;
            }
            
            .user-welcome {
                display: none;
            }
            
            .brand-text h1 {
                font-size: 1rem;
            }
            
            .brand-text p {
                font-size: 0.75rem;
            }
            
            .logo {
                height: 32px;
            }
            
            .mobile-header {
                padding: 0.5rem 0;
            }
        }
        
        @media (max-width: 575.98px) {
            .brand-text h1 {
                font-size: 0.9rem;
            }
            
            .brand-text p {
                font-size: 0.7rem;
            }
            
            .logo {
                height: 28px;
            }
            
            .mobile-nav-menu {
                width: 280px;
                left: -280px;
            }
        }
        
        @media (min-width: 768px) {
            .mobile-nav-toggler {
                display: none;
            }
            
            .mobile-nav-menu {
                display: none;
            }
            
            .mobile-nav-overlay {
                display: none;
            }
        }
        
        /* Notification dropdown styles */
        .notification-dropdown {
            min-width: 320px;
            max-height: 450px;
            overflow-y: auto;
            padding: 0;
        }
        
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
            text-decoration: none;
            display: block;
            color: #212529;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #fff3e0;
            border-left: 3px solid var(--accent-color);
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            white-space: normal;
            word-break: break-word;
        }
        
        .notification-time {
            font-size: 0.7rem;
            color: #adb5bd;
        }
        
        .dropdown-header {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="brand-section">
                    <div class="logo-container">
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo-mes.png" alt="MES Society - University of Lahore" class="logo">
                    </div>
                    <div class="brand-text">
                        <h1>MES Society</h1>
                        <p>Mechanical Engineering</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <?php if($isLoggedIn): ?>
                        <div class="user-welcome d-none d-md-block">
                            <div class="name">Welcome, <?php echo htmlspecialchars($userName); ?>!</div>
                            <div class="role"><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></div>
                        </div>

                        <!-- Dynamic Notification Dropdown with REAL DATA -->
                        <div class="dropdown d-none d-md-block">
                            <button class="btn btn-outline-secondary position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if($unreadNotifications > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unreadNotifications; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu notification-dropdown" aria-labelledby="notificationDropdown">
                                <li><h6 class="dropdown-header">Notifications (<?php echo $unreadNotifications; ?> unread)</h6></li>
                                <?php if (count($recentNotifications) > 0): ?>
                                    <?php foreach ($recentNotifications as $notif): ?>
                                        <li>
                                            <a class="dropdown-item notification-item unread" 
                                               href="<?php echo SITE_URL; ?>/member/notifications.php">
                                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                                <div class="notification-message"><?php echo htmlspecialchars(substr($notif['message'], 0, 80)) . (strlen($notif['message']) > 80 ? '...' : ''); ?></div>
                                                <div class="notification-time">
                                                    <i class="far fa-clock me-1"></i><?php echo time_elapsed_string($notif['created_at']); ?>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="<?php echo SITE_URL; ?>/member/notifications.php">View All Notifications</a></li>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted text-center">No new notifications</span></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="<?php echo SITE_URL; ?>/member/notifications.php">Go to Notifications</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="dropdown d-none d-md-block">
                            <button class="btn btn-accent dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>Account
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="userDropdown">
                                <?php if($userRole === 'super_admin' ): ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                                    </a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/member/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Member Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/member/profile.php">
                                    <i class="fas fa-user me-2"></i>My Profile
                                </a></li>
                                
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/includes/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/public/login.php" class="btn btn-accent d-none d-md-inline-flex">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    <?php endif; ?>

                    <div class="logo-container d-none d-md-flex">
                        <div class="brand-text text-end">
                            <h1>University of Lahore</h1>
                            <p>Department of ME</p>
                        </div>
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo-university.png" alt="University of Lahore" class="logo">
                    </div>

                    <button class="mobile-nav-toggler d-md-none" type="button" id="mobileNavToggler">
                        <i class="fas fa-bars"></i>
                        <?php if($unreadNotifications > 0): ?>
                            <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Desktop Navigation -->
    <nav class="desktop-nav d-none d-md-block">
        <div class="container">
            <div class="d-flex justify-content-center">
                <a href="<?php echo SITE_URL; ?>/public/" class="desktop-nav-link">Home</a>
                <a href="<?php echo SITE_URL; ?>/public/about.php" class="desktop-nav-link">About</a>
                <a href="<?php echo SITE_URL; ?>/public/events.php" class="desktop-nav-link">Events</a>
                <a href="<?php echo SITE_URL; ?>/public/team.php" class="desktop-nav-link">Team</a>
                <a href="<?php echo SITE_URL; ?>/public/gallery.php" class="desktop-nav-link">Gallery</a>
                <a href="<?php echo SITE_URL; ?>/public/certificates.php" class="desktop-nav-link">Certificates</a>
                <a href="<?php echo SITE_URL; ?>/public/competitions.php" class="desktop-nav-link">Competitions</a>
                <a href="<?php echo SITE_URL; ?>/public/apply.php" class="desktop-nav-link">Join Us</a>
                <a href="<?php echo SITE_URL; ?>/public/contact.php" class="desktop-nav-link">Contact</a>
                <a href="<?php echo SITE_URL; ?>/public/download-app.php" class="desktop-nav-link">Download App</a>
            </div>
        </div>
    </nav>

    <!-- Mobile Navigation Menu (fully preserved with original empty links) -->
    <div class="mobile-nav-menu d-md-none" id="mobileNavMenu">
        <div class="mobile-nav-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Menu</h5>
                <button class="mobile-nav-toggler text-white" type="button" id="mobileNavClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="mobile-nav-body">
            <a href="<?php echo SITE_URL; ?>/public/" class="mobile-nav-link">
                <i class="fas fa-home"></i>Home
            </a>
            <a href="<?php echo SITE_URL; ?>/public/about.php" class="mobile-nav-link">
                <i class="fas fa-info-circle"></i>About
            </a>
            <a href="<?php echo SITE_URL; ?>/public/events.php" class="mobile-nav-link">
                <i class="fas fa-calendar"></i>Events
            </a>
            <a href="<?php echo SITE_URL; ?>/public/team.php" class="mobile-nav-link">
                <i class="fas fa-users"></i>Team
            </a>
            <a href="<?php echo SITE_URL; ?>/public/gallery.php" class="mobile-nav-link">
                <i class="fas fa-images"></i>Gallery
            </a>
            <a href="<?php echo SITE_URL; ?>/public/certificates.php" class="mobile-nav-link">
                <i class="fas fa-certificate me-2"></i>Certificates
            </a>
            <a href="<?php echo SITE_URL; ?>/public/competitions.php" class="mobile-nav-link">
                <i class="fas fa-trophy"></i>Competitions
            </a>
            <a href="<?php echo SITE_URL; ?>/public/apply.php" class="mobile-nav-link">
                <i class="fas fa-user-plus"></i>Join Us
            </a>
            <a href="<?php echo SITE_URL; ?>/public/contact.php" class="mobile-nav-link">
                <i class="fas fa-envelope"></i>Contact
            </a>
            
            <a href="<?php echo SITE_URL; ?>/public/download-app.php" class="mobile-nav-link">
                <i class="fas fa-download me-2"></i>Download App
            </a>
            
            <?php if($isLoggedIn): ?>
                <div class="border-top mt-3 pt-3">
                    <div class="mobile-nav-link text-muted">
                        <i class="fas fa-user"></i>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($userName); ?></div>
                            <small><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></small>
                        </div>
                    </div>
                    <a href="<?php echo $userRole === 'super_admin' ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/member/dashboard.php'; ?>" class="mobile-nav-link">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a href="<?php echo SITE_URL; ?>/member/profile.php" class="mobile-nav-link">
                        <i class="fas fa-user"></i>My Profile
                    </a>
                    <a href="<?php echo SITE_URL; ?>/member/notifications.php" class="mobile-nav-link">
                        <i class="fas fa-bell me-2"></i>Notifications
                        <?php if($unreadNotifications > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/includes/logout.php" class="mobile-nav-link text-danger">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                    <!-- Preserved empty link from original -->
                    <a href="#" class="mobile-nav-link text-danger">
                        <i></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="border-top mt-3 pt-3">
                    <a href="<?php echo SITE_URL; ?>/public/login.php" class="mobile-nav-link">
                        <i class="fas fa-sign-in-alt"></i>Member Login
                    </a>
                    <!-- Preserved empty link from original -->
                    <a href="#" class="mobile-nav-link text-danger">
                        <i></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNavToggler = document.getElementById('mobileNavToggler');
            const mobileNavClose = document.getElementById('mobileNavClose');
            const mobileNavMenu = document.getElementById('mobileNavMenu');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            if (mobileNavToggler && mobileNavMenu) {
                mobileNavToggler.addEventListener('click', function() {
                    mobileNavMenu.classList.add('show');
                    mobileNavOverlay.classList.add('show');
                    document.body.style.overflow = 'hidden';
                });
                
                function closeMobileMenu() {
                    mobileNavMenu.classList.remove('show');
                    mobileNavOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
                
                if (mobileNavClose) {
                    mobileNavClose.addEventListener('click', closeMobileMenu);
                }
                
                mobileNavOverlay.addEventListener('click', closeMobileMenu);
                
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && mobileNavMenu.classList.contains('show')) {
                        closeMobileMenu();
                    }
                });
                
                const mobileNavLinks = mobileNavMenu.querySelectorAll('.mobile-nav-link');
                mobileNavLinks.forEach(link => {
                    link.addEventListener('click', closeMobileMenu);
                });
            }
        });
    </script>
</body>
</html>