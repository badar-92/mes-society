// MES Society Website Custom JavaScript - CLEANED (Feed features removed)
document.addEventListener('DOMContentLoaded', function() {
    console.log('MES Society Website loaded successfully!');
    
    // Initialize all components
    initializeBootstrapComponents();
    setupEventListeners();
    setupSmoothScrolling();
    addBackToTopButton();
    initializeTeamCarousel();
    initializeLazyLoading();
    initializeFormValidation();
    initializeTouchpadFriendlyInteractions();
    initializeAdminSidebar();
});

function initializeBootstrapComponents() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

function setupEventListeners() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Image preview for file uploads
    document.querySelectorAll('input[type="file"]').forEach(function(input) {
        input.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                var preview = this.parentNode.querySelector('.image-preview');
                
                if (preview) {
                    reader.onload = function(e) {
                        preview.innerHTML = '<img src="' + e.target.result + '" class="img-thumbnail mt-2" style="max-height: 150px;">';
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            }
        });
    });
}

function setupSmoothScrolling() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

function addBackToTopButton() {
    // Check if button already exists to prevent duplicates
    if (document.querySelector('.back-to-top')) {
        console.log('Back to top button already exists');
        return;
    }

    // Create back to top button
    var backToTopButton = document.createElement('button');
    backToTopButton.innerHTML = '<i class="fas fa-chevron-up"></i>';
    backToTopButton.className = 'back-to-top';
    backToTopButton.setAttribute('title', 'Back to top');
    backToTopButton.style.cssText = `
        position: fixed; 
        bottom: 30px; 
        right: 30px; 
        display: none; 
        z-index: 1000; 
        width: 50px; 
        height: 50px; 
        border-radius: 50%; 
        background: #FF6600; 
        border: none; 
        color: white; 
        cursor: pointer;
        transition: all 0.3s ease;
    `;
    
    // Add hover effect
    backToTopButton.addEventListener('mouseenter', function() {
        this.style.background = '#e55a00';
        this.style.transform = 'scale(1.1)';
    });
    
    backToTopButton.addEventListener('mouseleave', function() {
        this.style.background = '#FF6600';
        this.style.transform = 'scale(1)';
    });

    document.body.appendChild(backToTopButton);

    // Show/hide back to top button
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.style.display = 'block';
        } else {
            backToTopButton.style.display = 'none';
        }
    });

    // Back to top functionality
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
}

// Team carousel auto-advance
function initializeTeamCarousel() {
    const carousel = document.getElementById('teamCarousel');
    if (carousel) {
        // Auto-advance every 5 seconds
        setInterval(() => {
            const carouselInstance = bootstrap.Carousel.getInstance(carousel);
            if (carouselInstance) {
                carouselInstance.next();
            }
        }, 5000);
    }
}

// Enhanced notification system
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show notification`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

// Image lazy loading
function initializeLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.getAttribute('data-src');
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Enhanced form validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Add shake animation to invalid fields
                const invalidFields = form.querySelectorAll(':invalid');
                invalidFields.forEach(field => {
                    field.classList.add('is-invalid');
                    
                    // Remove animation class after animation completes
                    setTimeout(() => {
                        field.classList.remove('shake-animation');
                    }, 500);
                });
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        });
    });
}

// Touchpad-friendly interactions for laptops
function initializeTouchpadFriendlyInteractions() {
    console.log('Initializing touchpad-friendly interactions...');
    
    // Improved dropdown handling for laptops/touchpads
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        // Remove any hover-based behaviors
        toggle.addEventListener('mouseenter', function(e) {
            e.stopPropagation();
        });
        
        // Ensure clean click behavior
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

    // Improved modal handling
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetModal = document.querySelector(this.getAttribute('data-bs-target'));
            if (targetModal) {
                // Close any open dropdowns first
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                // Then open the modal
                const modal = new bootstrap.Modal(targetModal);
                modal.show();
            }
        });
    });

    // Prevent table row hover effects from interfering
    const tableRows = document.querySelectorAll('.table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function(e) {
            this.style.backgroundColor = 'rgba(0,0,0,0.02)';
        });
        
        row.addEventListener('mouseleave', function(e) {
            this.style.backgroundColor = '';
        });
        
        // Prevent click on entire row
        row.addEventListener('click', function(e) {
            if (!e.target.closest('.btn') && !e.target.closest('.dropdown')) {
                e.stopPropagation();
            }
        });
    });

    // Handle escape key to close dropdowns
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
}

// Utility functions
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Password reset functionality
function resetUserPassword(userId, userName) {
    if (confirm(`Are you sure you want to reset password for ${userName}? A PDF with new password will be generated.`)) {
        fetch('generate-password-reset-pdf.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Password reset successful! PDF generated.', 'success');
                // Auto-download the PDF
                window.location.href = data.download_url;
            } else {
                showNotification('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Network error occurred.', 'danger');
        });
    }
}

// Initialize admin sidebar functionality
function initializeAdminSidebar() {
    const adminSidebarToggle = document.querySelector('.admin-sidebar-toggle');
    const offcanvasSidebar = document.querySelector('.offcanvas-sidebar');
    const adminSidebarOverlay = document.querySelector('.admin-sidebar-overlay');
    const mobileSidebarClose = document.querySelector('.mobile-sidebar-close');
    
    if (adminSidebarToggle && offcanvasSidebar) {
        // Create overlay if it doesn't exist
        if (!adminSidebarOverlay) {
            const overlay = document.createElement('div');
            overlay.className = 'admin-sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        const overlay = document.querySelector('.admin-sidebar-overlay');
        
        // Toggle sidebar
        adminSidebarToggle.addEventListener('click', function() {
            offcanvasSidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            document.body.style.overflow = offcanvasSidebar.classList.contains('show') ? 'hidden' : '';
        });
        
        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function() {
            offcanvasSidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        });
        
        // Close sidebar when clicking close button
        if (mobileSidebarClose) {
            mobileSidebarClose.addEventListener('click', function() {
                offcanvasSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
        
        // Close sidebar when clicking nav links on mobile
        if (window.innerWidth < 768) {
            const navLinks = offcanvasSidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    offcanvasSidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    document.body.style.overflow = '';
                });
            });
        }
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && offcanvasSidebar.classList.contains('show')) {
                offcanvasSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
}