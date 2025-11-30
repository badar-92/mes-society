/* [file name]: mobile-sidebar.js */
// Mobile Offcanvas Sidebar Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const offcanvasSidebar = document.querySelector('.offcanvas-sidebar');
    
    if (sidebarToggle && offcanvasSidebar) {
        // Create overlay if it doesn't exist
        let overlay = document.querySelector('.offcanvas-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'offcanvas-overlay';
            document.body.appendChild(overlay);
        }
        
        // Toggle sidebar
        sidebarToggle.addEventListener('click', function() {
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
        
        // Close sidebar when clicking nav links on mobile
        const navLinks = offcanvasSidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                offcanvasSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        });
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && offcanvasSidebar.classList.contains('show')) {
                offcanvasSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
    
    // Enhance touch interactions
    document.querySelectorAll('.btn, .card, .nav-link').forEach(element => {
        element.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        element.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
});