let swRegistration = null;

if ('serviceWorker' in navigator && 'PushManager' in window) {
  
  navigator.serviceWorker
    .register('_resources/vendor/sunnysideup/push-notifications/client/dist/javascript/service-worker.js')
    .then(function (swReg) {
      console.log('Service Worker is registered', swReg);
      swRegistration = swReg;


      
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


function requestPushNotifications() {
  swRegistration.pushManager.getSubscription().then(function (subscription) {
    const isSubscribed = !(subscription === null)

    if (isSubscribed) {
      console.log('User IS subscribed.')
    } else {
      // Ask the user for permission to send push notifications
      Notification.requestPermission().then(function (permission) {

        if (permission === 'granted') {

          swRegistration.pushManager
            .subscribe({
              userVisibleOnly: true,
              applicationServerKey: urlBase64ToUint8Array(
                vapid_public_key
              )
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



}