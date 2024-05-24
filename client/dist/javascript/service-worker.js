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

self.addEventListener("pushXZ", (event) => {
  if (!(self.Notification && self.Notification.permission === "granted")) {
    return;
  }

  const data = event.data?.json() ?? {};
  const title = data.title || "Something Has Happened";
  const message =
    data.message || "Here's something you might want to check out.";
  const icon = "images/new-notification.png";

  const notification = new self.Notification(title, {
    body: message,
    tag: "simple-push-demo-notification",
    icon,
  });

  notification.addEventListener("click", () => {
    clients.openWindow(
      "https://example.blog.com/2015/03/04/something-new.html",
    );
  });
});