<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\NotificationHelper;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
use Sunnysideup\PushNotifications\Api\Providers\PushNotificationOneSignal;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\Subscriber;

class UpdateOneSignal extends BuildTask
{
    protected $title = 'Update OneSignal Data';

    protected $description = 'Goes through all the Groups and all Members and updates their OneSignal data';

    private static $segment = 'update-one-signal';

    protected $api = null;

    public function run($request)
    {
        Environment::increaseTimeLimitTo(3600);
        Environment::increaseMemoryLimitTo('512M');
        // $this->header('WRITING GROUPS');
        // $groups = Group::get()->filter(['OneSignalSegmentID:not' => ['', null, 0]]);
        // foreach($groups as $group) {
        //     $this->outcome('Group: ' . $group->getBreadcrumbsSimple());
        //     $group->write();
        // }
        // $this->header('WRITING SUBSCRIPTIONS');
        // $subscribers = Subscriber::get()->filter(['OneSignalUserID:not' => ['', null, 0]]);
        // foreach($subscribers as $subscriber) {
        //     $this->outcome('Writing: ' . $subscriber->Member()?->Email, ' - '. $subscriber->OneSignalUserID);
        //     $subscriber->write();
        // }
        $this->header('WRITING NOTIFICATIONS');
        /** @var OneSignalSignupApi $api */
        $api = Injector::inst()->get(OneSignalSignupApi::class);
        $allNotifications = $api->getAllNotifications();
        $notificationList = $allNotifications['notifications'] ?? [];
        foreach($notificationList as $oneSignalNotification) {
            $id = $oneSignalNotification['id'] ?? '';
            if(! $id) {
                $this->outcome('ERROR: ' . ' no id for '.print_r($oneSignalNotification, 1));
                continue;
            }
            $this->outcome('Notification: ' . $oneSignalNotification['id']);
            $filter = ['OneSignalNotificationID' => $oneSignalNotification['id']];
            $notification = PushNotification::get()->filter($filter)->first();
            if(! $notification) {
                $notification = PushNotification::create($filter);
                $notificationList->ProviderClass = PushNotificationOneSignal::class;
                if(! $notification->Title) {
                    $notification->Title = $oneSignalNotification['headings']['en'] ?? '';
                }
                if(! $notification->Content) {
                    $notification->Content = $oneSignalNotification['contents']['en'] ?? '';
                }
                $notification->write();
            }
            $valuesForNotificationDataOneObject = NotificationHelper::singleton()
                ->getValuesForNotificationDataOneObject($oneSignalNotification);
            foreach($valuesForNotificationDataOneObject as $key => $value) {
                $notification->{$key} = $value;
            }
            $notification->write();
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
            print_r($mixed);
            echo PHP_EOL;
        } else {
            echo '<pre>';
            print_r($mixed);
            echo '</pre>';
        }
    }

}
