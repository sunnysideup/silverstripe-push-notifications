<div class="previous-message">
    <h2>Previous Messages</h2>
    <div class="push-rows">
    <% loop $PushNotifications %>
        <div class='row'>
        <h3>$Title</h3>
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
</div>
