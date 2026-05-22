<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$page_title = "Reports & Analytics";
require_once '../includes/header.php';

$db = Database::getInstance()->getConnection();

// Date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'overview';

// Get report data based on type
$reportData = [];
$chartData = [];

try {
    switch($report_type) {
        case 'membership':
            // Membership growth
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM users 
                WHERE created_at BETWEEN ? AND ? 
                GROUP BY DATE(created_at) 
                ORDER BY date
            ");
            $stmt->execute([$start_date, $end_date]);
            $reportData = $stmt->fetchAll();
            
            // Prepare chart data
            $chartData['labels'] = [];
            $chartData['values'] = [];
            foreach($reportData as $row) {
                $chartData['labels'][] = date('M j', strtotime($row['date']));
                $chartData['values'][] = $row['count'];
            }
            break;
            
        case 'events':
            // Event statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_events,
                    SUM(CASE WHEN start_date >= NOW() THEN 1 ELSE 0 END) as upcoming_events,
                    COALESCE(AVG(participant_count), 0) as avg_participants
                FROM (
                    SELECT e.*, 
                    (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as participant_count
                    FROM events e 
                    WHERE e.created_at BETWEEN ? AND ?
                ) as event_stats
            ");
            $stmt->execute([$start_date, $end_date]);
            $reportData = $stmt->fetch();
            break;
            
        case 'applications':
            // Application statistics
            $stmt = $db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM applications WHERE applied_at BETWEEN ? AND ?), 2) as percentage
                FROM applications 
                WHERE applied_at BETWEEN ? AND ?
                GROUP BY status
                ORDER BY count DESC
            ");
            $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
            $reportData = $stmt->fetchAll();
            
            // Prepare chart data
            $chartData['labels'] = [];
            $chartData['values'] = [];
            foreach($reportData as $row) {
                $chartData['labels'][] = ucfirst(str_replace('_', ' ', $row['status']));
                $chartData['values'][] = $row['count'];
            }
            break;

        case 'competitions':
            // Competitions overview
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_competitions,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_competitions,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_competitions,
                    SUM(CASE WHEN registration_deadline >= NOW() AND status = 'published' THEN 1 ELSE 0 END) as open_registrations,
                    SUM(CASE WHEN registration_deadline < NOW() THEN 1 ELSE 0 END) as closed_registrations
                FROM competitions 
                WHERE created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $reportData = $stmt->fetch();

            // Also get top 5 competitions by registrations (for bar chart)
            $stmt = $db->prepare("
                SELECT 
                    c.title,
                    COUNT(cr.id) as registration_count
                FROM competitions c
                LEFT JOIN competition_registrations cr ON c.id = cr.competition_id
                WHERE c.created_at BETWEEN ? AND ?
                GROUP BY c.id
                ORDER BY registration_count DESC
                LIMIT 5
            ");
            $stmt->execute([$start_date, $end_date]);
            $topCompetitions = $stmt->fetchAll();
            $chartData['labels'] = array_column($topCompetitions, 'title');
            $chartData['values'] = array_column($topCompetitions, 'registration_count');
            break;

        case 'competition_registrations':
            // Competition registrations analysis
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_registrations,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_registrations,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_registrations,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_registrations
                FROM competition_registrations 
                WHERE registration_date BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $reportData = $stmt->fetch();

            // Status distribution for doughnut chart
            $chartData['labels'] = ['Approved', 'Rejected', 'Pending'];
            $chartData['values'] = [
                $reportData['approved_registrations'],
                $reportData['rejected_registrations'],
                $reportData['pending_registrations']
            ];

            // Also get registrations per competition (for detailed table)
            $stmt = $db->prepare("
                SELECT 
                    c.title,
                    COUNT(cr.id) as registration_count,
                    SUM(CASE WHEN cr.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN cr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN cr.status = 'pending' THEN 1 ELSE 0 END) as pending_count
                FROM competitions c
                LEFT JOIN competition_registrations cr ON c.id = cr.competition_id AND cr.registration_date BETWEEN ? AND ?
                WHERE c.created_at BETWEEN ? AND ?
                GROUP BY c.id
                ORDER BY registration_count DESC
            ");
            $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
            $competitionRegistrations = $stmt->fetchAll();
            break;
            
        case 'overview':
        default:
            // Overview statistics (including competition stats)
            $reportData = [
                'total_members' => 0,
                'active_members' => 0,
                'total_events' => 0,
                'upcoming_events' => 0,
                'pending_applications' => 0,
                'total_applications' => 0,
                'total_competitions' => 0,
                'total_registrations' => 0,
                'pending_registrations' => 0,
                'approved_registrations' => 0
            ];
            
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role != 'public'");
            $reportData['total_members'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND role != 'public'");
            $reportData['active_members'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM events");
            $reportData['total_events'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM events WHERE start_date >= NOW() AND status = 'published'");
            $reportData['upcoming_events'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
            $reportData['pending_applications'] = $stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM applications");
            $reportData['total_applications'] = $stmt->fetchColumn();

            // Competition stats
            $stmt = $db->query("SELECT COUNT(*) FROM competitions");
            $reportData['total_competitions'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM competition_registrations");
            $reportData['total_registrations'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM competition_registrations WHERE status = 'pending'");
            $reportData['pending_registrations'] = $stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM competition_registrations WHERE status = 'approved'");
            $reportData['approved_registrations'] = $stmt->fetchColumn();
            break;
    }
} catch(PDOException $e) {
    error_log("Reports query error: " . $e->getMessage());
    // Optionally set a session error message
    $_SESSION['error'] = "An error occurred while generating the report. Please try again.";
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Desktop Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Admin Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Mobile Header Bar -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">Reports & Analytics</h4>
                    <small class="text-muted">Data Analysis</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Reports & Analytics</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="export-report.php?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=csv">Export as CSV</a></li>
                        <li><a class="dropdown-item" href="export-report.php?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=excel">Export as Excel</a></li>
                    </ul>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter me-2"></i>Report Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" id="report_type" name="report_type">
                                <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                                <option value="membership" <?php echo $report_type === 'membership' ? 'selected' : ''; ?>>Membership Growth</option>
                                <option value="events" <?php echo $report_type === 'events' ? 'selected' : ''; ?>>Event Statistics</option>
                                <option value="applications" <?php echo $report_type === 'applications' ? 'selected' : ''; ?>>Application Analysis</option>
                                <option value="competitions" <?php echo $report_type === 'competitions' ? 'selected' : ''; ?>>Competitions Overview</option>
                                <option value="competition_registrations" <?php echo $report_type === 'competition_registrations' ? 'selected' : ''; ?>>Competition Registrations</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-accent w-100">
                                    <i class="fas fa-chart-bar me-2"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Display any session error -->
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Report Content -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        <?php 
                        switch($report_type) {
                            case 'membership': echo 'Membership Growth Report'; break;
                            case 'events': echo 'Event Statistics Report'; break;
                            case 'applications': echo 'Application Analysis Report'; break;
                            case 'competitions': echo 'Competitions Overview Report'; break;
                            case 'competition_registrations': echo 'Competition Registrations Analysis'; break;
                            default: echo 'System Overview Report'; break;
                        }
                        ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if($report_type === 'overview'): ?>
                        <!-- Overview Report with Doughnut Graph -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_members']; ?></h2>
                                        <h5 class="card-title">Total Members</h5>
                                        <small><?php echo $reportData['active_members']; ?> active</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_events']; ?></h2>
                                        <h5 class="card-title">Total Events</h5>
                                        <small><?php echo $reportData['upcoming_events']; ?> upcoming</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_applications']; ?></h2>
                                        <h5 class="card-title">Total Applications</h5>
                                        <small><?php echo $reportData['pending_applications']; ?> pending</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_competitions']; ?></h2>
                                        <h5 class="card-title">Total Competitions</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-accent text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_registrations']; ?></h2>
                                        <h5 class="card-title">Competition Registrations</h5>
                                        <small><?php echo $reportData['approved_registrations']; ?> approved, <?php echo $reportData['pending_registrations']; ?> pending</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Doughnut Chart Row -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">System Composition</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container" style="height: 250px;">
                                            <canvas id="overviewSummaryChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Quick Stats</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $total_items = $reportData['total_members'] + $reportData['total_events'] + $reportData['total_competitions'] + $reportData['total_applications'];
                                        if ($total_items > 0) {
                                            $members_pct = round(($reportData['total_members'] / $total_items) * 100, 1);
                                            $events_pct = round(($reportData['total_events'] / $total_items) * 100, 1);
                                            $competitions_pct = round(($reportData['total_competitions'] / $total_items) * 100, 1);
                                            $applications_pct = round(($reportData['total_applications'] / $total_items) * 100, 1);
                                        } else {
                                            $members_pct = $events_pct = $competitions_pct = $applications_pct = 0;
                                        }
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-users text-primary me-2"></i> Members</span>
                                                <span><strong><?php echo $reportData['total_members']; ?></strong> (<?php echo $members_pct; ?>%)</span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-calendar-alt text-success me-2"></i> Events</span>
                                                <span><strong><?php echo $reportData['total_events']; ?></strong> (<?php echo $events_pct; ?>%)</span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-trophy text-info me-2"></i> Competitions</span>
                                                <span><strong><?php echo $reportData['total_competitions']; ?></strong> (<?php echo $competitions_pct; ?>%)</span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-alt text-warning me-2"></i> Applications</span>
                                                <span><strong><?php echo $reportData['total_applications']; ?></strong> (<?php echo $applications_pct; ?>%)</span>
                                            </div>
                                        </div>
                                        <hr>
                                        <p class="text-muted mb-0 small">Total items: <?php echo $total_items; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity and System Health -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Recent Activity</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $recentActivity = [];
                                        try {
                                            $stmt = $db->query("
                                                (SELECT 'event' as type, title, created_at FROM events ORDER BY created_at DESC LIMIT 3)
                                                UNION ALL
                                                (SELECT 'application' as type, CONCAT('Application from ', COALESCE(name, 'Unknown User')) as title, applied_at as created_at FROM applications a LEFT JOIN users u ON a.user_id = u.id ORDER BY applied_at DESC LIMIT 3)
                                                UNION ALL
                                                (SELECT 'user' as type, CONCAT('New member: ', name) as title, created_at FROM users WHERE role != 'public' ORDER BY created_at DESC LIMIT 3)
                                                UNION ALL
                                                (SELECT 'competition' as type, CONCAT('New competition: ', title) as title, created_at FROM competitions ORDER BY created_at DESC LIMIT 3)
                                                UNION ALL
                                                (SELECT 'registration' as type, CONCAT('New registration for competition ', c.title) as title, cr.registration_date as created_at FROM competition_registrations cr JOIN competitions c ON cr.competition_id = c.id ORDER BY cr.registration_date DESC LIMIT 3)
                                                ORDER BY created_at DESC LIMIT 5
                                            ");
                                            $recentActivity = $stmt->fetchAll();
                                        } catch(PDOException $e) {
                                            error_log("Recent activity error: " . $e->getMessage());
                                        }
                                        ?>
                                        
                                        <?php if(empty($recentActivity)): ?>
                                            <p class="text-muted">No recent activity</p>
                                        <?php else: ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach($recentActivity as $activity): ?>
                                                    <div class="list-group-item px-0">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-<?php 
                                                                    switch($activity['type']) {
                                                                        case 'event': echo 'calendar'; break;
                                                                        case 'application': echo 'file-alt'; break;
                                                                        case 'user': echo 'user-plus'; break;
                                                                        case 'competition': echo 'trophy'; break;
                                                                        case 'registration': echo 'user-check'; break;
                                                                        default: echo 'circle';
                                                                    }
                                                                ?> me-2 text-<?php 
                                                                    switch($activity['type']) {
                                                                        case 'event': echo 'primary'; break;
                                                                        case 'application': echo 'warning'; break;
                                                                        case 'user': echo 'success'; break;
                                                                        case 'competition': echo 'info'; break;
                                                                        case 'registration': echo 'secondary'; break;
                                                                        default: echo 'secondary';
                                                                    }
                                                                ?>"></i>
                                                                <?php echo htmlspecialchars($activity['title'] ?? ''); ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?php echo date('M j', strtotime($activity['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">System Health</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $systemHealth = [
                                            'database' => 'Healthy',
                                            'storage' => 'Normal',
                                            'performance' => 'Good'
                                        ];
                                        
                                        // Check storage usage
                                        $uploadPath = '../uploads/';
                                        $totalSize = 0;
                                        if (is_dir($uploadPath)) {
                                            $files = new RecursiveIteratorIterator(
                                                new RecursiveDirectoryIterator($uploadPath)
                                            );
                                            foreach ($files as $file) {
                                                if ($file->isFile()) {
                                                    $totalSize += $file->getSize();
                                                }
                                            }
                                        }
                                        $totalSizeMB = round($totalSize / (1024 * 1024), 2);
                                        
                                        if ($totalSizeMB > 500) {
                                            $systemHealth['storage'] = 'Warning';
                                        } elseif ($totalSizeMB > 1000) {
                                            $systemHealth['storage'] = 'Critical';
                                        }
                                        ?>
                                        
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-database me-2 text-primary"></i>
                                                    Database
                                                </span>
                                                <span class="badge bg-success"><?php echo $systemHealth['database']; ?></span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-hdd me-2 text-primary"></i>
                                                    Storage Usage
                                                </span>
                                                <span class="badge bg-<?php 
                                                    echo $systemHealth['storage'] === 'Normal' ? 'success' : 
                                                         ($systemHealth['storage'] === 'Warning' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $totalSizeMB; ?> MB
                                                </span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                                                    Performance
                                                </span>
                                                <span class="badge bg-success"><?php echo $systemHealth['performance']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif($report_type === 'membership'): ?>
                        <!-- Membership Growth Report -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Membership Growth Chart</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container" style="height: 250px;">
                                            <canvas id="membershipChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Statistics</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $totalNewMembers = array_sum(array_column($reportData, 'count'));
                                        $avgDailyGrowth = $totalNewMembers > 0 ? round($totalNewMembers / count($reportData), 2) : 0;
                                        ?>
                                        <div class="text-center">
                                            <h3 class="text-primary"><?php echo $totalNewMembers; ?></h3>
                                            <p class="text-muted">New Members</p>
                                            <hr>
                                            <p><strong>Average Daily:</strong> <?php echo $avgDailyGrowth; ?></p>
                                            <p><strong>Period:</strong> <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif($report_type === 'events'): ?>
                        <!-- Event Statistics Report -->
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_events'] ?? 0; ?></h2>
                                        <h6 class="card-title">Total Events</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['published_events'] ?? 0; ?></h2>
                                        <h6 class="card-title">Published</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['upcoming_events'] ?? 0; ?></h2>
                                        <h6 class="card-title">Upcoming</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo round($reportData['avg_participants'] ?? 0, 1); ?></h2>
                                        <h6 class="card-title">Avg Participants</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif($report_type === 'applications'): ?>
                        <!-- Application Analysis Report -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Application Status Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container" style="height: 250px;">
                                            <canvas id="applicationsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Application Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <?php foreach($reportData as $app): ?>
                                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <span class="badge bg-<?php 
                                                            switch($app['status']) {
                                                                case 'pending': echo 'warning'; break;
                                                                case 'under_review': echo 'info'; break;
                                                                case 'interview_scheduled': echo 'primary'; break;
                                                                case 'selected': echo 'success'; break;
                                                                case 'rejected': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?> me-2">●</span>
                                                        <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                                    </span>
                                                    <span>
                                                        <strong><?php echo $app['count']; ?></strong>
                                                        <small class="text-muted">(<?php echo $app['percentage']; ?>%)</small>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif($report_type === 'competitions'): ?>
                        <!-- Competitions Overview Report -->
                        <div class="row mb-5">
                            <div class="col-md-3 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_competitions'] ?? 0; ?></h2>
                                        <h6 class="card-title">Total Competitions</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['published_competitions'] ?? 0; ?></h2>
                                        <h6 class="card-title">Published</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['open_registrations'] ?? 0; ?></h2>
                                        <h6 class="card-title">Open Registrations</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-secondary text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['closed_registrations'] ?? 0; ?></h2>
                                        <h6 class="card-title">Closed Registrations</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Competitions by Registrations -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Top Competitions by Registrations</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if(empty($chartData['labels']) || array_sum($chartData['values']) == 0): ?>
                                            <p class="text-muted text-center">No registration data available for the selected period.</p>
                                        <?php else: ?>
                                            <div class="chart-container" style="height: 300px;">
                                                <canvas id="topCompetitionsChart"></canvas>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif($report_type === 'competition_registrations'): ?>
                        <!-- Competition Registrations Analysis -->
                        <div class="row mb-5">
                            <div class="col-md-3 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['total_registrations'] ?? 0; ?></h2>
                                        <h6 class="card-title">Total Registrations</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['approved_registrations'] ?? 0; ?></h2>
                                        <h6 class="card-title">Approved</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['rejected_registrations'] ?? 0; ?></h2>
                                        <h6 class="card-title">Rejected</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h2 class="card-text"><?php echo $reportData['pending_registrations'] ?? 0; ?></h2>
                                        <h6 class="card-title">Pending</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Distribution Doughnut Chart -->
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Registration Status Distribution</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container" style="height: 250px;">
                                            <canvas id="regStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Status Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $total = $reportData['total_registrations'] ?? 0;
                                        $approved = $reportData['approved_registrations'] ?? 0;
                                        $rejected = $reportData['rejected_registrations'] ?? 0;
                                        $pending = $reportData['pending_registrations'] ?? 0;
                                        $approved_pct = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
                                        $rejected_pct = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;
                                        $pending_pct = $total > 0 ? round(($pending / $total) * 100, 1) : 0;
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-check-circle text-success me-2"></i> Approved</span>
                                                <span><strong><?php echo $approved; ?></strong> (<?php echo $approved_pct; ?>%)</span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-times-circle text-danger me-2"></i> Rejected</span>
                                                <span><strong><?php echo $rejected; ?></strong> (<?php echo $rejected_pct; ?>%)</span>
                                            </div>
                                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-clock text-warning me-2"></i> Pending</span>
                                                <span><strong><?php echo $pending; ?></strong> (<?php echo $pending_pct; ?>%)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Registrations per Competition Table -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Registrations per Competition</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if(empty($competitionRegistrations)): ?>
                                            <p class="text-muted text-center">No registration data available for the selected period.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Competition</th>
                                                            <th>Total Registrations</th>
                                                            <th>Approved</th>
                                                            <th>Rejected</th>
                                                            <th>Pending</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($competitionRegistrations as $comp): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($comp['title']); ?></td>
                                                                <td><?php echo $comp['registration_count']; ?></td>
                                                                <td class="text-success"><?php echo $comp['approved_count']; ?></td>
                                                                <td class="text-danger"><?php echo $comp['rejected_count']; ?></td>
                                                                <td class="text-warning"><?php echo $comp['pending_count']; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Membership Chart
<?php if($report_type === 'membership' && !empty($chartData)): ?>
const membershipCtx = document.getElementById('membershipChart').getContext('2d');
new Chart(membershipCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartData['labels']); ?>,
        datasets: [{
            label: 'New Members',
            data: <?php echo json_encode($chartData['values']); ?>,
            borderColor: '#FF6600',
            backgroundColor: 'rgba(255, 102, 0, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Applications Doughnut Chart
<?php if($report_type === 'applications' && !empty($chartData)): ?>
const applicationsCtx = document.getElementById('applicationsChart').getContext('2d');
new Chart(applicationsCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($chartData['labels']); ?>,
        datasets: [{
            data: <?php echo json_encode($chartData['values']); ?>,
            backgroundColor: [
                '#FFC107', // pending - yellow
                '#17A2B8', // under_review - teal
                '#007BFF', // interview_scheduled - blue
                '#28A745', // selected - green
                '#DC3545'  // rejected - red
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Top Competitions Bar Chart
<?php if($report_type === 'competitions' && !empty($chartData['labels']) && array_sum($chartData['values']) > 0): ?>
const topCompetitionsCtx = document.getElementById('topCompetitionsChart').getContext('2d');
new Chart(topCompetitionsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartData['labels']); ?>,
        datasets: [{
            label: 'Registrations',
            data: <?php echo json_encode($chartData['values']); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Competition Registrations Status Doughnut Chart
<?php if($report_type === 'competition_registrations' && !empty($chartData['values']) && array_sum($chartData['values']) > 0): ?>
const regStatusCtx = document.getElementById('regStatusChart').getContext('2d');
new Chart(regStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($chartData['labels']); ?>,
        datasets: [{
            data: <?php echo json_encode($chartData['values']); ?>,
            backgroundColor: ['#28A745', '#DC3545', '#FFC107'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Overview Summary Doughnut Chart
<?php if($report_type === 'overview'): ?>
const overviewCtx = document.getElementById('overviewSummaryChart').getContext('2d');
new Chart(overviewCtx, {
    type: 'doughnut',
    data: {
        labels: ['Members', 'Events', 'Competitions', 'Applications'],
        datasets: [{
            data: [
                <?php echo $reportData['total_members']; ?>,
                <?php echo $reportData['total_events']; ?>,
                <?php echo $reportData['total_competitions']; ?>,
                <?php echo $reportData['total_applications']; ?>
            ],
            backgroundColor: [
                '#007BFF',  // members - blue
                '#28A745',  // events - green
                '#17A2B8',  // competitions - teal
                '#FFC107'   // applications - yellow
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>
</script>

<style>
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}

/* Offcanvas sidebar styling */
.offcanvas-header {
    background: var(--primary-color);
    color: white;
}

.offcanvas-body {
    padding: 0;
}

.offcanvas-body .nav {
    padding: 1rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>