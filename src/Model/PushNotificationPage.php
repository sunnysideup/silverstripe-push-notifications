<?php

namespace Sunnysideup\PushNotifications\Model;

use Exception;
use SilverStripe\Control\Controller;
use Sunnysideup\PushNotifications\Model\Subscriber;
use SilverStripe\Security\Security;
use Page;
use Sunnysideup\PushNotifications\Controllers\PushNotificationPageController;



/**
 * Class \Sunnysideup\PushNotifications\Model\PushNotificationPage
 *
 */
class PushNotificationPage extends Page
{
    private $controller_class = PushNotificationPageController::class;


}
