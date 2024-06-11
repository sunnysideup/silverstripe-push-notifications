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
  const icon = obj.icon || false;
  const badge = obj.badge || false;


 // const title = 'Push Timeline Update 1'

  const options = {
    body: body,
    icon: icon,
    badge: badge,    // 96x96
    action: 'open',
    data: {
      url: url
    }
  }
  
  event.waitUntil(self.registration.showNotification(title, options))
});


self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  if (event.notification.data.url) {
    event.waitUntil(clients.openWindow(event.notification.data.url));
  }
});