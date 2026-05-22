<?php
// api/applications.php
session_start();
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array('success' => false, 'message' => '');
    
    try {
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $sap_id = trim($_POST['sap_id'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $cgpa = trim($_POST['cgpa'] ?? '');
        $current_courses = trim($_POST['current_courses'] ?? '');
        $skills_experience = trim($_POST['skills_experience'] ?? '');
        $portfolio_links = trim($_POST['portfolio_links'] ?? '');
        $motivation_statement = trim($_POST['motivation_statement'] ?? '');
        
        // Validate required fields
        $required_fields = [
            'name', 'sap_id', 'department', 'semester', 
            'email', 'phone', 'cgpa', 'skills_experience', 'motivation_statement'
        ];
        
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        // Validate SAP ID format (assuming numeric)
        if (!is_numeric($sap_id)) {
            throw new Exception('SAP ID must be numeric');
        }
        
        // Check if profile picture is uploaded
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Profile picture is required. Please upload a clear photo of yourself.');
        }
        
        // Check if application already exists with same SAP ID or email
        $db = Database::getInstance()->getConnection();
        $check_sql = "SELECT id FROM applications WHERE JSON_EXTRACT(personal_info, '$.sap_id') = ? OR JSON_EXTRACT(personal_info, '$.email') = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([$sap_id, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('An application with this SAP ID or email already exists');
        }
        
        // Prepare personal and academic info arrays
        $personal_info = [
            'name' => $name,
            'sap_id' => $sap_id,
            'department' => $department,
            'semester' => $semester,
            'email' => $email,
            'phone' => $phone
        ];
        
        $academic_info = [
            'cgpa' => $cgpa,
            'current_courses' => $current_courses
        ];
        
        // Handle file uploads
        $resume_path = null;
        $profile_picture = null;
        
        $functions = new Functions();
        
        // Upload profile picture (REQUIRED)
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload = $functions->uploadFile($_FILES['profile_picture'], 'profile_picture');
            if ($upload['success']) {
                $profile_picture = $upload['file_name'];
                // Store profile picture in personal_info for easy access
                $personal_info['profile_picture'] = $profile_picture;
            } else {
                throw new Exception('Profile picture upload failed: ' . $upload['message']);
            }
        }
        
        // Upload resume if provided
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $upload = $functions->uploadFile($_FILES['resume'], 'resume');
            if ($upload['success']) {
                $resume_path = $upload['file_name'];
            } else {
                throw new Exception('Resume upload failed: ' . $upload['message']);
            }
        }
        
        // Insert application into database with profile picture in personal_info
        $sql = "INSERT INTO applications (
            user_id, 
            applied_for, 
            personal_info, 
            academic_info, 
            skills_experience, 
            portfolio_links, 
            motivation_statement, 
            resume_path, 
            status
        ) VALUES (NULL, 'membership', ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            json_encode($personal_info),
            json_encode($academic_info),
            $skills_experience,
            $portfolio_links,
            $motivation_statement,
            $resume_path
        ]);
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Application submitted successfully! We will review your application and contact you soon.';
            
            // Log the application
            error_log("New membership application submitted: " . $name . " (" . $sap_id . ")");
            
        } else {
            throw new Exception('Database error: Failed to save application');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        
        // Log the error for debugging
        error_log("Application submission error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}

// If not POST request
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>