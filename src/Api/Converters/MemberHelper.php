<?php

namespace Sunnysideup\PushNotifications\Api\Converters;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class MemberHelper
{
    use Configurable;
    use Injectable;

    protected static $limit_for_tags = 10;

    public function member2externalUserId(Member $member): string
    {
        return 'member_' . $member->ID;
    }

    public function member2externalUserArray(Member $member): array
    {
        return [
            'external_user_id' => $this->member2externalUserId($member),
        ];
    }

    public function memberGroups2tagCodes(Member $member): array
    {
        $tags = [];
        // note that OneSignal limits tags to 10 per user!
        $memberGroups = $member->Groups()->limit($this->Config()->limit_for_tags)->columnUnique();
        foreach(Group::get() as $group) {
            if(in_array($group->ID, $memberGroups, true)) {
                $tags[GroupHelper::singleton()->group2oneSignalCode($group)] = 'Y';
            }
        }
        return $tags;
    }
}
