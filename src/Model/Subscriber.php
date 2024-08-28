<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;

/**
 * Class \Sunnysideup\PushNotifications\Model\Subscriber
 *
 * @property string $Subscription
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\SubscriberMessage[] SubscriberMessages()
 */
class Subscriber extends DataObject
{
    private static $table_name = 'Subscriber';

    private static $db = [
        'Subscription' => 'Text',
        'Subscribed' => 'Boolean',
        'OneSignalUserID' => 'Varchar(64)',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $summary_fields = [
        'Member.Title' => 'Who',
        'Subscribed.Nice' => 'Subscribed',
        'SubscriptionReadable' => 'Details',
        'OneSignalUserID' => 'One Signal ID',
        'SubscriberMessages.Count' => 'Messages',
    ];

    private static $has_many = [
        'SubscriberMessages' => SubscriberMessage::class,
    ];

    private static $casting = [
        'SubscriptionReadable' => 'HTMLText',
    ];

    private static $indexes = [
        'Subscribed' => true,
        'OneSignalUserID' => true,
    ];

    public function getSubscriptionReadable(): DBHTMLText
    {
        return DBHTMLText::create_field('HTMLText', '<pre>' . json_decode($this->Subscription, true) . '</pre>');
    }

    /**
     * DataObject create permissions
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return true;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Subscription');
        $fields->replaceField(
            'Subscription',
            ReadonlyField::create('SubscriptionReadable', 'Subscription')
        );
        return $fields;
    }
}
