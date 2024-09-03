<?php

namespace Sunnysideup\PushNotifications\Model;

use DateTime;
use DateTimeInterface;
use Exception;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
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
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\NotificationHelper;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;
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
 * @property int $OneSignalCommsError
 * @property int $SendJobID
 * @method QueuedJobDescriptor SendJob()
 * @method DataList|SubscriberMessage[] SubscriberMessages()
 * @method ManyManyList|Member[] RecipientMembers()
 * @method ManyManyList|Group[] RecipientGroups()
 */
class PushNotification extends DataObject
{
    private static $max_unsent_messages = 50;

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
        'OneSignalCommsError' => 'Int',
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
        'TestOnly' => 'Only used as test; make sure you set this before you send so that it can be hidden from a list of notifications.',
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
        'OneSignalCommsError' => 'Number of Comms Errors',
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
        if (PushNotificationPage::get_one()->UseOneSignal) {
            $this->ProviderClass = PushNotificationOneSignal::class;
        }
        return $this;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if ($this->isOverMaxOfNumberOfUnsentNotifications()) {
            $fields->unshift(
                LiteralField::create(
                    'MaxUnsentMessages',
                    _t(
                        'Push.MAXUNSENTMESSAGES',
                        '<p class="message warning">
                            You have reached the maximum number of unsent messages.
                            Please send some before creating more.
                            This is a safety measure to prevent spamming.
                        </p>'
                    )
                )
            );
        }
        if ($this->canEdit() && ! $this->canSend()) {
            $fields->unshift(
                LiteralField::create(
                    'CanEditCanSendInfo',
                    _t(
                        'Push.CANINFOCANSENDINFO',
                        '<p class="message warning">
                            To send this message, you need to have at least one valid recipient selected.
                        </p>'
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
        if ($this->HasExternalProvider()) {
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
            $fields->removeByName('HasSendingErrors');
            if (interface_exists(QueuedJob::class) || $this->HasExternalProvider()) {
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
        if (! $this->HasExternalProvider()) {
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
            $recipientMembers = $this->RecipientMembers()->count();
            $groupCount = $this->RecipientGroups()->count();
            $allCount = $recipientMembers + $groupCount;
            if ($groupCount === 0 || $allCount === 0) {
                $possibleRecipientsIds = Subscriber::get()->filter(['Subscribed' => true])->columnUnique('MemberID') + [-1 => -1];
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
            if ($recipientMembers === 0 || $allCount === 0) {
                $fields->addFieldsToTab(
                    'Root.Recipients',
                    [
                        CheckboxSetField::create(
                            'RecipientGroups',
                            'Recipients'.PHP_EOL.'SELECT WITH CARE!',
                            PushNotificationPage::get_list_of_recipient_groups()
                                ->map('ID', 'BreadcrumbsSimpleWithCount'),
                        )->setDescription(_t(
                            'Push.RECIPIENTGROUPSDESCRIPTION',
                            'If you select recipient groups, then individual recipient members will be ignored!'
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

        $fields->addFieldsToTab('Root.History', [
            ReadonlyField::create('Created', _t('Push.CREATED', 'Created')),
            ReadonlyField::create('LastEdited', _t('Push.LASTEDITED', 'Last Edited')),
        ]);

        // dont allow adding new subscribers!
        $subscriberField = $fields->dataFieldByName('SubscriberMessages');
        if ($subscriberField) {
            $subscriberField->getConfig()->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        }
        if ($this->IsOneSignal()) {
            if ($this->IsScheduledInThePast() === false && $this->OneSignalNotificationID) {
                $fields->unshift(
                    LiteralField::create(
                        'OneSignalLink',
                        LinkHelper::singleton()->createHtmlLink(
                            LinkHelper::singleton()->notificationLinkEdit($this->OneSignalNotificationID),
                            'Head to OneSignal now to review / send your message!',
                            true
                        )
                    )
                );
            }
            // OneSignal connection
            $fields->addFieldsToTab(
                'Root.OneSignal',
                [
                    $fields->dataFieldByName('OneSignalNotificationID')->setReadonly(true),
                    $fields->dataFieldByName('OneSignalNotificationNote')->setReadonly(true),
                    $fields->dataFieldByName('OneSignalNumberOfDeliveries')->setReadonly(true),
                    $fields->dataFieldByName('OneSignalCommsError')->setReadonly(true),
                ]
            );
            if ($this->OneSignalNotificationID) {
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
        if ($this->canSend()) {
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
        if ($this->canSend()) {
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
        if ($this->OneSignalNotificationID) {
            return false;
        }
        if ($this->Sent) {
            return false;
        }
        if ($this->HasRecipients() && $this->canEdit($member) && $this->getProvider() && $this->getProvider()->isEnabled()) {
            return true;
        }
        return false;
    }

    public function HasRecipients(): bool
    {
        return $this->getRecipientsCount() > 0;
    }

    public function getRecipientsCount(): int
    {
        return $this->getRecipients()->count();
    }

    public function getRecipientsDescription(): string
    {
        $members = $this->RecipientMembers();
        $membersCount = $members->count();
        if ($membersCount > 0) {
            if ($membersCount > 3) {
                return 'Msg to '.$membersCount.': '.implode(', ', $members->limit(3)->column('Email')).'...';
            } else {
                return 'Msg to '.implode(', ', $members->column('Email'));
            }
        } else {
            $groupsCount = $this->RecipientGroups()->count();
            if ($groupsCount > 0) {
                if ($groupsCount > 3) {
                    return 'Msg to '.$groupsCount.' groups: '.implode(', ', $this->RecipientGroups()->limit(3)->column('Title')).'...';
                } else {
                    return 'Msg to '.implode(', ', $this->RecipientGroups()->column('Title'));
                }
            }
        }
        return 'Msg to all website members';
    }

    public function getGroupsSummary(): int
    {
        return $this->getRecipients()->count();
    }

    public function getValidator()
    {
        if ($this->HasExternalProvider()) {
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

        if (! $this->Sent && $this->IsScheduledInThePast()) {
            $result->addFieldError(
                'ScheduledAt',
                _t(
                    'Push.CANTSCHEDULEINPAST',
                    'You cannot schedule notifications in the past'
                )
            );
        }
        if (! $this->getProvider()) {
            $result->addFieldError(
                'Provider',
                _t(
                    'Push.CANTSCHEDULEWOPROVIDER',
                    'You cannot schedule a notification without a valid provider configured'
                )
            );
        }
        if (! $this->Content) {
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

    protected function IsScheduledInThePast(): bool
    {
        if (!$this->exists()) {
            return false;
        }
        if (! $this->ScheduledAt) {
            if ($this->OneSignalNotificationID) {
                // created more than a day go, forget it.
                return strtotime($this->Created) < strtotime(' -1 day');
            }
            return false;
        }
        return strtotime($this->ScheduledAt) < time();
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->Sent && ! $this->SentAt) {
            $this->SentAt = date('Y-m-d H:i:s');
        }
        if (! $this->ID) {
            $this->resave = true;
        } else {
            $this->scheduleJob();
            $this->OneSignalComms(false);
        }
    }

    protected function scheduleJob()
    {
        if ($this->exists()) {
            if (!$this->HasExternalProvider()) {
                if (interface_exists(QueuedJob::class)) {
                    if ($this->ScheduledAt) {
                        if ($this->SendJobID) {
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
            }

        }
    }

    public function OneSignalComms(?bool $write = false): bool
    {
        if ($this->OneSignalNotificationID) {
            // dont bother about things that are old!
            if (strtotime($this->LastEdited) < strtotime(' -1 week')) {
                return false;
            }
            /** @var OneSignalSignupApi $api */
            $api = Injector::inst()->get(OneSignalSignupApi::class);
            $outcome = $api->getOneNotification($this->OneSignalNotificationID);
            if (OneSignalSignupApi::test_success($outcome)) {
                $valuesForNotificationDataOneObject = NotificationHelper::singleton()
                    ->getValuesForNotificationDataOneObject($outcome);
                foreach ($valuesForNotificationDataOneObject as $key => $value) {
                    $this->{$key} = $value;
                }
                $this->OneSignalCommsError = 0;
            } else {
                $this->OneSignalNotificationNote = 'Could not get OneSignal notification. This has happpened '.$this->OneSignalCommsError.' times.';
                $this->OneSignalCommsError++;
                if ($this->OneSignalCommsError > 10) {
                    $this->OneSignalNotificationID = '';
                    $this->OneSignalNotificationNote = 'There were more than 10 errors in a row. The notification ID has been removed.';
                }
            }
            if ($write) {
                $this->write();
            }
        }
        return $this->OneSignalNotificationID ? true : false;
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
        return $this->numberOfUnsentNotifications() > $this->config()->max_unsent_messages;
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
        if ($members->exists()) {
            $array = array_merge($array, $members->columnUnique('ID'));
        } elseif ($this->RecipientGroups()->exists()) {
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
        if (! $outcome) {
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
        if ($this->OneSignalNotificationID) {
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
        if ($date) {
            return $date->format('D M d Y H:i:s \G\M\TO (T)');
        }
        return 'No date set';
        // Format the timestamp
    }

    public function ScheduledAtDateTimeInterface(?string $alternativeTime = ''): ?DateTimeInterface
    {
        $timeAsString = $alternativeTime ?: $this->ScheduledAt;
        if (! $timeAsString) {
            return null;
        }
        return new DateTime($timeAsString);

    }

    public function IsOneSignal(): bool
    {
        return $this->ProviderClass === PushNotificationOneSignal::class;
    }

}
