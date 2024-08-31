<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\MemberHelper;
use Sunnysideup\PushNotifications\Model\PushNotificationPage;
use Sunnysideup\PushNotifications\Model\Subscriber;
use Sunnysideup\PushNotifications\Model\SubscriberMessage;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property Member|MemberExtension $owner
 * @method DataList|Subscriber[] PushNotificationSubscribers()
 * @method DataList|SubscriberMessage[] SubscriberMessages()
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
    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        if($owner->exists()) {
            $subscribers = $owner->PushNotificationSubscribers();
            /** @var Subscriber $subscriber */
            foreach ($subscribers as $subscriber) {
                $subscriber->write();
            }
        }
    }



    public function onBeforeDelete()
    {
        $owner = $this->getOwner();
        $subscribers = $owner->PushNotificationSubscribers();
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
        $fields->removeFieldFromTab('Root', 'SubscriberMessages');
        $fields->addFieldsToTab(
            'Root.Push',
            [
                GridField::create(
                    'PushNotificationSubscribers',
                    'Push Subscriptions',
                    $owner->PushNotificationSubscribers(),
                    GridFieldConfig_RecordViewer::create()
                ),
                GridField::create(
                    'SubscriberMessages',
                    'Push Messages Sent',
                    $owner->SubscriberMessages(),
                    GridFieldConfig_RecordViewer::create()
                ),
                ReadonlyField::create('CodeInOneSignal', 'Code in OneSignal', $owner->getCodeInOneSignal()),
                ReadonlyField::create('TagsInOneSignal', 'Tags in OneSignal', $owner->getTagsInOneSignal()),
            ]
        );
        return $fields;
    }

    public function getCodeInOneSignal(): string
    {
        $owner = $this->getOwner();
        return MemberHelper::singleton()->member2externalUserId($owner);
    }

    public function getTagsInOneSignal(): string
    {
        $owner = $this->getOwner();
        $array = MemberHelper::singleton()->memberGroups2tagCodes($owner);
        return implode(', ', array_keys($array));
    }
}
