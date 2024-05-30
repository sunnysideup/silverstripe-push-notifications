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
class Subscriber extends DataObject
{
    private static $table_name = 'Subscriber';

    private static $db = array(
        'Subscription' => 'Text',
    );

    private static $has_one = array(
        'Member' => Member::class,
    );

    private static $has_many = array(
        'SubscriberMessages' => SubscriberMessage::class,
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
        return true;
    }
}
