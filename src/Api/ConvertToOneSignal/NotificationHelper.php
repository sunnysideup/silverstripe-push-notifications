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
        $date = null;
        if(isset($oneSignalNotification['completed_at'])) {
            $date = date('Y-m-d H:i:s', strtotime($oneSignalNotification['completed_at']));
        }
        $oneSignalNotification['heading'] = (string) ($oneSignalNotification['headings']['en'] ?? 'ERROR: NO HEADING PROVIDED FOR '.$title);
        $oneSignalNotification['content'] = (string) ($oneSignalNotification['contents']['en'] ?? 'NO CONTENT PROVIDED FOR '.$title);
        $oneSignalNotification['hasErrors'] = (bool) ($oneSignalNotification['errored'] ?? true);
        $oneSignalNotification['successfulDeliveries'] = (int) ($oneSignalNotification['successful'] ?? 0);
        $oneSignalNotification['completedAt'] = $date;
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
        // 'includedSegments' => 'IncludedSegments',
        // 'includeAliases' => 'Included',
        // 'excludedSegments' => 'ExcludedSegments',
        // 'id' => 'OneSignalNotificationID',
        // raw data ...
        // 'adm_big_picture' => '',
        // 'adm_group' => '',
        // 'adm_group_message' => '',
        // 'adm_large_icon' => '',
        // 'adm_small_icon' => '',
        // 'adm_sound' => '',
        // 'spoken_text' => '',
        // 'alexa_ssml' => '',
        // 'alexa_display_title' => '',
        // 'amazon_background_data' => '',
        // 'android_accent_color' => '',
        // 'android_group' => '',
        // 'android_group_message' => '',
        // 'android_led_color' => '',
        // 'android_sound' => '',
        // 'android_visibility' => '',
        // 'app_id' => '',
        // 'big_picture' => '',
        // 'buttons' => '',
        // 'canceled' => '',
        // 'chrome_big_picture' => '',
        // 'chrome_icon' => '',
        // 'chrome_web_icon' => '',
        // 'chrome_web_image' => '',
        // 'chrome_web_badge' => '',
        // 'content_available' => '',
        // // 'contents' => ['en' => ''],
        // 'converted' => '',
        // 'data' => '',
        // 'delayed_option' => '',
        // 'delivery_time_of_day' => '',
        // 'errored' => '',
        // // 'excluded_segments' => [],
        // 'failed' => '',
        // 'firefox_icon' => '',
        // 'global_image' => '',
        // 'headings' => [],
        // 'include_player_ids' => '',
        // 'include_external_user_ids' => '',
        // 'include_aliases' => '',
        // // 'included_segments' => [''],
        // 'thread_id' => '',
        // 'ios_badgeCount' => '',
        // 'ios_badgeType' => '',
        // 'ios_category' => '',
        // 'ios_interruption_level' => '',
        // 'ios_relevance_score' => '',
        // 'ios_sound' => '',
        // 'apns_alert' => '',
        // 'target_content_identifier' => '',
        // 'isAdm' => '',
        // 'isAndroid' => '',
        // 'isChrome' => '',
        // 'isChromeWeb' => '',
        // 'isAlexa' => '',
        // 'isFirefox' => '',
        // 'isIos' => '',
        // 'isSafari' => '',
        // 'isWP' => '',
        // 'isWP_WNS' => '',
        // 'isEdge' => '',
        // 'isHuawei' => '',
        // 'isSMS' => '',
        // 'large_icon' => '',
        // 'priority' => '',
        // 'queued_at' => '',
        // 'remaining' => '',
        // 'send_after' => '',
        // 'completed_at' => '',
        // 'small_icon' => '',
        // 'successful' => '',
        // 'received' => 3,
        // 'tags' => '',
        // 'filters' => [
        //     [
        //         'key' => 'is_vip',
        //         'field' => 'tag',
        //         'value' => 'true',
        //         'relation' => '!=',
        //     ],
        //     [
        //         'operator' => 'OR',
        //     ],
        //     [
        //         'key' => 'is_admin',
        //         'field' => 'tag',
        //         'value' => 'true',
        //         'relation' => '=',
        //     ],
        // ],
        // 'template_id' => '',
        // 'ttl' => '',
        // 'url' => '',
        // 'web_url' => '',
        // 'app_url' => '',
        // 'web_buttons' => '',
        // 'web_push_topic' => '',
        // 'wp_sound' => '',
        // 'wp_wns_sound' => '',
        // 'platform_delivery_stats' => [
        //     'chrome_web_push' => [
        //         'successful' => '',
        //         'failed' => '',
        //         'errored' => '',
        //         'converted' => '',
        //         'received' => 3,
        //         'suppressed' => '',
        //         'frequency_capped' => '',
        //     ],
        //     'firefox_web_push' => [
        //         'successful' => '',
        //         'failed' => '',
        //         'errored' => '',
        //         'converted' => '',
        //         'received' => '',
        //         'suppressed' => '',
        //         'frequency_capped' => '',
        //     ],
        //     'safari_web_push' => [
        //         'successful' => '',
        //         'failed' => '',
        //         'errored' => '',
        //         'converted' => '',
        //         'received' => '',
        //         'suppressed' => '',
        //         'frequency_capped' => '',
        //     ],
        // ],
        // 'ios_attachments' => '',
        // 'huawei_sound' => '',
        // 'huawei_led_color' => '',
        // 'huawei_accent_color' => '',
        // 'huawei_visibility' => '',
        // 'huawei_group' => '',
        // 'huawei_group_message' => '',
        // 'huawei_channel_id' => '',
        // 'huawei_existing_channel_id' => '',
        // 'huawei_small_icon' => '',
        // 'huawei_large_icon' => '',
        // 'huawei_big_picture' => '',
        // 'huawei_msg_type' => '',
        // 'throttle_rate_per_minute' => '',
        // 'fcap_group_ids' => '',
        // 'fcap_status' => 'uncapped',
        // 'sms_from' => '',
        // 'sms_media_urls' => '',
        // 'subtitle' => '',
        // 'name' => '',
        // 'email_click_tracking_disabled' => '',
        // 'isEmail' => '',
        // 'email_subject' => '',
        // 'email_from_name' => '',
        // 'email_from_address' => '',
        // 'email_preheader' => '',
        // 'email_reply_to_address' => '',
        // 'include_unsubscribed' => '',
        // 'huawei_category' => '',
        // 'huawei_bi_tag' => '',
        // '_status_code' => 200,
    ];


}
