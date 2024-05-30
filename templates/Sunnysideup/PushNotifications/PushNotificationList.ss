<% loop PushNotifications %>
<div class='row'>
  <h2>$Title</h2>
  <span class='notification-date'>$SentAt.Nice</span>
  <p>
    $Content
  </p>
</div>
<% end_loop %>