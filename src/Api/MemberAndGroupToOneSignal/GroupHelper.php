<?php

namespace Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Group;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;

class GroupHelper
{
    public static function group_2_code(Group $group): string
    {
        return 'group_' . $group->ID;
    }

    public static function group_2_name(Group $group): string
    {
        return $group->Title;
    }


}
