<?php
require_once '../includes/session.php';
$session = new SessionManager();
if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
$user = $session->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Enable Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f57c00; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Poppins', sans-serif; }
        .card { max-width: 400px; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); text-align: center; background: white; }
        .btn-enable { background: #f57c00; color: white; font-weight: bold; padding: 15px 30px; border-radius: 50px; border: none; font-size: 18px; width: 100%; }
        .btn-skip { background: transparent; color: #6c757d; border: 1px solid #6c757d; padding: 10px 20px; border-radius: 50px; margin-top: 15px; width: 100%; }
        .logo { height: 80px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <img src="<?php echo SITE_URL; ?>/assets/images/logo-mes.png" alt="MES Logo" class="logo">
        <h2>Stay Updated</h2>
        <p>Allow notifications to receive important announcements, duty alerts, and event reminders.</p>
        <button class="btn-enable" id="enableBtn">Enable Notifications</button>
        <button class="btn-skip" id="skipBtn">Not Now</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const VAPID_PUBLIC_KEY = 'BN6yll4C3KBHrJiYCrZiQcAZT_xC3k9U0sXfqmU-Iy_ET-2x8TqnOuMuUOH83oIzHsCI4qAkI9DB9Ry7Wm2Pfyc';

        function urlBase64ToUint8Array(b) {
            const pad = '='.repeat((4 - b.length % 4) % 4);
            const base64 = (b + pad).replace(/-/g, '+').replace(/_/g, '/');
            return new Uint8Array([...atob(base64)].map(c => c.charCodeAt(0)));
        }

        async function enableNotifications() {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    Swal.fire({ icon: 'warning', title: 'Permission Denied', text: 'You can enable later from settings.' });
                    redirectToDashboard();
                    return;
                }

                // Unregister old workers first
                const oldRegs = await navigator.serviceWorker.getRegistrations();
                for (let reg of oldRegs) await reg.unregister();

                // Register the correct service worker
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
                    localStorage.setItem('onboarding_complete', 'true');
                    Swal.fire({ icon: 'success', title: 'Notifications Enabled', text: 'Thank you!', confirmButtonColor: '#f57c00' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: result.error });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error', text: e.message });
            } finally {
                redirectToDashboard();
            }
        }

        function redirectToDashboard() {
            window.location.href = '/mes-society/member/dashboard.php';
        }

        if (localStorage.getItem('onboarding_complete') === 'true') {
            redirectToDashboard();
        }

        document.getElementById('enableBtn').addEventListener('click', enableNotifications);
        document.getElementById('skipBtn').addEventListener('click', function() {
            localStorage.setItem('onboarding_complete', 'true');
            redirectToDashboard();
        });
    </script>
</body>
</html>