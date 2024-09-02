<?php

namespace Sunnysideup\PushNotifications\Controllers;

use Exception;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Sunnysideup\PushNotifications\Extensions\GroupExtension;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\Subscriber;

/**
 * Class \Sunnysideup\PushNotifications\Controllers\PushNotificationPageController
 *
 * @property PushNotificationPage $dataRecord
 * @method PushNotificationPage data()
 * @mixin PushNotificationPage
 */
class PushNotificationPageController extends ContentController
{
    private static $allowed_actions = [
        'subscribe' => 'isMember',
        'unsubscribe' => true,
        'subscribeonesignal' => true,
        'unsubscribeonesignal' => true,
        'SelectGroupsForm' => true,
    ];


    public function subscribe($request)
    {
        return $this->subscribeSubcribeInner($request, true);
    }

    public function unsubscribe($request)
    {
        return $this->subscribeSubcribeInner($request, false);
    }

    public function subscribeSubcribeInner($request, ?bool $subscribed = true)
    {
        $subscription = $request->getBody();

        try {
            $subscriber = Subscriber::create();
            $subscriber->Subscription = $subscription;

            $member = Security::getCurrentUser();
            if ($member) {
                $subscriber->MemberID = $member->ID;
            }
            $subscriber->Subscribed = $subscribed;

            $subscriber->write();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }


    public function subscribeonesignal($request)
    {
        return $this->subscribeUnsubscribeOneSignalInner($request, true);
    }

    public function unsubscribeonesignal($request)
    {
        return $this->subscribeUnsubscribeOneSignalInner($request, false);
    }

    protected function subscribeUnsubscribeOneSignalInner($request, ?bool $subscribed = true)
    {
        // print_r($request->getBody());
        HTTPCacheControlMiddleware::singleton()->disableCache();
        $userId = (string) $request->postVar('userId');
        $token = (string) $request->postVar('token');
        if (!$userId) {
            return HTTPResponse::create(json_encode(['success' => false, 'error' => 'No user ID provided']))
                ->addHeader('Content-Type', 'application/json')
                ->setStatusCode(404);
        }
        try {
            $member = Security::getCurrentUser();
            $filter = [
                'MemberID' => $member->ID,
                'OneSignalUserID' => $userId,
            ];
            $subscriber = Subscriber::get()->filter($filter)->first();
            if (! $subscriber) {
                $subscriber = Subscriber::create($filter);
            }
            $subscriber->Subscribed = $subscribed;
            $subscriber->Subscription = (string) $token;

            $subscriber->write();
            return HTTPResponse::create(json_encode(['success' => true]))
                ->addHeader('Content-Type', 'application/json')
                ->setStatusCode(201);

        } catch (Exception $e) {
            return HTTPResponse::create(json_encode(['success' => false, 'error' => $e->getMessage()]))
                ->addHeader('Content-Type', 'application/json')
                ->setStatusCode(500);
        }
    }
    public function getPushNotifications()
    {
        $notifications = PushNotification::get()
            ->filter(
                [
                    'Sent' => true,
                    'TestOnly' => false,
                ]
            )
            ->sort('SentAt', 'DESC');
        $output = ArrayList::create();

        foreach ($notifications as $notification) {
            if ($notification->canView()) {
                $output->push($notification);
            }
        }

        return $output;
    }

    protected function init()
    {
        parent::init();
        $link = Director::absoluteURL($this->Link());
        $link = str_replace('?stage=Stage', '', $link);
        Requirements::customScript('window.push_notification_url="'.$link.'";', "push_notification_url");
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/add-to-home-screen.js');
        // Requirements::themedCSS('client/dist/css/push');
        if ($this->owner->UseOneSignal) {
            Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/one-signal.js');
            return;
        }
        $key = Environment::getEnv('SS_VAPID_PUBLIC_KEY');
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/service-worker-start.js');
        if ($key && ! $this->UseOneSignal) {
            Requirements::customScript('let vapid_public_key="'.$key.'";', "VapidPublicKey");
        }
    }

    public function SelectGroupsForm(): Form
    {
        $member = Security::getCurrentUser();
        $memberGroups = (array) $member->Groups()->columnUnique();
        $signupableGroups = (array) $this->SignupGroups()->columnUnique();
        $groupOptions = Group::get()
            ->filter(['ID' => array_merge([-1], $signupableGroups)])
            ->map('ID', 'BreadcrumbsSimple');
        $fields = FieldList::create(
            CheckboxSetField::create('Groups', 'Select Your Groups', $groupOptions)
            ->setValue($memberGroups)
        );

        $actions = FieldList::create(
            FormAction::create('doSelectGroupsForm', 'Submit')
        );

        $validator = RequiredFields::create();

        $form = Form::create(
            $this,
            'SelectGroupsForm',
            $fields,
            $actions,
            $validator
        );

        return $form;
    }

    public function doSelectGroupsForm(array $data, Form $form)
    {
        // member stuff
        $member = Security::getCurrentUser();
        $memberGroups = $member->Groups();

        // old Groups
        $oldGroups = (array) $memberGroups->columnUnique();
        // ....
        // deal with default group
        $defaultGroup = GroupExtension::get_all_subscribers_group();
        if (isset($oldGroups[$defaultGroup->ID])) {
            unset($oldGroups[$defaultGroup->ID]);
        } else {
            $memberGroups->add($defaultGroup);
        }
        // filter for values you can change!
        if (!isset($data['Groups']) || ! is_array($data['Groups'])) {
            $data['Groups'] = [];
        }
        // IMPORTANT SECURITY CHECK!!!
        // YOU EITHER ARE ALREADY SUBSCRIBED TO THE GROUP OR THE GROUP IS SIGNUPABLE
        $signupableGroups = (array) $this->SignupGroups()->columnUnique();
        $newGroups = array_intersect($data['Groups'], $signupableGroups);
        $oldGroups = array_intersect($oldGroups, $signupableGroups);

        // make it easy to unset old groups
        $oldGroups = array_combine($oldGroups, $oldGroups);
        // add new groups
        foreach ($newGroups as $groupID) {
            $groupID = (int) $groupID;
            // already subscribed, nothing more to do.
            if (in_array($groupID, $oldGroups)) {
                unset($oldGroups[$groupID]);
                continue;
            }
            $group = Group::get()->byID((int) $groupID);
            if (! $group) {
                continue;
            }
            $memberGroups->add($group);
            $group->write();
            unset($oldGroups[$groupID]);
        }

        // remove remaining old groups
        foreach ($oldGroups as $groupID) {
            $group = Group::get()->byID((int) $groupID);
            if (! $group) {
                continue;
            }
            $memberGroups->remove($group);
            $group->write();
        }

        // update member
        $member->write();
        $this->redirectBack();
    }


}
