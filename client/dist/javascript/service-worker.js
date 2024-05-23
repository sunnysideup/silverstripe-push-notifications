self.addEventListener('push', function (event) {
  const title = 'Push Timeline Update'
  const options = {
    body: 'Yay it works.',
    icon: 'images/icon.png',
    badge: 'images/badge.png'
  }
  console.log('Push event', title, options);
  event.waitUntil(self.registration.showNotification(title, options))
})
