<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\ScriptIncludeExtension
 */
class ScriptIncludeExtension extends Extension
{
    public function onAfterInit()
    {
        Requirements::javascript('/_resources/vendor/sunnysideup/push-notifications/client/dist/javascript/service-worker-start.js');
    }
}
