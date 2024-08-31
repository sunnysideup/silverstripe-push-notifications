<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\GroupHelper;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\NotificationHelper;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\PushNotificationPage;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property Group|GroupExtension $owner
 * @property string $OneSignalSegmentID
 * @property string $OneSignalSegmentNote
 * @method ManyManyList|PushNotification[] PushNotifications()
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
        'getBreadcrumbsSimpleWithCount' => 'Varchar',
    ];

    private static $belongs_many_many = [
        'PushNotifications' => PushNotification::class,
    ];

    public function getBreadcrumbsSimple(): string
    {
        return $this->getOwner()->getBreadcrumbs(' » ');
    }

    public function getBreadcrumbsSimpleWithCount(): string
    {
        return $this->getOwner()->getBreadcrumbsSimple() . ' (' . $this->getOwner()->Members()->count() . ')';
    }

    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        if($owner->hasOneSignalSegment()) {
            if($owner->hasUnsentOneSignalMessages() && $this->useOneSignalSegmentsForSending()) {
                /** @var OneSignalSignupApi $api */
                $api = Injector::inst()->get(OneSignalSignupApi::class);
                $outcome = $api->createSegmentBasedOnGroup($owner);
                $goodResult = $api->processResults(
                    $owner,
                    $outcome,
                    'OneSignalSegmentID',
                    'OneSignalSegmentNote',
                    'Could not add OneSignal segment'
                );
            } elseif($this->removeUnusedSegmentsFromOneSignal()) {
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
                ReadonlyField::create('NameInOneSignal', 'Name in OneSignal', $owner->getNameInOneSignal()),
                ReadonlyField::create('CodeInOneSignal', 'Code in OneSignal', $owner->getCodeInOneSignal()),
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


    public function useOneSignalSegmentsForSending(): bool
    {
        return (bool) Config::inst()->get(NotificationHelper::class, 'use_segments_to_target_groups');
    }

    public function removeUnusedSegmentsFromOneSignal(): bool
    {
        return (bool) Config::inst()->get(NotificationHelper::class, 'remove_unused_segments_from_onesignal');
    }


    public function getNameInOneSignal(): string
    {
        return GroupHelper::singleton()->group2oneSignalName($this->getOwner());
    }

    public function getCodeInOneSignal(): string
    {
        return GroupHelper::singleton()->group2oneSignalCode($this->getOwner());
    }

}
