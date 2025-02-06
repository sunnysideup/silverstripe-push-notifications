<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\GroupHelper;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\NotificationHelper;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Model\PushNotification;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property Group|GroupExtension $owner
 * @property string $OneSignalSegmentID
 * @property string $OneSignalSegmentNote
 * @method ManyManyList|PushNotification[] PushNotifications()
 */
class GroupExtension extends Extension
{
    protected static $subscriber_group = null;

    public static function get_all_subscribers_group(): ?Group
    {
        if (! isset(self::$subscriber_group)) {
            $defaultSubscriberGroupDetails = Config::inst()->get(self::class, 'all_subscribers_group_details');
            self::$subscriber_group = Group::get()->filter(['Code' => $defaultSubscriberGroupDetails['Code']])->first();
            if (!self::$subscriber_group) {
                self::$subscriber_group = Group::create($defaultSubscriberGroupDetails);
                self::$subscriber_group->write();
            }
        }
        return self::$subscriber_group;
    }

    private static $all_subscribers_group_details = [
        'Code' => 'allsubscribers',
        'Title' => 'All Push Notification Subscribers',
        'Description' => 'All subscribers to push notifications and similar.',
    ];

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

    public function updateSummaryFields(&$fields)
    {
        unset($fields['Title']);
        $fields = ['getBreadcrumbsSimpleWithCount' => 'getBreadcrumbsSimpleWithCount'] + $fields;
    }

    public function getBreadcrumbsSimple(): string
    {
        return $this->getOwner()->getBreadcrumbs(' » ');
    }

    public function getBreadcrumbsSimpleWithCount(): string
    {
        return $this->getOwner()->getBreadcrumbsSimple() . ' (' . $this->getOwner()->Members()->count() . ')';
    }

    public function canDelete($member)
    {
        $owner = $this->getOwner();
        $defaultSubscriberGroupDetails = Config::inst()->get(self::class, 'all_subscribers_group_details');
        if ($owner->Code === $defaultSubscriberGroupDetails['Code']) {
            return false;
        }
    }

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

    public function OneSignalComms(?bool $write = false): bool
    {
        $owner = $this->getOwner();
        if (! empty($this->noOneSignalComms[$owner->ID])) {
            return false;
        }

        if ($owner->hasOneSignalSegment()) {
            $owner->OneSignalSegmentID = trim((string) $owner->OneSignalSegmentID);
            if (strlen($owner->OneSignalSegmentID) < 10) {
                $owner->OneSignalSegmentID = null;
            }
            if ($owner->hasUnsentOneSignalMessages() && $this->useOneSignalSegmentsForSending()) {
                /** @var OneSignalSignupApi $api */
                $api = Injector::inst()->get(OneSignalSignupApi::class);
                $outcome = $api->createSegmentBasedOnGroup($owner);
                $api->processResults(
                    $owner,
                    $outcome,
                    'OneSignalSegmentID',
                    'OneSignalSegmentNote',
                    'Could not add OneSignal segment'
                );
                // we do not need to write the owner here, because this is an onBeforeWrite call!
            } elseif ($this->removeUnusedSegmentsFromOneSignal()) {
                $this->removeOneSignalSegment();
                $owner->OneSignalSegmentID = '';
                $owner->OneSignalSegmentNote = 'ID was ' . $this->OneSignalSegmentID;
            }
            if ($write) {
                $this->setNoOneSignalComms();
                $owner->write();
            }
        }
        return $owner->hasOneSignalSegment();
    }


    public function onBeforeDelete()
    {
        $this->removeOneSignalSegment();
    }

    /**
     * no write here!
     *
     * @return void
     */
    protected function removeOneSignalSegment()
    {
        $owner = $this->getOwner();
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
            'Root.OneSignal',
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

    public function requireDefaultRecords()
    {
        $defaultSubscriberGroupDetails = Config::inst()->get(self::class, 'all_subscribers_group_details');
        if (! isset($defaultSubscriberGroupDetails['Code'])) {
            user_error('Please add a Code to the default subscriber group details');
        }
        if (! Group::get()->filter(['Code' => $defaultSubscriberGroupDetails['Code']])->exists()) {
            if (! isset($defaultSubscriberGroupDetails['Title'])) {
                user_error('Please add a Title to the default subscriber group details');
            }
            if (! isset($defaultSubscriberGroupDetails['Description'])) {
                user_error('Please add a Description to the default subscriber group details');
            }
            DB::alteration_message('Creating default subscriber group', 'created');
            $group = Group::create($defaultSubscriberGroupDetails);
            $group->write();
            if ($group->Code !== $defaultSubscriberGroupDetails['Code']) {
                user_error('Code does not match for default subscriber group. Please check the code.');
            }
        }
    }
}
