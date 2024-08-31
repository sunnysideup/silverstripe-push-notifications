<?php

namespace Sunnysideup\PushNotifications\Api\Providers;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use Sunnysideup\PushNotifications\Api\PushNotificationProvider;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\SubscriberMessage;

/**
 * @package silverstripe-push
 */
class PushNotificationVapid extends PushNotificationProvider
{
    use Configurable;

    /**
     * e.g. https://mysite.com/icon.png OR icon.png
     *
     * @var string
     */
    private static $notification_icon;

    /**
     * e.g. https://mysite.com/icon.png OR icon.png
     *
     * @var string
     */
    private static $notification_badge;

    public function getTitle()
    {
        return _t('Push.VAPID', 'Vapid');
    }

    public function sendPushNotification(PushNotification $notification): bool
    {
        $error = false;
        $publicKey = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        $privateKey = Environment::getEnv('SS_VAPID_PRIVATE_KEY');
        $subject = Environment::getEnv('SS_VAPID_SUBJECT');
        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $icon = static::config()->get('notification_icon');
        if (! is_null($icon) && ! isset(parse_url($icon)['host'])) {
            $icon = Director::absoluteURL($icon);
        }

        $badge = static::config()->get('notification_badge');
        if (! is_null($badge) && ! isset(parse_url($badge)['host'])) {
            $badge = Director::absoluteURL($badge);
        }
        $defaultOptions = [
            'TTL' => 86400 * 21, // defaults to 4 weeks
            'urgency' => 'high', // protocol defaults to "normal". (very-low, low, normal, or high)
            'batchSize' => 200, // defaults to 1000
        ];

        // for every notification
        $webPush = new WebPush($auth);
        $webPush->setDefaultOptions($defaultOptions);

        $payloadArray = [
            'title' => $notification->Title,
            'body' => $notification->Content,
            'url' => $notification->Link(),
        ];
        if($icon) {
            $payloadArray['icon'] = $icon;
        }
        if($badge) {
            $payloadArray['badge'] = $badge;
        }
        $payload = json_encode($payloadArray);

        // $subscriptionJsons = [];

        foreach ($notification->getRecipients() as $recipient) {
            $subscriptions = $recipient->PushNotificationSubscribers();
            foreach ($subscriptions as $subscriber) {
                $log = SubscriberMessage::create_new($recipient, $notification, $subscriber);
                $subscription = Subscription::create(json_decode($subscriber->Subscription, true));

                $outcome = $webPush->sendOneNotification($subscription, $payload);

                if ($outcome->isSuccess()) {
                    // $subscriptionJsons[$subscriber->ID]['success'] = true;
                    // $subscriptionJsons[$subscriber->ID]['outcome'] = 'Success!';
                    $log->Success = true;
                } else {
                    // $subscriptionJsons[$subscriber->ID]['success'] = false;
                    // $subscriptionJsons[$subscriber->ID]['outcome'] = $outcome->getReason();
                    $log->ErrorMessage = $outcome->getReason();
                    $log->Success = false;
                    $error = true;
                }
                $log->write();
            }
        }

        return $error;
    }



    public function isEnabled(?bool $showErrors = false): bool
    {
        $allGood = true;
        $publicKey = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        $privateKey = Environment::getEnv('SS_VAPID_PRIVATE_KEY');
        $subject = Environment::getEnv('SS_VAPID_SUBJECT');
        if (! $subject) {
            if($showErrors) {
                user_error('SS_VAPID_SUBJECT is not defined');
            }
            $allGood = false;
        }

        $publicKey = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        if (! $publicKey) {
            if($showErrors) {
                user_error('SS_VAPID_PUBLIC_KEY is not defined');
            }
            $allGood = false;
        }

        $privateKey = Environment::getEnv('SS_VAPID_PRIVATE_KEY');
        if (! $privateKey) {
            if($showErrors) {
                user_error('SS_VAPID_PRIVATE_KEY is not defined');
            }
            $allGood = false;
        }
        return $allGood;
    }
}
