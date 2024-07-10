<div class="member-info">
    <h2>Your Current Subscription</h2>
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

<div class="subscribe-now">
    <h2>Subscribe Now</h2>
    <button id='subscribe-button' onClick="pushNotifications.requestPushNotifications();return false;">Subscribe to notifications</button>
</div>
