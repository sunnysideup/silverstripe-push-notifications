<div class="content-container unit size3of4 lastUnit">
    <article>
        <div class="subscribe-intro">
            <h1>$Title</h1>
            <div class="content">$Content</div>
        </div>

        <% include Sunnysideup\\PushNotifications\\AddToHomeScreen %>

        <% if $UseOneSignal %>
            <% include Sunnysideup\\PushNotifications\\SignUpOneSignal %>
        <% else %>
            <% include Sunnysideup\\PushNotifications\\SignUpVapid %>
        <% end_if %>

        $SelectGroupsForm

        <% include Sunnysideup\\PushNotifications\\PushNotificationList %>

    </article>
</div>
