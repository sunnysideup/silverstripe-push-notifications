<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PushNotifications\Model\PushNotificationPage;
use Sunnysideup\PushNotifications\Model\Subscriber;
use Sunnysideup\PushNotifications\Model\SubscriberMessage;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property \SilverStripe\Security\Member|\Sunnysideup\PushNotifications\Extensions\MemberExtension $owner
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\Subscriber[] PushNotificationSubscribers()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\SubscriberMessage[] SubscriberMessages()
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
        $page = PushNotificationPage::get_one();
        $fields->removeFieldFromTab('Root', 'PushNotificationSubscribers');
        $fields->removeFieldFromTab('Root', 'SubscriberMessages');
        if($page) {
            if(! $page->UseOneSignal) {
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
                    ]
                );
            }
        }
        return $fields;
    }
}
