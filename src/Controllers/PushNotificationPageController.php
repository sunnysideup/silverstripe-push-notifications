<?php

namespace Sunnysideup\PushNotifications\Controllers;

use Exception;
use SilverStripe\CMS\Controllers\ContentController;
use Sunnysideup\PushNotifications\Model\PushNotification;



/**
 * Class \Sunnysideup\PushNotifications\Controllers\PushNotificationPageController
 *
 */
class PushNotificationPageController extends ContentController
{
    public function getPushNotifications() {
        return PushNotification::get()->filter('Sent', 1)->sort('SentAt', 'DESC');
    }
}
