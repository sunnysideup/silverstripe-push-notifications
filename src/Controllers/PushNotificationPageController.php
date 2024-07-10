<?php

namespace Sunnysideup\PushNotifications\Controllers;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\Requirements;
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

    protected function init()
    {
        parent::init();
        if($this->owner->dataRecord->UseOneSignal) {
            return;
        }
        $key = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/service-worker-start.js');
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/add-to-home-screen.js');
        Requirements::css('sunnysideup/push-notifications: client/dist/css/push.css');
        if($key && ! $this->UseOneSignal) {
            Requirements::customScript('let vapid_public_key="'.$key.'";', "VapidPublicKey");
        }
    }

}
