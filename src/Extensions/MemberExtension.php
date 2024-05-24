<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\ORM\DataExtension;
use Sunnysideup\PushNotifications\Model\Subscriber;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\MemberExtension
 *
 * @property \SilverStripe\Security\Member|\Sunnysideup\PushNotifications\Extensions\MemberExtension $owner
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\Subscriber[] PushNotificationSubscribers()
 */
class MemberExtension extends DataExtension {

  private static $has_many = [
    'PushNotificationSubscribers' => Subscriber::class,
  ];
  
}