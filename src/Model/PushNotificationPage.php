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
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Security;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\LinkHelper;
use Sunnysideup\PushNotifications\Extensions\GroupExtension;

/**
 * Class \Sunnysideup\PushNotifications\Model\PushNotificationPage
 *
 * @property bool $UseOneSignal
 * @property string $ThemeColour
 * @property string $BackgroundColour
 * @property string $SignupGroupsIntro
 * @property int $StartPageForHomeScreenApp
 * @method SiteTree StartPageForHomeScreenApp()
 * @method ManyManyList|Group[] SignupGroups()
 */
class PushNotificationPage extends Page
{
    /**
     * the list that the ADMIN can use to select subscrible list from
     * @return \SilverStripe\ORM\DataList
     */
    public static function get_list_as_subscribable_groups(): DataList
    {
        $allSubscribersGroup = GroupExtension::get_all_subscribers_group();
        return Group::get()
            ->filter(
                [
                    'Code:not' => [
                        'administrators',
                        ($allSubscribersGroup ? $allSubscribersGroup->Code : 'nothing-here')
                    ]
                ]
            );
    }


    /**
     * the list that the ADMIN can use to send push notification to
     * @return \SilverStripe\ORM\DataList
     */
    public static function get_list_of_recipient_groups(): DataList
    {
        $allSubscribersGroup = GroupExtension::get_all_subscribers_group();
        return Group::get()
            ->filter(
                [
                    'ID' => array_merge(
                        PushNotificationPage::get_one()->SignupGroups()->columnUnique(),
                        [(int) $allSubscribersGroup?->ID]
                    )
                ]
            );
    }


    public const ONESIGNAL_INIT_FILE_NAME = 'OneSignalSDKWorker.js';

    private static $table_name = 'PushNotificationPage';

    private static $description = 'Page to manage push notifications';

    private static $icon_class = 'font-icon-fast-forward';
    private static $singular_name = 'Push Notification Page';
    private static $plural_name = 'Push Notification Pages';

    private static $controller_name = PushNotificationPageController::class;

    private static $db = [
        'UseOneSignal' => 'Boolean',
        'SignupGroupsIntro' => 'HTMLText',
    ];

    private static $has_one = [
        'StartPageForHomeScreenApp' => SiteTree::class,
    ];

    private static $many_many = [
        'SignupGroups' => Group::class,
    ];

    private static $defaults = [
        'URLSegment' => 'push-notifications',
    ];

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->ensureStandardLocation();
        $this->copyOneSignalFile();
        // Modify the JSON value
    }

    private static $manifest_file_name = 'manifest.json';

    protected function getManifestPath(): string
    {
        return Controller::join_links(PUBLIC_PATH, $this->Config()->get('manifest_file_name'));
    }

    protected function getManifestLink(): string
    {
        return Controller::join_links(Director::baseURL(),  $this->Config()->get('manifest_file_name'));
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
                        Please make sure to review your
                        <a href="' . $this->getManifestLink() . '?x=' . rand(0, 999999999999) . '" target="_blank">' . $this->Config()->get('manifest_file_name') . '</a>
                        file and adjust as required.
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
                        You may want to set the start page for the home screen app to be ' . $this->StartPageForHomeScreenApp()->AbsoluteLink() . '
                    </p>'
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Provider',
            [
                CheckboxField::create('UseOneSignal', 'Use OneSignal'),
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
        if ($this->UseOneSignal) {
            if (!$this->getOneSignalKey()) {
                // $fields->removeByName('PushNotifications');
                $fields->addFieldsToTab(
                    'Root.Provider',
                    [
                        LiteralField::create(
                            'OneSignalInfo',
                            '<h2>OneSignal</h2>
                            <p>
                                Please access OneSignal to manage your push notifications:
                                <br />
                                <a href="' . LinkHelper::singleton()->createNewAppLink() . '" target="_blank">Create a new app</a>
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
                            '<h2>OneSignal</h2>
                            <p>
                                Please access OneSignal to manage your push notifications:
                                <br />' .
                                LinkHelper::singleton()->createHtmlLink(
                                    LinkHelper::singleton()->configurePushNotificationsLink(),
                                    'Configure (with care!)'
                                ) . '<br />
                                <br />' .
                                LinkHelper::singleton()->createHtmlLink(
                                    LinkHelper::singleton()->sendNewPushNotificationLink(),
                                    'Send New Push Notification'
                                ) . '
                                <strong>Do not forget also record your message here.</strong>
                                <br />
                                <br />' .
                                LinkHelper::singleton()->createHtmlLink(
                                    LinkHelper::singleton()->sentPushNotificationLink(),
                                    'Review sent messages'
                                ) . '
                                <br />
                                <hr />
                                <h3>Advanced Tools (use with care)</h3>
                                <br />' .
                                LinkHelper::singleton()->createHtmlLink(
                                    '/dev/tasks/update-one-signal',
                                    'Update website content with lastest OneSignal information'
                                ) . '
                                    <br />
                                    <br />' .
                                LinkHelper::singleton()->createHtmlLink(
                                    '/dev/tasks/test-one-signal',
                                    'Test OneSignal connection'
                                ) . '
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
                            Please make sure to review your <a href="/' . self::ONESIGNAL_INIT_FILE_NAME . '">' . self::ONESIGNAL_INIT_FILE_NAME . '</a> file and adjust as required.
                            This page may write to this file. This file currently is ' . ($accessible ? '' : 'not') . ' writeable.
                        </p>'
                    )
                ]
            );
        }
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

        $fields->addFieldsToTab(
            'Root.SignupGroups',
            [
                HTMLEditorField::create('SignupGroupsIntro', 'Intro for users to ask them to sign up to groups'),
                CheckboxSetField::create(
                    'SignupGroups',
                    'Joinable Groups' . PHP_EOL . 'CAREFUL SEE BELOW',
                    self::get_list_as_subscribable_groups()
                        ->map('ID', 'BreadcrumbsSimpleWithCount'),
                )
                    ->setDescription('CAREFUL: only select groups without any special permissions as otherwise users can grant themselves those permissions.')
            ]
        );


        return $fields;
    }

    public function canView($member = null)
    {
        if (! $member) {
            $member = Security::getCurrentUser();
        }
        if (! $member) {
            return false;
        }
        return parent::canView($member);
    }

    public function getSettingsFields()
    {
        $fields = parent::getSettingsFields();
        $source = $fields->dataFieldByName('CanViewType')->getSource();
        unset($source['Inherit']);
        unset($source['Anyone']);
        $fields->dataFieldByName('CanViewType')->setSource($source);
        return $fields;
    }

    public function canAccessOrCreateFile(?string $filePath = ''): bool
    {
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
        return Controller::join_links(BASE_PATH, 'public', self::ONESIGNAL_INIT_FILE_NAME);
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

    public function getOneSignalKey(): ?string
    {
        return (string) Environment::getEnv('SS_ONESIGNAL_APP_ID');
    }

    protected function ensureStandardLocation()
    {
        $defaults = $this->COnfig()->get('defaults');
        $this->URLSegment = $defaults['URLSegment'];
        $this->ParentID = 0;
    }



    protected function copyOneSignalFile()
    {
        if ($this->UseOneSignal) {
            try {
                copy(
                    Controller::join_links(
                        Director::baseFolder(),
                        '/vendor/sunnysideup/push-notifications/client/dist/third-party/',
                        self::ONESIGNAL_INIT_FILE_NAME
                    ),
                    Controller::join_links(
                        PUBLIC_PATH,
                        self::ONESIGNAL_INIT_FILE_NAME
                    ),
                );
            } catch (Exception $e) {
                // do nothing
            }
        }
    }
}
