<% if $OneSignalKey %>

<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer crossorigin="anonymous"></script>
<script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(
        async function(OneSignal) {
            await OneSignal.init(
                {
                    appId: "$OneSignalKey",
                }
            );
            if(window.MyOneSignalCommsBackToWebsite) {
                window.MyOneSignalCommsBackToWebsite.init(OneSignal);
            } else {
                console.log("window.MyOneSignalCommsBackToWebsite is not defined");
            }
        }
    );
</script>
<div class="subscribe-now">
    <h2>Welcome back $CurrentUser.FirstName, manage your notifications:</h2>
</div>
<div class="subscribe-now">
    <div class='onesignal-customlink-container'></div>
</div>

<% end_if %>
