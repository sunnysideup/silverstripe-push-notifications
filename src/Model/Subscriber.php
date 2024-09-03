<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\LinkHelper;
use Sunnysideup\PushNotifications\Api\ConvertToOneSignal\MemberHelper;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;

/**
 * Class \Sunnysideup\PushNotifications\Model\Subscriber
 *
 * @property string $Subscription
 * @property bool $Subscribed
 * @property string $OneSignalUserID
 * @property string $OneSignalUserNote
 * @property string $OneSignalUserTagsNote
 * @property int $OneSignalCommsError
 * @property int $MemberID
 * @method Member Member()
 * @method DataList|SubscriberMessage[] SubscriberMessages()
 */
class Subscriber extends DataObject
{
    private static $table_name = 'Subscriber';

    private static $db = [
        'Subscription' => 'Text',
        'Subscribed' => 'Boolean(1)',
        'Device' => 'Varchar(64)',
        'OneSignalUserID' => 'Varchar(64)',
        'OneSignalUserNote' => 'Varchar(255)',
        'OneSignalUserTagsNote' => 'Varchar(255)',
        'OneSignalCommsError' => 'Int',
    ];

    private static $field_labels = [
        'Subscription' => 'Code for subscription',
        'Subscribed' => 'Is Subscribed',
        'OneSignalUserID' => 'Subscription ID',
        'OneSignalUserNote' => 'OneSignal User Connection Note',
        'OneSignalUserTagsNote' => 'OneSignal User Tags (Groups) Added',
        'OneSignalCommsError' => 'Number of Comms Errors',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $summary_fields = [
        'Created' => 'Created',
        'Member.Title' => 'Who',
        'Subscribed.Nice' => 'Subscribed',
        'IsOneSignalUser.Nice' => 'Is OneSignal User',
        'SubscriberMessages.Count' => 'Messages',
    ];

    private static $has_many = [
        'SubscriberMessages' => SubscriberMessage::class,
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'SubscriptionReadable' => 'HTMLText',
        'IsOneSignalUser' => 'Boolean',
    ];

    private static $indexes = [
        'Subscribed' => true,
        'OneSignalUserID' => true,
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    public function getSubscriptionReadable(): DBHTMLText
    {
        return DBHTMLText::create_field('HTMLText', '<pre>' . json_decode((string) $this->Subscription, true) . '</pre>');
    }

    public function getIsOneSignalUser(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->IsOneSignalSubscription());
    }

    /**
     * DataObject create permissions
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        if (Director::isDev()) {
            return parent::canEdit($member);
        }
        return false;
    }

    public function canDelete($member = null)
    {
        return parent::canDelete($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // $fields->removeByName('Subscription');
        $fields->replaceField(
            'Subscribed',
            ReadonlyField::create('SubscribedReadable', 'Subscribed', $this->dbObject('Subscribed')->Nice())
        );
        $fields->replaceField(
            'Subscription',
            ReadonlyField::create('SubscriptionReadable', 'Subscription')
        );
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('IsOneSignalUserReadable', 'Is OneSignal User', $this->getIsOneSignalUser()->Nice()),
            ],
            'OneSignalUserID'
        );
        if ($this->IsOneSignalSubscription()) {
            $fields->addFieldsToTab(
                'Root.OneSignal',
                [
                    $fields->dataFieldByName('OneSignalUserID')->setReadonly(true)
                        ->setTitle('Subscription ID'),
                    $fields->dataFieldByName('OneSignalUserNote')->setReadonly(true),
                    $fields->dataFieldByName('OneSignalUserTagsNote')->setReadonly(true),
                    $fields->dataFieldByName('OneSignalCommsError')->setReadonly(true),
                ]
            );
            if ($this->OneSignalUserID) {
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
        } else {
            $fields->removeByName('OneSignalUserID');
            $fields->removeByName('OneSignalUserNote');
            $fields->removeByName('OneSignalUserTagsNote');
        }
        $fields->removeByName('MemberID');
        $member = $this->Member();
        if ($member) {
            $fields->addFieldsToTab(
                'Root.RelatedMember',
                [
                    ReadonlyField::create('Groups', 'Member Groups', implode(', ', $this->Member()->Groups()->column('Title'))),
                    ReadonlyField::create(
                        'MemberNice',
                        'Member',
                        DBHTMLText::create_field('HTMLText', '<a href="'.$this->Member()->CMSEditLink().'">'.$this->Member()->getTitle().'</a>')
                    ),
                ]
            );
        } else {
            $fields->addFieldsToTab(
                'Root.RelatedMember',
                [
                    ReadonlyField::create(
                        'MemberNice',
                        'Member',
                        'Not linked to any member'
                    ),
                ]
            );
        }
        $fields->addFieldsToTab('Root.History', [
            ReadonlyField::create('Created', _t('Push.CREATED', 'Created')),
            ReadonlyField::create('LastEdited', _t('Push.LASTEDITED', 'Last Edited')),
        ]);

        return $fields;
    }

    public function getOneSignalLink(): string
    {
        return LinkHelper::singleton()->subscriberLink($this->OneSignalUserID);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->exists()) {
            $this->OneSignalComms(false);
        }
    }

    public function getTitle(): string
    {
        return $this->Member()->getTitle() . ' - ' . $this->Created . ' - ' . ($this->Subscribed ? '' : 'NOT SUBSCRIBED');
    }

    protected bool $noOneSignalComms = false;

    public function setNoOneSignalComms(): static
    {
        $this->noOneSignalComms = true;
        return $this;
    }

    public function OneSignalComms(?bool $write = false)
    {
        if ($this->noOneSignalComms) {
            return;
        }
        if ($this->OneSignalUserID) {
            // dont bother about things that are old!
            if (strtotime($this->LastEdited) < strtotime(' -12 month')) {
                return;
            }

            /** @var OneSignalSignupApi $api */
            $api = Injector::inst()->get(OneSignalSignupApi::class);
            $outcome =  $api->getOneDevice($this->OneSignalUserID);
            $error = false;
            if (OneSignalSignupApi::test_success($outcome)) {
                // subscribed
                $isInvalid = $outcome['invalid_identifier'] ?? false;
                $this->Subscribed = $isInvalid ? false : true;
                // device
                $deviceMo = $outcome['device_model'] ?? 'unknown model';
                $deviceTy = $outcome['device_type'] ?? 'unknown type';
                $deviceOs = $outcome['device_os'] ?? 'unknown os';
                $this->Device = $deviceMo . ' ('.$deviceTy . ') - ' . $deviceOs;

                $this->OneSignalCommsError = 0;
            } else {
                $error = true;
                $this->OneSignalCommsError++;
                if ($this->OneSignalCommsError > 10) {
                    $this->OneSignalUserID = '';
                    $this->OneSignalUserNote = 'Subscription not found';
                    $this->OneSignalUserTagsNote = 'Subscription not found';
                    $this->Subscribed = false;
                }
            }
            $externalUserId = (string) ($outcome['external_user_id'] ?? '');
            if ($error === false && $this->Subcribed) {
                // extra stuff for member
                $member = $this->Member();
                if ($member && $member->exists()) {
                    $expectedExternalUserId = (string) MemberHelper::singleton()->member2externalUserId($member);
                    if ($externalUserId !== $expectedExternalUserId) {
                        $outcome = $api->addExternalUserIdToUser($this->OneSignalUserID, $member);
                        if (OneSignalSignupApi::test_success($outcome)) {
                            $this->OneSignalUserNote = 'Succesfully connected to OneSignal with external user id '.$externalUserId;
                            $externalUserId = $expectedExternalUserId;
                        } else {
                            $this->OneSignalUserNote = OneSignalSignupApi::get_error($outcome);
                            $this->OneSignalUserTagsNote = 'Error: could not add external user id with id '.$externalUserId;
                        }
                    }
                    if ($externalUserId === $expectedExternalUserId) {
                        $outcome = $api->addTagsToUserBasedOnGroups($member);
                        if (OneSignalSignupApi::test_success($outcome)) {
                            $this->OneSignalUserTagsNote = 'Sucessfully added group tags to user';
                        } else {
                            $this->OneSignalUserTagsNote = OneSignalSignupApi::get_error($outcome);
                        }
                    }
                } else {
                    $this->OneSignalUserNote = 'Error: No member found';
                    $this->OneSignalUserTagsNote = 'Error: No member found';
                }
            }
        }
        if ($write) {
            $this->write();
        }
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        if ($this->IsOneSignalSubscription()) {
            /** @var OneSignalSignupApi $api */
            $api = Injector::inst()->get(OneSignalSignupApi::class);
            $api->deleteDevice($this->OneSignalUserID);
        }
    }

    public function IsOneSignalSubscription(): bool
    {
        return $this->OneSignalUserID ? true : false;
    }
}
