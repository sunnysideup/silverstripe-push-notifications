<?php

namespace Sunnysideup\PushNotifications\Controllers;

use Exception;
use SilverStripe\CMS\Controllers\ContentController;
use Sunnysideup\PushNotifications\Model\PushNotification;
use SilverStripe\ORM\ArrayList;

class PushNotificationPageController extends ContentController
{
    public function getPushNotifications() {
        $notifications = PushNotification::get()->filter('Sent', 1)->sort('SentAt', 'DESC');
        $output = ArrayList::create();

        foreach ($notifications as $notification) {
            if ($notification->canView()) {
                $output->push($notification);
            }
        }


        return $output;
    }
}
