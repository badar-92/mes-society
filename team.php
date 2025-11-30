
<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$functions = new Functions();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_member':
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $member = $functions->getTeamMember($id);
            if ($member) {
                $member['contact_info'] = json_decode($member['contact_info'], true);
                echo json_encode($member);
            } else {
                echo json_encode(['error' => 'Member not found']);
            }
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
