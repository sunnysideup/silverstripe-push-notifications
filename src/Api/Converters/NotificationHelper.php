<?php

namespace Sunnysideup\PushNotifications\Api\Converters;

use DateTime;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Sunnysideup\PushNotifications\Model\PushNotification;

class NotificationHelper
{
    use Configurable;
    use Injectable;

    private static string $default_scheduled_at_string = '1 hour';

    public function notification2oneSignal(PushNotification $pushNotification, $segments = []): array
    {
        $targetChannel = $pushNotification->OneSignalTargetChannel();
        if(! in_array($targetChannel, ['push', 'email', 'sms'])) {
            user_error('Target channel must be one of the following: push, email, sms');
        }
        $sendAfterString = $pushNotification->ScheduledAt ?: $this->config()->default_scheduled_at_string;
        $dataForNotification = [
            'headings' => [
                'en' => $pushNotification->Title,
            ],
            'contents' => [
                'en' => $pushNotification->Content,
            ],
            // 'data' => ['foo' => 'bar'],
            // 'isChrome' => true,
            'send_after' => new DateTime($sendAfterString),
            "target_channel" => $targetChannel,
            "url" => $pushNotification->AbsoluteLink(),
        ];
        $aliases = MemberHelper::singleton()->members2oneSignalAliases($pushNotification->RecipientGroups());
        if(! empty($aliases)) {
            $dataForNotification['include_aliases'] = [
                'external_id' => $aliases,
            ];
        } else {
            $filter = GroupHelper::singleton()->groups2oneSignalFilter($pushNotification->RecipientGroups());
            if(! empty($filter)) {
                $dataForNotification['filters'] = $filter;
            } elseif(! empty($segments)) {
                $dataForNotification['included_segments'] = GroupHelper::singleton()->groups2oneSignalSegmentFilter($pushNotification->RecipientGroups());
            }
        }
        return $dataForNotification;
    }

}


// ..other options
