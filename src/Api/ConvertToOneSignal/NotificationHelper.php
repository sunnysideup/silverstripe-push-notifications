<?php

namespace Sunnysideup\PushNotifications\Api\ConvertToOneSignal;

use DateTime;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Sunnysideup\PushNotifications\Model\PushNotification;

class NotificationHelper
{
    use Configurable;
    use Injectable;

    private static string $default_scheduled_at_string = '1 hour';

    private static bool $use_segments_to_target_groups = false;

    public function notification2oneSignal(PushNotification $pushNotification): array
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
            'send_after' => $pushNotification->ScheduledAtNice($sendAfterString),
            "target_channel" => $targetChannel,
            "url" => $pushNotification->AbsoluteLink(),
        ];
        $aliases = MemberHelper::singleton()->members2oneSignalAliases($pushNotification->RecipientMembers());
        if(! empty($aliases)) {
            $dataForNotification['include_aliases'] = [
                'external_id' => $aliases,
            ];
        } else {
            if($this->config()->use_segments_to_target_groups) {
                $segments = GroupHelper::singleton()->groups2oneSignalSegmentFilter($pushNotification->RecipientGroups());
                if(! empty($segments)) {
                    $dataForNotification['included_segments'] = $segments;
                } else {
                    $dataForNotification['included_segments'] = ['do not send to anybody'];
                }
            } else {
                $filters = GroupHelper::singleton()->groups2oneSignalFilter($pushNotification->RecipientGroups());
                if(! empty($filters)) {
                    $dataForNotification['filters'] = $filters;
                } else {
                    $dataForNotification['included_segments'] = ['do not send to anybody'];
                }
            }

        }
        return $dataForNotification;
    }

}


// ..other options
