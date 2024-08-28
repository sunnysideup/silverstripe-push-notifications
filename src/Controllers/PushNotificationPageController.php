<?php

namespace Sunnysideup\PushNotifications\Controllers;

use Exception;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\Subscriber;

/**
 * Class \Sunnysideup\PushNotifications\Controllers\PushNotificationPageController
 *
 * @property \Sunnysideup\PushNotifications\Model\PushNotificationPage $dataRecord
 * @method \Sunnysideup\PushNotifications\Model\PushNotificationPage data()
 * @mixin \Sunnysideup\PushNotifications\Model\PushNotificationPage
 */
class PushNotificationPageController extends ContentController
{
    private static $allowed_actions = [
        'subscribe' => true,
        'unsubscribe' => true,
        'subscribeonesignal' => true,
        'unsubscribeonesignal' => true,
    ];


    public function subscribe($request)
    {
        return $this->subscribeSubcribeInner($request, true);
    }

    public function unsubscribe($request)
    {
        return $this->subscribeSubcribeInner($request, false);
    }

    public function subscribeSubcribeInner($request, ?bool $subscribed = true)
    {
        $subscription = $request->getBody();

        try {
            $subscriber = Subscriber::create();
            $subscriber->Subscription = $subscription;

            $member = Security::getCurrentUser();
            if ($member) {
                $subscriber->MemberID = $member->ID;
            }
            $subscriber->Subscribed = $subscribed;

            $subscriber->write();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }


    public function subscribeonesignal($request)
    {
        return $this->subscribeUnsubscribeOneSignalInner($request, true);
    }

    public function unsubscribeonesignal($request)
    {
        return $this->subscribeUnsubscribeOneSignalInner($request, false);
    }

    protected function subscribeUnsubscribeOneSignalInner($request, bool $subscribed = true)
    {
        $userID = (string) $request->postVar('userId');
        $token = (string) $request->postVar('token');
        if(!$userID) {
            echo json_encode(['success' => false, 'error' => 'No user ID provided']);
            return;
        }
        try {
            $member = Security::getCurrentUser();
            $filter = [
                'MemberID' => $member->ID,
                'OneSignalUserID' => $userID,
            ];
            $subscriber = Subscriber::get()->filter($filter)->first();
            if(! $subscriber) {
                $subscriber = Subscriber::create($filter);
            }
            $subscriber->Subscribed = $subscribed;
            $subscriber->Subscription = $token;

            $subscriber->write();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    public function getPushNotifications()
    {
        $notifications = PushNotification::get()->filter('Sent', 1)->sort('SentAt', 'DESC');
        $output = ArrayList::create();

        foreach ($notifications as $notification) {
            if ($notification->canView()) {
                $output->push($notification);
            }
        }

        return $output;
    }

    protected function init()
    {
        parent::init();
        $link = Director::absoluteURL($this->Link());
        $link = str_replace('?stage=Stage', '', $link);
        Requirements::customScript('window.push_notification_url="'.$link.'";', "push_notification_url");
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/add-to-home-screen.js');
        // Requirements::themedCSS('client/dist/css/push');
        if($this->owner->UseOneSignal) {
            Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/one-signal.js');
            return;
        }
        $key = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/service-worker-start.js');
        if($key && ! $this->UseOneSignal) {
            Requirements::customScript('let vapid_public_key="'.$key.'";', "VapidPublicKey");
        }
    }


}
