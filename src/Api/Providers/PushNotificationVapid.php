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

/**
 * A simple email push provider which sends an email to all users.
 *
 * @package silverstripe-push
 */
class PushNotificationVapid extends PushNotificationProvider
{
    public function getTitle()
    {
        return _t('Push.VAPID', 'Vapid');
    }

    public function sendPushNotification(PushNotification $notification)
    {
        // Payload should be a string

        $auth = [
            'VAPID' => [
                "subject" => Environment::getEnv('SS_VAPID_SUBJECT'),
                "publicKey" => Environment::getEnv('SS_VAPID_PUBLIC_KEY'),
                "privateKey" =>  Environment::getEnv('SS_VAPID_PRIVATE_KEY')
            ],
        ];

        $webPush = new WebPush($auth);

        $payload = json_encode(['title' => 'Hello!', 'body' => 'Your first push notification']);
        $subscribers = Subscriber::get();
        foreach($subscribers as $key => $subscriber) {
            $subscription = Subscription::create($subscriber->Title);
            $outcome = $webPush->sendOneNotification($subscription, $payload);
            if ($outcome->isSuccess()) {
                $subscriptionJsons[$key]['success'] = true;
                $subscriptionJsons[$key]['outcome'] = 'Success!';
            } else {
                $subscriptionJsons[$key]['success'] = false;
                $subscriptionJsons[$key]['outcome'] = $outcome->getReason();
            }
        }
        echo json_encode(['success' => true, 'error' => print_r($subscriptionJsons, 1)]);

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
            if ($outcome->isSuccess()) {
                echo 'Success!';
            } else {
                echo 'Failure: ' . $outcome->getReason();
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
            $result->addFieldError(
                'Subject',
                _t(
                    'Push.EMAILSUBJECTREQUIRED',
                    'An email subject is required'
                )
            );
        }

        return $result;
    }
}
