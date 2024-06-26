



<div class="content-container unit size3of4 lastUnit">
  <article>
    <h1>$Title</h1>
    <div class="content">$Content</div>

    <div class="member-info">
        <hr />
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
        <hr />
        <h2>Subscribe Now</h2>
        <% include Sunnysideup\\PushNotifications\\SubscribeButton %>
    </div>

    <div class="previous-message" style="clear: both;">
        <hr />
        <h2>Previous Messages</h2>
        <% include Sunnysideup\\PushNotifications\\PushNotificationList %>
    </div>


  </article>
</div>
