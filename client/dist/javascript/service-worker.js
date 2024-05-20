self.addEventListener('push', function (event) {
  const title = 'Push Timeline Update'
  const options = {
    body: 'Yay it works.',
    icon: 'images/icon.png',
    badge: 'images/badge.png'
  }

  event.waitUntil(self.registration.showNotification(title, options))
})
