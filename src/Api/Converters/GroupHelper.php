<?php

namespace Sunnysideup\PushNotifications\Api\Converters;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;

class GroupHelper
{
    use Configurable;
    use Injectable;

    public function group2oneSignalCode(Group $group): string
    {
        return 'group_' . $group->ID;
    }

    public function group2oneSignalName(Group $group): string
    {
        return 'Website: '.$group->getBreadcrumbs();
    }

    public function groups2oneSignalFilter(DataList $groups): array
    {
        $length = $groups->count();
        $count = 0;
        $filters = [];
        foreach($groups as $count => $group) {
            $count++;
            $filters[] = [
                'field' => 'tag',
                'key' => $this->group2oneSignalCode($group),
                'relation' => '=',
                'value' => 'Y',
            ];
            if($count < $length) {
                $filters[] = [
                    'operator' => 'OR',
                ];
            }
        }
        return $filters;

    }

    public function groups2oneSignalSegmentFilter(DataList $groups): array
    {
        $list = [];
        foreach($groups as $group) {
            $list[] = $this->group2oneSignalCode($group);
        }
        return $list;

    }

}
