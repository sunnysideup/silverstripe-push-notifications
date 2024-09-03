<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Class \Sunnysideup\PushNotifications\Model\Subscriber
 *
 * @property bool $Success
 * @property string $ErrorMessage
 * @property int $SubscriberID
 * @property int $PushNotificationID
 * @property int $MemberID
 * @method Subscriber Subscriber()
 * @method PushNotification PushNotification()
 * @method Member Member()
 */
class SubscriberMessage extends DataObject
{
    public static function subscriber_message_exists(Member $member, PushNotification $pushNotification, ?Subscriber $subscriber = null): bool
    {
        return self::get()->filter(self::get_filter_for_new($member, $pushNotification, $subscriber))->exists();
    }

    public static function create_new(Member $member, PushNotification $pushNotification, ?Subscriber $subscriber = null)
    {
        if (self::subscriber_message_exists($member, $pushNotification, $subscriber)) {
            return null;
        }
        $obj = self::create(self::get_filter_for_new($member, $pushNotification, $subscriber));
        $obj->write();
        return $obj;
    }

    protected static function get_filter_for_new(Member $member, PushNotification $pushNotification, ?Subscriber $subscriber = null)
    {
        $filter = [
            'MemberID' => $member->ID,
            'PushNotificationID' => $pushNotification->ID,
        ];
        if ($subscriber instanceof Subscriber) {
            $filter['SubscriberID'] = $subscriber->ID;
        }
        return $filter;
    }


    private static $table_name = 'SubscriberMessage';

    private static $db = [
        'Success' => 'Boolean',
        'ErrorMessage' => 'Text',
    ];

    private static $has_one = [
        'Subscriber' => Subscriber::class,
        'PushNotification' => PushNotification::class,
        'Member' => Member::class,
    ];

    private static $summary_fields = [
        'Created.Nice' => 'When',
        'Member.Email' => 'To',
        'Subscriber.Title' => 'Subscription',
        'PushNotification.Title' => 'Message',
        'Success.Nice' => 'Success',
        'ErrorMessage.Summary' => 'Error',
    ];

    private static $casting = [
        'Title' => 'Varchar',
    ];

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
        return false;
    }

    public function canView($member = null)
    {
        if (Permission::check('ADMIN')) {
            return true;
        }
        if (! $member) {
            $member = Security::getCurrentUser();
        }
        if ($member) {
            return $member->ID === $this->MemberID;
        }
        return false;
    }

    /**
     * Event handler called before writing to the database.
     *
     * @uses DataExtension->onAfterWrite()
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
    }

    public function getTitle()
    {
        return $this->PushNotification()?->Title .
            ', TO: ' .
            $this->Subscriber()?->Member()?->getTitle()
            . ', ON: ' .
            $this->Created;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Success');
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('SuccessNice', 'Success', $this->dbObject('Success')->Nice()),
                ReadonlyField::create('Created', 'When'),
            ]
        );
        $fields->addFieldsToTab('Root.History', [
            ReadonlyField::create('Created', _t('Push.CREATED', 'Created')),
            ReadonlyField::create('LastEdited', _t('Push.LASTEDITED', 'Last Edited')),
        ]);
        return $fields;
    }
}
