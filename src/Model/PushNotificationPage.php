<?php

namespace Sunnysideup\PushNotifications\Model;

use Exception;
use Page;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\SiteConfig\SiteConfig;
use stdClass;
use Sunnysideup\PushNotifications\Controllers\PushNotificationPageController;

class PushNotificationPage extends Page
{
    public const ONE_SIGNAL_INIT_FILE_NAME = 'OneSignalSDKWorker.js';

    private static $table_name = 'PushNotificationPage';

    private static $icon_class = 'font-icon-fast-forward';

    private static $controller_name = PushNotificationPageController::class;

    private static $db = [
        'UseOneSignal' => 'Boolean',
        'OneSignalKey' => 'Varchar(65)',
    ];

    protected function modifyJsonValue(string $filePath, string $key, $newValue): void
    {
        // Check if the file exists
        if (! file_exists($filePath)) {
            throw new Exception('File not found.');
        }

        // Read the file contents
        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new Exception('Failed to read the file.');
        }

        // Decode the JSON data into an associative array
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode JSON: ' . json_last_error_msg());
        }

        // Modify the value
        $data[$key] = $newValue;

        // Encode the data back to JSON
        $newJsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($newJsonContent === false) {
            throw new Exception('Failed to encode JSON: ' . json_last_error_msg());
        }

        // Save the modified JSON data back to the file
        try {
            file_put_contents($filePath, $newJsonContent);
        } catch (Exception $e) {
            throw $e;
        }

    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->URLSegment = 'push-notifications';
        $this->ParentID = 0;
        // Modify the JSON value
        if($this->canAccessOrCreateFile()) {
            $this->modifyJsonValue($this->getManifestPath(), '$schema', "https://json.schemastore.org/web-manifest-combined.json");
            $this->modifyJsonValue($this->getManifestPath(), 'name', SiteConfig::current_site_config()->Title);
            $this->modifyJsonValue($this->getManifestPath(), 'short_name', SiteConfig::current_site_config()->Title);
            $this->modifyJsonValue($this->getManifestPath(), 'start_url', '/push-notifications');
            $this->modifyJsonValue($this->getManifestPath(), 'display', 'standalone');
        }
        if($this->UseOneSignal) {
            try {
                copy(
                    Controller::join_links(
                        Director::baseFolder(),
                        '/vendor/sunnysideup/push-notifications/client/dist/third-party/',
                        self::ONE_SIGNAL_INIT_FILE_NAME
                    ),
                    Controller::join_links(
                        PUBLIC_PATH,
                        self::ONE_SIGNAL_INIT_FILE_NAME
                    ),
                );
            } catch (Exception $e) {
                // do nothing
            }
        }

    }

    public function canPublish($member = null)
    {
        if($this->canAccessOrCreateFile()) {
            return parent::canPublish($member);
        }
        return false;
    }

    protected function getManifestPath(): string
    {
        return Controller::join_links(BASE_PATH, 'public', 'manifest.json');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.PushNotifications',
            [
                CheckboxField::create('UseOneSignal', 'Use OneSignal'),
                TextField::create('OneSignalKey', 'One Signal Key'),

            ]
        );
        if($this->UseOneSignal) {
            if(!$this->OneSignalKey) {
                // $fields->removeByName('PushNotifications');
                $fields->addFieldsToTab(
                    'Root.Manage',
                    [
                        LiteralField::create(
                            'OneSignalInfo',
                            '<h2>One Signal</h2>
                            <p>
                                Please access one signal to manage your push notifications:
                                <br />
                                <a href="https://app.onesignal.com/apps/new" target="_blank">Create a new app</a>
                                </p>
                            '
                        ),
                    ]
                );
            } else {
                $fields->addFieldsToTab(
                    'Root.Manage',
                    [
                        LiteralField::create(
                            'OneSignalInfo',
                            '<h2>One Signal</h2>
                            <p>
                                Please access one signal to manage your push notifications:
                                <br />
                                <a href="https://dashboard.onesignal.com/apps/'.$this->OneSignalKey.'/settings/webpush/configure" target="_blank" rel="noopener noreferrer">Configure (with care!)</a>
                                <br />
                                <a href="https://dashboard.onesignal.com/apps/'.$this->OneSignalKey.'/campaigns" target="_blank"  rel="noopener noreferrer">Send Push Notification</a>
                                <br />
                                <a href="https://dashboard.onesignal.com/apps/'.$this->OneSignalKey.'/notifications" target="_blank" rel="noopener noreferrer">Review sent messages</a>
                            </p>
                            '
                        ),
                    ]
                );
            }
        } else {
            $fields->addFieldsToTab(
                'Root.PushNotifications',
                [
                    GridField::create(
                        'PushNotifications',
                        'Push Notifications',
                        PushNotification::get(),
                        $config = GridFieldConfig_RecordEditor::create()
                    )
                ]
            );
            $fields->addFieldsToTab(
                'Root.Subscribers',
                [
                    GridField::create(
                        'Subscribers',
                        'Subscribers',
                        Subscriber::get(),
                        $config = GridFieldConfig_RecordEditor::create()
                    )
                ]
            );
        }
        $fields->addFieldsToTab(
            'Root.Main',
            [
                LiteralField::create(
                    'PushNotificationsInfo',
                    '
                    <p class="message warning">
                        Please make sure to review your <a href="/manifest.json">manifest.json</a> file and adjust as required.
                        This page may write to this file. This file currently is '.($this->canAccessOrCreateFile() ? '' : 'not').' writeable.
                        '.($this->canAccessOrCreateFile() ? '' : 'Only once the file is writeable you can publish this page. ').'
                    </p>'
                )
            ]
        );
        if($this->UseOneSignal) {
            $accessible = $this->canAccessOrCreateFile($this->OneSignalSDKWorkerPath());
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    LiteralField::create(
                        'OneSignalWorkerInfo',
                        '
                        <p class="message warning">
                            Please make sure to review your <a href="/'.self::ONE_SIGNAL_INIT_FILE_NAME.'">'.self::ONE_SIGNAL_INIT_FILE_NAME.'</a> file and adjust as required.
                            This page may write to this file. This file currently is '.($accessible ? '' : 'not').' writeable.
                        </p>'
                    )
                ]
            );
        }
        return $fields;
    }

    public function canAccessOrCreateFile(?string $filePath = ''): bool
    {
        if(! $filePath) {
            $filePath = $this->getManifestPath();
        }
        if (file_exists($filePath)) {
            return is_writable($filePath);
        } else {
            $dir = dirname($filePath);
            if (is_writable($dir)) {
                $fileHandle = fopen($filePath, 'w');
                if ($fileHandle) {
                    fwrite($fileHandle, json_encode(new stdClass()));
                    fclose($fileHandle);
                    return true;
                }
            }
        }
        return false;
    }

    protected function OneSignalSDKWorkerPath(): string
    {
        return Controller::join_links(BASE_PATH, 'public', self::ONE_SIGNAL_INIT_FILE_NAME);
    }


}
