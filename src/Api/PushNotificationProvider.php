<?php

namespace Sunnysideup\PushNotifications\Api;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ValidationResult;
use Sunnysideup\PushNotifications\ErrorHandling\PushException;
use Sunnysideup\PushNotifications\Forms\PushProviderField;
use Sunnysideup\PushNotifications\Model\PushNotification;

/**
 * @package silverstripe-push
 */
abstract class PushNotificationProvider
{
    protected $settings = [];

    protected $field;

    /**
     * @return string
     */
    abstract public function getTitle();

    /**
     * returns true if the notification was sent successfully
     *
     * @param PushNotification $notification the notification to send
     * @throws PushException if sending the notification fails
     */
    abstract public function sendPushNotification(PushNotification $notification): bool;
    public function sendingComplete(PushNotification $notification): bool
    {
        return $notification->SubscriberMessages()->count() >= $notification->getRecipientsCount();
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Populates this provider's settings from an array of data, usually
     * received in a request.
     */
    public function setSettings(array $data)
    {
        $this->settings = $data;
    }

    public function getSetting($key)
    {
        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }
        return null;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function setSetting($key, $value)
    {
        $this->settings[$key] = $value;
    }

    /**
     * Returns a list of form fields used for populating the custom settings.
     *
     * @return FieldList
     */
    public function getSettingsFields()
    {
        return new FieldList();
    }

    /**
     * Validates if the currently set settings are valid.
     *
     * @return ValidationResult
     */
    public function validateSettings()
    {
        return new ValidationResult();
    }

    /**
     * @return PushProviderField
     */
    public function getFormField()
    {
        return $this->field;
    }

    public function setFormField(PushProviderField $field)
    {
        $this->field = $field;
    }

    /**
     * @param  string $setting
     * @return string
     */
    protected function getSettingFieldName($setting)
    {
        return sprintf('%s[Settings][%s]', $this->field->getName(), $setting);
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
