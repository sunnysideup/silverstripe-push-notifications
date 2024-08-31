<?php

namespace Sunnysideup\PushNotifications\Api\ConvertToOneSignal;

use DateTime;
use DateTimeZone;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Sunnysideup\PushNotifications\Model\PushNotification;

class NotificationHelper
{
    use Configurable;
    use Injectable;

    private static string $default_scheduled_at_string = '1 hour';

    private static bool $use_segments_to_target_groups = false;
    private static bool $remove_unused_segments_from_onesignal = true;

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
            'send_after' => $pushNotification->ScheduledAtDateTimeInterface($sendAfterString),
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

    public function getValuesForNotificationDataOneObject(array $oneSignalNotification): array
    {
        $array = [];
        $title = $oneSignalNotification['id'] ?? ' ERROR - NO ID PROVIDED';
        $localDateTime = null;
        if(! empty($oneSignalNotification['completed_at'])) {
            $utcDateTime = (new DateTime())
                ->setTimestamp($oneSignalNotification['completed_at'])
                ->setTimezone(new DateTimeZone('UTC'));

            // Set the desired timezone (e.g., 'America/New_York', 'Europe/London', etc.)
            $timezone = new DateTimeZone(date_default_timezone_get()); // Replace with your timezone

            // Convert the UTC DateTime to the desired timezone
            $utcDateTime->setTimezone($timezone);

            // Format the DateTime object to your desired format
            $localDateTime = $utcDateTime->format('Y-m-d H:i:s');
        }
        $oneSignalNotification['heading'] = (string) ($oneSignalNotification['headings']['en'] ?? 'ERROR: NO HEADING PROVIDED FOR '.$title);
        $oneSignalNotification['content'] = (string) ($oneSignalNotification['contents']['en'] ?? 'NO CONTENT PROVIDED FOR '.$title);
        $oneSignalNotification['hasErrors'] = (bool) ($oneSignalNotification['errored'] ?? true);
        $oneSignalNotification['successfulDeliveries'] = (int) ($oneSignalNotification['successful'] ?? 0);
        $oneSignalNotification['completedAt'] = $localDateTime;
        // $oneSignalNotification['includedSegments'] = implode(',', $oneSignalNotification['included_segments'] ?? []);
        // $oneSignalNotification['excludedSegments'] = implode(',', $oneSignalNotification['excluded_segments'] ?? []);
        // $transformedArray = $this->flattenArray($oneSignalNotification);
        foreach(self::TRANSFORMATION_ID as $key => $silverstripeFieldName) {
            if($silverstripeFieldName) {
                if(isset($oneSignalNotification[$key])) {
                    $array[$silverstripeFieldName] = $oneSignalNotification[$key];
                }
            }
        }
        return $array;
    }


    private const TRANSFORMATION_ID = [
        'heading' => 'Title',
        'content' => 'Content',
        'hasErrors' => 'HasSendingErrors',
        'successfulDeliveries' => 'OneSignalNumberOfDeliveries',
        'completedAt' => 'SentAt',
    ];


}
