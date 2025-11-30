<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $messageId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'mark_read':
            if ($messageId) {
                $stmt = $db->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
                $stmt->execute([$messageId]);
                $_SESSION['success'] = "Message marked as read";
            }
            break;
            
        case 'mark_replied':
            if ($messageId) {
                $stmt = $db->prepare("UPDATE contact_messages SET status = 'replied', replied_at = NOW() WHERE id = ?");
                $stmt->execute([$messageId]);
                $_SESSION['success'] = "Message marked as replied";
            }
            break;
            
        case 'delete':
            if ($messageId) {
                $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
                $stmt->execute([$messageId]);
                $_SESSION['success'] = "Message deleted successfully";
            }
            break;
            
        case 'add_note':
            if ($messageId && isset($_POST['admin_notes'])) {
                $notes = trim($_POST['admin_notes']);
                $stmt = $db->prepare("UPDATE contact_messages SET admin_notes = ? WHERE id = ?");
                $stmt->execute([$notes, $messageId]);
                $_SESSION['success'] = "Notes updated successfully";
            }
            break;
    }
    
    header("Location: contact-messages.php");
    exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Get messages based on filter
$messages = [];
try {
    $query = "SELECT * FROM contact_messages";
    
    switch($filter) {
        case 'unread':
            $query .= " WHERE status = 'unread'";
            break;
        case 'read':
            $query .= " WHERE status = 'read'";
            break;
        case 'replied':
            $query .= " WHERE status = 'replied'";
            break;
    }
    
    $query .= " ORDER BY created_at DESC";
    $stmt = $db->query($query);
    $messages = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Contact messages query error: " . $e->getMessage());
}

// Get message statistics
$msgStats = [
    'total' => 0,
    'unread' => 0,
    'read' => 0,
    'replied' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages");
    $msgStats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'");
    $msgStats['unread'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'read'");
    $msgStats['read'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'replied'");
    $msgStats['replied'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Message stats error: " . $e->getMessage());
}

$page_title = "Contact Messages";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
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
                    <h4 class="mb-0">Contact Messages</h4>
                    <small class="text-muted">Message Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Contact Messages</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="export-contact-messages.php">Export as Excel</a></li>
                    </ul>
                </div>
            </div>

            <!-- Success Message -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Message Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card <?php echo $filter === 'all' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-primary"><?php echo $msgStats['total']; ?></h2>
                            <h6 class="card-title">Total Messages</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card <?php echo $filter === 'unread' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-warning"><?php echo $msgStats['unread']; ?></h2>
                            <h6 class="card-title">Unread</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card <?php echo $filter === 'read' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-info"><?php echo $msgStats['read']; ?></h2>
                            <h6 class="card-title">Read</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card <?php echo $filter === 'replied' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-success"><?php echo $msgStats['replied']; ?></h2>
                            <h6 class="card-title">Replied</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-envelope me-2"></i>Contact Messages
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i><?php echo ucfirst($filter); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All Messages</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'unread' ? 'active' : ''; ?>" href="?filter=unread">Unread</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'read' ? 'active' : ''; ?>" href="?filter=read">Read</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'replied' ? 'active' : ''; ?>" href="?filter=replied">Replied</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($messages)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-envelope fa-3x mb-3"></i><br>
                                            No messages found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($messages as $msg): ?>
                                        <tr class="<?php echo $msg['status'] === 'unread' ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($msg['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($msg['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                            <td>
                                                <small><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100) . '...')); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($msg['status']) {
                                                        case 'unread': echo 'warning'; break;
                                                        case 'read': echo 'info'; break;
                                                        case 'replied': echo 'success'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($msg['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group touchpad-friendly">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                                        <i class="fas fa-cog me-1"></i>Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewMessageModal<?php echo $msg['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <?php if($msg['status'] === 'unread'): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="?action=mark_read&id=<?php echo $msg['id']; ?>">
                                                                    <i class="fas fa-check me-2"></i>Mark as Read
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-success" href="?action=mark_replied&id=<?php echo $msg['id']; ?>">
                                                                <i class="fas fa-reply me-2"></i>Mark as Replied
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addNotesModal<?php echo $msg['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Add Notes
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $msg['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete this message?')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Message Modal -->
                                        <div class="modal fade" id="viewMessageModal<?php echo $msg['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Message from <?php echo htmlspecialchars($msg['name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <strong>From:</strong> <?php echo htmlspecialchars($msg['name']); ?><br>
                                                                <strong>Email:</strong> <?php echo htmlspecialchars($msg['email']); ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?><br>
                                                                <strong>Status:</strong> 
                                                                <span class="badge bg-<?php echo $msg['status'] === 'unread' ? 'warning' : ($msg['status'] === 'read' ? 'info' : 'success'); ?>">
                                                                    <?php echo ucfirst($msg['status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <strong>Subject:</strong><br>
                                                            <?php echo htmlspecialchars($msg['subject']); ?>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <strong>Message:</strong><br>
                                                            <div class="border rounded p-3 bg-light">
                                                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if(!empty($msg['admin_notes'])): ?>
                                                            <div class="mb-3">
                                                                <strong>Admin Notes:</strong><br>
                                                                <div class="border rounded p-3 bg-warning bg-opacity-10">
                                                                    <?php echo nl2br(htmlspecialchars($msg['admin_notes'])); ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <!-- SIMPLE WORKING REPLY LINK -->
                                                        <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" 
                                                           class="btn btn-primary" target="_blank">
                                                            <i class="fas fa-reply me-2"></i>Reply via Email
                                                        </a>
                                                        <?php if($msg['status'] === 'unread'): ?>
                                                            <a href="?action=mark_read&id=<?php echo $msg['id']; ?>" class="btn btn-success">Mark as Read</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add Notes Modal -->
                                        <div class="modal fade" id="addNotesModal<?php echo $msg['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="?action=add_note&id=<?php echo $msg['id']; ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Add Notes - <?php echo htmlspecialchars($msg['name']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="admin_notes" class="form-label">Admin Notes</label>
                                                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4" 
                                                                          placeholder="Add any internal notes about this message..."><?php echo htmlspecialchars($msg['admin_notes'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Notes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple dropdown handling to prevent blinking
document.addEventListener('DOMContentLoaded', function() {
    // Improved dropdown handling for laptops/touchpads
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            // Close other open dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(openMenu => {
                if (openMenu !== menu) {
                    openMenu.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            menu.classList.toggle('show');
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

    // Handle escape key to close dropdowns
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
});
</script>

<style>
/* Simple fixes for dropdown stability */
.dropdown-menu {
    animation: none !important;
    transition: none !important;
}

.btn-group .dropdown-toggle {
    min-height: 38px;
    display: flex;
    align-items: center;
}

/* Mobile optimizations */
@media (max-width: 767.98px) {
    .btn, .dropdown-item {
        min-height: 44px;
        display: flex;
        align-items: center;
    }
    
    .modal-footer {
        flex-direction: column;
        gap: 10px;
    }
    
    .modal-footer .btn {
        width: 100%;
        margin: 2px 0;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>