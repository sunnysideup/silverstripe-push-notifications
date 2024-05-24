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

        $webPush = new WebPush($auth);

        $payload = json_encode(['title' => $notification->Title, 'body' => $notification->Content]);

        //$subscribers = Subscriber::get();
        $subscriptionJsons = [];

        foreach ($notification->getRecipients() as $recipient) {
            $subscriptions = $recipient->PushNotificationSubscribers();
            foreach ($subscriptions as $subscriber) {
                $subscription = Subscription::create(json_decode($subscriber->Subscription, true));
                
                $outcome = $webPush->sendOneNotification($subscription, $payload);
                
                if ($outcome->isSuccess()) {
                    $subscriptionJsons[$subscriber->ID]['success'] = true;
                    $subscriptionJsons[$subscriber->ID]['outcome'] = 'Success!';
                } else {
                    $subscriptionJsons[$subscriber->ID]['success'] = false;
                    $subscriptionJsons[$subscriber->ID]['outcome'] = $outcome->getReason();
                }
            }
        }


        return json_encode(['success' => true, 'results' => $subscriptionJsons]);


    }

/*
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
*/

    
}
