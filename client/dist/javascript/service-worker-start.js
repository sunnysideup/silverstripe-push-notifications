
let pushNotifications = {
  swRegistration: null,
  requestPushNotifications: function() {
  pushNotifications.swRegistration.pushManager.getSubscription().then(function (subscription) {
    const isSubscribed = !(subscription === null)

    if (isSubscribed) {
      console.log('User IS subscribed.')
      alert('You are already subscribed to push notifications on this device.')
    } else {
      // Ask the user for permission to send push notifications
      Notification.requestPermission().then(function (permission) {
        if (permission === 'granted') {
          pushNotifications.swRegistration.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: pushNotifications.urlBase64ToUint8Array(vapid_public_key)
            })
            .then(function (subscription) {
              fetch('pushnotificationsubscription/subscribe', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify(subscription)
              })
                .then(function (response) {
                  if (!response.ok) {
                    alert(
                      'Sorry, we could not subscribe you - ERROR 1:' +
                        response.statusText
                    )
                    throw new Error(
                      'Failed to send subscription object to server'
                    )
                  }
                  return response.json()
                })
                .then(function (responseData) {
                  console.log(
                    'Subscription object was successfully sent to the server',
                    responseData
                  )
                  alert('You are now subcribed on this device.')
                })
                .catch(function (error) {
                  alert('Sorry, we could not subscribe you - ERROR 2:'.error)
                  console.error(error)
                })
            })
            .catch(function (error) {
              console.log('Failed to subscribe the user: ', error)
              alert('Sorry, we could not subscribe you - ERROR 3:'.error)
            })
          }
        })
      }
    })
  },

  // Utility function for VAPID keys
  urlBase64ToUint8Array: function(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/')

    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i)
    }
    return outputArray
  }

}



if ('serviceWorker' in navigator && 'PushManager' in window) {
  console.log('a');
  navigator.serviceWorker
    .register(
      '_resources/vendor/sunnysideup/push-notifications/client/dist/javascript/service-worker.js'
    )
    .then(function (swReg) {
      console.log('Service Worker is registered', swReg)
      pushNotifications.swRegistration = swReg
    })
    .catch(function (error) {
      console.error('Service Worker Error', error)
    })
} else {
  console.warn('Push messaging is not supported')
}


let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    deferredPrompt = e;
});

const installAppButton = document.getElementById('install-button');
if (installAppButton) {
  installAppButton.addEventListener('click', async () => {
    if (typeof deferredPrompt !== 'undefined') {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      if (outcome === 'accepted') {
          deferredPrompt = null;
      }
    }
  });
}



const installAndSubscribeButton = document.getElementById('install-and-subscribe');
if (installAndSubscribeButton) {
  installAndSubscribeButton.addEventListener('click', async () => {
    if (typeof deferredPrompt !== 'undefined') {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      if (outcome === 'accepted') {
        deferredPrompt = null;
        pushNotifications.requestPushNotifications();
      }
    }
  });
}
