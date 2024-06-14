<?php

namespace Sunnysideup\PushNotifications\Controllers;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\ORM\ArrayList;
use Sunnysideup\PushNotifications\Model\PushNotification;

/**
 * Class \Sunnysideup\PushNotifications\Controllers\PushNotificationPageController
 *
 * @property \Sunnysideup\PushNotifications\Model\PushNotificationPage $dataRecord
 * @method \Sunnysideup\PushNotifications\Model\PushNotificationPage data()
 * @mixin \Sunnysideup\PushNotifications\Model\PushNotificationPage
 */
class PushNotificationPageController extends ContentController
{
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
}
