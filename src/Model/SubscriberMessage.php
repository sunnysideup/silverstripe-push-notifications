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
 * @method \Sunnysideup\PushNotifications\Model\Subscriber Subscriber()
 * @method \Sunnysideup\PushNotifications\Model\PushNotification PushNotification()
 * @method \SilverStripe\Security\Member Member()
 */
class SubscriberMessage extends DataObject
{
    public static function create_new(Member $member, PushNotification $pushNotification, ?Subscriber $subscriber = null)
    {
        $obj = self::create();
        $obj->MemberID = $member->ID;
        $obj->PushNotificationID = $pushNotification->ID;
        if($subscriber) {
            $obj->SubscriberID = $subscriber->ID;
        }
        $obj->write();
        return $obj;
    }
    private static $table_name = 'SubscriberMessage';

    private static $db = array(
        'Success' => 'Boolean',
        'ErrorMessage' => 'Text',
    );

    private static $has_one = array(
        'Subscriber' => Subscriber::class,
        'PushNotification' => PushNotification::class,
        'Member' => Member::class,
    );


    private static $summary_fields = [
        'Created.Nice' => 'When',
        'Member.Email' => 'To',
        'Subscriber.ID' => 'Subscription ID',
        'PushNotification.Title' => 'Message',
        'Success.Nice' => 'Success',
        'ErrorMessage.Summary' => 'Error',
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
        if(Permission::check('ADMIN')) {
            return true;
        }
        if(! $member) {
            $member = Security::getCurrentUser();
        }
        if($member) {
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
        $this->Title =
            (string) ($this->PushNotification()?->Title) .
            '--- TO: ' .
            (string) ($this->Subscriber()?->Member()?->Email)
            .'--- ON: '.
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
        return $fields;
    }

}
