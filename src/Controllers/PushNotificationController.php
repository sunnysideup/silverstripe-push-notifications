<?php

namespace Sunnysideup\PushNotifications\Controllers;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Security;
use Sunnysideup\PushNotifications\Model\Subscriber;

class PushNotificationController extends Controller
{
    private static $allowed_actions = [
        'subscribe' => true,
        'subscribeonesignal' => true,
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
        $userID = (int) $request->postVar('userId');
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

            $subscriber->write();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
