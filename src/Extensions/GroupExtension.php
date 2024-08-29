<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
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

    public function getBreadcrumbsSimple()
    {
        return $this->getOwner()->getBreadcrumbs(' » ');
    }

    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        $api = Injector::inst()->get(OneSignalSignupApi::class);
        $outcome = $api->createSegmentBasedOnGroup($owner);
        if(OneSignalSignupApi::test_success($outcome)) {
            $owner->OneSignalSegmentID = $outcome['id'] ?? '';
            $owner->OneSignalSegmentNote = 'Successfully added segment for group ' . $owner->Title;
        } else {
            $owner->OneSignalSegmentNote = OneSignalSignupApi::get_error($outcome);
        }
    }


    public function onBeforeDelete()
    {
        $owner = $this->getOwner();
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
}
