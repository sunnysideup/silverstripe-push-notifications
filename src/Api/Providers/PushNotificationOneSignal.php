<?php

namespace Sunnysideup\PushNotifications\Api\Providers;

use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
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

    public function sendPushNotification(PushNotification $notification)
    {
    }


    public function getSettingsFields()
    {
        return new FieldList([
            new TextField(
                $this->getSettingFieldName('Subject'),
                _t('Push.EMAILSUBJECT', 'Email Subject'),
                $this->getSetting('Subject')
            ),
            new TextField(
                $this->getSettingFieldName('From'),
                _t('Push.EMAILFROM', 'Email From Address'),
                $this->getSetting('From')
            ),
        ]);
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
        return true;
    }
}
