<?php

namespace Sunnysideup\PushNotifications\Model;

use DateTime;
use DateTimeInterface;
use Exception;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\LinkHelper;
use Sunnysideup\PushNotifications\Api\Providers\PushNotificationOneSignal;
use Sunnysideup\PushNotifications\Api\PushNotificationProvider;
use Sunnysideup\PushNotifications\ErrorHandling\PushException;
use Sunnysideup\PushNotifications\Forms\PushProviderField;
use Sunnysideup\PushNotifications\Jobs\SendPushNotificationsJob;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class \Sunnysideup\PushNotifications\Model\PushNotification
 *
 * @property string $Title
 * @property bool $TestOnly
 * @property string $Content
 * @property string $AdditionalInfo
 * @property string $ProviderClass
 * @property string $ProviderSettings
 * @property string $ScheduledAt
 * @property bool $Sent
 * @property bool $HasSendingErrors
 * @property string $SentAt
 * @property string $OneSignalNotificationID
 * @property string $OneSignalNotificationNote
 * @property int $OneSignalNumberOfDeliveries
 * @property int $SendJobID
 * @method QueuedJobDescriptor SendJob()
 * @method DataList|SubscriberMessage[] SubscriberMessages()
 * @method ManyManyList|Member[] RecipientMembers()
 * @method ManyManyList|Group[] RecipientGroups()
 */
class PushNotification extends DataObject
{
    private const MAX_UNSENT_MESSAGES = 3;

    private static $table_name = 'PushNotification';

    private static $db = [
        'Title' => 'Varchar(100)',
        'TestOnly' => 'Boolean',
        'Content' => 'Text',
        'AdditionalInfo' => 'HTMLText',
        'ProviderClass' => 'Varchar(255)',
        'ProviderSettings' => 'Text',
        'ScheduledAt' => 'Datetime',
        'Sent' => 'Boolean',
        'HasSendingErrors' => 'Boolean',
        'SentAt' => 'Datetime',
        'OneSignalNotificationID' => 'Varchar(64)',
        'OneSignalNotificationNote' => 'Varchar(255)',
        'OneSignalNumberOfDeliveries' => 'Int',
    ];

    private static $has_one = [
        'SendJob' => QueuedJobDescriptor::class,
    ];

    private static $has_many = [
        'SubscriberMessages' => SubscriberMessage::class,
    ];

    private static $many_many = [
        'RecipientMembers' => Member::class,
        'RecipientGroups' => Group::class,
    ];

    private static $field_labels = [
        'Title' => 'Title',
        'Content' => 'Short message',
        'AdditionalInfo' => 'Website only info',
        'ProviderClass' => 'How it is send',
        'ProviderSettings' => 'Details for sending',
        'ScheduledAt' => 'Scheduled to be sent at',
        'Sent' => 'Has it been sent?',
        'SentAt' => 'When was it sent?',
        'HasSendingErrors' => 'Sending errors',
        'OneSignalNotificationID' => 'OneSignal Notification ID',
        'OneSignalNotificationNote' => 'Any notes around connection to OneSignal',
        'OneSignalNumberOfDeliveries' => 'Number of delivery attempts',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'TestOnly.Nice' => 'Test only',
        'RecipientsCount' => 'Number of Recipients',
        'SentAt' => 'Sent',
        'HasSendingErrors.Nice' => 'Sending Errors',
    ];

    private static $searchable_fields = [
        'Title',
        'TestOnly',
        'Content',
        'Sent',
        'OneSignalNotificationID',
        'HasSendingErrors',
    ];

    private static $casting = [
        'RecipientsCount' => 'Int',
        'GroupsSummary' => 'Varchar',
    ];

    private static $indexes = [
        'Title' => true,
        'Sent' => true,
        'OneSignalNotificationID' => true,
    ];

    private static $default_sort = 'ID DESC';

    protected $providerInst;

    public function populateDefaults()
    {
        if(PushNotificationPage::get_one()->UseOneSignal) {
            $this->ProviderClass = PushNotificationOneSignal::class;
        }
        return $this;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if($this->isOverMaxOfNumberOfUnsentNotifications()) {
            $fields->unshift(
                HeaderField::create(
                    'MaxUnsentMessages',
                    _t(
                        'Push.MAXUNSENTMESSAGES',
                        '
                            You have reached the maximum number of unsent messages.
                            Please send some before creating more.
                            This is a safety measure to prevent spamming.
                        '
                    )
                )
            );
        }
        $fields->removeByName('Provider');
        $fields->removeByName('ProviderClass');
        $fields->removeByName('ProviderSettings');
        $fields->removeByName('RecipientMembers');
        $fields->removeByName('RecipientGroups');
        $fields->removeByName('SendJobID');
        $fields->removeByName('SentAt');
        if($this->HasExternalProvider()) {
            // $fields->removeByName('ScheduledAt');
            $fields->dataFieldByName('Sent')
                ->setDescription('Careful! Once ticked and saved, you can not edit this message.');
        } else {
            $fields->removeByName('Sent');
        }


        if ($this->Sent) {
            $fields->insertBefore(
                'Title',
                new LiteralField('SentAtMessage', sprintf('<p class="message">%s</p>', _t(
                    'Push.SENTAT',
                    'This notification was sent at {at}',
                    ['at' => $this->obj('SentAt')->Nice()]
                ))),
            );
        } else {
            if(interface_exists(QueuedJob::class) || $this->HasExternalProvider()) {
                $fields->insertBefore(
                    'Title',
                    $fields->dataFieldByName('ScheduledAt')
                        ->setDescription($this->ScheduledAtNice()),
                );
            } else {
                $fields->insertBefore(
                    'Title',
                    new LiteralField('ScheduledAtMessage', sprintf('<p class="message">%s</p>', _t(
                        'Push.SCHEDULEDAT',
                        'This notification will be send at {at}',
                        ['at' => $this->obj('ScheduledAt')->Nice()]
                    ))),
                );
            }
        }
        $fields->dataFieldByName('Title')->setDescription(_t(
            'Push.USEDASTITLE',
            '(Used as the title of the notification)'
        ));
        $fields->dataFieldByName('Content')->setRows(2);
        $fields->dataFieldByName('AdditionalInfo')->setRows(4);

        $fields->dataFieldByName('Content')->setDescription(_t(
            'Push.USEDMAINBODY',
            '(Used as the main body of the notification)'
        ));
        if(! $this->HasExternalProvider()) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    PushProviderField::create(
                        'Provider',
                        _t('Push.PROVIDER', 'Provider')
                    ),
                ]
            );
        }
        if ($this->ID) {
            $recipientCount = $this->getRecipients()->count();
            $groupCount = $this->RecipientGroups()->count();
            $allCount = $recipientCount + $groupCount;
            if($groupCount === 0 || $allCount === 0) {
                $possibleRecipientsIds = Subscriber::get()->columnUnique('MemberID') + [-1 => -1];
                $fields->addFieldsToTab(
                    'Root.Recipients',
                    [
                        CheckboxSetField::create(
                            'RecipientMembers',
                            _t('Push.RECIPIENTMEMBERS', 'Recipient Members'),
                            Member::get()
                                ->filter(['ID' => $possibleRecipientsIds])
                                ->map()
                        )->setDescription(_t(
                            'Push.RECIPIENTMEMBERSDESCRIPTION',
                            'If you select individual members, then recipient groups will be ignored!'
                        )),

                    ]
                );
            }
            if($recipientCount === 0 || $allCount === 0) {
                $fields->addFieldsToTab(
                    'Root.Recipients',
                    [
                        CheckboxSetField::create(
                            'RecipientGroups',
                            'Recipients'.PHP_EOL.'SELECT WITH CARE!',
                            PushNotificationPage::get_one()->SignupGroups()
                                ->map('ID', 'BreadcrumbsSimple'),
                        )->setDescription(_t(
                            'Push.RECIPIENTGROUPSDESCRIPTION',
                            'If you select recipeitn groups, then individual recipient members will be ignored!'
                        )),

                        ReadonlyField::create('RecipientsCount', _t('Push.RECIPIENTCOUNT', 'Recipient Count')),
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.Recipients',
                [
                    ReadonlyField::create('RecipientsCount', _t('Push.RECIPIENTCOUNT', 'Recipient Count')),
                ]
            );
        }

        $fields->addFieldsToTab('Root.Main', [
            HeaderField::create('Editing History', _t('Push.EDITING_HISTORY', 'Editing History')),
            ReadonlyField::create('Created', _t('Push.CREATED', 'Created')),
            ReadonlyField::create('LastEdited', _t('Push.LASTEDITED', 'Last Edited')),
        ]);

        // dont allow adding new subscribers!
        $subscriberField = $fields->dataFieldByName('SubscriberMessages');
        if($subscriberField) {
            $subscriberField->getConfig()->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        }
        if($this->IsOneSignal()) {
            // OneSignal connection
            $fields->addFieldsToTab(
                'Root.OneSignal',
                [
                    ReadonlyField::create('OneSignalNotificationID', 'Notification ID'),
                    ReadonlyField::create('OneSignalNotificationNote', 'Notification Note'),
                    ReadonlyField::create('OneSignalNumberOfDeliveries', 'Number of Delivery Attempts'),
                ]
            );
            if($this->OneSignalNotificationID) {
                $fields->addFieldToTab(
                    'Root.OneSignal',
                    LiteralField::create(
                        'OneSignalLink',
                        LinkHelper::singleton()->createHtmlLink(
                            $this->getOneSignalLink(),
                            'View on OneSignal',
                        )
                    )
                );
            }
        }
        return $fields;
    }

    public function getCMSActions()
    {
        $actions = parent::getCMSActions();
        if($this->canSend()) {
            $actions->push($action = new CustomAction("doSendCMSAction", "Send"));
            $action
                ->addExtraClass('ss-ui-action btn btn-primary font-icon-block-email action--new discard-confirmation')
                ->setUseButtonTag(true)
                ->setShouldRefresh(true);
        }

        return $actions;
    }

    public function doSendCMSAction()
    {
        if($this->canSend()) {
            $this->doSend();
            return 'Sent!';
        } else {
            return 'Cannot send';
        }
    }

    public function canView($member = null)
    {
        if (! $member) {
            $member = Security::getCurrentUser();
        }

        $testIndividuals = $this->RecipientMembers()->filter('ID', $member->ID)->count() > 0;
        if ($testIndividuals) {
            return true;
        } else {
            $recipientGroups = $this->RecipientGroups()->column('ID');
            $memberGroups = $member->Groups()->columnUnique('ID');

            $testGroups = count(array_intersect($recipientGroups, $memberGroups)) > 0;
            if ($testGroups) {
                return true;
            }
        }
        return parent::canView($member);
    }

    public function canSend($member = null)
    {
        return ! $this->HasExternalProvider() && ! $this->Sent && $this->canEdit($member) && $this->getProvider() && $this->HasRecipients();
    }

    public function HasRecipients(): bool
    {
        return $this->getRecipientsCount() > 0;
    }

    public function getRecipientsCount(): int
    {
        return $this->getRecipients()->count();
    }

    public function getGroupsSummary(): int
    {
        return $this->getRecipients()->count();
    }

    public function getValidator()
    {
        if($this->HasExternalProvider()) {
            return RequiredFields::create(['Title','Content']);
        } else {
            return RequiredFields::create(['Title', 'Content', 'ProviderClass']);
        }
    }

    /**
     * Validate the current object.
     *
     * By default, there is no validation - objects are always valid!  However, you can overload this method in your
     * DataObject sub-classes to specify custom validation, or use the hook through DataExtension.
     *
     * Invalid objects won't be able to be written - a warning will be thrown and no write will occur.  onBeforeWrite()
     * and onAfterWrite() won't get called either.
     *
     * It is expected that you call validate() in your own application to test that an object is valid before
     * attempting a write, and respond appropriately if it isn't.
     *
     * @see {@link ValidationResult}
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (! $this->Sent && $this->ScheduledAt && strtotime($this->ScheduledAt) < time()) {
            $result->addFieldError(
                'ScheduledAt',
                _t(
                    'Push.CANTSCHEDULEINPAST',
                    'You cannot schedule notifications in the past'
                )
            );
        }
        if (! $this->HasExternalProvider() && ! $this->getProvider()) {
            $result->addFieldError(
                'Provider',
                _t(
                    'Push.CANTSCHEDULEWOPROVIDER',
                    'You cannot schedule a notification without a valid provider configured'
                )
            );
        }
        if(! $this->Content) {
            $result->addFieldError(
                'Content',
                _t(
                    'Push.CANTBEBLANK',
                    'This field cannot be blank'
                )
            );
        }
        return $result;
    }

    private $resave = false;

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->Sent && ! $this->SentAt) {
            $this->SentAt = date('Y-m-d H:i:s');
        }
        if (! interface_exists(QueuedJob::class)) {
            return;
        }

        if ($this->ScheduledAt) {
            if (! $this->ID) {
                $this->resave = true;
            } elseif ($this->SendJobID) {
                $job = $this->SendJob();
                $job->StartAfter = $this->ScheduledAt;
                $job->write();
            } else {
                $this->SendJobID = singleton(QueuedJobService::class)->queueJob(
                    new SendPushNotificationsJob($this),
                    $this->ScheduledAt
                );
            }
        } elseif ($this->SendJobID) {
            $this->SendJob()->delete();
        }
    }

    public function onAfterWrite()
    {
        if ($this->resave) {
            $this->resave = false;
            $this->write();
        }
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->isOverMaxOfNumberOfUnsentNotifications() ? false : parent::canCreate($member, $context);
    }

    protected function isOverMaxOfNumberOfUnsentNotifications(): bool
    {
        return $this->numberOfUnsentNotifications() > self::MAX_UNSENT_MESSAGES;
    }

    protected function numberOfUnsentNotifications()
    {
        return self::get()->filter(['Sent' => false])->count();
    }

    public function canEdit($member = null)
    {
        return ! $this->Sent && parent::canEdit($member);
    }

    /**
     * @return PushNotificationProvider|null
     */
    public function getProvider()
    {
        if ($this->providerInst) {
            return $this->providerInst;
        }

        $class = $this->ProviderClass;
        $settings = $this->ProviderSettings;

        if ($class) {
            if (! is_subclass_of($class, PushNotificationProvider::class)) {
                throw new Exception("An invalid provider class $class was encountered.");
            }

            $this->providerInst = new $class();
            if ($settings) {
                $this->providerInst->setSettings(unserialize($settings));
            }

            return $this->providerInst;
        }
        return null;
    }

    public function setProvider(?PushNotificationProvider $provider = null)
    {
        if ($provider instanceof PushNotificationProvider) {
            $this->providerInst = $provider;
            $this->ProviderClass = get_class($provider);
            $this->ProviderSettings = serialize($provider->getSettings());
        } else {
            $this->providerInst = null;
            $this->ProviderClass = null;
            $this->ProviderSettings = null;
        }
    }

    /**
     * Returns all member recipient objects.
     *
     */
    public function getRecipients(): DataList
    {
        $array = [0 => 0];
        $members = $this->RecipientMembers();
        if($members->count() > 0) {
            $array = array_merge($array, $members->columnUnique('ID'));
        } else {
            /** @var Group $group */
            foreach ($this->RecipientGroups() as $group) {
                $array = array_merge($array, $group->Members()->columnUnique('ID'));

            }
        }

        return Member::get()->filter(['ID' => $array]);
    }

    /**
     * Sends the push notification then locks this record so it cannot be sent
     * again.
     *
     * @throws PushException
     */
    public function doSend()
    {
        $provider = $this->getProvider();

        if ($this->Sent) {
            throw new PushException('This notification has already been sent.');
        }

        if (! $provider) {
            throw new PushException('No push notification provider has been set.');
        }

        $outcome = $provider->sendPushNotification($this);

        $this->Sent = true;
        if(! $outcome) {
            $this->HasSendingErrors = true;
        }
        $this->SentAt = date('Y-m-d H:i:s');
        $this->write();
    }

    public function Link()
    {
        $link = PushNotificationPage::get_one()?->Link() ?: '/';
        return Director::absoluteURL($link);
    }

    public function AbsoluteLink()
    {
        return $this->Link();
    }

    public function getOneSignalLink(): string
    {
        if($this->OneSignalNotificationID) {
            return LinkHelper::singleton()->notificationLink($this->OneSignalNotificationID);
        }
        return '';
    }

    public function getCMSValidator()
    {
        return RequiredFields::create('Title', 'ProviderClass');
    }


    public function HasExternalProvider(): bool
    {
        return (bool) PushNotificationPage::get_one()?->HasExternalProvider();
    }

    public function OneSignalTargetChannel(): string
    {
        return 'push';
    }

    public function ScheduledAtNice(?string $alternativeTime = ''): string
    {
        $date = $this->ScheduledAtDateTimeInterface($alternativeTime);

        // Format the timestamp
        return $date->format('D M d Y H:i:s \G\M\TO (T)');
    }

    public function ScheduledAtDateTimeInterface(?string $alternativeTime = ''): DateTimeInterface
    {
        $timeAsString = $alternativeTime ?: $this->ScheduledAt;
        return new DateTime($timeAsString);

    }

    public function IsOneSignal(): bool
    {
        return $this->ProviderClass === PushNotificationOneSignal::class;
    }

}
