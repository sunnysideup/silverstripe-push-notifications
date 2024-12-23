<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\PushNotificationPage;
use Sunnysideup\PushNotifications\Model\Subscriber;

class TestOneSignalTask extends BuildTask
{
    protected $title = 'Test OneSignal Task';

    protected $description = 'This task is used to test the OneSignal connectivity';

    private static $segment = 'test-one-signal';

    protected $api = null;

    public function run($request)
    {
        $this->api = Injector::inst()->get(OneSignalSignupApi::class);

        $this->header('getApps');
        $this->outcome($this->api->getApps());

        $this->header('getCurrentApp');
        $this->outcome($this->api->getCurrentApp());

        $page = PushNotificationPage::get()->first();
        if ($page) {
            $member = Security::getCurrentUser();
            if ($member) {
                $subscriptions = Subscriber::get()
                    ->filter(['OneSignalUserID:not' => null, 'Subcribed' => true, 'MemberID' => $member->ID])
                    ->sort(['ID' => 'ASC']);
                if ($subscriptions) {
                    foreach ($subscriptions as $subscription) {
                        if ($subscription->OneSignalUserID) {
                            $this->header('addExternalUserIdToUser: '.$subscription->getTitle() . ' - ' .$subscription->OneSignalUserID);
                            $this->outcome($this->api->addExternalUserIdToUser($subscription->OneSignalUserID, $member));

                            $this->header('updateDevice: '.$subscription->getTitle() . ' - ' .$subscription->OneSignalUserID);
                            $this->outcome($this->api->updateDevice($subscription->OneSignalUserID, ['amount_spent' => 999999.99]));

                            $this->header('getOneDevice: '.$subscription->getTitle() . ' - ' .$subscription->OneSignalUserID);
                            $this->outcome($this->api->getOneDevice($subscription->OneSignalUserID));
                        }
                    }

                    $this->header('createSegment: test segment');
                    $this->outcome($this->api->createSegment('test segment', ['test KEY' => 'test Value']));

                    $this->header('addTagsToUser: '.$member->Email. ' test KEY');
                    $this->outcome($this->api->addTagsToUser($member, ['test KEY' => 'test Value']));

                    $this->header('addTagsToUserBasedOnGroups: '.$member->Email);
                    $this->outcome($this->api->addTagsToUserBasedOnGroups($member));
                } else {
                    $this->header('User functions');
                    $this->outcome('Error: To test the user functions, please make sure you are signed up for push notifications!');
                }
                $group = $page->SignupGroups()->first();
                if ($group) {
                    $segmentOutcome = $this->api->createSegmentBasedOnGroup($group);
                    $this->header('createSegmentBasedOnGroup: '.$group->Title);
                    $this->outcome($segmentOutcome);

                    $segmentId = OneSignalSignupApi::get_id_from_outcome($segmentOutcome);
                    if ($segmentId) {
                        $this->header('deleteSegment with id: '.$segmentId);
                        $this->outcome($this->api->deleteSegment($segmentId));
                    }
                } else {
                    $this->header('Group functions');
                    $this->outcome('Error: To test the group functions, please make sure you have a group set up in the push notification page.');
                }
                $testPushNotification = PushNotification::create();
                $testPushNotification->Title = 'Header for Test created '.date('Y-m-d H:i:s');
                $testPushNotification->Content = 'Content for Test created '.date('Y-m-d H:i:s');
                $testPushNotification->TestOnly = true;
                $testPushNotification->RecipientMembers()->add($member);
                $testPushNotification->write();
                $api = Injector::inst()->get(OneSignalSignupApi::class);
                $outcome = $api->doSendNotification($testPushNotification);
                $this->header('Test push notification');
                $this->outcome($outcome);
                $api->processResults(
                    $testPushNotification,
                    $outcome,
                    'OneSignalNotificationID',
                    'OneSignalNotificationNote',
                    'Could not add OneSignal notification'
                );
                // important to write results
                $testPushNotification->write();


                $this->header('getAllNotifications');
                $notifications = $this->api->getAllNotifications();
                $count = $notifications['total_count'] ?? 0;
                $this->outcome('There are ' . $count . ' notifications');
                if ($count > 0) {
                    $shown = 0;
                    $tries = 0;
                    while ($shown < 3 && $tries < 10) {
                        $pos = rand(0, $count - 1);
                        $id = $notifications['notifications'][$pos]['id'] ?? '';
                        if ($id) {
                            $shown++;
                            $this->header('getOneNotification - position '.($pos + 1).' - with id: '.$id);
                            $this->outcome($this->api->getOneNotification($id));
                        } else {
                            $this->outcome('ERROR: Could not notification with pos ' . $pos . '');
                            $tries++;
                        }
                    }
                }

                $this->header('getAllDevices');
                $devices = $this->api->getAllDevices();
                $count = $devices['total_count'] ?? 0;
                $this->outcome('There are ' . $count . ' devices subscribed');
                if ($count > 0) {
                    $shown = 0;
                    $tries = 0;
                    while ($shown < 3 && $tries < 10) {
                        $pos = rand(0, $count - 1);
                        $id = $devices['players'][$pos]['id'] ?? '';
                        if ($id) {
                            $shown++;
                            $this->header('getOneDevice - position '.($pos + 1).' - with id: '.$id);
                            $this->outcome($this->api->getOneDevice($id));
                        } else {
                            $this->outcome('ERROR: Could not device with pos ' . $pos . '');
                            $tries++;
                        }
                    }
                }
            } else {
                $this->header('User functions');
                $this->outcome('Error: Please log in first or set up a test user!');
            }
        } else {
            $this->header('User functions');
            $this->outcome('Error: Set up  push notifications page first!');
        }
        $this->header('THE END');
    }


    protected function header($message)
    {
        if (Director::is_cli()) {
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
        if (Director::is_cli()) {
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
