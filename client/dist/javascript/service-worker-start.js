if ('serviceWorker' in navigator && 'PushManager' in window) {
  navigator.serviceWorker
    .register('_resources/silverstripe-push-notifications/client/dist/javascript/service-worker.js')
    .then(function (swReg) {
      console.log('Service Worker is registered', swReg)

      swReg.pushManager.getSubscription().then(function (subscription) {
        const isSubscribed = !(subscription === null)

        if (isSubscribed) {
          console.log('User IS subscribed.')
        } else {
          // Ask the user for permission to send push notifications
          Notification.requestPermission().then(function (permission) {
            if (permission === 'granted') {
              swReg.pushManager
                .subscribe({
                  userVisibleOnly: true,
                  applicationServerKey: urlBase64ToUint8Array(
                    'BO8z380pXbsnPIKN855GcXtza79AYolbXcEj1gsawgNJsiadX37m4TIrobEv6-zUK9QFHQM_7penLZ8HUG1ldPk'
                  )
                })
                .then(function (subscription) {
                  console.log('User is subscribed:', subscription)
                  fetch('server.php', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                      action: 'subscribe',
                      subscription: subscription
                    })
                  })
                    .then(function (response) {
                      if (!response.success) {
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
                    })
                    .catch(function (error) {
                      console.error(error)
                    })
                  // You might want to send the subscription object to your server here to save it
                })
                .catch(function (err) {
                  console.log('Failed to subscribe the user: ', err)
                })
            }
          })
        }
      })
    })
    .catch(function (error) {
      console.error('Service Worker Error', error)
    })
} else {
  console.warn('Push messaging is not supported')
}

// Utility function for VAPID keys
function urlBase64ToUint8Array (base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/')

  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}
