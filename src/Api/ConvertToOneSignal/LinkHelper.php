<?php

namespace Sunnysideup\PushNotifications\Api\ConvertToOneSignal;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class LinkHelper
{
    use Configurable;
    use Injectable;

    private const BASE_LINK = 'https://dashboard.onesignal.com';


    public function createHtmlLink(string $link, string $title, ?bool $isWarning = false): string
    {
        $link = '<a href="'.$link.'" target="_blank"  rel="noopener noreferrer">'.$title.'</a>';
        if ($isWarning) {
            $link = '<p class="message warning"><span style="color:red;">'.$link.'</span></ap>';
        }
        return $link;
    }

    public function createNewAppLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            'new'
        );
    }

    public function dashbooardLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
        );
    }

    public function configurePushNotificationsLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'settings',
            'webpush',
            'configure'
        );
    }

    public function sendNewPushNotificationLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'notifications',
            'new'
        );
    }

    public function subscriptionsLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'subscriptions',
        );
    }

    public function subscriberLink(string $id): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'subscriptions',
            $id
        );
    }

    public function sendPushNotificationLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'campaigns'
        );
    }

    public function sentPushNotificationLink(): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'notifications'
        );
    }


    public function notificationLink(string $id): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'notifications',
            $id
        );
    }

    public function notificationLinkEdit(string $id): string
    {
        return Controller::join_links(
            $this->baseLink(),
            'apps',
            Environment::getEnv('SS_ONESIGNAL_APP_ID'),
            'notifications',
            $id,
            'edit'
        );
    }

    public function scheduledNotificationsLinks(): string
    {
        return $this->sentPushNotificationLink() .'?schedule=true';
    }


    protected function baseLink(): string
    {
        return self::BASE_LINK;
    }
}
