<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PushNotifications\Model\Subscriber;
use Sunnysideup\PushNotifications\Model\SubscriberMessage;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property \SilverStripe\Security\Member|\Sunnysideup\PushNotifications\Extensions\MemberExtension $owner
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\Subscriber[] PushNotificationSubscribers()
 */
class MemberExtension extends DataExtension
{
    private static $has_many = [
      'PushNotificationSubscribers' => Subscriber::class,
      'SubscriberMessages' => SubscriberMessage::class,
    ];

    private static $field_labels = [
      'PushNotificationSubscribers' => 'Push Subscriptions',
      'SubscriberMessages' => 'Push Messages Sent',
    ];

    public function onBeforeDelete()
    {
        $subscribers = $this->owner->PushNotificationSubscribers();
        /** @var Subscriber $subscriber */
        foreach ($subscribers as $subscriber) {
            $subscriber->delete();
        }
    }

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $fields->removeFieldFromTab('Root', 'PushNotificationSubscribers');
        $fields->addFieldToTab(
            'Root.PushSubscriptions',
            GridField::create(
                'PushNotificationSubscribers',
                'Push Subscriptions',
                $owner->PushNotificationSubscribers(),
                GridFieldConfig_RecordEditor::create()
            )
        );
        return $fields;
    }

}
