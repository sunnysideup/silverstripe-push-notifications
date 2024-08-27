<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;

class TestOneSignalTask extends BuildTask
{
    protected $title = 'Test One Signal Task';

    protected $description = 'This task is used to test the one signal connectivity';

    private static $segment = 'test-one-signal-task';

    protected $api = null;
    protected $userId = null;

    public function run($request)
    {
        $this->api = Injector::inst()->get(OneSignalSignupApi::class);
        $this->userId = '123';

        $this->header('getApps');
        $this->outcome($this->api->getApps());

        $this->header('getCurrentApp');
        $this->outcome($this->api->getCurrentApp());

        $this->header('addTagsToUser');
        $this->outcome($this->api->addTagsToUser($this->userId, ['test KEY' => 'test Value']));

        $this->header('updateUser');
        $this->outcome($this->api->updateUser('XYZ', ['amount_spent' => 999999.99]));

        $this->header('createSegment');
        $this->outcome($this->api->createSegment('test segment', ['test KEY' => 'test Value']));

        $this->header('deleteSegment');
        $this->outcome($this->api->deleteSegment('123'));

        $this->header('getAllNotifications');
        $notifications = $this->api->getAllNotifications();
        $count = $notifications['total_count'] ?? 0;
        $this->outcome('There are ' . $count . ' notifications');
        if($count > 0) {
            $id = $notifications['notifications'][0]['id'];
            $this->header('getOneNotification');
            $this->outcome($this->api->getOneNotification($id));
        }

        $this->header('THE END');
    }


    protected function header($message)
    {
        if(Director::is_cli()) {
            echo PHP_EOL;
            echo PHP_EOL;
            echo PHP_EOL;
            echo PHP_EOL;
            echo '========================='.PHP_EOL;
            ;
            echo $message . PHP_EOL;
            echo '========================='.PHP_EOL;
            ;
        } else {
            echo '<h2>' . $message . '</h2>';
        }
    }

    protected function outcome($mixed)
    {
        if(Director::is_cli()) {
            echo PHP_EOL;
            echo '========================='.PHP_EOL;
            print_r($mixed);
            echo '========================='.PHP_EOL;
        } else {
            echo '<pre>';
            print_r($mixed);
            echo '</pre>';
        }
    }

}
