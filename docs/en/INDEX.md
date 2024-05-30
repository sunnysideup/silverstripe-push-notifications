# providers

-   https://pushover.net/

# you can set your own providers like this:

```yml
SilverStripe\Core\Injector\Injector:
    Sunnysideup\PushNotifications\Api\PushProvidersRegistry:
        properties:
            providers:
                - Sunnysideup\PushNotifications\Api\Providers\PushNotificationEmail
                - Sunnysideup\PushNotifications\Api\Providers\PushNotificationVapid
```
