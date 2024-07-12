<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
    appId: "$OneSignalKey",
    });
});
</script>
<div class="subscribe-now">
    <h2>Welcome back $CurrentUser.FirstName, manage your notifications:</h2>
</div>
<div class="subscribe-now">
    <div class='onesignal-customlink-container'></div>
</div>
