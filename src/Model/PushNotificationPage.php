<?php

namespace Sunnysideup\PushNotifications\Model;

use Exception;
use Page;
use SilverStripe\AssetAdmin\Forms\UploadField;
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
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\TreeDropdownField;

class PushNotificationPage extends Page
{
    public const ONE_SIGNAL_INIT_FILE_NAME = 'OneSignalSDKWorker.js';

    private static $table_name = 'PushNotificationPage';

    private static $icon_class = 'font-icon-fast-forward';

    private static $controller_name = PushNotificationPageController::class;

    private static $db = [
        'UseOneSignal' => 'Boolean',
        'OneSignalKey' => 'Varchar(65)',
        'ThemeColour' => 'Varchar(6)',
        'BackgroundColour' => 'Varchar(6)',
        'OverwriteManifestFile' => 'Boolean',
    ];

    private static $has_one = [
        'ManifestIcon' => Image::class,
        'StartPageForHomeScreenApp' => SiteTree::class,
    ];

    private static $owns = [
        'ManifestIcon',
    ];

    protected function modifyJsonValue(string $filePath, string $key, $newValue, ?bool $overwrite = false): void
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
        if($overwrite) {
            $data[$key] = $newValue;
        } else {
            // Check if the key exists in the data
            if (! array_key_exists($key, $data)) {
                $data[$key] = $newValue;
            }
        }
        // Update the value (if the key exists in the data

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
            die('error writing file!');
        }

    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->URLSegment = 'push-notifications';
        $this->ParentID = 0;
        // Modify the JSON value
        if($this->canAccessOrCreateFile()) {
            $icon = $this->ManifestIconID ? $this->ManifestIcon() : null;
            $icons = [];
            if($icon && $icon->exists()) {
                $icons = [
                    [
                        "src" => $this->ManifestIcon()->ScaleWidth(192)->getAbsoluteURL(),
                        "sizes" => "192x192",
                        "type" => "image/png"
                    ],
                    [
                        "src" => $this->ManifestIcon()->ScaleWidth(512)->getAbsoluteURL(),
                        "sizes" => "512x512",
                        "type" => "image/png"
                    ]
                ];
            } else {
                $icons = [
                    [
                        "src" => Director::absoluteURL('/_resources/vendor/sunnysideup/push-notifications/client/dist/images/icon-192x192.png'),
                        "sizes" => "192x192",
                        "type" => "image/png"
                    ],
                    [
                        "src" => Director::absoluteURL('/_resources/vendor/sunnysideup/push-notifications/client/dist/images/icon-512x512.png'),
                        "sizes" => "512x512",
                        "type" => "image/png"
                    ]
                ];
            }
            $link = $this->StartPageForHomeScreenAppID ? $this->StartPageForHomeScreenApp()->AbsoluteLink() : $this->AbsoluteLink();
            $link = $this->removeGetVariables($link);
            $this->modifyJsonValue($this->getManifestPath(), '$schema', "https://json.schemastore.org/web-manifest-combined.json", $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'name', SiteConfig::current_site_config()->Title, $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'short_name', SiteConfig::current_site_config()->Title, $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'start_url', $link, $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'display', 'standalone', $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'background_color', $this->BackgroundColour ? '#' . $this->BackgroundColour : '#ffffff', $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'theme_color', $this->ThemeColour ? '#' . $this->ThemeColour : '#000000', $this->OverwriteManifestFile);
            $this->modifyJsonValue($this->getManifestPath(), 'icons', $icons, $this->OverwriteManifestFile);
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

    protected function getManifestPath(): string
    {
        return Controller::join_links(BASE_PATH, 'public', 'manifest.json');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.Manifest',
            [
                LiteralField::create(
                    'PushNotificationsInfo',
                    '
                    <p class="message warning">
                        Please make sure to review your <a href="/manifest.json?x='.rand(0, 999999999999).'" target="_blank">manifest.json</a> file and adjust as required.
                        This page may write to this file (see options below about overwriting this file).
                        The file currently is '.($this->canAccessOrCreateFile() ? '' : 'not').' writeable.
                        For proper functonality, please make sure that the file has the following features:
                        <ul>
                            <li>name</li>
                            <li>short_name</li>
                            <li>start_url</li>
                            <li>display</li>
                            <li>background_color</li>
                            <li>theme_color</li>
                            <li>icons (512x512 / 192x192)</li>
                        </ul>
                    </p>'
                ),
                CheckboxField::create('OverwriteManifestFile', 'Overwrite manifest values (untick to edit manually). If you are not sure, then just overwrite it here to have correct values in your manifest.json'),
                TextField::create('ThemeColour', 'Theme Colour')
                    ->setDescription('Please enter a 6 digit hex colour code - e.g. a2a111 or ffffff'),
                TextField::create('BackgroundColour', 'Background Colour')
                    ->setDescription('Please enter a 6 digit hex colour code - e.g. f1a111 or ffffff'),
                TreeDropdownField::create('StartPageForHomeScreenAppID', 'Start Page For Home Screen App', SiteTree::class),
                UploadField::create('ManifestIcon', 'Manifest Icon')
                    ->setFolderName('manifest-icons')
                    ->setAllowedExtensions(['png'])
                    ->setDescription('Please upload a 512x512 pixel PNG file exactly. Apple does not allow transparency in this image.')
                    ->setAllowedFileCategories('image')
                    ->setAllowedMaxFileNumber(1)

            ]
        );
        $fields->addFieldsToTab(
            'Root.Provider',
            [
                CheckboxField::create('UseOneSignal', 'Use OneSignal'),
                TextField::create('OneSignalKey', 'One Signal Key'),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Messages',
            [
                GridField::create(
                    'PushNotifications',
                    'Push Notifications',
                    PushNotification::get(),
                    GridFieldConfig_RecordEditor::create()
                )
            ]
        );
        if($this->UseOneSignal) {
            if(!$this->OneSignalKey) {
                // $fields->removeByName('PushNotifications');
                $fields->addFieldsToTab(
                    'Root.Provider',
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
                    'Root.Provider',
                    [
                        LiteralField::create(
                            'OneSignalInfo',
                            '<h2>One Signal</h2>
                            <p>
                                Please access one signal to manage your push notifications:
                                <br />
                                <a href="https://dashboard.onesignal.com/apps/'.$this->OneSignalKey.'/settings/webpush/configure" target="_blank" rel="noopener noreferrer">Configure (with care!)</a>
                                <br />
                                <br />
                                <a href="https://dashboard.onesignal.com/apps/'.$this->OneSignalKey.'/campaigns" target="_blank"  rel="noopener noreferrer">Send Push Notification</a>
                                <strong>Do not forget also record your message here.</strong>
                                <br />
                                <br />
                                <a href="https://dashboard.onesignal.com/apps/'.$this->OneSignalKey.'/notifications" target="_blank" rel="noopener noreferrer">Review sent messages</a>
                            </p>
                            '
                        ),
                    ]
                );
            }
            $accessible = $this->canAccessOrCreateFile($this->OneSignalSDKWorkerPath());
            $fields->addFieldsToTab(
                'Root.Provider',
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
        } else {
            $fields->addFieldsToTab(
                'Root.Subscribers',
                [
                    GridField::create(
                        'Subscribers',
                        'Subscribers',
                        Subscriber::get(),
                        GridFieldConfig_RecordEditor::create()
                    )
                ]
            );
        }

        if($this->UseOneSignal) {

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
    protected function removeGetVariables(string $url): string
    {
        $parsedUrl = parse_url($url);

        $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        if (isset($parsedUrl['port'])) {
            $newUrl .= ':' . $parsedUrl['port'];
        }

        if (isset($parsedUrl['path'])) {
            $newUrl .= $parsedUrl['path'];
        }

        return $newUrl;
    }

    public function HasExternalProvider(): bool
    {
        return $this->UseOneSignal;
    }


}
