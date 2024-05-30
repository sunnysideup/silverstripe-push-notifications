<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Class \Sunnysideup\PushNotifications\Model\Subscriber
 *
 * @property string $Subscription
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 */
class SubscriberMessage extends DataObject
{
    public static function create_new(Subscriber $subscriber, PushNotification $pushNotification)
    {
        $obj = self::create();
        $obj->SubscriberID = $subscriber->ID;
        $obj->PushNotificationID = $pushNotification->ID;
        $obj->Title =
            (string) ($pushNotification->Title) .
            '--- TO: ' .
            (string) ($subscriber?->Member()?->Email)
            .'--- ON: '.
            date('Y-m-d H:i:s');
        $obj->write();
        return $obj;
    }
    private static $table_name = 'SubscriberMessage';

    private static $db = array(
        'Title' => 'Text',
        'Success' => 'Boolean',
    );

    private static $has_one = array(
        'Subscriber' => Subscriber::class,
        'PushNotification' => PushNotification::class,
    );

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

}
