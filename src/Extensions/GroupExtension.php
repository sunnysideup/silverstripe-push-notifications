<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\PushNotificationPage;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property \SilverStripe\Security\Member|\Sunnysideup\PushNotifications\Extensions\MemberExtension $owner
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\Subscriber[] PushNotificationSubscribers()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\SubscriberMessage[] SubscriberMessages()
 */
class GroupExtension extends DataExtension
{
    private static $db = [
        'OneSignalSegmentID' => 'Varchar(64)',
        'OneSignalSegmentNote' => 'Varchar(255)',
    ];

    private static $indexes = [
        'OneSignalSegmentID' => true,
    ];

    private static $casting = [
        'getBreadcrumbsSimple' => 'Varchar',
    ];

    private static $belongs_many_many = [
        'PushNotifications' => PushNotification::class,
    ];

    public function getBreadcrumbsSimple(): string
    {
        return $this->getOwner()->getBreadcrumbs(' » ') . ' (' . $this->getOwner()->Members()->count() . ')';
    }

    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        if($owner->hasOneSignalSegment()) {
            if($owner->hasUnsentOneSignalMessages()) {
                /** @var OneSignalSignupApi $api */
                $api = Injector::inst()->get(OneSignalSignupApi::class);
                $outcome = $api->createSegmentBasedOnGroup($owner);
                if(OneSignalSignupApi::test_success($outcome)) {
                    $owner->OneSignalSegmentID = OneSignalSignupApi::test_id($outcome);
                    $owner->OneSignalSegmentNote = 'Succesfully connected to OneSignal';
                } else {
                    $owner->OneSignalSegmentID = '';
                    $owner->OneSignalSegmentNote = OneSignalSignupApi::get_error($outcome);
                }
            } else {
                $this->removeOneSignalSegment();
            }
        }

    }


    public function onBeforeDelete()
    {
        $this->removeOneSignalSegment();

    }

    protected function removeOneSignalSegment()
    {
        $owner = $this->getOwner();
        $owner->OneSignalSegmentID = '';
        $owner->OneSignalSegmentNote = '';
        /** @var OneSignalSignupApi $api */
        $api = Injector::inst()->get(OneSignalSignupApi::class);
        $api->deleteSegmentBasedOnGroup($owner);
    }

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $fields->addFieldsToTab(
            'Root.Push',
            [
                ReadonlyField::create('OneSignalSegmentID', 'OneSignal Segment ID'),
                ReadonlyField::create('OneSignalSegmentNote', 'OneSignal Segment Note'),
            ]
        );
        return $fields;
    }

    public function hasOneSignalSegment(): bool
    {
        $owner = $this->getOwner();
        return $owner->OneSignalSegmentID ? true : false;
    }

    public function hasUnsentOneSignalMessages(): bool
    {
        $owner = $this->getOwner();
        return $owner->PushNotifications()->filter(['Sent' => 0])->count() ? true : false;
    }


}
