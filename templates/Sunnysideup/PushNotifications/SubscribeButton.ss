<div class="add-to-home-screen-container">
    <button id="add-to-home-screen">Add to Home Screen</button>
</div>

<% if $UseOneSignal %>

<div class='onesignal-customlink-container'></div>

<% else %>

<button id='subscribe-button' onClick="pushNotifications.requestPushNotifications();return false;">Click to subscribe to notifications</button>

<% end_if %>

