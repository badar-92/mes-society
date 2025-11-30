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
            // Event statistics - FIXED: Handle null values for average participants
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
            
        case 'overview':
        default:
            // Overview statistics
            $reportData = [
                'total_members' => 0,
                'active_members' => 0,
                'total_events' => 0,
                'upcoming_events' => 0,
                'pending_applications' => 0,
                'total_applications' => 0
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
            break;
    }
} catch(PDOException $e) {
    error_log("Reports query error: " . $e->getMessage());
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
                        <li><a class="dropdown-item" href="export-report.php?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=pdf">Export as PDF</a></li>
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
                            default: echo 'System Overview Report'; break;
                        }
                        ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if($report_type === 'overview'): ?>
                        <!-- Overview Report -->
                        <div class="row mb-5">
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

                        <!-- Quick Stats -->
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
                                                                        default: echo 'circle';
                                                                    }
                                                                ?> me-2 text-<?php 
                                                                    switch($activity['type']) {
                                                                        case 'event': echo 'primary'; break;
                                                                        case 'application': echo 'warning'; break;
                                                                        case 'user': echo 'success'; break;
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize charts
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