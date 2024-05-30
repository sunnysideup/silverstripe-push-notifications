<div class="push-rows">
<% loop PushNotifications %>
    <div class='row'>
    <h2>$Title</h2>
    <div class="time"><time class='notification-date'>$SentAt.Nice</time></div>
    <p>
        $Content
    </p>
    <div>
        $AdditionalInfo
    </div>

    </div>
    <hr />
<% end_loop %>
</div>
