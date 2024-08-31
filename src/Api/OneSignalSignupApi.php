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
use Sunnysideup\PushNotifications\Api\Converters\GroupHelper;
use Sunnysideup\PushNotifications\Api\Converters\MemberHelper;
use Sunnysideup\PushNotifications\Api\Converters\NotificationHelper;
use Sunnysideup\PushNotifications\Model\PushNotification;

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

    /**
     * returns the id based on the outcome
     *
     * @param mixed $outcome
     * @return string
     */
    public static function test_id($outcome): string
    {
        if(! is_array($outcome)) {
            return '';
        }
        return (string) $outcome['id'] ?? '';
    }

    public static function get_error($outcome): string
    {
        if(! is_array($outcome)) {
            return 'Outcome is not an array, '.print_r($outcome, 1);
        }
        return implode(',', $outcome['errors']) ?? 'No error message found';
    }

    public static function notification_id_to_onesignal_link($id): string
    {
        return 'https://app.onesignal.com/apps/' . Environment::getEnv('SS_ONESIGNAL_APP_ID') . '/notifications/' . $id;
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
            MemberHelper::singleton()->member2externalUserArray($member)
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
        //reset first...
        $this->addTagsToUser(
            $member,
            []
        );
        return $this->addTagsToUser(
            $member,
            MemberHelper::singleton()->memberGroups2tagCodes($member)
        );
    }



    public function addTagsToUser(Member $member, array $tags): array
    {
        $externalUserId = MemberHelper::singleton()->member2externalUserId($member);
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


    public function createSegmentBasedOnGroup(Group $group): array
    {
        $this->deleteSegmentBasedOnGroup($group);
        $filters[] = [
            'field' => 'tag',
            'key' => GroupHelper::singleton()->group2oneSignalCode($group),
            'relation' => '=',
            'value' => 'Y',
        ];
        $outcome = $this->oneSignal->apps()->createSegment(
            $this->getMyAppID(),
            [
                'name' => GroupHelper::singleton()->group2oneSignalName($group),
                'filters' => $filters,
            ]
        );

        return $outcome;
    }


    public function deleteSegmentBasedOnGroup(Group $group): array
    {
        if($group->OneSignalSegmentID) {
            return $this->deleteSegment($group->OneSignalSegmentID);
        } else {
            return ['success' => 1, ['_status_code' => 200]];
        }
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


    public function doSendNotification(PushNotification $pushNotification, ?array $additionalData = []): array
    {
        $dataForNotification = NotificationHelper::singleton()->notification2oneSignal($pushNotification);
        // add custom data...
        $dataForNotification = array_merge($dataForNotification, $additionalData);

        return $this->oneSignal->notifications()->add($dataForNotification);
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
