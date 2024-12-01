<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\MemberHelper;
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
        if ($owner->exists()) {
            $owner->OneSignalComms(false);
        }
    }
    protected array $noOneSignalComms = [];

    public function setNoOneSignalComms(): static
    {
        $owner = $this->getOwner();
        $this->noOneSignalComms[$owner->ID] = true;
        return $this;
    }

    public function OneSignalComms(?bool $write = false, ?bool $alsoRunSubscribers = true): bool
    {
        $owner = $this->getOwner();
        if (! empty($this->noOneSignalComms[$owner->ID])) {
            return false;
        }
        $subscribers = $owner->ValidForOneSignalPushNotificationSubscribers();
        $defaultGroup = GroupExtension::get_all_subscribers_group();
        $memberGroups = $owner->Groups();
        if ($subscribers->exists()) {
            if ($alsoRunSubscribers) {
                /** @var Subscriber $subscriber */
                foreach ($subscribers as $subscriber) {
                    $subscriber->OneSignalComms(true);
                }
            }

            $memberGroups->add($defaultGroup);
            if ($write) {
                $this->setNoOneSignalComms();
                $owner->write();
            }
        } elseif ($defaultGroup) {
            $memberGroups->remove($defaultGroup);
        }
        return true;
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
            'Root.OneSignal',
            [
                GridField::create(
                    'PushNotificationSubscribers',
                    'Push Subscriptions',
                    $owner->PushNotificationSubscribers(),
                    GridFieldConfig_RecordEditor::create()
                ),
                GridField::create(
                    'SubscriberMessages',
                    'Push Messages Sent',
                    $owner->SubscriberMessages(),
                    GridFieldConfig_RecordEditor::create()
                ),
                ReadonlyField::create('CodeInOneSignal', 'Code in OneSignal', $owner->getCodeInOneSignal()),
                ReadonlyField::create('TagsInOneSignal', 'Tags in OneSignal', $owner->getTagsInOneSignal()),
            ]
        );
        return $fields;
    }

    public function ValidForOneSignalPushNotificationSubscribers(): DataList|ManyManyList
    {
        $owner = $this->getOwner();
        return $owner->PushNotificationSubscribers()
            ->filter(['OneSignalUserID:not' => null, 'Subscribed' => true]);
    }

    public function ValidForVapidPushNotificationSubscribers(): DataList|ManyManyList
    {
        $owner = $this->getOwner();
        return $owner->PushNotificationSubscribers()
            ->filter(['OneSignalUserID' => null, 'Subscribed' => true]);
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
