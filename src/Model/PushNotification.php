<?php

namespace Sunnysideup\PushNotifications\Model;

use Exception;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\PushNotificationProvider;
use Sunnysideup\PushNotifications\ErrorHandling\PushException;
use Sunnysideup\PushNotifications\Forms\PushProviderField;
use Sunnysideup\PushNotifications\Jobs\SendPushNotificationsJob;

/**
 * Class \Sunnysideup\PushNotifications\Model\PushNotification
 *
 * @property string $Title
 * @property string $Content
 * @property string $ProviderClass
 * @property string $ProviderSettings
 * @property string $ScheduledAt
 * @property bool $Sent
 * @property string $SentAt
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Member[] RecipientMembers()
 * @method \SilverStripe\ORM\ManyManyList|\SilverStripe\Security\Group[] RecipientGroups()
 */
class PushNotification extends DataObject
{
    private static $table_name = 'PushNotification';

    private static $db = array(
        'Title'            => 'Varchar(100)',
        'Content'          => 'Text',
        'ProviderClass'    => 'Varchar(255)',
        'ProviderSettings' => 'Text',
        'ScheduledAt'      => 'Datetime',
        'Sent'             => 'Boolean',
        'SentAt'           => 'Datetime'
    );

    private static $many_many = array(
        'RecipientMembers' => Member::class,
        'RecipientGroups'  => Group::class,
    );

    private static $summary_fields = array(
        'Title',
        'SentAt'
    );

    private static $searchable_fields = array(
        'Title',
        'Content',
        'Sent'
    );

    private static $default_sort = 'Created DESC';

    protected $providerInst;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('ProviderClass');
        $fields->removeByName('ProviderSettings');
        $fields->removeByName('Sent');
        $fields->removeByName('SentAt');
        $fields->removeByName('RecipientMembers');
        $fields->removeByName('RecipientGroups');
        $fields->removeByName('SendJobID');

        if ($this->Sent) {
            $fields->insertBefore(
                'Title',
                new LiteralField('SentAsMessage', sprintf('<p class="message">%s</p>', _t(
                    'Push.SENTAT',
                    'This notification was sent at {at}',
                    array('at' => $this->obj('SentAt')->Nice())
                ))),
            );
        }

        if ($this->Sent || !interface_exists('QueuedJob')) {
            $fields->removeByName('ScheduledAt');
        } else {
            $fields->dataFieldByName('ScheduledAt')->getDateField()->setConfig('showcalendar', true);
        }

        $fields->dataFieldByName('Content')->setDescription(_t(
            'Push.USEDMAINBODY',
            '(Used as the main body of the notification)'
        ));

        if ($this->ID) {
            $fields->addFieldsToTab('Root.Main', array(
                new CheckboxSetField(
                    'RecipientMembers',
                    _t('Push.RECIPIENTMEMBERS', 'Recipient Members'),
                    Member::get()->map()
                ),
                new TreeMultiselectField(
                    'RecipientGroups',
                    _t('Push.RECIPIENTGROUPS', 'Recipient Groups'),
                    Group::class
                )
            ));
        }

        $fields->addFieldsToTab('Root.Main', array(
            PushProviderField::create(
                'Provider',
                _t('Push.PROVIDER', 'Provider')
            )
        ));

        return $fields;
    }

    public function getValidator()
    {
        return new RequiredFields('Title');
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
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (!$this->Sent && $this->ScheduledAt) {
            if (strtotime($this->ScheduledAt) < time()) {
                $result->addFieldError(
                    'ScheduledAt',
                    _t(
                        'Push.CANTSCHEDULEINPAST',
                        'You cannot schedule notifications in the past'
                    )
                );
            }

            if (!$this->getProvider()) {
                $result->addFieldError(
                    'Provider',
                    _t(
                        'Push.CANTSCHEDULEWOPROVIDER',
                        'You cannot schedule a notification without a valid provider configured'
                    )
                );
            }
        }

        return $result;
    }

    private $resave = false;

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!interface_exists('QueuedJob')) {
            return;
        }

        if ($this->ScheduledAt) {
            if (!$this->ID) {
                $this->resave = true;
            } else {
                if ($this->SendJobID) {
                    $job = $this->SendJob();
                    $job->StartAfter = $this->ScheduledAt;
                    $job->write();
                } else {
                    $this->SendJobID = singleton('QueuedJobService')->queueJob(
                        new SendPushNotificationsJob($this),
                        $this->ScheduledAt
                    );
                }
            }
        } else {
            if ($this->SendJobID) {
                $this->SendJob()->delete();
            }
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
        return !$this->Sent && parent::canEdit($member);
    }

    /**
     * @return PushNotificationProvider
     */
    public function getProvider()
    {
        if ($this->providerInst) {
            return $this->providerInst;
        }

        $class    = $this->ProviderClass;
        $settings = $this->ProviderSettings;

        if ($class) {
            if (!is_subclass_of($class, PushNotificationProvider::class)) {
                throw new Exception("An invalid provider class $class was encountered.");
            }

            $this->providerInst = new $class();
            if ($settings) {
                $this->providerInst->setSettings(unserialize($settings));
            }

            return $this->providerInst;
        }
    }

    public function setProvider(PushNotificationProvider $provider)
    {
        if ($provider) {
            $this->providerInst     = $provider;
            $this->ProviderClass    = get_class($provider);
            $this->ProviderSettings = serialize($provider->getSettings());
        } else {
            $this->providerInst     = null;
            $this->ProviderClass    = null;
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

        if (!$provider) {
            throw new PushException('No push notification provider has been set.');
        }

        $provider->sendPushNotification($this);

        $this->Sent   = true;
        $this->SentAt = date('Y-m-d H:i:s');
        $this->write();
    }
}
