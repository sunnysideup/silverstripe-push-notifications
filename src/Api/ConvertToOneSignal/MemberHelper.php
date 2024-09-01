<?php

namespace Sunnysideup\PushNotifications\Api\ConvertToOneSignal;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Model\Subscriber;

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

    public function members2oneSignalAliases(DataList|ManyManyList $members): array
    {
        $memberListIdCondensed = $this->ValidSubscribersBasedOneMemberList($members)
            ->columnUnique('MemberID');
        $members = $members->filter(['ID' => $memberListIdCondensed]);
        if($members->count() > 2000) {
            user_error('You are trying to send a message to more than 2000 members. This is not allowed.');
        }
        // note that OneSignal limits tags to 10 per user!
        foreach($members as $member) {
            $includedAliases[] = $this->member2externalUserId($member);
        }
        return $includedAliases;
    }

    public function members2oneSignalSubscriptionIds(DataList|ManyManyList $members): array
    {
        // note that OneSignal limits tags to 10 per user!
        $includedSubscriptions = $this->ValidSubscribersBasedOneMemberList($members)
            ->columnUnique('OneSignalUserID');
        return array_filter(array_unique($includedSubscriptions));
    }

    public function ValidSubscribersBasedOneMemberList(DataList|ManyManyList $members): DataList|ManyManyList
    {
        return Subscriber::get()
            ->filter(
                [
                    'MemberID' => $members->columnUnique('ID'),
                    'OneSignalUserID:not' => [null, '', 0],
                    'Subscribed' => true,
                ]
            )->limit(2000);
    }
}
