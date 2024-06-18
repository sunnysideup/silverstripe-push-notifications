<?php

namespace Sunnysideup\PushNotifications\Extensions;

use SilverStripe\Core\Environment;
use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Class \Sunnysideup\PushNotifications\Extensions\ScriptIncludeExtension
 */
class ScriptIncludeExtension extends Extension
{
    public function onAfterInit()
    {
        $key = Environment::getEnv('SS_VAPID_PUBLIC_KEY');

        Requirements::javascript('/_resources/vendor/sunnysideup/push-notifications/client/dist/javascript/service-worker-start.js');
        Requirements::customScript(<<<JS
            let vapid_public_key="$key";
        JS
        );
    }
}
