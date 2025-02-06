
# general info

This module allows you to send push notifications to your users.
You can either use OneSignal or you can use Vapid.
It will require you to do some styling and testing!

# Sign-Up to Push Notifications - known issues

## IPhone

On an iPhone, you first need to add the site to the home screen before you can receive push notifications.

The way you add it to the home screen is by clicking on the share icon in the browser and then selecting "Add to Home Screen".

The share icon can usually a square with an arrow pointing up that allows you to review the page details, etc...

You then need to open the site from the home screen to set up the push notifications.

### iOS 16.4 and above only

Only iOS 16.4 and above allows you to add push notifications.

## Incognito Browser

Incognito does not allow you to sign up for push notifications

# Configuration

Here is how you set up the basic configuration to use Vapid (see below for more details!).

_Firstly_, you have to make sure that the `PushProvidersRegistry` is configured correctly.

You will have to chose your providers (see below which one is right for you).

```yml
SilverStripe\Core\Injector\Injector:
    Sunnysideup\PushNotifications\Api\PushProvidersRegistry:
        properties:
            providers:
                - Sunnysideup\PushNotifications\Api\Providers\PushNotificationVapid
                # - Sunnysideup\PushNotifications\Api\Providers\PushNotificationEmail
```

Also see </_config/push-notifications.yml.example>

**_Secondly_, you have to make sure to link to `manifest.json` in your `Page.ss` file.**

```html
<link rel="manifest" href="manifest.json">
```

_Thirdly_, you may opt to add more details to your `manifest.json` file.  
Do that carefully so that you make it work with the features in the CMS.

## CMS Configuration

Most of the config is done in the Push Notification Page.  You will need to create this page first.

At the moment, this page is hard-coded to always live at the following location:  `/push-notifications/`.

# FEATURE 1: Push Notifications

Please note that push notifications are not supported on all devices and browsers. That will never be the case.
It is therefore important to have a back up plan for those who do not receive push notifications.

We provide two options (providers) here and it is up to you to decide which one works best for you.

**Please note that you will need to do some styling and testing to make this work.**

**You can not use both providers at the same time and you can not really switch between them without some work. So choose carefully.**

## OPTION 1: use vapid - the vanilla approach

Please go to <https://vapidkeys.com/> to generate your vapid keys.

Then add the following to your `.env` file:

```env
SS_VAPID_SUBJECT=""
SS_VAPID_PUBLIC_KEY=""
SS_VAPID_PRIVATE_KEY=""
```

## OPTION 2: use OneSignal - the SaaS approach

Head to <https://onesignal.com/> and create an account and follow your nose from there.

Then you will need to add the following to your `.env` file:

```env
# OneSignal APP
SS_ONESIGNAL_APP_ID="" 
SS_ONESIGNAL_REST_API_KEY=""
# OneSignal User
SS_ONESIGNAL_USER_AUTH_KEY=""
```

At the moment, you will need to copy messages on OneSignal to the push-notifications page.

### setup a sync  

To run a sync of all members and groups with OneSignal, you can run the following task from the command line:

```shell
`vendor/bin/sake dev/tasks/update-one-signal`
```

You could consider running as a cron job at regular intervals.

You can also run this from the browser `/dev/tasks/update-one-signal`.

# Feature 2: Add to home Screen (PWA)

Note that the home screen feature is not supported on all devices.

Especially on iOS, you will need to do some extra work to make it work.

Ensure your site meets the following criteria:

- It is served over HTTPS.
- It has a valid Web App Manifest with the necessary properties.
- The web app manifest includes start_url and display.
- The user has visited your site at least once, and it is not in an incognito window.

**Note that `iOS` and `Firefox` do not support anything much for adding to home screen.**

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

# Testing

## how to repeat testing

Once added, you may want to remove yourself again.  

To remove yourself from the `added to home screen` functionality. You need to change localStorage value of `appInstalled` to `no`.

To remove yourself from notification, in chrome, you can click on the lock icon in the address bar and remove the notifications from there.

You may also remove yourself from the CMS list of recipients.

## Testing Members and Groups on OneSignal

There is a task that you can run to test the basic connections:

You can run this from the command line:

```shell
`vendor/bin/sake dev/tasks/test-one-signal`
```

Or from the browser `/dev/tasks/test-one-signal`.

#### Many Many Syncing ideas for Silverstripe

In Group, the method Members calls `DirectMembers()` which calls `getManyManyComponents()` which has an extension hook `updateManyManyComponents`.

# more reading

- <https://medium.com/@firt/progressive-web-apps-on-ios-are-here-d00430dee3a7>
- <https://web.dev/articles/customize-install>
- <https://stackoverflow.com/questions/50332119/is-it-possible-to-make-an-in-app-button-that-triggers-the-pwa-add-to-home-scree/50356149#50356149>
- <https://firt.dev/tags/ios>
- <https://www.netguru.com/blog/pwa-ios>

# other providers to consider

- <https://web-push-codelab.glitch.me/>
- <https://pushover.net/>


# setting up a push notification job

THere is a variety of ways to run pushes, using the sample job below is one of them:

```php
<?php

namespace Sunnysideup\PushNotifications\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * @package silverstripe-push
 */
class SendPushNotificationsJob extends AbstractQueuedJob
{
    public function __construct($notification = null)
    {
        if ($notification) {
            $this->setObject($notification);
        }
    }

    public function getTitle()
    {
        $message = _t('Push.SENDNOTIFICATIONSFOR', 'Send notifications for "%s"');
        return sprintf($message, $this->getObject()->Title);
    }

    public function process()
    {
        $this->getObject()->doSend();
        $this->isComplete = true;
    }
}

```
