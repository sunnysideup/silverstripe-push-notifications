<?php

namespace Sunnysideup\PushNotifications\Api\Providers;

use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Api\PushNotificationProvider;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\SubscriberMessage;

/**
 * A simple email push provider which sends an email to all users.
 *
 * @package silverstripe-push
 */
class PushNotificationOneSignal extends PushNotificationProvider
{
    public function getTitle()
    {
        return _t('Push.ONESIGNAL', 'OneSignal');
    }

    public function sendPushNotification(PushNotification $notification): bool
    {
        /** @var OneSignalSignupApi $api */
        $api = Injector::inst()->get(OneSignalSignupApi::class);
        $outcome = $api->doSendNotification($notification);
        $isGoodResult = $api->processResults(
            $notification,
            $outcome,
            'OneSignalNotificationID',
            'OneSignalNotificationNote',
            'Could not add OneSignal notification'
        );
        foreach ($notification->getRecipients() as $recipient) {
            $subscriptions = $recipient->PushNotificationSubscribers();
            foreach ($subscriptions as $subscriber) {
                $log = SubscriberMessage::create_new($recipient, $notification, $subscriber);
                $log->Success = $isGoodResult;
                $log->write();
            }
        }
        return $isGoodResult;
    }


    public function getSettingsFields()
    {
        return new FieldList();
    }

    public function setSettings(array $data)
    {
        parent::setSettings($data);
    }

    public function validateSettings()
    {
        $result = parent::validateSettings();

        return $result;
    }

    public function isEnabled(): bool
    {
        return OneSignalSignupApi::is_enabled();
    }
}
