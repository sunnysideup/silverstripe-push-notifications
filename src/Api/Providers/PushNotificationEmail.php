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
class PushNotificationEmail extends PushNotificationProvider
{
    public function getTitle()
    {
        return _t('Push.EMAIL', 'Email');
    }

    public function sendPushNotification(PushNotification $notification)
    {
        $email = new Email();
        $email->setFrom($this->getSetting('From'));
        $email->setSubject($this->getSetting('Subject'));
        $email->setBody($notification->Content);

        foreach ($notification->getRecipients() as $recipient) {
            $log = SubscriberMessage::create_new($recipient, $notification);
            if(!$this->isValidEmail($recipient->Email)) {
                $log->Success = false;
                $log->ErrorMessage = 'Not a valid email address';
                $log->write();
                continue;
            }
            $email->setTo($recipient->Email);
            try {
                $email->send();
                $log->Success = true;
            } catch (\Exception $e) {
                // log error
                $log->ErrorMessage = $e->getMessage();
                $log->Success = false;
            }
            $log->write();
        }
    }

    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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
            $result->addFieldMessage(
                'Subject',
                _t(
                    'Push.EMAILSUBJECTREQUIRED',
                    'An email subject is required'
                )
            );
        }

        return $result;
    }


    public function isEnabled(): bool
    {
        return true;
    }
}
