<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use Sunnysideup\PushNotifications\Api\OneSignalSignupApi;

/**
 * Class \Sunnysideup\PushNotifications\Model\Subscriber
 *
 * @property string $Subscription
 * @property int $MemberID
 * @method \SilverStripe\Security\Member Member()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\PushNotifications\Model\SubscriberMessage[] SubscriberMessages()
 */
class Subscriber extends DataObject
{
    private static $table_name = 'Subscriber';

    private static $db = [
        'Subscription' => 'Text',
        'Subscribed' => 'Boolean',
        'OneSignalUserID' => 'Varchar(64)',
        'OneSignalUserNote' => 'Varchar(255)',
        'OneSignalUserTagsNote' => 'Varchar(255)',
    ];

    private static $field_labels = [
        'Subscription' => 'Code for subscription',
        'Subscribed' => 'Is Subscribed',
        'OneSignalUserID' => 'OneSignal User ID',
        'OneSignalUserNote' => 'OneSignal User Connection Note',
        'OneSignalUserTagsNote' => 'OneSignal User Tags (Groups) Added',
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $summary_fields = [
        'Member.Title' => 'Who',
        'Subscribed.Nice' => 'Subscribed',
        'IsOneSignalUser.Nice' => 'Is OneSignal User',
        'SubscriberMessages.Count' => 'Messages',
    ];

    private static $has_many = [
        'SubscriberMessages' => SubscriberMessage::class,
    ];

    private static $casting = [
        'SubscriptionReadable' => 'HTMLText',
        'IsOneSignalUser' => 'Boolean',
    ];

    private static $indexes = [
        'Subscribed' => true,
        'OneSignalUserID' => true,
    ];

    public function getSubscriptionReadable(): DBHTMLText
    {
        return DBHTMLText::create_field('HTMLText', '<pre>' . json_decode((string) $this->Subscription, true) . '</pre>');
    }

    public function getIsOneSignalUser(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->OneSignalUserID ? true : false);
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
        if(Director::isDev()) {
            return true;
        }
        return false;
    }

    public function canDelete($member = null)
    {
        return true;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if(! Director::isDev()) {
            foreach(['OneSignalUserID', 'OneSignalUserNote', 'OneSignalUserTagsNote'] as $fieldName) {
                $fields->replaceField(
                    $fieldName,
                    ReadonlyField::create($fieldName, $fields->dataFieldByName($fieldName)->Title())
                );
            }
        }
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
        $fields->addFieldsToTab(
            'Root.Main',
            [
                HeaderField::create('OneSignalUserHeader', 'Related Member'),
                ReadonlyField::create('Groups', 'Groups', implode(', ', $this->Member()->Groups()->column('Title'))),
                ReadonlyField::create('OneSignalUserTagsNote', 'Update Notes'),
            ]
        );
        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->OneSignalUserID) {
            $member = $this->Member();
            if($member && $member->exists()) {
                /** @var OneSignalSignupApi $api */
                $api = Injector::inst()->get(OneSignalSignupApi::class);
                $outcome = $api->addExternalUserIdToUser($this->OneSignalUserID, $member);
                if(OneSignalSignupApi::test_success($outcome)) {
                    $this->OneSignalUserNote = 'Succesfully connected to OneSignal';
                    $outcome = $api->addTagsToUserBasedOnGroups($member);
                    if(OneSignalSignupApi::test_success($outcome)) {
                        $this->OneSignalUserTagsNote = 'Sucessfully added group tags to user';
                    } else {
                        $this->OneSignalUserTagsNote = OneSignalSignupApi::get_error($outcome);
                    }
                } else {
                    $this->OneSignalUserNote = OneSignalSignupApi::get_error($outcome);
                    $this->OneSignalUserTagsNote = 'Error: could not add external user id so could not add tags';
                }
            } else {
                $this->OneSignalUserNote = 'Error: No member found';
                $this->OneSignalUserTagsNote = 'Error: No member found';
            }
        }
    }

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        if($this->OneSignalUserID) {
            /** @var OneSignalSignupApi $api */
            $api = Injector::inst()->get(OneSignalSignupApi::class);
            $api->deleteDevice($this->OneSignalUserID);
        }
    }
}
