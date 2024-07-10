<?php

namespace Sunnysideup\PushNotifications\Model;

use Exception;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
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
 * @property string $Content
 * @property string $AdditionalInfo
 * @property string $ProviderClass
 * @property string $ProviderSettings
 * @property string $ScheduledAt
 * @property bool $Sent
 * @property string $SentAt
 * @property int $SendJobID
 * @method \Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor SendJob()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\SubscriberMessage[] SubscriberMessages()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] RecipientMembers()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Group[] RecipientGroups()
 */
class PushNotification extends DataObject
{
    private static $table_name = 'PushNotification';

    private static $db = [
        'Title' => 'Varchar(100)',
        'Content' => 'Text',
        'AdditionalInfo' => 'HTMLText',
        'ProviderClass' => 'Varchar(255)',
        'ProviderSettings' => 'Text',
        'ScheduledAt' => 'Datetime',
        'Sent' => 'Boolean',
        'SentAt' => 'Datetime',
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

    private static $summary_fields = [
        'Title',
        'SentAt',
        'RecipientsCount',
    ];

    private static $searchable_fields = [
        'Title',
        'Content',
        'Sent',
    ];

    private static $casting = [
        'RecipientsCount' => 'Int',
    ];

    private static $default_sort = 'Created DESC';

    protected $providerInst;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Provider');
        $fields->removeByName('ProviderClass');
        $fields->removeByName('ProviderSettings');
        $fields->removeByName('RecipientMembers');
        $fields->removeByName('RecipientGroups');
        $fields->removeByName('SendJobID');
        $fields->removeByName('SentAt');
        if($this->HasExternalProvider()) {
            $fields->removeByName('ScheduledAt');
            $fields->dataFieldByName('Sent')
                ->setDescription('Careful! Once ticked and saved, you can not edit this message.');
        } else {
            $fields->removeByName('Sent');
        }


        if ($this->Sent) {
            $fields->insertBefore(
                'Title',
                new LiteralField('SentAsMessage', sprintf('<p class="message">%s</p>', _t(
                    'Push.SENTAT',
                    'This notification was sent at {at}',
                    ['at' => $this->obj('SentAt')->Nice()]
                ))),
            );
        }
        $fields->dataFieldByName('Title')->setDescription(_t(
            'Push.USEDASTITLE',
            '(Used as the title of the notification)'
        ));
        $fields->dataFieldByName('Content')->setRows(2);
        $fields->dataFieldByName('AdditionalInfo')->setRows(4);

        if ($this->Sent || ! interface_exists(QueuedJob::class)) {
            $fields->removeByName('ScheduledAt');
        }

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
        if ($this->ID && ! $this->HasExternalProvider()) {
            $possibleRecipientsIds = Subscriber::get()->columnUnique('MemberID') + [-1 => -1];
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    HeaderField::create('RecipientsHeader', _t('Push.RECIPIENTS', 'Recipients')),
                    new CheckboxSetField(
                        'RecipientMembers',
                        _t('Push.RECIPIENTMEMBERS', 'Recipient Members'),
                        Member::get()
                            ->filter(['ID' => $possibleRecipientsIds])
                            ->map()
                    ),
                    new TreeMultiselectField(
                        'RecipientGroups',
                        _t('Push.RECIPIENTGROUPS', 'Recipient Groups'),
                        Group::class
                    ),
                    ReadonlyField::create('RecipientsCount', _t('Push.RECIPIENTCOUNT', 'Recipient Count')),
                ]
            );
        }

        $fields->addFieldsToTab('Root.Main', [
            HeaderField::create('Editing History', _t('Push.EDITING_HISTORY', 'Editing History')),
            ReadonlyField::create('Created', _t('Push.CREATED', 'Created')),
            ReadonlyField::create('LastEdited', _t('Push.LASTEDITED', 'Last Edited')),
        ]);
        $subscriberField = $fields->dataFieldByName('SubscriberMessages');
        if($subscriberField) {
            $subscriberField->getConfig()->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
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

        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }
        if($this->HasExternalProvider()) {
            return true;
        }
        if ($this->RecipientMembers()->filter('ID', $member->ID)->exists()) {
            return true;
        }
        $recipientGroups = $this->RecipientGroups()->column('ID');
        $memberGroups = $member->Groups()->columnUnique('ID');

        return (bool) count(array_intersect($recipientGroups, $memberGroups)) > 0;
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
     * @return ArrayList
     */
    public function getRecipients()
    {
        $set = new ArrayList();
        $set->merge($this->RecipientMembers());

        /** @var Group $group */
        foreach ($this->RecipientGroups() as $group) {
            $set->merge($group->Members());
        }

        $set->removeDuplicates();
        return $set;
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

        $provider->sendPushNotification($this);

        $this->Sent = true;
        $this->SentAt = date('Y-m-d H:i:s');
        $this->write();
    }

    public function Link()
    {
        $link = PushNotificationPage::get()->first()?->Link() ?: '/';
        return Director::absoluteURL($link);
    }

    public function getCMSValidator()
    {
        return RequiredFields::create('Title', 'ProviderClass');
    }


    public function HasExternalProvider(): bool
    {
        return (bool) PushNotificationPage::get_one()?->HasExternalProvider();
    }
}
