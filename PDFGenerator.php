<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PDFGenerator {
    
    public function generateWelcomeLetterPDF($userData) {
        try {
            // Set up DomPDF options
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isPhpEnabled', true);
            $options->set('chroot', realpath(__DIR__ . '/../../'));
            
            $dompdf = new Dompdf($options);
            
            // HTML content for the PDF
            $html = $this->getWelcomeLetterHTML($userData);
            
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Return the PDF content directly
            return $dompdf->output();
            
        } catch(Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generatePasswordResetPDF($user, $new_password) {
        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isPhpEnabled', true);
            $options->set('chroot', realpath(__DIR__ . '/../../'));
            
            $dompdf = new Dompdf($options);
            
            $html = $this->getPasswordResetHTML($user, $new_password);
            
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return $dompdf->output();
            
        } catch(Exception $e) {
            error_log("Password Reset PDF generation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateIDCardPDF($user) {
        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isPhpEnabled', true);
            $options->set('chroot', realpath(__DIR__ . '/../../'));
            
            $dompdf = new Dompdf($options);
            
            $html = $this->getIDCardHTML($user);
            
            $dompdf->loadHtml($html, 'UTF-8');
            // Updated: Portrait orientation with increased height for better display
            $dompdf->setPaper(array(0, 0, 340, 480), 'portrait'); // Portrait ID card size
            $dompdf->render();
            
            return $dompdf->output();
            
        } catch(Exception $e) {
            error_log("ID Card PDF generation error: " . $e->getMessage());
            return false;
        }
    }
    
private function getPasswordResetHTML($user, $new_password) {
    $currentDate = date('F j, Y');
    $siteUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
        <style>
            @page {
                margin: 15px;
            }
            body { 
                font-family: DejaVu Sans, Arial, sans-serif; 
                margin: 0; 
                padding: 0; 
                color: #333; 
                line-height: 1.3;
                font-size: 11px;
            }
            .header { 
                background: #000000;
                color: white;
                padding: 12px;
                text-align: center;
                margin-bottom: 15px;
            }
            .header h2 {
                margin: 0 0 5px 0;
                font-size: 14px;
            }
            .header p {
                margin: 0;
                font-size: 10px;
                opacity: 0.9;
            }
            .title { 
                color: #FF6600; 
                margin: 12px 0 8px 0; 
                font-size: 14px;
                font-weight: bold;
                text-align: center;
            }
            .user-info { 
                margin: 12px 0; 
                border: 1px solid #ddd;
                padding: 12px;
                border-radius: 4px;
            }
            .info-table { 
                width: 100%; 
                border-collapse: collapse; 
            }
            .info-table td { 
                padding: 6px 0; 
                border-bottom: 1px solid #eee; 
                font-size: 10px;
            }
            .info-table td:first-child { 
                font-weight: bold; 
                width: 35%; 
            }
            .password-section {
                background: #FFF8F0;
                border: 2px solid #FF6600;
                border-radius: 4px;
                padding: 12px;
                margin: 15px 0;
                text-align: center;
            }
            .new-password {
                font-size: 16px;
                font-weight: bold;
                color: #FF6600;
                background: white;
                padding: 8px;
                border-radius: 4px;
                margin: 8px 0;
                border: 1px dashed #FF6600;
                letter-spacing: 1px;
            }
            .instructions {
                background: #f8f9fa;
                border-left: 3px solid #FF6600;
                padding: 8px 12px;
                margin: 8px 0;
                font-size: 10px;
            }
            .instructions h4 {
                margin: 0 0 5px 0;
                font-size: 11px;
                color: #FF6600;
            }
            .instructions ol, .instructions ul {
                margin: 5px 0;
                padding-left: 15px;
            }
            .instructions li {
                margin-bottom: 3px;
            }
            .compact-section {
                display: flex;
                gap: 10px;
                margin: 12px 0;
            }
            .compact-column {
                flex: 1;
            }
            .footer {
                margin-top: 20px;
                padding-top: 12px;
                border-top: 1px solid #ddd;
                text-align: center;
                color: #666;
                font-size: 9px;
            }
            .reference {
                text-align: right; 
                color: #666; 
                margin-bottom: 10px;
                font-size: 9px;
            }
        </style>
    </head>
    <body>
        <div class=\"header\">
            <h2>MES Society - Password Reset Confirmation</h2>
            <p>University of Lahore - Department of Mechanical Engineering</p>
        </div>
        
        <div class=\"title\">Password Reset Confirmation</div>
        
        <div class=\"reference\">
            Generated on: $currentDate<br>
            Reference: MES-PWD-{$user['sap_id']}-" . date('YmdHis') . "
        </div>
        
        <div class=\"user-info\">
            <table class=\"info-table\">
                <tr><td>Full Name:</td><td>{$user['name']}</td></tr>
                <tr><td>SAP ID:</td><td>{$user['sap_id']}</td></tr>
                <tr><td>Email:</td><td>{$user['email']}</td></tr>
                <tr><td>Department:</td><td>{$user['department']}</td></tr>
                <tr><td>Semester:</td><td>{$user['semester']}</td></tr>
                <tr><td>Role:</td><td>" . ucfirst(str_replace('_', ' ', $user['role'])) . "</td></tr>
            </table>
        </div>
        
        <div class=\"password-section\">
            <h3 style=\"color: #FF6600; margin: 0 0 8px 0; font-size: 12px;\">NEW TEMPORARY PASSWORD</h3>
            <div class=\"new-password\">$new_password</div>
            <p style=\"margin: 5px 0 0 0; font-size: 10px;\"><strong>Important:</strong> This is a temporary password. You must change it on first login.</p>
        </div>
        
        <div class=\"compact-section\">
            <div class=\"compact-column\">
                <div class=\"instructions\">
                    <h4>Login Instructions:</h4>
                    <ol>
                        <li>Go to: $siteUrl/login.php</li>
                        <li>Enter your email: {$user['email']}</li>
                        <li>Enter the temporary password</li>
                        <li>Set a new password when prompted</li>
                    </ol>
                </div>
            </div>
            <div class=\"compact-column\">
                <div class=\"instructions\">
                    <h4>Security Instructions:</h4>
                    <ul>
                        <li>Keep this document secure</li>
                        <li>Login immediately to change password</li>
                        <li>Use a strong, memorable password</li>
                        <li>Contact admin for any issues</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class=\"footer\">
            <strong>MES Society - University of Lahore</strong><br>
            This is a system-generated document. Please keep it secure.<br>
            Valid through: " . date('Y-m-d', strtotime('+7 days')) . "
        </div>
    </body>
    </html>
    ";
}
    
   private function getIDCardHTML($user) {
    // Get user posts from user data - REMOVED for PDF download
    // $user_posts = $user['posts'] ?? [];
    // $post_display = !empty($user_posts) ? implode(', ', $user_posts) : 'Member';
    
    // Get profile picture
    $profile_pic = '';
    if (!empty($user['profile_picture']) && $user['profile_picture'] !== 'default-avatar.png') {
        $profilePicturePath = realpath(__DIR__ . '/../../uploads/profile-pictures/' . $user['profile_picture']);
        if ($profilePicturePath && file_exists($profilePicturePath)) {
            $profile_pic = $this->imageToBase64($profilePicturePath);
        }
    }
    
    // Get MES logo - using the same logo as web preview
    $mes_logo = '';
    $mesLogoPath = realpath(__DIR__ . '/../../assets/images/logo-mes1.1.png');
    if ($mesLogoPath && file_exists($mesLogoPath)) {
        $mes_logo = $this->imageToBase64($mesLogoPath);
    }
    // Get QR code image
$qr_code = '';
$qrCodePath = realpath(__DIR__ . '/../../assets/images/MES UOL.png');
if ($qrCodePath && file_exists($qrCodePath)) {
    $qr_code = $this->imageToBase64($qrCodePath);
}
    
    // Get user initials for placeholder
    $initials = '';
    if (!empty($user['name'])) {
        $name_parts = explode(' ', $user['name']);
        $initials = '';
        foreach ($name_parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        $initials = substr($initials, 0, 2);
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
        <style>
            @page {
                margin: 0;
                padding: 0;
            }
            body { 
                font-family: DejaVu Sans, Arial, sans-serif; 
                margin: 0; 
                padding: 0; 
                width: 340px;
                height: 480px;
                background: linear-gradient(135deg, #FF6600 0%, #FF8533 100%);
                color: white;
            }
            .id-card-container {
                width: 340px;
                height: 480px;
                background: linear-gradient(135deg, #FF6600 0%, #FF8533 100%);
                border: 3px solid #000;
                border-radius: 12px;
                overflow: hidden;
                position: relative;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            }
            .id-card-header {
                background: #000000;
                padding: 12px 15px;
                text-align: center;
                border-bottom: 2px solid #FF6600;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 70px;
            }
            .logo-container {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .mes-logo {
                height: 35px;
                width: auto;
                margin-right: 10px;
            }
            .header-text {
                text-align: center;
            }
            .header-text h4 {
                margin: 0;
                font-size: 1.1rem;
                font-weight: 700;
                color: #FF6600;
                line-height: 1.1;
            }
            .header-text p {
                margin: 0;
                font-size: 0.8rem;
                opacity: 0.9;
                color: white;
                line-height: 1.1;
            }
            .id-card-body {
                padding: 20px;
                position: relative;
                height: 350px;
            }
            .id-photo-section {
                position: absolute;
                right: 20px;
                top: 20px;
                text-align: center;
            }
            .photo-container {
                width: 100px;
                height: 100px;
                border: 3px solid #000;
                border-radius: 8px;
                overflow: hidden;
                background: white;
            }
            .photo {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .photo-placeholder {
                width: 100%;
                height: 100%;
                background: #FF6600;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 1.5rem;
            }
            .validity {
                margin-top: 8px;
                font-size: 0.7rem;
                opacity: 0.8;
                color: #000;
                font-weight: bold;
            }
            .id-info-section {
                margin-right: 120px;
            }
            .info-row {
                margin-bottom: 10px;
            }
            .label {
                font-size: 0.8rem;
                opacity: 0.8;
                color: #000;
                font-weight: bold;
                margin-bottom: 2px;
            }
            .value {
                font-size: 1rem;
                font-weight: 600;
                color: #000;
            }
            .sap-value {
                font-size: 0.9rem;
                font-weight: 500;
                color: #000;
            }
            .dept-value {
                font-size: 0.9rem;
                font-weight: 500;
                color: #000;
            }
            .sem-value {
                font-size: 0.9rem;
                font-weight: 500;
                color: #000;
            }
            .id-qr-section {
                text-align: center;
                margin-top: 15px;
                padding-top: 12px;
                border-top: 2px solid #000;
                position: absolute;
                bottom: 50px;
                left: 20px;
                right: 20px;
            }
            .qr-container {
                background: white;
                display: inline-block;
                padding: 6px;
                border-radius: 6px;
                border: 2px solid #000;
            }
            .qr-code {
                width: 60px;
                height: 60px;
                object-fit: contain;
            }
            .qr-placeholder {
                width: 60px;
                height: 60px;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.6rem;
                color: #000;
                font-weight: bold;
                text-align: center;
                border: 1px solid #000;
            }
            .qr-label {
                font-size: 0.7rem;
                margin-top: 4px;
                opacity: 0.8;
                color: #000;
                font-weight: bold;
            }
            .id-card-footer {
                background: rgba(0, 0, 0, 0.9);
                padding: 8px 15px;
                text-align: center;
                font-size: 0.7rem;
                opacity: 0.9;
                color: #FF6600;
                font-weight: bold;
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
            }
        </style>
    </head>
    <body>
        <div class=\"id-card-container\">
            <!-- Header Section - Black with MES Logo -->
            <div class=\"id-card-header\">
                <div class=\"logo-container\">
                    " . ($mes_logo ? 
                        "<img src=\"$mes_logo\" class=\"mes-logo\" alt=\"MES Logo\">" : 
                        "<div style=\"width: 35px; height: 35px; background: #FF6600; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #000; font-weight: bold; font-size: 12px; margin-right: 10px;\">MES</div>"
                    ) . "
                    <div class=\"header-text\">
                        <h4>MECHANICAL ENGINEERING SOCIETY</h4>
                        <p>University of Lahore</p>
                    </div>
                </div>
            </div>
            
            <!-- Body Section -->
            <div class=\"id-card-body\">
                
                <!-- Photo Section -->
                <div class=\"id-photo-section\">
                    <div class=\"photo-container\">
                        " . ($profile_pic ? 
                            "<img src=\"$profile_pic\" class=\"photo\" alt=\"Photo\">" : 
                            "<div class=\"photo-placeholder\">$initials</div>"
                        ) . "
                    </div>
                    <div class=\"validity\">Valid: " . date('Y') . " - " . (date('Y') + 1) . "</div>
                </div>
                
                <!-- Information Section -->
                <div class=\"id-info-section\">
                    <!-- Name -->
                    <div class=\"info-row\">
                        <div class=\"label\">FULL NAME</div>
                        <div class=\"value\">" . strtoupper($user['name']) . "</div>
                    </div>
                    
                    <!-- SAP ID -->
                    <div class=\"info-row\">
                        <div class=\"label\">SAP ID</div>
                        <div class=\"sap-value\">{$user['sap_id']}</div>
                    </div>
                    
                    <!-- Department -->
                    <div class=\"info-row\">
                        <div class=\"label\">DEPARTMENT</div>
                        <div class=\"dept-value\">" . strtoupper($user['department']) . "</div>
                    </div>
                    
                    <!-- Semester -->
                    <div class=\"info-row\">
                        <div class=\"label\">SEMESTER</div>
                        <div class=\"sem-value\">{$user['semester']}</div>
                    </div>
                </div>
                
                  <!-- QR Code Section -->
                  <div class=\"id-qr-section\">
                      <div class=\"qr-container\">
                          " . ($qr_code ? 
                              "<img src=\"$qr_code\" class=\"qr-code\" alt=\"QR Code\">" : 
                              "<div class=\"qr-placeholder\">QR CODE<br>SCAN TO The MES Page</div>"
                          ) . "
                      </div>
                      <div class=\"qr-label\">Scan to verify membership</div>
                  </div>
            </div>
            
            <!-- Footer -->
            <div class=\"id-card-footer\">
                <div>Official ID Card - Mechanical Engineering Society</div>
                <div>Valid through: " . date('Y-m-d', strtotime('+1 year')) . "</div>
            </div>
        </div>
    </body>
    </html>
    ";
}
    
    private function getWelcomeLetterHTML($userData) {
        $siteUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
        $currentDate = date('F j, Y');
        
        // Get logo paths
        $mesLogoPath = realpath(__DIR__ . '/../../assets/images/logo-mes.png');
        $universityLogoPath = realpath(__DIR__ . '/../../assets/images/logo-university.png');
        
        // Convert logos to base64 for embedding in PDF
        $mesLogo = $this->imageToBase64($mesLogoPath);
        $universityLogo = $this->imageToBase64($universityLogoPath);
        
        // Get user profile picture if exists
        $userPhoto = '';
        if (!empty($userData['profile_picture']) && $userData['profile_picture'] !== 'default-avatar.png') {
            $profilePicturePath = realpath(__DIR__ . '/../../uploads/profile-pictures/' . $userData['profile_picture']);
            if ($profilePicturePath && file_exists($profilePicturePath)) {
                $userPhoto = $this->imageToBase64($profilePicturePath);
            }
        }

        // Ensure password is set and displayed - FIXED PASSWORD DISPLAY
        $tempPassword = $userData['temp_password'] ?? 'Not generated - Please contact administrator';
        if (empty($tempPassword)) {
            $tempPassword = 'Not generated - Please contact administrator';
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
            <style>
                @page {
                    margin: 20px;
                }
                body { 
                    font-family: DejaVu Sans, Arial, sans-serif; 
                    margin: 0; 
                    padding: 0; 
                    color: #333; 
                    line-height: 1.4;
                    font-size: 12px;
                }
                .header { 
                    width: 100%;
                    margin-bottom: 15px; 
                    border-bottom: 3px solid #FF6600; 
                    padding-bottom: 10px; 
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .header-table td {
                    vertical-align: top;
                    padding: 0;
                }
                .logo-left {
                    text-align: left;
                    width: 50%;
                }
                .logo-right {
                    text-align: right;
                    width: 50%;
                }
                .logo-container {
                    display: inline-block;
                    text-align: center;
                    vertical-align: top;
                }
                .logo {
                    height: 40px;
                    width: auto;
                    max-width: 120px;
                    vertical-align: middle;
                }
                .logo-text {
                    display: inline-block;
                    vertical-align: middle;
                    text-align: left;
                    margin-left: 8px;
                    line-height: 1.1;
                }
                .logo-right .logo-text {
                    text-align: right;
                    margin-left: 0;
                    margin-right: 8px;
                }
                .logo-text h3 {
                    margin: 0;
                    color: #000000;
                    font-size: 12px;
                    font-weight: bold;
                    line-height: 1.1;
                }
                .logo-text p {
                    margin: 0;
                    color: #666;
                    font-size: 9px;
                    line-height: 1.1;
                }
                .title { 
                    text-align: center; 
                    color: #FF6600; 
                    margin: 15px 0; 
                    font-size: 16px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .date { 
                    text-align: right; 
                    color: #666; 
                    margin-bottom: 12px; 
                    font-size: 10px;
                }
                .user-info { 
                    margin: 12px 0; 
                }
                .info-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 8px 0;
                }
                .info-table td { 
                    padding: 5px 0; 
                    border-bottom: 1px solid #eee; 
                    vertical-align: top;
                    font-size: 10px;
                }
                .info-table td:first-child { 
                    font-weight: bold; 
                    width: 30%; 
                    color: #333;
                }
                .highlight {
                    color: #FF6600;
                    font-weight: bold;
                }
                .credentials { 
                    background: #FFF8F0; 
                    border: 2px solid #FF6600; 
                    border-radius: 5px; 
                    padding: 10px; 
                    margin: 12px 0; 
                    font-size: 10px;
                }
                .footer { 
                    margin-top: 20px; 
                    padding-top: 12px; 
                    border-top: 1px solid #FF6600; 
                    text-align: center; 
                    color: #666; 
                    font-size: 8px; 
                }
                .welcome-message { 
                    line-height: 1.5; 
                    margin: 12px 0; 
                    font-size: 10px;
                }
                ul, ol { 
                    line-height: 1.5; 
                    margin: 8px 0;
                    padding-left: 12px;
                    font-size: 10px;
                }
                .section-title {
                    color: #FF6600;
                    font-size: 11px;
                    margin: 12px 0 8px 0;
                    border-bottom: 1px solid #FF6600;
                    padding-bottom: 2px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .user-photo-section {
                    text-align: center;
                    margin: 8px 0;
                }
                .user-photo {
                    width: 70px;
                    height: 70px;
                    border: 2px solid #FF6600;
                    border-radius: 5px;
                    object-fit: cover;
                }
                .photo-placeholder {
                    width: 70px;
                    height: 70px;
                    background: #f8f9fa;
                    border: 2px solid #FF6600;
                    border-radius: 5px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    color: #666;
                    font-size: 8px;
                    text-align: center;
                }
                .password-box {
                    background: #fff;
                    border: 1px dashed #FF6600;
                    padding: 8px;
                    margin: 5px 0;
                    border-radius: 3px;
                    font-family: monospace;
                    font-size: 11px;
                    text-align: center;
                    font-weight: bold;
                    color: #d9534f;
                }
                .warning-note {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 3px;
                    padding: 8px;
                    margin: 8px 0;
                    font-size: 9px;
                    color: #856404;
                }
            </style>
        </head>
        <body>
            <div class=\"header\">
                <table class=\"header-table\">
                    <tr>
                        <td class=\"logo-left\">
                            <div class=\"logo-container\">
                                " . ($mesLogo ? "<img src=\"$mesLogo\" class=\"logo\" alt=\"MES Society\">" : "") . "
                                <div class=\"logo-text\">
                                    <h3>MES Society</h3>
                                    <p>Mechanical Engineering Society</p>
                                </div>
                            </div>
                        </td>
                        <td class=\"logo-right\">
                            <div class=\"logo-container\">
                                <div class=\"logo-text\">
                                    <h3>University of Lahore</h3>
                                    <p>Department of Mechanical Engineering</p>
                                </div>
                                " . ($universityLogo ? "<img src=\"$universityLogo\" class=\"logo\" alt=\"University of Lahore\">" : "") . "
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class=\"title\">Membership Confirmation Letter</div>
            
            <div class=\"date\">Date Issued: $currentDate</div>
            
            <div class=\"user-photo-section\">
                " . ($userPhoto ? 
                    "<img src=\"$userPhoto\" class=\"user-photo\" alt=\"{$userData['name']}\">" : 
                    "<div class=\"photo-placeholder\">Member<br>Photo</div>"
                ) . "
            </div>
            
            <div class=\"user-info\">
                <table class=\"info-table\">
                    <tr><td>Full Name:</td><td class=\"highlight\">{$userData['name']}</td></tr>
                    <tr><td>SAP ID:</td><td>" . ($userData['sap_id'] ?: 'Not Provided') . "</td></tr>
                    <tr><td>Department:</td><td>" . ($userData['department'] ?: 'Not Provided') . "</td></tr>
                    <tr><td>Semester:</td><td>" . ($userData['semester'] ?: 'Not Provided') . "</td></tr>
                    <tr><td>Email:</td><td>{$userData['email']}</td></tr>
                    <tr><td>Phone:</td><td>" . ($userData['phone'] ?: 'Not Provided') . "</td></tr>
                    <tr><td>Assigned Post:</td><td class=\"highlight\">" . ($userData['posts'] ?: 'General Member') . "</td></tr>
                </table>
            </div>
            
            <div class=\"credentials\">
                <div class=\"section-title\">Login Credentials</div>
                <table style=\"width: 100%; font-size: 10px;\">
                    <tr>
                        <td style=\"width: 35%; font-weight: bold;\">Member Portal:</td>
                        <td>{$siteUrl}/member/login.php</td>
                    </tr>
                    <tr>
                        <td style=\"font-weight: bold;\">Email:</td>
                        <td>{$userData['email']}</td>
                    </tr>
                    <tr>
                        <td style=\"font-weight: bold;\">Temporary Password:</td>
                        <td>
                            <div class=\"password-box\">{$tempPassword}</div>
                        </td>
                    </tr>
                </table>
                <div class=\"warning-note\">
                    <strong>⚠️ IMPORTANT:</strong> Save this password securely. You must change it immediately after first login for security reasons.
                </div>
            </div>
            
            <div class=\"welcome-message\">
                <p>Dear <strong class=\"highlight\">{$userData['name']}</strong>,</p>
                
                <p>We are delighted to inform you that your membership application for the Mechanical Engineering Society (MES) has been approved. Welcome to our vibrant community of engineering enthusiasts!</p>
                
                <div class=\"section-title\">Membership Benefits</div>
                <ul>
                    <li>Access to exclusive technical workshops, seminars, and training sessions</li>
                    <li>Participation in national and international engineering competitions</li>
                    <li>Networking opportunities with industry professionals and alumni</li>
                    <li>Skill development programs and certification opportunities</li>
                    <li>Leadership roles and committee participation</li>
                    <li>Access to MES resources and library materials</li>
                    <li>Career guidance and internship opportunities</li>
                </ul>
                
                <div class=\"section-title\">Getting Started</div>
                <ol>
                    <li><strong>Login Immediately:</strong> Access the member portal using the credentials above</li>
                    <li><strong>Complete Your Profile:</strong> Update your personal and academic information</li>
                    <li><strong>Download Digital ID:</strong> Generate your membership ID card from the dashboard</li>
                    <li><strong>Explore Events:</strong> Browse and register for upcoming events and activities</li>
                    <li><strong>Change Password:</strong> Set a secure personal password on first login</li>
                    <li><strong>Join Community:</strong> Connect with fellow members and committee heads</li>
                </ol>
                
                <p>Your unique perspective and skills will be valuable assets to our society. We encourage you to actively participate in our events and contribute to our growing community.</p>
                
                <p>Should you require any assistance or have questions regarding your membership, please don't hesitate to contact the MES executive committee.</p>
                
                <p>Once again, welcome aboard! We look forward to your active participation and contributions.</p>
                
                <div style=\"margin-top: 25px;\">
                    <strong>With warm regards,</strong><br>
                    <div style=\"color: #FF6600; font-size: 11px; margin-top: 5px; font-weight: bold;\">
                        MES Society Executive Board<br>
                        Department of Mechanical Engineering<br>
                        University of Lahore<br>
                        Email: mesuolofficial@gmail.com | Phone: +92 313 3150346
                    </div>
                </div>
            </div>
            
            <div class=\"footer\">
                <strong>Official Membership Confirmation - Mechanical Engineering Society</strong><br>
                This is an auto-generated digital confirmation letter. No physical signature is required.<br>
                MES Society - University of Lahore | {$siteUrl} | Valid through academic year " . date('Y') . "-" . (date('Y')+1) . "
            </div>
        </body>
        </html>
        ";
    }
    
    private function imageToBase64($imagePath) {
        if (!file_exists($imagePath)) {
            return null;
        }
        
        try {
            $imageData = file_get_contents($imagePath);
            if ($imageData === false) {
                return null;
            }
            
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                return null;
            }
            
            $mimeType = $imageInfo['mime'];
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            
        } catch (Exception $e) {
            error_log("Image conversion error: " . $e->getMessage());
            return null;
        }
    }
    
    private function getQRCodeImage() {
        $qrCodePath = realpath(__DIR__ . '/../../assets/images/qr-code-mes.png');
        if ($qrCodePath && file_exists($qrCodePath)) {
            return $this->imageToBase64($qrCodePath);
        }
        return null;
    }
    
    private function getUserPosts($userId) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(PDOException $e) {
            error_log("Get user posts error: " . $e->getMessage());
            return [];
        }
    }
    
    public function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
?>