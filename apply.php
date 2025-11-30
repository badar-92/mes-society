<?php
$page_title = "Apply for Membership";
require_once '../includes/header.php';
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Apply for Membership</h1>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div id="alertContainer"></div>

            <form method="post" enctype="multipart/form-data" id="applicationForm">
                <!-- Personal Information -->
                <div class="card mb-4">
                    <div class="card-header bg-accent text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sap_id" class="form-label">SAP ID *</label>
                                    <input type="text" class="form-control" id="sap_id" name="sap_id" required>
                                    <div class="form-text">Must be numeric</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department *</label>
                                    <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Mechanical Engineering, Computer Science" required>
                                    <div class="form-text">Enter your department name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="semester" class="form-label">Current Semester *</label>
                                    <input type="number" class="form-control" id="semester" name="semester" min="1" max="12" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">University Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">WhatsApp Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="card mb-4">
                    <div class="card-header bg-accent text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cgpa" class="form-label">Current CGPA *</label>
                                    <input type="number" step="0.01" class="form-control" id="cgpa" name="cgpa" min="0" max="4" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_courses" class="form-label">Current Courses *</label>
                                    <input type="text" class="form-control" id="current_courses" name="current_courses" placeholder="e.g., Thermodynamics, Fluid Mechanics" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skills and Experience -->
                <div class="card mb-4">
                    <div class="card-header bg-accent text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-tools me-2"></i>Skills and Experience</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="skills_experience" class="form-label">Describe your skills, experience, and achievements *</label>
                            <textarea class="form-control" id="skills_experience" name="skills_experience" rows="5" placeholder="Include technical skills, soft skills, projects, competitions, etc." required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Portfolio and Motivation -->
                <div class="card mb-4">
                    <div class="card-header bg-accent text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-link me-2"></i>Portfolio and Motivation</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="portfolio_links" class="form-label">Portfolio Links (Optional)</label>
                            <textarea class="form-control" id="portfolio_links" name="portfolio_links" rows="3" placeholder="e.g., GitHub, LinkedIn, Behance, Personal Website"></textarea>
                            <div class="form-text">Include links to your online portfolios or projects</div>
                        </div>

                        <div class="mb-3">
                            <label for="motivation_statement" class="form-label">Why do you want to join MES Society? *</label>
                            <textarea class="form-control" id="motivation_statement" name="motivation_statement" rows="5" placeholder="Tell us about your motivation, what you hope to gain, and how you can contribute..." required></textarea>
                        </div>
                    </div>
                </div>

                <!-- File Uploads -->
                <div class="card mb-4">
                    <div class="card-header bg-accent text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-upload me-2"></i>Document Upload</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="profile_picture" class="form-label">Profile Picture *</label>
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png" required>
                                    <div class="form-text">Clear photo of yourself. Any aspect ratio accepted, max 2MB</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="resume" class="form-label">Upload Resume (PDF, max 2MB) - Optional</label>
                                    <input type="file" class="form-control" id="resume" name="resume" accept=".pdf">
                                    <div class="form-text">Upload your updated resume/CV in PDF format (Optional)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-accent btn-lg px-5" id="submitBtn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                    </button>
                    <p class="text-muted mt-2">We'll review your application and contact you for the next steps</p>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('applicationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Validate file sizes and types
    const profilePicture = document.getElementById('profile_picture');
    const resume = document.getElementById('resume');
    
    // Validate profile picture (REQUIRED)
    if (profilePicture.files.length === 0) {
        showAlert('Profile picture is required. Please upload a clear photo of yourself.', 'danger');
        return false;
    }
    
    if (profilePicture.files.length > 0) {
        const file = profilePicture.files[0];
        const fileSize = file.size / 1024 / 1024; // in MB
        const fileType = file.type;
        
        if (fileSize > 2) {
            showAlert('Profile picture size must be less than 2MB', 'danger');
            return false;
        }
        
        if (!['image/jpeg', 'image/jpg', 'image/png'].includes(fileType)) {
            showAlert('Profile picture must be in JPG or PNG format', 'danger');
            return false;
        }
    }
    
    // Validate resume if provided
    if (resume.files.length > 0) {
        const file = resume.files[0];
        const fileSize = file.size / 1024 / 1024; // in MB
        const fileType = file.type;
        
        if (fileSize > 2) {
            showAlert('Resume file size must be less than 2MB', 'danger');
            return false;
        }
        
        if (fileType !== 'application/pdf') {
            showAlert('Resume must be in PDF format', 'danger');
            return false;
        }
    }
    
    // Disable submit button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    
    // Create FormData object
    const formData = new FormData(this);
    
    // Submit via AJAX
    fetch('../api/applications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            document.getElementById('applicationForm').reset();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('There was an error submitting your application. Please try again.', 'danger');
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    
    alertContainer.innerHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Scroll to top to show message
    window.scrollTo(0, 0);
    
    // Auto-dismiss success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }
}

// Real-time SAP ID validation
document.getElementById('sap_id').addEventListener('input', function(e) {
    const value = e.target.value;
    if (value && !/^\d+$/.test(value)) {
        e.target.setCustomValidity('SAP ID must contain only numbers');
    } else {
        e.target.setCustomValidity('');
    }
});

// Preview profile picture before upload
document.getElementById('profile_picture').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // You can add a preview feature here if needed
            console.log('Profile picture selected:', file.name);
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>