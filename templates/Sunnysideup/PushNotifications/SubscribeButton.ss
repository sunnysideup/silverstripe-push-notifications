<div class="add-to-home-screen-container">
    <button id="add-to-home-screen">Add to Home Screen</button>
    <p class="add-to-home-screen-instructions">Add this website to your home screen for easy access to updates.</p>
    <p class="message warning" id="add-to-home-screen-alternative-info">
        If this message persist then you may not be able to add this site to your home screen automatically.
        If you are on an Apple Device, go to the Share This option and choose "add to home screen".
        Similarly, on an Android device, go to the menu and choose "add to home screen".
    </p>
    <p class="message good" id="added-to-home-screen-info">
        This website has been added to your home screen.
    </p>
</div>

<% if $UseOneSignal %>

<div class='onesignal-customlink-container'></div>

<% else %>

<button id='subscribe-button' onClick="pushNotifications.requestPushNotifications();return false;">Subscribe to notifications</button>

<% end_if %>

