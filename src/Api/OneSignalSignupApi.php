<?php

namespace Sunnysideup\PushNotifications\Api;

use OneSignal\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

use OneSignal\OneSignal;
use Symfony\Component\HttpClient\Psr18Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal\GroupHelper;
use Sunnysideup\PushNotifications\Api\MemberAndGroupToOneSignal\MemberHelper;

class OneSignalSignupApi
{
    use Configurable;
    use Extensible;
    use Injectable;
    private const ACCEPTED_USER_SETTING = [
        'ad_id',
        'amount_spent',
        'app_id',
        'badge_count',
        'country',
        'created_at',
        'device_model',
        'device_os',
        'external_user_id',
        'external_user_id_auth_hash',
        'game_version',
        'identifier',
        'identifier_auth_hash',
        'ip',
        'language',
        'last_active',
        'lat',
        'long',
        'notification_types',
        'playtime',
        'sdk',
        'session_count',
        'tags',
        'test_type',
        'timezone',
    ];

    protected $oneSignal = null;

    public static function test_success($outcome): bool
    {
        if(! is_array($outcome)) {
            return false;
        }
        if(isset($outcome['success']) && (int) $outcome['success'] === 1) {
            return true;
        }
        return false;
    }

    public static function get_error($outcome): string
    {
        if(! is_array($outcome)) {
            return 'Outcome is not an array, '.print_r($outcome, 1);
        }
        return $outcome['errors'] ?? 'No error message found';
    }

    public function __construct()
    {

        $config = new Config(
            ...$this->checkAndGetCredentials()
        );
        $httpClient = new Psr18Client();
        $requestFactory = $streamFactory = new Psr17Factory();

        $this->oneSignal = new OneSignal($config, $httpClient, $requestFactory, $streamFactory);
    }


    public function getApps()
    {
        return $this->oneSignal->apps()->getAll();
    }


    public function getCurrentApp()
    {
        return $this->oneSignal->apps()->getOne($this->getMyAppID());
    }

    public function addExternalUserIdToUser(string $deviceID, Member $member): array
    {
        return $this->updateDevice(
            $deviceID,
            MemberHelper::member_2_external_user_array($member)
        );
    }


    public function deleteDevice(string $deviceID): array
    {
        return $this->oneSignal->devices()->delete(
            $deviceID,
        );
    }



    public function addTagsToUserBasedOnGroups(Member $member): array
    {
        return $this->addTagsToUser(
            $member,
            MemberHelper::member_groups_2_tag_codes($member)
        );
    }



    public function addTagsToUser(Member $member, array $tags): array
    {
        $externalUserId = MemberHelper::member_2_external_user_id($member);
        return $this->oneSignal->devices()->editTags(
            $externalUserId,
            [
                'tags' => $tags
            ]
        );
    }


    public function updateDevice(string $deviceID, array $data): array
    {
        foreach(array_keys($data) as $key) {
            if(! in_array($key, self::ACCEPTED_USER_SETTING, true)) {
                user_error('Key ' . $key . ' is not accepted. Please use one of the following: ' . implode(', ', self::ACCEPTED_USER_SETTING));
            }
        }
        return $this->oneSignal->devices()->update(
            $deviceID,
            $data
        );
    }

    public function createSegment(string $name, array $filters): array
    {
        return $this->oneSignal->apps()->createSegment(
            $this->getMyAppID(),
            [
                'name' => $name,
                'filters' => $filters,
            ]
        );
    }

    public function createSegmentBasedOnMembers(string $name, DataList $members): array
    {
        $filters = [];
        foreach($members as $member) {
            $filters[] = [
                'field' => 'external_user_id',
                'key' => MemberHelper::member_2_external_user_id($member),
                'relation' => '=',
                'value' => 'true',
            ];
        }
        return $this->oneSignal->apps()->createSegment(
            $this->getMyAppID(),
            [
                'name' => $name,
                'filters' => $filters,
            ]
        );
    }


    public function createSegmentBasedOnGroup(Group $group): array
    {
        $filters[] = [
            'field' => 'tag',
            'key' => GroupHelper::group_2_code($group),
            'relation' => '=',
            'value' => 'Y',
        ];
        return $this->oneSignal->apps()->createSegment(
            $this->getMyAppID(),
            [
                'name' => GroupHelper::group_2_name($group),
                'filters' => $filters,
            ]
        );
    }


    public function deleteSegmentBasedOnGroup(Group $group): array
    {
        return $this->deleteSegment($group->OneSignalSegmentID);
    }

    public function deleteSegment(string $segmentId): array
    {
        return $this->oneSignal->apps()->deleteSegment(
            $this->getMyAppID(),
            $segmentId
        );
    }


    public function getAllNotifications(): array
    {
        return $this->oneSignal->notifications()->getAll();
    }

    public function getOneNotification(string $id): array
    {
        return $this->oneSignal->notifications()->getOne($id);
        ;
    }

    public function doSendNotification(): array
    {
        if(1 === 2) {
            $this->oneSignal->notifications()->add([
                'contents' => [
                    'en' => 'Notification message'
                ],
                'included_segments' => ['All'],
                'data' => ['foo' => 'bar'],
                'isChrome' => true,
                'send_after' => new \DateTime('1 hour'),
                'filters' => [
                    [
                        'field' => 'tag',
                        'key' => 'is_vip',
                        'relation' => '!=',
                        'value' => 'true',
                    ],
                    [
                        'operator' => 'OR',
                    ],
                    [
                        'field' => 'tag',
                        'key' => 'is_admin',
                        'relation' => '=',
                        'value' => 'true',
                    ],
                ],
                // ..other options
            ]);
        }
        die('not implemented');
    }


    protected function checkAndGetCredentials(): array
    {
        $vars = [
            'SS_ONESIGNAL_APP_ID',
            'SS_ONESIGNAL_REST_API_KEY',
            'SS_ONESIGNAL_USER_AUTH_KEY',
        ];
        $array = [];
        foreach($vars as $key) {
            if(! Environment::getEnv($key)) {
                user_error('Please add ' . $key . ' to your .env file');
            } else {
                $array[] = Environment::getEnv($key);
            }
        }
        return $array;
    }

    protected function getMyAppID()
    {
        return Environment::getEnv('SS_ONESIGNAL_APP_ID');
    }

}
