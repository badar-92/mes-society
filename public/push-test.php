<?php
require_once '../includes/session.php';
$session = new SessionManager();
$session->requireLogin();

$user = $session->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Push Diagnostic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 15px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .badge { padding: 4px 8px; border-radius: 20px; color: white; font-weight: bold; }
        .green { background: #4CAF50; }
        .red { background: #f44336; }
        .yellow { background: #ff9800; }
        button { background: #f57c00; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 16px; margin: 5px; }
        pre { background: #eee; padding: 10px; overflow-x: auto; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>🔔 Push Notification Diagnostic</h2>
    <p>Logged in as: <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>

    <div class="card">
        <h3>1. Service Worker</h3>
        <div id="sw-status">Checking...</div>
        <button id="force-register-sw">Force Register SW</button>
    </div>

    <div class="card">
        <h3>2. Notification Permission</h3>
        <div id="perm-status">Checking...</div>
        <button id="request-permission-btn">Request Permission</button>
    </div>

    <div class="card">
        <h3>3. Push Subscription</h3>
        <div id="sub-status">Checking...</div>
        <button id="subscribe-btn">Subscribe</button>
        <button id="test-notification-btn">Send Test Notification</button>
    </div>

    <div class="card">
        <h3>4. Raw Data</h3>
        <pre id="raw-data">Click buttons above</pre>
    </div>

   <script>
    const vapidKey = 'BN6yll4C3KBHrJiYCrZiQcAZT_xC3k9U0sXfqmU-Iy_ET-2x8TqnOuMuUOH83oIzHsCI4qAkI9DB9Ry7Wm2Pfyc';
    let logs = [];

    function addLog(msg) {
        logs.push(new Date().toLocaleTimeString() + ': ' + msg);
        if (logs.length > 20) logs.shift();
        document.getElementById('raw-data').textContent = logs.join('\n');
    }

    function urlBase64ToUint8Array(b) {
        const pad = '='.repeat((4 - b.length % 4) % 4);
        const base64 = (b + pad).replace(/-/g, '+').replace(/_/g, '/');
        return new Uint8Array([...atob(base64)].map(c => c.charCodeAt(0)));
    }

    async function checkSW() {
        const reg = await navigator.serviceWorker.getRegistration();
        const status = reg ? 'Registered' : 'Not Registered';
        document.getElementById('sw-status').innerHTML = reg 
            ? `<span class="badge green">${status}</span> Scope: ${reg.scope}` 
            : `<span class="badge red">${status}</span>`;
        addLog('SW status: ' + status);
        return reg;
    }

    async function checkPermission() {
        const perm = Notification.permission;
        let color = perm === 'granted' ? 'green' : (perm === 'denied' ? 'red' : 'yellow');
        document.getElementById('perm-status').innerHTML = `<span class="badge ${color}">${perm}</span>`;
        addLog('Permission: ' + perm);
        return perm;
    }

    async function checkSubscription() {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        document.getElementById('sub-status').innerHTML = sub 
            ? `<span class="badge green">Subscribed</span> Endpoint: ${sub.endpoint.substring(0,50)}...` 
            : `<span class="badge red">Not Subscribed</span>`;
        addLog('Subscription: ' + (sub ? 'exists' : 'none'));
        return sub;
    }

    async function refreshAll() {
        await checkSW();
        await checkPermission();
        await checkSubscription();
    }

    // Force register minimal SW
    document.getElementById('force-register-sw').addEventListener('click', async () => {
        try {
            addLog('Unregistering existing...');
            const oldReg = await navigator.serviceWorker.getRegistration();
            if (oldReg) {
                await oldReg.unregister();
                addLog('Old SW unregistered');
            }
            
            addLog('Registering /mes-society/sw-min.php...');
            const newReg = await navigator.serviceWorker.register('/mes-society/sw-min.php', { scope: '/mes-society/' });
            addLog('Register promise resolved');
            
            // Wait for activation
            if (newReg.installing) {
                addLog('Waiting for SW to activate...');
                await new Promise(resolve => {
                    newReg.installing.addEventListener('statechange', function() {
                        if (this.state === 'activated') {
                            addLog('SW activated');
                            resolve();
                        }
                    });
                });
            }
            
            await refreshAll();
            addLog('Registration complete');
        } catch(e) {
            addLog('ERROR: ' + e.message);
            alert('SW registration failed: ' + e.message);
        }
    });

    // Request permission
    document.getElementById('request-permission-btn').addEventListener('click', async function() {
        const perm = await Notification.requestPermission();
        addLog('Permission result: ' + perm);
        await refreshAll();
        if (perm === 'granted') {
            document.getElementById('subscribe-btn').click();
        }
    });

    // Subscribe
    document.getElementById('subscribe-btn').addEventListener('click', async function() {
        try {
            const reg = await navigator.serviceWorker.ready;
            addLog('Getting subscription...');
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                addLog('Subscribing...');
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey)
                });
                addLog('Subscribed');
            }
            const res = await fetch('/mes-society/api/save-subscription.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(sub)
            });
            const result = await res.json();
            addLog('Save result: ' + (result.success ? 'success' : result.error));
            alert(result.success ? 'Subscription saved!' : 'Error: ' + result.error);
            await refreshAll();
        } catch(e) {
            addLog('Subscribe error: ' + e.message);
            alert('Subscribe error: ' + e.message);
        }
    });

    // Test notification
    document.getElementById('test-notification-btn').addEventListener('click', async function() {
        if (Notification.permission !== 'granted') {
            alert('Permission not granted');
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        addLog('Showing test notification...');
        reg.showNotification('Test Notification', {
            body: 'This is a local test.',
            icon: '/mes-society/assets/images/android-chrome-192x192.png',
            vibrate: [200,100,200]
        });
    });

    // Initial check
    refreshAll();
</script>
</body>
</html>