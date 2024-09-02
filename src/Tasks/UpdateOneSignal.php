<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
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
        Config::modify()->set(DataObject::class, 'validation_enabled', false);
        $this->syncGroups();
        $this->syncSubscribers();
        $this->syncNotificationsFromOneSignal();
        $this->syncNotificationsFromWebsite();

    }

    protected function syncGroups()
    {
        $this->header('WRITING GROUPS');
        $groups = Group::get()->filter(['OneSignalSegmentID:not' => ['', null, 0]]);
        foreach ($groups as $group) {
            $this->outcome('Group: ' . $group->getBreadcrumbsSimpleWithCount());
            $group->OneSignalComms(true);
        }
    }

    protected function syncSubscribers()
    {
        $this->header('WRITING SUBSCRIPTIONS');
        $subscribers = Subscriber::get()->filter(['OneSignalUserID:not' => ['', null, 0]]);
        foreach ($subscribers as $subscriber) {
            $this->outcome('Writing: ' . $subscriber->getTitle(). ' - '. $subscriber->OneSignalUserID);
            $subscriber->OneSignalComms(true);
        }
    }

    protected function syncNotificationsFromOneSignal()
    {
        $this->header('WRITING NOTIFICATIONS FROM ONE SIGNAL');
        /** @var OneSignalSignupApi $api */
        $api = Injector::inst()->get(OneSignalSignupApi::class);
        $allNotifications = $api->getAllNotifications();
        $notificationList = $allNotifications['notifications'] ?? [];
        foreach ($notificationList as $oneSignalNotification) {
            $id = $oneSignalNotification['id'] ?? '';
            if (! $id) {
                $this->outcome('ERROR: ' . ' no id for '.print_r($oneSignalNotification, 1));
                continue;
            }
            $this->outcome('Checking Notification: ' . $oneSignalNotification['id']);
            $filter = ['OneSignalNotificationID' => $oneSignalNotification['id']];
            $notification = PushNotification::get()->filter($filter)->first();
            if (! $notification) {
                $notification = PushNotification::create($filter);
                $notification->ProviderClass = PushNotificationOneSignal::class;
                if (! $notification->Title) {
                    $notification->Title = $oneSignalNotification['headings']['en'] ?? '';
                }
                if (! $notification->Content) {
                    $notification->Content = $oneSignalNotification['contents']['en'] ?? '';
                }
                $notification->write();
            }
            $valuesForNotificationDataOneObject = NotificationHelper::singleton()
                ->getValuesForNotificationDataOneObject($oneSignalNotification);
            foreach ($valuesForNotificationDataOneObject as $key => $value) {
                $notification->{$key} = $value;
            }
            $notification->write();
        }

    }

    protected function syncNotificationsFromWebsite()
    {
        $this->header('WRITING NOTIFICATIONS FROM WEBSITE');
        /** @var OneSignalSignupApi $api */
        $notifications = PushNotification::get()
            ->filter(['OneSignalNotificationID:not' => ['', null, 0]]);
        /** @var PushNotification $notification */
        foreach ($notifications as $notification) {
            $this->outcome('Writing: ' . $notification->getTitle());
            $notification->OneSignalComms(true);
        }

    }


    protected function header($message)
    {
        if (Director::is_cli()) {
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
        if (Director::is_cli()) {
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
