<?php

namespace Sunnysideup\PushNotifications\Api;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * A simple email push provider which sends an email to all users.
 *
 * @package silverstripe-push
 */
class PushNotificationVapid extends PushNotificationProvider
{
    public function getTitle()
    {
        return _t('Push.EMAIL', 'Email');
    }

    public function sendPushNotification(PushNotification $notification)
    {

        // Assuming you have a function to get the stored subscription data
        $subscriptionData = getSubscriptionsFromDatabase(); // Implement this

        // Payload should be a string
        $payload = json_encode(['title' => 'Hello!', 'body' => 'Your first push notification']);

        $auth = [
            'VAPID' => [
                "subject" => "",
                "publicKey" => "",
                "privateKey" =>  ""
            ],
        ];

        $webPush = new WebPush($auth);

        foreach($subscriptionData as $subscription) {
            $subscription = Subscription::create($subscription);
            $outcome = $webPush->sendOneNotification($subscription, $payload);
            if ($report->isSuccess()) {
                echo 'Success!';
            } else {
                echo 'Failure: ' . $report->getReason();
            }

        }


    }

    public function getSettingsFields()
    {
        return new FieldList(array(
            new TextField(
                $this->getSettingFieldName('Subject'),
                _t('Push.EMAILSUBJECT', 'Email Subject'),
                $this->getSetting('Subject')
            ),
            new TextField(
                $this->getSettingFieldName('From'),
                _t('Push.EMAILFROM', 'Email From Address'),
                $this->getSetting('From')
            )
        ));
    }

    public function setSettings(array $data)
    {
        parent::setSettings($data);

        $this->setSetting('Subject', isset($data['Subject']) ? (string) $data['Subject'] : null);
        $this->setSetting('From', isset($data['From']) ? (string) $data['From'] : null);
    }

    public function validateSettings()
    {
        $result = parent::validateSettings();

        if (!$this->getSetting('Subject')) {
            $result->error(_t(
                'Push.EMAILSUBJECTREQUIRED',
                'An email subject is required'
            ));
        }

        return $result;
    }
}
