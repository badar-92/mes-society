<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
$userRole = $isLoggedIn ? $_SESSION['user_role'] : '';

// Get unread notifications count
$unreadNotifications = 0;
if ($isLoggedIn) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotifications = $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log("Notification count error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Favicon Generated from favicon.io -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL; ?>/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL; ?>/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL; ?>/assets/images/favicon-16x16.png">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/assets/images/site.webmanifest">
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">
    <meta name="msapplication-TileColor" content="#000000">
    <meta name="theme-color" content="#000000">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/custom.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/responsive.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    
    <style>
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
            
            /* Add padding to body to account for fixed header */
            body {
                padding-top: 70px;
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
            min-width: 300px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.3s ease;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #f0f8ff;
        }
        
        .notification-item .notification-time {
            font-size: 0.75rem;
            color: var(--dark-gray);
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Left: Brand & Logo -->
                <div class="brand-section">
                    <div class="logo-container">
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo-mes.png" alt="MES Society" class="logo">
                    </div>
                    <div class="brand-text">
                        <h1>MES Society</h1>
                        <p>Mechanical Engineering</p>
                    </div>
                </div>

                <!-- Right: User Info & Mobile Menu Toggle -->
                <div class="d-flex align-items-center gap-3">
                    <!-- User Welcome (Desktop) -->
                    <?php if($isLoggedIn): ?>
                        <div class="user-welcome d-none d-md-block">
                            <div class="name">Welcome, <?php echo $userName; ?>!</div>
                            <div class="role"><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></div>
                        </div>

                        <!-- Notification Bell (Desktop) -->
                        <div class="dropdown d-none d-md-block">
                            <button class="btn btn-outline-secondary position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php if($unreadNotifications > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unreadNotifications; ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu notification-dropdown" aria-labelledby="notificationDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if($unreadNotifications > 0): ?>
                                    <li><a class="dropdown-item notification-item unread" href="#">
                                        <div class="d-flex w-100 justify-content-between">
                                            <strong>New Event</strong>
                                            <small class="notification-time">2 min ago</small>
                                        </div>
                                        <p class="mb-1 small">New workshop announced</p>
                                    </a></li>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted">No new notifications</span></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="#">View All Notifications</a></li>
                            </ul>
                        </div>

                        <!-- User Dropdown (Desktop) -->
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

                    <!-- University Logo -->
                    <div class="logo-container d-none d-md-flex">
                        <div class="brand-text text-end">
                            <h1>University of Lahore</h1>
                            <p>Department of ME</p>
                        </div>
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo-university.png" alt="University of Lahore" class="logo">
                    </div>

                    <!-- Mobile Menu Toggle -->
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
                    <?php if($unreadNotifications > 0): ?>
                        <span class="badge bg-danger"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/public/team.php" class="desktop-nav-link">Team</a>
                <a href="<?php echo SITE_URL; ?>/public/gallery.php" class="desktop-nav-link">Gallery</a>
                <a href="<?php echo SITE_URL; ?>/public/competitions.php" class="desktop-nav-link">Competitions</a>
                <a href="<?php echo SITE_URL; ?>/public/apply.php" class="desktop-nav-link">Join Us</a>
                <a href="<?php echo SITE_URL; ?>/public/contact.php" class="desktop-nav-link">Contact</a>
            </div>
        </div>
    </nav>

    <!-- Mobile Navigation Menu -->
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
            <!-- Navigation Links -->
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
            <a href="<?php echo SITE_URL; ?>/public/competitions.php" class="mobile-nav-link">
                <i class="fas fa-trophy"></i>Competitions
            </a>
            <a href="<?php echo SITE_URL; ?>/public/apply.php" class="mobile-nav-link">
                <i class="fas fa-user-plus"></i>Join Us
            </a>
            <a href="<?php echo SITE_URL; ?>/public/contact.php" class="mobile-nav-link">
                <i class="fas fa-envelope"></i>Contact
            </a>
            
            <!-- User Section for Mobile -->
            <?php if($isLoggedIn): ?>
                <div class="border-top mt-3 pt-3">
                    <div class="mobile-nav-link text-muted">
                        <i class="fas fa-user"></i>
                        <div>
                            <div class="fw-bold"><?php echo $userName; ?></div>
                            <small><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></small>
                        </div>
                    </div>
                    <a href="<?php echo $userRole === 'super_admin' || $userRole === 'department_head' || strpos($userRole, 'head') !== false ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/member/dashboard.php'; ?>" class="mobile-nav-link">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a href="<?php echo SITE_URL; ?>/member/profile.php" class="mobile-nav-link">
                        <i class="fas fa-user"></i>My Profile
                    </a>
                    <a href="<?php echo SITE_URL; ?>/includes/logout.php" class="mobile-nav-link text-danger">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </div>
            <?php else: ?>
                <div class="border-top mt-3 pt-3">
                    <a href="<?php echo SITE_URL; ?>/public/login.php" class="mobile-nav-link">
                        <i class="fas fa-sign-in-alt"></i>Member Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Navigation Overlay -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

    <!-- Mobile Navigation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNavToggler = document.getElementById('mobileNavToggler');
            const mobileNavClose = document.getElementById('mobileNavClose');
            const mobileNavMenu = document.getElementById('mobileNavMenu');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            
            if (mobileNavToggler && mobileNavMenu) {
                // Open mobile menu
                mobileNavToggler.addEventListener('click', function() {
                    mobileNavMenu.classList.add('show');
                    mobileNavOverlay.classList.add('show');
                    document.body.style.overflow = 'hidden';
                });
                
                // Close mobile menu
                function closeMobileMenu() {
                    mobileNavMenu.classList.remove('show');
                    mobileNavOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
                
                // Close button
                if (mobileNavClose) {
                    mobileNavClose.addEventListener('click', closeMobileMenu);
                }
                
                // Overlay click
                mobileNavOverlay.addEventListener('click', closeMobileMenu);
                
                // Close on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && mobileNavMenu.classList.contains('show')) {
                        closeMobileMenu();
                    }
                });
                
                // Close when clicking nav links
                const mobileNavLinks = mobileNavMenu.querySelectorAll('.mobile-nav-link');
                mobileNavLinks.forEach(link => {
                    link.addEventListener('click', closeMobileMenu);
                });
            }
        });
    </script>