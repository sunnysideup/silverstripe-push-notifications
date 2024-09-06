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
use Sunnysideup\PushNotifications\Extensions\GroupExtension;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\Subscriber;

class UpdateOneSignal extends BuildTask
{
    protected $title = 'Update OneSignal Data';

    protected $description = 'Goes through all the Groups and all Members and updates their OneSignal data';

    private static $segment = 'update-one-signal';
    private static bool $also_sync_notifications_back = false;

    protected $api = null;

    public function run($request)
    {
        Environment::increaseTimeLimitTo(3600);
        Environment::increaseMemoryLimitTo('512M');
        Config::modify()->set(DataObject::class, 'validation_enabled', false);
        $this->syncGroups();
        $this->syncSubscribersAndMembers();
        $this->syncNotificationsFromOneSignal();
        $this->syncNotificationsFromWebsite();
        $this->header('THE END!');

    }

    protected function syncGroups()
    {
        $this->header('WRITING GROUPS');
        $groups = Group::get()->filter(['OneSignalSegmentID:not' => null]);
        foreach ($groups as $group) {
            $this->outcome('Group: ' . $group->getBreadcrumbsSimpleWithCount());
            $groupUpdated = $group->OneSignalComms(true);
            $this->outcome('... Group '.($groupUpdated ? '' : 'NOT').' updated.');
        }
    }

    protected function syncSubscribersAndMembers()
    {
        $this->header('WRITING SUBSCRIPTIONS');
        $membersDone = [-1 => -1];
        $subscribers = Subscriber::get()
            ->filter(['OneSignalUserID:not' => null])
            ->sort(['ID' => 'DESC'])
            ->limit(2000);
        foreach ($subscribers as $subscriber) {
            $this->outcome('Writing: ' . $subscriber->getTitle(). ' - '. $subscriber->OneSignalUserID);
            $subcriberUpdated = $subscriber->OneSignalComms(true);
            $this->outcome('... Subscriber '.($subcriberUpdated ? '' : 'NOT').' updated.');
            if ($subscriber->MemberID && !isset($membersDone[$subscriber->MemberID])) {
                $membersDone[$subscriber->MemberID] = $subscriber->MemberID;
                $member = $subscriber->Member();
                if ($member && $member->exists()) {
                    $this->outcome('Checking if : ' . $member->getTitle(). ' is part of the all subscribers group');
                    $memberUpdated = $member->OneSignalComms(true, false);
                    $this->outcome('... Member '.($memberUpdated ? '' : 'NOT').' updated: ');
                }

            }
        }
        $allSubcribersGroup = GroupExtension::get_all_subscribers_group();
        foreach ($allSubcribersGroup->Members()->exclude(['ID' => $membersDone]) as $member) {
            $this->outcome('Checking if : ' . $member->getTitle(). ' still needs to be part of the all subscribers group');
            $memberUpdated = $member->OneSignalComms(true, false);
            $this->outcome('... Member '.($memberUpdated ? '' : 'NOT').' updated: ');
        }
    }

    protected function syncNotificationsFromOneSignal()
    {
        $this->header('WRITING NOTIFICATIONS FROM ONE SIGNAL');
        if ($this->config()->also_sync_notifications_back !== true) {
            $this->outcome('skip sync notifications back, this is set in the also_sync_notifications_back variable.');
            return;
        }
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
            $notification->Sent = true;
            $notification->write();
        }
    }

    protected function syncNotificationsFromWebsite()
    {
        $this->header('WRITING NOTIFICATIONS FROM WEBSITE');
        /** @var OneSignalSignupApi $api */
        $notifications = PushNotification::get()
            ->filter(['OneSignalNotificationID:not' => null])
            ->limit(200);
        /** @var PushNotification $notification */
        foreach ($notifications as $notification) {
            $this->outcome('Writing: ' . $notification->getTitle());
            $notificationUpdatd = $notification->OneSignalComms(true);
            $this->outcome('... Notification '.($notificationUpdatd ? '' : 'NOT').' updated.');
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
