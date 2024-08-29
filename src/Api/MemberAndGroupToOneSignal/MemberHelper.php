<?php

namespace Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal;

use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class MemberHelper
{
    protected const LIMIT_FOR_TAGS = 10;

    public static function member_2_external_user_id(Member $member): string
    {
        return 'member_' . $member->ID;
    }

    public static function member_2_external_user_array(Member $member): array
    {
        return [
            'external_user_id' => self::member_2_external_user_id($member),
        ];
    }

    public static function member_groups_2_tag_codes(Member $member): array
    {
        $tags = [];
        // note that OneSignal limits tags to 10 per user!
        $memberGroups = $member->Groups()->limit(self::LIMIT_FOR_TAGS)->columnUnique();
        foreach(Group::get() as $group) {
            if(in_array($group->ID, $memberGroups, true)) {
                $tags[GroupHelper::group_2_code($group)] = 'Y';
            }
        }
        return $tags;
    }
}
