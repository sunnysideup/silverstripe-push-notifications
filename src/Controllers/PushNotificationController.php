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
    ];

    private static $url_handlers = [
        'subscribe' => 'subscribe',
    ];

    public function subscribe($request)
    {
        $subscription = $request->getBody();

        try {
            $subscriber = Subscriber::create();
            $subscriber->Subscription = $subscription;

            $member = Security::getCurrentUser();
            if ($member) {
                $subscriber->MemberID = $member->ID;
            }

            $subscriber->write();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
