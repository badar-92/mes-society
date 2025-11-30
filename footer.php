    </main>

    <!-- Footer -->
    <footer style="background: #000000; color: white; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 style="color: #FF6600;">MES Society</h5>
                    <p>Mechanical Engineering Society<br>
                    University of Lahore<br>
                    Department of Mechanical Engineering</p>
                    <div class="social-links">
                       <!-- <a href="#" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fab fa-facebook-f"></i> -->
                        </a>
                        <a href="https://www.instagram.com/mes_uol.official?igsh=MWJwYXBtazY0ZTM4eA==" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fab fa-instagram"></i>
                        </a>
                        
                        <a href="mailto:mesuolofficial@gmail.com" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fas fa-envelope"></i>
                        </a>
                      <!--   <a href="#" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" style="color: white; text-decoration: none;">
                            <i class="fab fa-twitter"></i> -->
                        </a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 style="color: #FF6600;">Quick Links</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li><a href="<?php echo SITE_URL; ?>/public/about.php" style="color: #ccc; text-decoration: none;">About Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/events.php" style="color: #ccc; text-decoration: none;">Events</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/gallery.php" style="color: #ccc; text-decoration: none;">Gallery</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/competitions.php" style="color: #ccc; text-decoration: none;">Competitions</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/public/apply.php" style="color: #ccc; text-decoration: none;">Join Society</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 style="color: #FF6600;">Contact Info</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 8px;">
                            <i class="fas fa-envelope" style="color: #FF6600; margin-right: 8px;"></i>
                            mesuolofficial@gmail.com 
                        </li>
                        <li style="margin-bottom: 8px;">
                            <i class="fas fa-phone" style="color: #FF6600; margin-right: 8px;"></i>
                            +92 313 3150346
                        </li>
                        <li style="margin-bottom: 8px;">
                            <i class="fas fa-map-marker-alt" style="color: #FF6600; margin-right: 8px;"></i>
                            University of Lahore
                        </li>
                    </ul>
                </div>
            </div>
            <hr style="border-color: #333; margin: 2rem 0;">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p style="margin: 0; color: #ccc;">&copy; <?php echo date('Y'); ?> MES Society. All rights reserved.</p>
                </div>
<div class="col-md-6 text-md-end">
    <a href="<?php echo SITE_URL; ?>/public/privacy-policy.php" style="color: #ccc; text-decoration: none; margin-right: 15px;">Privacy Policy</a>
    <a href="<?php echo SITE_URL; ?>/public/terms-of-service.php" style="color: #ccc; text-decoration: none;">Terms of Service</a>
</div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/custom.js"></script>

    <script>
        // Basic JavaScript functionality
    document.addEventListener('DOMContentLoaded', function() {
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
        });
          
    </script>
<!-- Mobile Sidebar Script -->
<script src="<?php echo SITE_URL; ?>/assets/js/mobile-sidebar.js"></script>
    
</body>
</html>