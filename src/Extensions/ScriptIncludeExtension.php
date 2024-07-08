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
        if($this->owner->dataRecord->UseOneSignal) {
            return;
        }
        $key = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        Requirements::javascript('/_resources/vendor/sunnysideup/push-notifications/client/dist/javascript/service-worker-start.js');
        Requirements::css('/_resources/vendor/sunnysideup/push-notifications/client/dist/css/push.css');
        Requirements::customScript(
            <<<JS
            let vapid_public_key="$key";
        JS
        );
    }
}
