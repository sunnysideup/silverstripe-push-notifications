<?php

namespace Sunnysideup\PushNotifications\Admin;

use Exception;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use Sunnysideup\PushNotifications\ErrorHandling\PushException;

/**
 * Handles sending push notifications.
 */
class PushNotificationsAdminItemRequest extends GridFieldDetailForm_ItemRequest
{
    private static $allowed_actions = array(
        'doSend'
    );
    
    public function doSend($data, $form)
    {
        try {
            $this->record->doSend();
        } catch (Exception $e) {
            return new HTTPResponse(
                $this->ItemEditForm()->forAjaxTemplate(),
                400,
                $e->getMessage()
            );
        }

        $response = new HTTPResponse($this->ItemEditForm()->forAjaxTemplate());
        $response->setStatusDescription(_t(
            'Push.PUSHSENT',
            'The push notification has been sent'
        ));
        return $response;
    }
}
