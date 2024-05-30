<?php

namespace Sunnysideup\PushNotifications\Controllers;

use Exception;
use SilverStripe\CMS\Controllers\ContentController;


/**
 * Class \Sunnysideup\PushNotifications\Controllers\PushNotificationPageController
 *
 */
class PushNotificationPageController extends ContentController
{
    public function PushNotifications() {
        return PushNotification::get()->filter('Sent', 1)->sort('SentAt', 'DESC');
    }
}
