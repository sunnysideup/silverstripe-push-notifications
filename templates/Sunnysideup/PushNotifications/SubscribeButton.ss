<button id='subscribe-button' onClick="requestPushNotifications();return false;">Click to subscribe to push notifications</button>


<button id="install-button">Install</button>

<button id="install-and-subscribe">Install and Subscribe</button>

<script>
    const vapid_public_key = 'BO8z380pXbsnPIKN855GcXtza79AYolbXcEj1gsawgNJsiadX37m4TIrobEv6-zUK9QFHQM_7penLZ8HUG1ldPk';

    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        deferredPrompt = e;
    });

    const installApp = document.getElementById('install-button');
    installApp.addEventListener('click', async () => {
        if (deferredPrompt !== null) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                deferredPrompt = null;
            }
        }
    });


    const installAndSubscribeButton = document.getElementById('install-and-subscribe');
    installAndSubscribeButton.addEventListener('click', async () => {
        if (deferredPrompt !== null) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                deferredPrompt = null;
                requestPushNotifications();
            }
        }
    });
  
</script>
