<?php
$page_title = "Our Team";
require_once '../includes/header.php';
require_once '../includes/database.php';

// Initialize database connection
try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    if (!$db) throw new Exception("Failed to get database connection");
} catch (Exception $e) {
    error_log("Database connection error in team.php: " . $e->getMessage());
    $db = null;
}

// Get society heads (leadership)
$heads = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT sh.*, u.name, u.profile_picture, u.department 
                            FROM society_heads sh 
                            JOIN users u ON sh.user_id = u.id 
                            WHERE sh.is_active = TRUE 
                            ORDER BY sh.display_order");
        $heads = $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Society heads query error: " . $e->getMessage());
    }
}
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Our Team</h1>

    <?php if (!$db): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Sorry, we're experiencing technical difficulties. Please check back later.
        </div>
    <?php endif; ?>

    <!-- Society Leadership Section -->
    <?php if (!empty($heads)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="text-center mb-4">Leadership</h2>
            </div>
            <?php foreach($heads as $head): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card text-center h-100 shadow-sm team-card">
                        <div class="card-body">
                            <img src="../uploads/profile-pictures/<?php echo htmlspecialchars($head['profile_picture']); ?>" 
                                 alt="<?php echo htmlspecialchars($head['name']); ?>" 
                                 class="rounded-circle mb-3 team-photo" 
                                 style="width: 150px; height: 150px; object-fit: cover;"
                                 onerror="this.src='../uploads/profile-pictures/default-avatar.png'">
                            <h5 class="card-title"><?php echo htmlspecialchars($head['name']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-accent"><?php echo htmlspecialchars($head['position']); ?></h6>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($head['department']); ?></p>
                            <p class="card-text"><?php echo htmlspecialchars($head['bio']); ?></p>
                            <?php 
                            $contact = json_decode($head['contact_info'], true);
                            if ($contact): ?>
                                <div class="contact-info mt-3">
                                    <?php if(isset($contact['email'])): ?>
                                        <div class="mb-1">
                                            <i class="fas fa-envelope me-2 text-muted"></i>
                                            <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($contact['email']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if(isset($contact['phone'])): ?>
                                        <div>
                                            <i class="fas fa-phone me-2 text-muted"></i>
                                            <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($contact['phone']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Department Teams (Dynamic from team_members_all, joined with users) -->
    <?php
    $team_members_by_dept = [];
    $dept_display_names = [];
    
    if ($db) {
        try {
            // Get department display names
            $stmt = $db->query("SELECT department_key, department_name FROM departments WHERE is_active = 1");
            $dept_rows = $stmt->fetchAll();
            foreach ($dept_rows as $row) {
                $dept_display_names[$row['department_key']] = $row['department_name'];
            }
            
            // Check if team_members_all table exists and has user_id column
            $table_exists = false;
            try {
                $result = $db->query("SHOW TABLES LIKE 'team_members_all'");
                if ($result->rowCount() > 0) {
                    $table_exists = true;
                    $col_check = $db->query("SHOW COLUMNS FROM team_members_all LIKE 'user_id'");
                    if ($col_check->rowCount() == 0) {
                        $db->exec("ALTER TABLE team_members_all ADD COLUMN user_id INT NULL, ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
                    }
                }
            } catch(PDOException $e) {
                $table_exists = false;
            }
            
            if ($table_exists) {
                $sql = "SELECT tm.*, 
                               u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.profile_picture AS user_profile_picture
                        FROM team_members_all tm
                        LEFT JOIN users u ON tm.user_id = u.id
                        WHERE tm.is_active = 1
                        ORDER BY tm.department, tm.display_order, COALESCE(u.name, tm.name)";
                $stmt = $db->query($sql);
                $all_members = $stmt->fetchAll();
                
                // Group by department
                foreach ($all_members as $member) {
                    $dept = $member['department'];
                    if (!isset($team_members_by_dept[$dept])) {
                        $team_members_by_dept[$dept] = [];
                    }
                    $team_members_by_dept[$dept][] = $member;
                }
            }
        } catch(PDOException $e) {
            error_log("Team members query error: " . $e->getMessage());
        }
    }
    ?>

    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-4">Our Department Teams</h2>
        </div>
        
        <?php if (!empty($team_members_by_dept)): ?>
            <?php foreach($team_members_by_dept as $dept_key => $members): 
                $dept_name = isset($dept_display_names[$dept_key]) ? $dept_display_names[$dept_key] : ucfirst(str_replace('_', ' ', $dept_key));
            ?>
                <div class="col-12 mb-5">
                    <h3 class="text-center mb-4 border-bottom pb-2 text-accent"><?php echo htmlspecialchars($dept_name); ?></h3>
                    <!-- Always center the row, and use responsive column classes -->
                    <div class="row justify-content-center">
                        <?php foreach($members as $member): 
                            $contact_info = json_decode($member['contact_info'], true);
                            $display_name = $member['user_name'] ?? $member['name'];
                            $display_email = $member['user_email'] ?? ($contact_info['email'] ?? '');
                            $display_phone = $member['user_phone'] ?? ($contact_info['phone'] ?? '');
                            $display_picture = $member['user_profile_picture'] ?? $member['profile_picture'];
                            $linkedin = $contact_info['linkedin'] ?? '';
                            $instagram = $contact_info['instagram'] ?? '';
                            $github = $contact_info['github'] ?? '';
                        ?>
                            <div class="col-6 col-md-4 col-lg-3 mb-4">
                                <div class="card h-100 text-center team-member-card border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="position-relative mb-3">
                                            <img src="../uploads/profile-pictures/<?php echo htmlspecialchars($display_picture); ?>" 
                                                 alt="<?php echo htmlspecialchars($display_name); ?>" 
                                                 class="rounded-circle team-member-photo mx-auto d-block"
                                                 style="width: 120px; height: 120px; object-fit: cover;"
                                                 onerror="this.src='../uploads/profile-pictures/default-avatar.png'">
                                            
                                            <?php 
                                            $role_lower = strtolower($member['role']);
                                            $badge_class = 'bg-secondary';
                                            if (strpos($role_lower, 'head') !== false || 
                                                strpos($role_lower, 'lead') !== false || 
                                                strpos($role_lower, 'coordinator') !== false) {
                                                $badge_class = 'bg-primary';
                                            } elseif (strpos($role_lower, 'senior') !== false || 
                                                     strpos($role_lower, 'coordin') !== false) {
                                                $badge_class = 'bg-info';
                                            } elseif (strpos($role_lower, 'member') !== false) {
                                                $badge_class = 'bg-success';
                                            } elseif (strpos($role_lower, 'volunteer') !== false) {
                                                $badge_class = 'bg-warning';
                                            }
                                            ?>
                                            <span class="position-absolute top-0 start-50 translate-middle badge <?php echo $badge_class; ?>" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($member['role']); ?>
                                            </span>
                                        </div>
                                        
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($display_name); ?></h5>
                                        
                                        <?php if ($member['position']): ?>
                                            <p class="card-text small text-muted mb-2"><?php echo htmlspecialchars($member['position']); ?></p>
                                        <?php endif; ?>
                                        
                                        <p class="card-text small">
                                            <i class="fas fa-graduation-cap text-primary me-1"></i>
                                            Year <?php echo htmlspecialchars($member['year']); ?>
                                        </p>
                                        
                                        <?php if ($member['bio']): ?>
                                            <p class="card-text small text-muted mb-3"><?php echo htmlspecialchars(substr($member['bio'], 0, 80)); ?>...</p>
                                        <?php endif; ?>
                                        
                                        <div class="social-links mt-3">
                                            <?php if($display_email): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($display_email); ?>" 
                                                   class="text-decoration-none mx-1" title="Email">
                                                    <i class="fas fa-envelope fa-lg text-success"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if($linkedin): ?>
                                                <a href="<?php echo htmlspecialchars($linkedin); ?>" 
                                                   target="_blank" class="text-decoration-none mx-1" title="LinkedIn">
                                                    <i class="fab fa-linkedin fa-lg text-primary"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if($instagram): ?>
                                                <a href="<?php echo htmlspecialchars($instagram); ?>" 
                                                   target="_blank" class="text-decoration-none mx-1" title="Instagram">
                                                    <i class="fab fa-instagram fa-lg text-danger"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if($github): ?>
                                                <a href="<?php echo htmlspecialchars($github); ?>" 
                                                   target="_blank" class="text-decoration-none mx-1" title="GitHub">
                                                    <i class="fab fa-github fa-lg text-dark"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if($display_phone): ?>
                                                <a href="tel:<?php echo htmlspecialchars($display_phone); ?>" 
                                                   class="text-decoration-none mx-1" title="Phone">
                                                    <i class="fas fa-phone fa-lg text-info"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback: Static department info if no team members -->
            <div class="col-12 text-center">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Team members will be displayed here once added by the admin.
                </div>
                <div class="row justify-content-center mt-4">
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-calendar-alt fa-3x text-accent mb-3"></i>
                                <h5>Event Planning</h5>
                                <p class="text-muted">Organizing memorable events and workshops</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-camera fa-3x text-accent mb-3"></i>
                                <h5>Media & Marketing</h5>
                                <p class="text-muted">Creating engaging content and promotions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-trophy fa-3x text-accent mb-3"></i>
                                <h5>Competitions</h5>
                                <p class="text-muted">Managing technical competitions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-accent mb-3"></i>
                                <h5>Recruitment</h5>
                                <p class="text-muted">Onboarding new members</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-code fa-3x text-accent mb-3"></i>
                                <h5>Technical Team</h5>
                                <p class="text-muted">Technical development and support</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-paint-brush fa-3x text-accent mb-3"></i>
                                <h5>Design Team</h5>
                                <p class="text-muted">Graphic and UI/UX design work</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-pen-fancy fa-3x text-accent mb-3"></i>
                                <h5>Content Writing</h5>
                                <p class="text-muted">Content creation and copywriting</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 text-center">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-truck-loading fa-3x text-accent mb-3"></i>
                                <h5>Logistics</h5>
                                <p class="text-muted">Event logistics and management</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.team-member-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 15px;
    height: 100%;
}
.team-member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.team-member-photo {
    border: 3px solid var(--accent-color);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.team-card {
    border-radius: 15px;
    overflow: hidden;
}
.team-photo {
    border: 3px solid var(--accent-color);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.social-links i {
    transition: transform 0.2s ease, color 0.2s ease;
    color: #6c757d;
}
.social-links a:hover i {
    transform: scale(1.2);
}
.social-links .fa-envelope:hover { color: #28a745 !important; }
.social-links .fa-linkedin:hover { color: #0077b5 !important; }
.social-links .fa-instagram:hover { color: #e4405f !important; }
.social-links .fa-github:hover { color: #333 !important; }
.social-links .fa-phone:hover { color: #17a2b8 !important; }
.badge {
    font-weight: 500;
    padding: 5px 10px;
}
.bg-primary { background-color: #007bff !important; }
.bg-info { background-color: #17a2b8 !important; }
.bg-success { background-color: #28a745 !important; }
.bg-warning { background-color: #ffc107 !important; color: #212529; }
.bg-secondary { background-color: #6c757d !important; }
.text-accent {
    color: var(--accent-color);
}
.card {
    border: 1px solid rgba(0,0,0,0.1);
}
</style>

<?php require_once '../includes/footer.php'; ?>