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

class OneSignalSignupApi
{
    use Configurable;
    use Extensible;
    use Injectable;

    protected $oneSignal = null;



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

    public function addTagsToUser(string $externalUserId, array $tags)
    {
        return $this->oneSignal->devices()->editTags(
            $externalUserId,
            [
                'tags' => $tags
            ]
        );
    }

    public function updateUser(string $deviceID, array $data)
    {
        return $this->oneSignal->devices()->update(
            $deviceID,
            $data
        );
    }

    public function createSegment(string $name, array $filters)
    {
        return $this->oneSignal->apps()->createSegment(
            $this->getMyAppID(),
            [
                'name' => $name,
                'filters' => $filters,
            ]
        );
    }


    public function deleteSegment(string $segmentId)
    {
        $this->oneSignal->apps()->deleteSegment(
            $this->getMyAppID(),
            $segmentId
        );
    }


    public function getAllNotifications()
    {
        return $this->oneSignal->notifications()->getAll();
    }

    public function getOneNotification(string $id)
    {
        return $this->oneSignal->notifications()->getOne($id);
        ;
    }

    public function doSendNotification()
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
