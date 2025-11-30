<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

// Get all messages
try {
    $stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $messages = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error fetching messages: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="contact_messages_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Excel output
echo "<html><head><meta charset=\"UTF-8\"></head><body>";
echo "<table border=\"1\">";
echo "<tr><th colspan=\"6\" style=\"background-color: #2c3e50; color: white;\">Contact Messages</th></tr>";
echo "<tr style=\"background-color: #34495e; color: white;\">";
echo "<th>#</th><th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Status</th>";
echo "</tr>";

foreach($messages as $index => $msg) {
    echo "<tr>";
    echo "<td>" . ($index + 1) . "</td>";
    echo "<td>" . htmlspecialchars($msg['name']) . "</td>";
    echo "<td>" . htmlspecialchars($msg['email']) . "</td>";
    echo "<td>" . htmlspecialchars($msg['subject']) . "</td>";
    echo "<td>" . date('M j, Y g:i A', strtotime($msg['created_at'])) . "</td>";
    echo "<td>" . ucfirst($msg['status']) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</body></html>";
exit();