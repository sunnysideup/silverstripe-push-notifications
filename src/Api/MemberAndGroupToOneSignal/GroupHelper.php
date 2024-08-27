<?php

namespace Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal;

use SilverStripe\Security\Group;

class GroupHelper
{
    public function groupToCode(Group $group): string
    {
        return 'group-' . $group->ID;
    }


}
