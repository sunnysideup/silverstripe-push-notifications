<?php

namespace Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal;

use SilverStripe\Security\Group;

class GroupHelper
{
    public static function group_2_code(Group $group): string
    {
        return 'group_' . $group->ID;
    }

    public static function group_2_name(Group $group): string
    {
        return 'Website: '.$group->Title;
    }


}
