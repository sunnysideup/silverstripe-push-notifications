<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Model\Subscriber;

class UpdateOneSignal extends BuildTask
{
    protected $title = 'Test One Signal Task';

    protected $description = 'Goes through all the Groups and all Members and updates their OneSignal data';

    private static $segment = 'update-one-signal';

    protected $api = null;

    public function run($request)
    {
        Environment::increaseTimeLimitTo(3600);
        Environment::increaseMemoryLimitTo('512M');
        $groups = Group::get();
        foreach($groups as $group) {
            $this->header('Group: ' . $group->Title);
            $group->write();
            foreach($group->Members() as $member) {
                $this->header('Member: ' . $member->Email);
                $member->write();
            }
        }
    }


    protected function header($message)
    {
        if(Director::is_cli()) {
            echo PHP_EOL;
            echo PHP_EOL;
            echo PHP_EOL;
            echo PHP_EOL;
            echo '========================='.PHP_EOL;
            ;
            echo $message . PHP_EOL;
            echo '========================='.PHP_EOL;
            ;
        } else {
            echo '<h2>' . $message . '</h2>';
        }
    }

    protected function outcome($mixed)
    {
        if(Director::is_cli()) {
            echo PHP_EOL;
            echo '========================='.PHP_EOL;
            print_r($mixed);
            echo '========================='.PHP_EOL;
        } else {
            echo '<pre>';
            print_r($mixed);
            echo '</pre>';
        }
    }

}
