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
}
