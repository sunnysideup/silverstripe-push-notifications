self.addEventListener('push', function (event) {

  const data = event.data?.json() ?? {};
  const title = data.title || "Fallback title";
  const body = data.body || "Fallback message";

 // const title = 'Push Timeline Update 1'

  const options = {
    body: body,
    icon: 'images/icon.png',
    badge: 'images/badge.png',
    action: 'open',
  }
  
  event.waitUntil(self.registration.showNotification(title, options))
});

self.addEventListener("fetch", (event) => {
  return fetch(event.request);
});

