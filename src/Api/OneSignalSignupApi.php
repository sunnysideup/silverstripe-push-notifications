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
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\GroupHelper;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\LinkHelper;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\MemberHelper;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\NotificationHelper;
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

    private const REQUIRED_ENV_VARS = [
        'SS_ONESIGNAL_APP_ID',
        'SS_ONESIGNAL_REST_API_KEY',
        'SS_ONESIGNAL_USER_AUTH_KEY',
    ];

    protected $oneSignal = null;

    public static function test_success($outcome): bool
    {
        if (! is_array($outcome)) {
            return false;
        }
        $success = (int) ($outcome['success'] ?? 0);
        if ($success) {
            return true;
        }
        $statusCode = (int) ($outcome['_status_code'] ?? 0);
        if ($statusCode > 199 && $statusCode < 300) {
            return true;
        }
        return false;
    }


    public static function is_enabled(): bool
    {
        foreach (self::REQUIRED_ENV_VARS as $key) {
            if (! Environment::getEnv($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * returns the id based on the outcome
     *
     * @param mixed $outcome
     * @return string
     */
    public static function get_id_from_outcome($outcome): string
    {
        if (! is_array($outcome)) {
            return '';
        }
        return (string) $outcome['id'] ?? '';
    }

    public static function get_error($outcome, ?string $alternativeError = ''): string
    {
        if (!$alternativeError) {
            $alternativeError = 'ERROR IN: '.get_called_class().'.';
        }
        if (! is_array($outcome)) {
            return $alternativeError.' Outcome is not an array: ' . print_r($outcome, 1);
        }
        if (! empty($outcome['errors'])) {
            foreach ($outcome['errors'] as $key => $error) {
                $outcome['errors'][$key] = print_r($error, 1);
            }
            return implode(',', $outcome['errors']);
        } else {
            return $alternativeError;
        }
    }

    public static function notification_id_to_onesignal_link($id): string
    {
        return LinkHelper::singleton()->notificationLink($id);
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


    /**
     * Process the results of an API call.
     * Returns true if the outcome is a success.
     * We can not write here as this maybe called onBeforeWrite
     * @param mixed $obj
     * @param array $outcome
     * @param mixed $idStoredInFieldName
     * @param mixed $noteStoredInFieldName
     * @param mixed $alternativeError
     * @return bool
     */
    public function processResults(
        $obj,
        array $outcome,
        ?string $idStoredInFieldName = '',
        ?string $noteStoredInFieldName = '',
        ?string $alternativeError = ''
    ): bool {
        if (OneSignalSignupApi::test_success($outcome)) {
            if ($idStoredInFieldName) {
                $obj->$idStoredInFieldName = OneSignalSignupApi::get_id_from_outcome($outcome);
            }
            if ($noteStoredInFieldName) {
                $obj->$noteStoredInFieldName = 'Succesfully connected to OneSignal';
            }
            // intended exit point
            return true;
        } else {
            // in case there is a comms error!
            // if ($idStoredInFieldName) {
            //     $obj->$idStoredInFieldName = '';
            // }
            if ($noteStoredInFieldName) {
                $obj->$noteStoredInFieldName = OneSignalSignupApi::get_error($outcome, $alternativeError);
            }
        }
        // alterntive exit point
        return false;
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
        foreach (array_keys($data) as $key) {
            if (! in_array($key, self::ACCEPTED_USER_SETTING, true)) {
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
        if ($group->OneSignalSegmentID) {
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


    public function getAllDevices(): array
    {
        return $this->oneSignal->devices()->getAll();
    }

    public function getOneDevice(string $id): array
    {
        return $this->oneSignal->devices()->getOne($id);
    }

    public function getAllNotifications(): array
    {
        return $this->oneSignal->notifications()->getAll();
    }

    public function getOneNotification(string $id): array
    {
        return $this->oneSignal->notifications()->getOne($id);
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
        $array = [];
        foreach (self::REQUIRED_ENV_VARS as $key) {
            if (! Environment::getEnv($key)) {
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
