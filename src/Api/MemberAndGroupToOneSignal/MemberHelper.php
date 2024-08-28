<?php

namespace Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal;

use OneSignal\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

use OneSignal\OneSignal;
use Symfony\Component\HttpClient\Psr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class MemberHelper
{
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
        $memberGroups = $member->Groups()->columnUnique();
        foreach(Group::get() as $group) {
            $tags[GroupHelper::group_2_code($group)] = in_array($group->ID, $memberGroups, true) ? 'Y' : 'N';
        }
        return $tags;
    }
}
