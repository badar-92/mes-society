<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

// Get parameters
$report_type = $_GET['type'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$format = $_GET['format'] ?? 'csv';

// Validate format
$allowed_formats = ['csv', 'excel', 'pdf'];
if (!in_array($format, $allowed_formats)) {
    die('Invalid export format.');
}

// Fetch data based on report type
$data = [];
$title = '';
$headers = [];
$rows = [];

try {
    switch($report_type) {
        case 'overview':
            $title = "System Overview Report";
            // We'll just export summary stats, not a table. For overview, we can export the cards data.
            $stmt = $db->query("SELECT COUNT(*) as total_members FROM users WHERE role != 'public'");
            $total_members = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as active_members FROM users WHERE status = 'active' AND role != 'public'");
            $active_members = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as total_events FROM events");
            $total_events = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as upcoming_events FROM events WHERE start_date >= NOW() AND status = 'published'");
            $upcoming_events = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as pending_applications FROM applications WHERE status = 'pending'");
            $pending_applications = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as total_applications FROM applications");
            $total_applications = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as total_competitions FROM competitions");
            $total_competitions = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as total_registrations FROM competition_registrations");
            $total_registrations = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as pending_registrations FROM competition_registrations WHERE status = 'pending'");
            $pending_registrations = $stmt->fetchColumn();
            $stmt = $db->query("SELECT COUNT(*) as approved_registrations FROM competition_registrations WHERE status = 'approved'");
            $approved_registrations = $stmt->fetchColumn();

            $headers = ['Metric', 'Value'];
            $rows = [
                ['Total Members', $total_members],
                ['Active Members', $active_members],
                ['Total Events', $total_events],
                ['Upcoming Events', $upcoming_events],
                ['Total Applications', $total_applications],
                ['Pending Applications', $pending_applications],
                ['Total Competitions', $total_competitions],
                ['Total Competition Registrations', $total_registrations],
                ['Approved Registrations', $approved_registrations],
                ['Pending Registrations', $pending_registrations],
            ];
            break;

        case 'membership':
            $title = "Membership Growth Report ($start_date to $end_date)";
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM users 
                WHERE created_at BETWEEN ? AND ? 
                GROUP BY DATE(created_at) 
                ORDER BY date
            ");
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll();
            $headers = ['Date', 'New Members'];
            $rows = [];
            foreach ($data as $row) {
                $rows[] = [date('Y-m-d', strtotime($row['date'])), $row['count']];
            }
            break;

        case 'events':
            $title = "Event Statistics Report ($start_date to $end_date)";
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
            $data = $stmt->fetch();
            $headers = ['Metric', 'Value'];
            $rows = [
                ['Total Events', $data['total_events']],
                ['Published Events', $data['published_events']],
                ['Upcoming Events', $data['upcoming_events']],
                ['Average Participants', round($data['avg_participants'], 2)]
            ];
            break;

        case 'applications':
            $title = "Application Analysis Report ($start_date to $end_date)";
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
            $data = $stmt->fetchAll();
            $headers = ['Status', 'Count', 'Percentage (%)'];
            $rows = [];
            foreach ($data as $row) {
                $rows[] = [ucfirst(str_replace('_', ' ', $row['status'])), $row['count'], $row['percentage']];
            }
            break;

        case 'competitions':
            $title = "Competitions Overview Report ($start_date to $end_date)";
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
            $data = $stmt->fetch();
            $headers = ['Metric', 'Value'];
            $rows = [
                ['Total Competitions', $data['total_competitions']],
                ['Published', $data['published_competitions']],
                ['Draft', $data['draft_competitions']],
                ['Open Registrations', $data['open_registrations']],
                ['Closed Registrations', $data['closed_registrations']]
            ];

            // Also get top competitions
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
            if (!empty($topCompetitions)) {
                $headers[] = 'Top Competitions';
                $rows[] = ['--- Top Competitions by Registrations ---', ''];
                foreach ($topCompetitions as $comp) {
                    $rows[] = [$comp['title'], $comp['registration_count']];
                }
            }
            break;

        case 'competition_registrations':
            $title = "Competition Registrations Analysis ($start_date to $end_date)";
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
            $data = $stmt->fetch();
            $headers = ['Metric', 'Value'];
            $rows = [
                ['Total Registrations', $data['total_registrations']],
                ['Approved', $data['approved_registrations']],
                ['Rejected', $data['rejected_registrations']],
                ['Pending', $data['pending_registrations']]
            ];

            // Get registrations per competition
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
            if (!empty($competitionRegistrations)) {
                $rows[] = ['', ''];
                $rows[] = ['Registrations per Competition', ''];
                $rows[] = ['Competition', 'Total', 'Approved', 'Rejected', 'Pending'];
                foreach ($competitionRegistrations as $comp) {
                    $rows[] = [$comp['title'], $comp['registration_count'], $comp['approved_count'], $comp['rejected_count'], $comp['pending_count']];
                }
            }
            break;

        default:
            die('Invalid report type.');
    }
} catch(PDOException $e) {
    error_log("Export report error: " . $e->getMessage());
    die('Failed to fetch data for export.');
}

// Output based on format
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_filename($title) . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
} elseif ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . sanitize_filename($title) . '.xls"');
    echo '<html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>th, td { border: 1px solid #ddd; padding: 8px; } th { background: #f2f2f2; }</style>';
    echo '</head><body>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<table border="1">';
    echo '<tr>';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit;
} elseif ($format === 'pdf') {
    // Use PDFGenerator if available
    $html = '<h1>' . htmlspecialchars($title) . '</h1>';
    $html .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0">';
    $html .= '<tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . htmlspecialchars($h) . '</th>';
    }
    $html .= '</tr>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    // Try to use PDFGenerator if exists
    if (file_exists('../includes/pdf-generator/PDFGenerator.php')) {
        require_once '../includes/pdf-generator/PDFGenerator.php';
        $pdfGen = new PDFGenerator();
        $pdfGen->generatePDF($html, sanitize_filename($title));
    } else {
        // Fallback: just output HTML for testing
        header('Content-Type: text/html');
        echo "<html><body>$html</body></html>";
        exit;
    }
}

function sanitize_filename($filename) {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
}
?>