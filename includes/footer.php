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
                        <a href="https://www.facebook.com/share/15XbQPXUnZ/" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fab fa-facebook-f"></i> 
                        </a>
                        <a href="https://www.instagram.com/mes_uol.official?igsh=MWJwYXBtazY0ZTM4eA==" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="mailto:mesuolofficial@gmail.com" style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fas fa-envelope"></i>
                        </a>
                        <a href="https://www.linkedin.com/company/mesuol/ " style="color: white; margin-right: 15px; text-decoration: none;">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="https://wa.me/923146139384" style="color: white; margin-right: 15px; text-decoration: none;" target="_blank">
                            <i class="fab fa-whatsapp"></i>
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
                            <a href="mailto:mesuolofficial@gmail.com" style="color: #ccc; text-decoration: none;">mesuolofficial@gmail.com</a>
                        </li>
                        <li style="margin-bottom: 8px;">
                            <i class="fas fa-phone" style="color: #FF6600; margin-right: 8px;"></i>
                            <a href="tel:+923133150346" style="color: #ccc; text-decoration: none;">+92 313 3150346</a>
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
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);

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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SELF-HOSTED PUSH NOTIFICATIONS -->
    <script>
        const VAPID_PUBLIC_KEY = 'BN6yll4C3KBHrJiYCrZiQcAZT_xC3k9U0sXfqmU-Iy_ET-2x8TqnOuMuUOH83oIzHsCI4qAkI9DB9Ry7Wm2Pfyc';

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            return new Uint8Array([...rawData].map(char => char.charCodeAt(0)));
        }

        async function subscribeUserToPush(showSuccess = true) {
            try {
                // Unregister old workers first
                const oldRegs = await navigator.serviceWorker.getRegistrations();
                for (let reg of oldRegs) await reg.unregister();

                // Register the local service worker
                const registration = await navigator.serviceWorker.register(
                    '/mes-society/sw.js',
                    { scope: '/mes-society/' }
                );
                await navigator.serviceWorker.ready;

                let subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                    });
                }

                const response = await fetch('/mes-society/api/save-subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(subscription)
                });
                const result = await response.json();
                if (result.success) {
                    if (showSuccess) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Notifications Enabled',
                            text: 'You will now receive important updates.',
                            confirmButtonColor: '#f57c00'
                        });
                    } else {
                        console.log('Push subscription renewed silently.');
                    }
                    return true;
                } else {
                    throw new Error(result.error || 'Backend error');
                }
            } catch (error) {
                console.error('Push subscription failed:', error);
                if (showSuccess) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Could Not Enable Notifications',
                        text: error.message,
                        confirmButtonColor: '#f57c00'
                    });
                }
                return false;
            }
        }

        async function requestNotificationPermission() {
            if (!('Notification' in window)) {
                Swal.fire({ icon: 'info', title: 'Not Supported', confirmButtonColor: '#f57c00' });
                return;
            }
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                await subscribeUserToPush(true);   // show success popup when user clicks
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Permission Denied',
                    text: 'You can enable notifications later from your browser settings.',
                    confirmButtonColor: '#f57c00'
                });
            }
        }

        // Silent auto-subscribe if permission already granted (no popup)
        (async function autoSubscribeIfGranted() {
            if (Notification.permission === 'granted') {
                try {
                    const reg = await navigator.serviceWorker.getRegistration();
                    if (!reg) {
                        await subscribeUserToPush(false);   // silent
                    } else {
                        const sub = await reg.pushManager.getSubscription();
                        if (!sub) {
                            await subscribeUserToPush(false);   // silent
                        }
                    }
                } catch (e) {
                    console.warn('Auto-subscribe check failed', e);
                }
            }
        })();

        // Auto-prompt if permission is 'default' (shows popup because user must click allow)
        document.addEventListener('DOMContentLoaded', () => {
            if (Notification.permission === 'default') {
                setTimeout(() => requestNotificationPermission(), 2000);
            }
        });
    </script>

</body>
</html>