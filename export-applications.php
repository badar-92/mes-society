<?php
// export-applications.php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'hiring_head']);

$db = Database::getInstance()->getConnection();

// Get filter if any
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$query = "SELECT a.*, u.name as applicant_name, u.email, u.phone 
          FROM applications a 
          LEFT JOIN users u ON a.user_id = u.id 
          WHERE a.applied_for = 'membership'";

switch($filter) {
    case 'pending':
        $query .= " AND a.status = 'pending'";
        break;
    case 'under_review':
        $query .= " AND a.status = 'under_review'";
        break;
    case 'interview_scheduled':
        $query .= " AND a.status = 'interview_scheduled'";
        break;
    case 'selected':
        $query .= " AND a.status = 'selected'";
        break;
    case 'rejected':
        $query .= " AND a.status = 'rejected'";
        break;
}

$query .= " ORDER BY a.applied_at DESC";

try {
    $stmt = $db->query($query);
    $applications = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error fetching applications: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="membership_applications_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Excel header
echo "<html>";
echo "<head>";
echo "<meta charset=\"UTF-8\">";
echo "</head>";
echo "<body>";

echo "<table border=\"1\">";
echo "<tr>";
echo "<th colspan=\"10\" style=\"background-color: #2c3e50; color: white; font-size: 16px; padding: 10px;\">Membership Applications - " . ucfirst(str_replace('_', ' ', $filter)) . "</th>";
echo "</tr>";
echo "<tr>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">#</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Applicant Name</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">SAP ID</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Department</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Semester</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Email</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Phone</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Applied Date</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Status</th>";
echo "<th style=\"background-color: #34495e; color: white; padding: 8px;\">Skills & Experience</th>";
echo "</tr>";

if(empty($applications)) {
    echo "<tr>";
    echo "<td colspan=\"10\" style=\"text-align: center; padding: 10px;\">No applications found</td>";
    echo "</tr>";
} else {
    $counter = 1;
    foreach($applications as $app) {
        // Decode personal info safely
        $personalInfo = json_decode($app['personal_info'] ?? '{}', true) ?? [];
        $academicInfo = json_decode($app['academic_info'] ?? '{}', true) ?? [];
        
        $name = htmlspecialchars($personalInfo['name'] ?? $app['applicant_name'] ?? 'Unknown');
        $sapId = htmlspecialchars($personalInfo['sap_id'] ?? 'N/A');
        $department = htmlspecialchars($personalInfo['department'] ?? 'N/A');
        $semester = htmlspecialchars($personalInfo['semester'] ?? 'N/A');
        $email = htmlspecialchars($personalInfo['email'] ?? $app['email'] ?? 'N/A');
        $phone = htmlspecialchars($personalInfo['phone'] ?? $app['phone'] ?? 'N/A');
        $appliedDate = date('M j, Y', strtotime($app['applied_at'] ?? 'now'));
        $status = ucfirst(str_replace('_', ' ', $app['status'] ?? 'unknown'));
        $skills = htmlspecialchars(substr($app['skills_experience'] ?? 'No information provided', 0, 100) . '...');
        
        // Status color coding
        $statusColor = '';
        switch($app['status']) {
            case 'pending': $statusColor = '#ffc107'; break; // Yellow
            case 'under_review': $statusColor = '#17a2b8'; break; // Blue
            case 'interview_scheduled': $statusColor = '#007bff'; break; // Primary Blue
            case 'selected': $statusColor = '#28a745'; break; // Green
            case 'rejected': $statusColor = '#dc3545'; break; // Red
            default: $statusColor = '#6c757d'; // Gray
        }
        
        echo "<tr>";
        echo "<td style=\"padding: 6px;\">" . $counter . "</td>";
        echo "<td style=\"padding: 6px;\">" . $name . "</td>";
        echo "<td style=\"padding: 6px;\">" . $sapId . "</td>";
        echo "<td style=\"padding: 6px;\">" . $department . "</td>";
        echo "<td style=\"padding: 6px;\">" . $semester . "</td>";
        echo "<td style=\"padding: 6px;\">" . $email . "</td>";
        echo "<td style=\"padding: 6px;\">" . $phone . "</td>";
        echo "<td style=\"padding: 6px;\">" . $appliedDate . "</td>";
        echo "<td style=\"padding: 6px; background-color: " . $statusColor . "; color: white; font-weight: bold;\">" . $status . "</td>";
        echo "<td style=\"padding: 6px;\">" . $skills . "</td>";
        echo "</tr>";
        
        $counter++;
    }
}

echo "</table>";

// Add summary
echo "<br>";
echo "<table border=\"1\" style=\"margin-top: 20px;\">";
echo "<tr>";
echo "<th colspan=\"4\" style=\"background-color: #2c3e50; color: white; padding: 8px;\">Application Summary</th>";
echo "</tr>";
echo "<tr>";
echo "<th style=\"background-color: #f8f9fa; padding: 6px;\">Total Applications</th>";
echo "<td style=\"padding: 6px;\">" . count($applications) . "</td>";
echo "<th style=\"background-color: #f8f9fa; padding: 6px;\">Export Date</th>";
echo "<td style=\"padding: 6px;\">" . date('F j, Y g:i A') . "</td>";
echo "</tr>";
echo "<tr>";
echo "<th style=\"background-color: #f8f9fa; padding: 6px;\">Filter Applied</th>";
echo "<td colspan=\"3\" style=\"padding: 6px;\">" . ucfirst(str_replace('_', ' ', $filter)) . " Applications</td>";
echo "</tr>";
echo "</table>";

echo "</body>";
echo "</html>";
exit();
?>