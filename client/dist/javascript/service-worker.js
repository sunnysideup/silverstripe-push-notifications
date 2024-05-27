self.addEventListener("fetch", (event) => {
  return fetch(event.request);
});

self.addEventListener('install', function (event) {
  console.log('installed');
});

self.addEventListener('push', function (event) {
  const obj = event.data.json();
  
  const title = obj.title || "Fallback title";
  const body = obj.body || "Fallback message";


 // const title = 'Push Timeline Update 1'

  const options = {
    body: body,
    icon: 'https://push.rakau.com/512.png',
    badge: 'https://push.rakau.com/512.png',
    action: 'open',
  }
  
  event.waitUntil(self.registration.showNotification(title, options))
});


self.addEventListener('notificationclick', function (event) {
  event.notification.close()
  event.waitUntil(clients.openWindow('https://push.rakau.com'))
});