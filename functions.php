<?php
require_once 'database.php';

class Functions {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // File upload function - FIXED PATHS
    public function uploadFile($file, $type = 'image') {
        $uploadDir = '';
        $allowedTypes = [];
        $maxSize = 0;
        
        switch($type) {
            case 'profile_picture':
                $uploadDir = '../uploads/profile-pictures/';
                $allowedTypes = ['jpg', 'jpeg', 'png'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                break;
            case 'resume':
                $uploadDir = '../uploads/resumes/';
                $allowedTypes = ['pdf'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                break;
            case 'event_image':
                $uploadDir = '../uploads/event-images/';
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                break;
            case 'gallery':
                $uploadDir = '../uploads/gallery/';
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                break;
            // REMOVED: feed_image case since feed system is removed
            default:
                return ['success' => false, 'message' => 'Invalid file type'];
        }
        
        // Check if file was uploaded
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            return ['success' => false, 'message' => $errorMessages[$file['error']] ?? 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size too large. Maximum size: ' . ($maxSize / 1024 / 1024) . 'MB'];
        }
        
        // Check file type
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)];
        }
        
        // Additional validation for images
        if (in_array($type, ['profile_picture', 'event_image', 'gallery'])) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                return ['success' => false, 'message' => 'Uploaded file is not a valid image'];
            }
        }
        
        // Generate unique filename
        $fileName = uniqid() . '_' . time() . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            return ['success' => false, 'message' => 'Upload directory is not writable'];
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => true, 
                'file_name' => $fileName, 
                'file_path' => $filePath,
                'message' => 'File uploaded successfully'
            ];
        } else {
            return ['success' => false, 'message' => 'File upload failed. Please try again.'];
        }
    }
    
    // Generate random password
    public function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    // Send email notification
    public function sendEmail($to, $subject, $message) {
        // In production, use PHPMailer or similar
        $headers = "From: noreply@mesociety.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    // Get user by ID
    public function getUser($userId) {
        try {
            $sql = "SELECT * FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Log activity
    public function logActivity($userId, $action, $details = '') {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
                    VALUES (:user_id, :action, :details, :ip_address)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
        } catch(PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
            return false;
        }
    }
    
    // Sanitize input
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    // Format date for display
    public function formatDate($date, $format = 'M j, Y') {
        return date($format, strtotime($date));
    }
    
    // Format date and time for display
    public function formatDateTime($date, $format = 'M j, Y g:i A') {
        return date($format, strtotime($date));
    }
    
    // Check if user can access resource
    public function canAccess($requiredRole) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'];
        $roles = [
            'public' => 0,
            'member' => 1,
            'media_head' => 2,
            'event_planner' => 2,
            'competition_head' => 2,
            'hiring_head' => 2,
            'department_head' => 3,
            'super_admin' => 4
        ];
        
        return isset($roles[$userRole]) && $roles[$userRole] >= $roles[$requiredRole];
    }
    
    // Get user role name for display
    public function getRoleDisplayName($role) {
        $roleNames = [
            'public' => 'Public User',
            'member' => 'Society Member',
            'media_head' => 'Media Head',
            'event_planner' => 'Event Planner',
            'competition_head' => 'Competition Head',
            'hiring_head' => 'Hiring Head',
            'department_head' => 'Department Head',
            'super_admin' => 'Super Admin'
        ];
        
        return $roleNames[$role] ?? 'Unknown Role';
    }
    
    // Validate email format
    public function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Validate phone number (basic validation)
    public function isValidPhone($phone) {
        // Remove any non-digit characters
        $cleanPhone = preg_replace('/\D/', '', $phone);
        // Check if it's a reasonable length for a phone number
        return strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15;
    }
    
    // Generate random token for password reset
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    // Redirect to another page
    public function redirect($url) {
        header("Location: $url");
        exit;
    }
    
    // Get current URL
    public function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    // Truncate text with ellipsis
    public function truncateText($text, $length = 100) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }
    
    // Format file size
    public function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    // Check if string is JSON
    public function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    // Get application status badge
    public function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'under_review' => '<span class="badge bg-info">Under Review</span>',
            'interview_scheduled' => '<span class="badge bg-primary">Interview Scheduled</span>',
            'selected' => '<span class="badge bg-success">Selected</span>',
            'rejected' => '<span class="badge bg-danger">Rejected</span>',
            'active' => '<span class="badge bg-success">Active</span>',
            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
            'suspended' => '<span class="badge bg-danger">Suspended</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
    
    // Get event status badge
    public function getEventStatusBadge($status) {
        $badges = [
            'draft' => '<span class="badge bg-secondary">Draft</span>',
            'pending' => '<span class="badge bg-warning">Pending Approval</span>',
            'approved' => '<span class="badge bg-info">Approved</span>',
            'published' => '<span class="badge bg-success">Published</span>',
            'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
    
    // Get user initials for avatar
    public function getUserInitials($name) {
        $words = explode(' ', $name);
        $initials = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }
        
        return substr($initials, 0, 2);
    }
    
    // Calculate age from date of birth
    public function calculateAge($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        return $age->y;
    }
    
    // Generate QR code data (placeholder - in real implementation, use a QR code library)
    public function generateQRCodeData($data) {
        // This is a placeholder. In a real implementation, you would use a QR code library
        // like phpqrcode or endroid/qr-code
        return "data:image/svg+xml;base64," . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">' .
            '<rect width="100" height="100" fill="white"/>' .
            '<text x="50" y="50" text-anchor="middle" fill="black">QR Code</text>' .
            '</svg>'
        );
    }
    
    // Clean filename for safe storage
    public function cleanFilename($filename) {
        // Remove any path information
        $filename = basename($filename);
        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);
        // Remove any non-alphanumeric characters except dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        // Limit filename length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        return $filename;
    }
    
    // Check if date is in the future
    public function isFutureDate($date) {
        return strtotime($date) > time();
    }
    
    // Check if date is in the past
    public function isPastDate($date) {
        return strtotime($date) < time();
    }
    
    // Get days between two dates
    public function getDaysBetween($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        return $interval->days;
    }
    
    // Add days to a date
    public function addDaysToDate($date, $days) {
        $newDate = new DateTime($date);
        $newDate->modify("+$days days");
        return $newDate->format('Y-m-d');
    }

    // Resize image to square - FIXED: Added explicit type casting to int
    public function resizeImageToSquare($filePath, $size = 300) {
        try {
            // Get image info
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                return false;
            }

            $mimeType = $imageInfo['mime'];
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            // Create image from file based on mime type
            switch($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($filePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($filePath);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($filePath);
                    break;
                default:
                    return false;
            }

            if (!$image) {
                return false;
            }

            // Create square canvas
            $square = min($width, $height);
            $squareImage = imagecreatetruecolor($size, $size);

            // Preserve transparency for PNG and GIF
            if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
                imagecolortransparent($squareImage, imagecolorallocatealpha($squareImage, 0, 0, 0, 127));
                imagealphablending($squareImage, false);
                imagesavealpha($squareImage, true);
            }

            // Calculate crop coordinates to center the image - FIXED: Explicit casting to int
            $src_x = (int)(($width - $square) / 2);
            $src_y = (int)(($height - $square) / 2);

            // Resize and crop to square - FIXED: Explicit casting to int for all parameters
            imagecopyresampled(
                $squareImage, 
                $image, 
                0, 
                0, 
                $src_x, 
                $src_y, 
                $size, 
                $size, 
                (int)$square, 
                (int)$square
            );

            // Save the resized image
            switch($mimeType) {
                case 'image/jpeg':
                    imagejpeg($squareImage, $filePath, 90);
                    break;
                case 'image/png':
                    imagepng($squareImage, $filePath, 9);
                    break;
                case 'image/gif':
                    imagegif($squareImage, $filePath);
                    break;
            }

            // Free memory
            imagedestroy($image);
            imagedestroy($squareImage);

            return true;
        } catch (Exception $e) {
            error_log("Image resize error: " . $e->getMessage());
            return false;
        }
    }

    // Team management functions
    public function addTeamMember($data) {
        try {
            $contactInfo = json_encode([
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? ''
            ]);
            
            $sql = "INSERT INTO team_members (user_id, name, position, bio, contact_info, profile_picture, display_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                $data['user_id'] ?? null,
                $data['name'],
                $data['position'],
                $data['bio'] ?? '',
                $contactInfo,
                $data['profile_picture'] ?? 'default-avatar.png',
                $data['display_order'] ?? 0,
                $data['is_active'] ?? 1
            ]);
        } catch(PDOException $e) {
            error_log("Add team member error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTeamMembers($activeOnly = true) {
        try {
            $sql = "SELECT * FROM team_members";
            if ($activeOnly) {
                $sql .= " WHERE is_active = TRUE";
            }
            $sql .= " ORDER BY display_order, name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get team members error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateTeamMember($id, $data) {
        try {
            $contactInfo = json_encode([
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? ''
            ]);
            
            $sql = "UPDATE team_members SET 
                    user_id = ?, name = ?, position = ?, bio = ?, 
                    contact_info = ?, profile_picture = ?, display_order = ?, is_active = ?
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                $data['user_id'] ?? null,
                $data['name'],
                $data['position'],
                $data['bio'] ?? '',
                $contactInfo,
                $data['profile_picture'],
                $data['display_order'] ?? 0,
                $data['is_active'] ?? 1,
                $id
            ]);
        } catch(PDOException $e) {
            error_log("Update team member error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteTeamMember($id) {
        try {
            $sql = "DELETE FROM team_members WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch(PDOException $e) {
            error_log("Delete team member error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getTeamMember($id) {
        try {
            $sql = "SELECT * FROM team_members WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get team member error: " . $e->getMessage());
            return false;
        }
    }

    // Get competition status badge
    public function getCompetitionStatusBadge($status) {
        $badges = [
            'draft' => '<span class="badge bg-secondary">Draft</span>',
            'published' => '<span class="badge bg-success">Published</span>',
            'ongoing' => '<span class="badge bg-primary">Ongoing</span>',
            'completed' => '<span class="badge bg-info">Completed</span>',
            'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }

    // Get gallery status badge
    public function getGalleryStatusBadge($status) {
        $badges = [
            'active' => '<span class="badge bg-success">Active</span>',
            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
            'archived' => '<span class="badge bg-info">Archived</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }

    // Get user status badge
    public function getUserStatusBadge($status) {
        $badges = [
            'active' => '<span class="badge bg-success">Active</span>',
            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
            'suspended' => '<span class="badge bg-danger">Suspended</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>