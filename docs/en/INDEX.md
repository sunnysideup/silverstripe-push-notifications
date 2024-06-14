
# you can set your own providers like this

```yml
SilverStripe\Core\Injector\Injector:
    Sunnysideup\PushNotifications\Api\PushProvidersRegistry:
        properties:
            providers:
                - Sunnysideup\PushNotifications\Api\Providers\PushNotificationEmail
                - Sunnysideup\PushNotifications\Api\Providers\PushNotificationVapid
```

# use vapid

Please go to <https://vapidkeys.com/> to generate your vapid keys.

Then add the following to your `.env` file:

```env
SS_VAPID_SUBJECT=""
SS_VAPID_PUBLIC_KEY=""
SS_VAPID_PRIVATE_KEY=""
```

# other providers

- <https://web-push-codelab.glitch.me/>
- <https://pushover.net/>
