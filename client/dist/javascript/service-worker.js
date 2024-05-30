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
  const url = obj.url || false;


 // const title = 'Push Timeline Update 1'

  const options = {
    body: body,
    icon: 'https://push.rakau.com/512.png',
    badge: 'https://push.rakau.com/512.png',
    action: 'open',
    data: {
      url: url
    }
  }
  
  event.waitUntil(self.registration.showNotification(title, options))
});


self.addEventListener('notificationclick', function (event) {
  console.log(event.notification);
  console.log(event.notification.data);
  event.notification.close()
  event.waitUntil(clients.openWindow('https://push.rakau.com'))
});