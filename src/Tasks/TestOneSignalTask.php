<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Model\Subscriber;

class TestOneSignalTask extends BuildTask
{
    protected $title = 'Test OneSignal Task';

    protected $description = 'This task is used to test the one signal connectivity';

    private static $segment = 'test-one-signal-task';

    protected $api = null;

    public function run($request)
    {
        $this->api = Injector::inst()->get(OneSignalSignupApi::class);

        $this->header('getApps');
        $this->outcome($this->api->getApps());

        $this->header('getCurrentApp');
        $this->outcome($this->api->getCurrentApp());

        $this->header('doSendNotification');
        $this->outcome($this->api->doSendNotification());

        // $subscription = Subscriber::get()
        //     ->filter(['OneSignalUserID:not' => ['', null, 0]])
        //     ->sort(['ID' => 'ASC'])
        //     ->first();
        // $member = $subscription->Member();
        // $group = Group::get()->first();

        // if($subscription && $member) {

        //     $this->header('addExternalUserIdToUser: '.$subscription->OneSignalUserID.' - '.$member->Email);
        //     $this->outcome($this->api->addExternalUserIdToUser($subscription->OneSignalUserID, $member));

        //     $this->header('updateDevice: '.$subscription->OneSignalUserID);
        //     $this->outcome($this->api->updateDevice($subscription->OneSignalUserID, ['amount_spent' => 999999.99]));

        //     $this->header('createSegment: test segment');
        //     $this->outcome($this->api->createSegment('test segment', ['test KEY' => 'test Value']));

        //     $this->header('addTagsToUser: '.$member->Email. ' test KEY');
        //     $this->outcome($this->api->addTagsToUser($member, ['test KEY' => 'test Value']));

        //     $this->header('addTagsToUserBasedOnGroups: '.$member->Email);
        //     $this->outcome($this->api->addTagsToUserBasedOnGroups($member));

        //     $segmentOutcome = $this->api->createSegmentBasedOnGroup($group);
        //     $this->header('createSegmentBasedOnGroup: '.$group->Title);
        //     $this->outcome($segmentOutcome);

        //     $segmentId = OneSignalSignupApi::test_id($segmentOutcome);
        //     if($segmentId) {
        //         $this->header('deleteSegment with id: '.$segmentId);
        //         $this->outcome($this->api->deleteSegment($segmentId));
        //     }
        // } else {
        //     $this->header('User functions');
        //     $this->outcome('Error: No user found!');
        // }

        $this->header('getAllNotifications');
        $notifications = $this->api->getAllNotifications();
        $count = $notifications['total_count'] ?? 0;
        $this->outcome('There are ' . $count . ' notifications');
        if($count > 0) {
            $id = $notifications['notifications'][0]['id'];
            $this->header('getOneNotification with id: '.$id);
            $this->outcome($this->api->getOneNotification($id));
        }

        $this->header('THE END');
    }


    protected function header($message)
    {
        if(Director::is_cli()) {
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
            print_r($mixed).PHP_EOL;
            echo PHP_EOL;
            echo '========================='.PHP_EOL;
            echo PHP_EOL;
        } else {
            echo '<pre>';
            print_r($mixed);
            echo '</pre>';
        }
    }

}
