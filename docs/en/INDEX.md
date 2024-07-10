
# general info

This module allows you to send push notifications to your users.
You can either use One Signal or you can use Vapid.
It will require you to do some styling and testing!

It also

# Configuration

Here is how you set up the basic configuration to use Vapid (see below for more details!).

```yml
SilverStripe\Core\Injector\Injector:
    Sunnysideup\PushNotifications\Api\PushProvidersRegistry:
        properties:
            providers:
                # - Sunnysideup\PushNotifications\Api\Providers\PushNotificationEmail
                - Sunnysideup\PushNotifications\Api\Providers\PushNotificationVapid
```

Also see </_config/push-notifications.yml.example>

## CMS Configuration

Most of the config is done in the Push Notification Page.  You will need to create this page first.

At the moment, this page is hard-coded to always live at the following location:  `/push-notifications/`.

# FEATURE 1: Push Notifications

Please note that push notifications are not supported on all devices and browsers. That will never be the case.
It is therefore important to have a back up plan for those who do not receive push notifications.

We provide two options (providers) here and it is up to you to decide which one works best for you.

**Please note that you will need to do some styling and testing to make this work.**

**You can not use both providers at the same time and you can not really switch between them without some work. So choose carefully.**

## OPTION 1: use OneSignal

Head to <https://onesignal.com/> and create an account and follow your nose from there.

At the moment, you will need to copy of our messages on OneSignal to the push-notifications page.

## OPTION 2: use vapid

Please go to <https://vapidkeys.com/> to generate your vapid keys.

Then add the following to your `.env` file:

```env
SS_VAPID_SUBJECT=""
SS_VAPID_PUBLIC_KEY=""
SS_VAPID_PRIVATE_KEY=""
```

# Feature 2: Add to home Screen (PWA)

Note that the home screen feature is not supported on all devices.

Especially on iOS, you will need to do some extra work to make it work.

Ensure your site meets the following criteria:

- It is served over HTTPS.
- It has a valid Web App Manifest with the necessary properties.
- The web app manifest includes start_url and display.
- The user has visited your site at least once, and it is not in an incognito window.

## ios support for home screens

To improve the experience on iOS, you can add the following to your `Page.ss` file:

```html
<!-- place this in a head section -->
<link rel="apple-touch-icon" href="touch-icon-iphone.png">
<link rel="apple-touch-icon" sizes="152x152" href="touch-icon-ipad.png">
<link rel="apple-touch-icon" sizes="180x180" href="touch-icon-iphone-retina.png">
<link rel="apple-touch-icon" sizes="167x167" href="touch-icon-ipad-retina.png">
<meta name="apple-mobile-web-app-capable" content="yes" />
<link href="/apple_splash_2048.png" sizes="2048x2732" rel="apple-touch-startup-image" />
<link href="/apple_splash_1668.png" sizes="1668x2224" rel="apple-touch-startup-image" />
<link href="/apple_splash_1536.png" sizes="1536x2048" rel="apple-touch-startup-image" />
<link href="/apple_splash_1125.png" sizes="1125x2436" rel="apple-touch-startup-image" />
<link href="/apple_splash_1242.png" sizes="1242x2208" rel="apple-touch-startup-image" />
<link href="/apple_splash_750.png" sizes="750x1334" rel="apple-touch-startup-image" />
<link href="/apple_splash_640.png" sizes="640x1136" rel="apple-touch-startup-image" />
```

# other providers to consider

- <https://web-push-codelab.glitch.me/>
- <https://pushover.net/>
