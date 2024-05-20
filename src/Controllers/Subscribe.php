<?php

namespace Sunnysideup\PushNotifications\Subscriber;
use Sunnysideup\PushNotifications\Model\Subscriber;

/**
 * @package silverstripe-push
 */
class Subscriber extends Controller
{

    private static $allowed_actions = array(
        'subscribe',
        'push',
    );

    public function subscribe($request)
    {
        $subscription = json_encode($request->postVar('subscription');

        try {

            $subscription = new Subscriber();
            $subscription->Subscription = $subscription;
            $subscription->write();

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }


        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }

    }
}
