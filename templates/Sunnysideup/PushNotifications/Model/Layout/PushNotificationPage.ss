



<div class="content-container unit size3of4 lastUnit">
  <article>
    <h1>$Title</h1>
    <div class="content">$Content</div>

    <div class="member-info">
        <% with $CurrentUser %>

        <p>Hi $FirstName,</p>
        <p>
            You have subscribed on $PushNotificationSubscribers.count devices.
        </p>
        <p>
            You should have received $SubscriberMessages.count messages so far.
        </p>
        <% end_with %>
    </div>

    <h2>Subscribe Now</h2>
    <% include Sunnysideup\\PushNotifications\\SubscribeButton %>

    <h2>Previous Messages</h2>
    <% include Sunnysideup\\PushNotifications\\PushNotificationList %>


  </article>
</div>
