<?php

namespace Sunnysideup\PushNotifications\Api\Providers;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use Sunnysideup\PushNotifications\Api\PushNotificationProvider;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\Subscriber;
use Sunnysideup\PushNotifications\Model\SubscriberMessage;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;

/**
 *
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
    private static $notification_icon = null;


    /**
     * e.g. https://mysite.com/icon.png OR icon.png
     *
     * @var string
     */
    private static $notification_badge = null;



    public function getTitle()
    {
        return _t('Push.VAPID', 'Vapid');
    }

    public function sendPushNotification(PushNotification $notification)
    {
        $subject = Environment::getEnv('SS_VAPID_SUBJECT');
        if (!$subject) {
            user_error('SS_VAPID_SUBJECT is not defined');
        }

        $publicKey = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        if (!$publicKey) {
            user_error('SS_VAPID_PUBLIC_KEY is not defined');
        }

        $privateKey = Environment::getEnv('SS_VAPID_PRIVATE_KEY');
        if (!$privateKey) {
            user_error('SS_VAPID_PRIVATE_KEY is not defined');
        }


        $auth = [
            'VAPID' => [
                "subject" => $subject,
                "publicKey" => $publicKey,
                "privateKey" => $privateKey,
            ],
        ];





        $icon = static::config()->get('notification_icon');
        if (!is_null($icon) && !isset(parse_url($icon)['host'])) {
            $icon = Director::absoluteURL($icon);
        }

        $badge = static::config()->get('notification_badge');
        if (!is_null($badge) && !isset(parse_url($badge)['host'])) {
            $badge = Director::absoluteURL($badge);
        }


        $webPush = new WebPush($auth);

        $payload = json_encode([
            'title' => $notification->Title,
            'body' => $notification->Content,
            'url' => $notification->Link(),
            'icon' => $icon,
            'badge' => $badge,
        ]);



        $subscriptionJsons = [];

        foreach ($notification->getRecipients() as $recipient) {
            $subscriptions = $recipient->PushNotificationSubscribers();
            foreach ($subscriptions as $subscriber) {
                $log = SubscriberMessage::create_new($recipient, $notification, $subscriber);
                $subscription = Subscription::create(json_decode($subscriber->Subscription, true));

                $outcome = $webPush->sendOneNotification($subscription, $payload);

                if ($outcome->isSuccess()) {
                    $subscriptionJsons[$subscriber->ID]['success'] = true;
                    $subscriptionJsons[$subscriber->ID]['outcome'] = 'Success!';
                    $log->Success = true;
                } else {
                    $subscriptionJsons[$subscriber->ID]['success'] = false;
                    $subscriptionJsons[$subscriber->ID]['outcome'] = $outcome->getReason();
                    $log->ErrorMessage = $outcome->getReason();
                    $log->Success = false;
                }
                $log->write();
            }
        }

        return json_encode(['success' => true, 'results' => $subscriptionJsons]);


    }

    public function isEnabled(): bool
    {
        $subject = Environment::getEnv('SS_VAPID_SUBJECT');
        $publicKey = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        $privateKey = Environment::getEnv('SS_VAPID_PRIVATE_KEY');
        return (bool) ($subject && $publicKey && $privateKey);
    }


}
