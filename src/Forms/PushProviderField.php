<?php

namespace Sunnysideup\PushNotifications\Forms;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Sunnysideup\PushNotifications\Api\PushNotificationProvider;

/**
 * Allows users to select and configure the push notification provider to use.
 *
 * @package silverstripe-push
 */
class PushProviderField extends FormField
{
    private static $url_handlers = [
        'fields/$Class!' => 'fields',
    ];

    private static $allowed_actions = [
        'fields',
    ];

    protected $registry;

    protected $provider;

    protected $providers = [];

    public function setRegistry($registry)
    {
        $this->registry = $registry;
        foreach ($this->registry->getProvidersAsEnabledObjects() as $inst) {
            $this->providers[get_class($inst)] = $inst;
            $inst->setFormField($this);
        }
    }

    public function fields($request)
    {
        $class = $request->param('Class');

        if (! $this->registry->has($class)) {
            $this->httpError(404);
        }

        if ($this->provider && $class == get_class($this->provider)) {
            $inst = $this->provider;
        } else {
            $inst = new $class();
            $inst->setFormField($this);
        }

        $fields = $this->isReadonly() ? $inst->getSettingsFields()->makeReadonly() : $inst->getSettingsFields();

        $data = new ArrayData([
            'SettingsFields' => $fields,
        ]);
        return $data->renderWith('PushProviderField_ProviderFields');
    }

    public function validate($validator)
    {
        if ($this->provider) {
            $result = $this->provider->validateSettings();

            if (! $result->isValid()) {
                // @TODO: handle multiple fields being returned
                $messages = $result->getMessages();
                $validator->validationError($this->name, $messages[0]['message'], 'validation');
                return false;
            }
        }

        return true;
    }

    public function performReadonlyTransformation()
    {
        $field = clone $this;
        $field->setReadonly(true);
        return $field;
    }

    public function setValue($value, $data = null)
    {
        if ($value instanceof PushNotificationProvider) {
            $this->provider = $value;
            $this->provider->setFormField($this);
        } elseif (is_array($value)) {
            $class = isset($value['Provider']) ? $value['Provider'] : null;
            $settings = isset($value['Settings']) ? $value['Settings'] : null;
            if ($class && is_subclass_of($class, PushNotificationProvider::class)) {
                $this->provider = new $class();
                $this->provider->setFormField($this);

                if (is_array($settings)) {
                    $this->provider->setSettings($settings);
                }
            } else {
                $this->provider = null;
            }
        }
        return $this;
    }

    public function saveInto(DataObjectInterface $record)
    {
        $record->{$this->name} = $this->provider;
    }

    public function FieldHolder($properties = [])
    {
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/PushProviderField.js');

        return $this->renderWith('PushProviderField');
    }

    public function ProviderField()
    {
        $values = [];

        foreach ($this->providers as $class => $inst) {
            $values[$class] = $inst->getTitle();
        }

        $field = DropdownField::create(
            "$this->name[Provider]",
            _t('Push.DELIVERYCHANNEL', 'Delivery Channel'),
            $values,
            $this->provider ? get_class($this->provider) : null
        )->setHasEmptyDefault(true);
        if ($this->isReadonly()) {
            return $field->performReadonlyTransformation();
        } else {
            return $field;
        }
    }

    public function SettingsFields()
    {
        if ($this->provider) {
            $fields = $this->provider->getSettingsFields();

            if ($this->isReadonly()) {
                return $fields->makeReadonly();
            } else {
                return $fields;
            }
        }
        return null;
    }
}
