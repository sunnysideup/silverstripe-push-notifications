<?php

namespace Sunnysideup\PushNotifications\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\View\Requirements;
use Sunnysideup\PushNotifications\Model\PushNotification;

class PushNotificationsAdmin extends ModelAdmin
{
    private static $menu_title = 'Push';

    private static $url_segment = 'push';

    private static $managed_models = [
        PushNotification::class => [
            'title' => 'Push Notifications',
        ],
    ];

    private static $model_importers = [];

    public function init()
    {
        parent::init();
        Requirements::javascript('sunnysideup/push-notifications: client/dist/javascript/PushNotificationsAdmin.js');
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $name = $this->sanitiseClassName($this->modelClass);
        $conf = $form->Fields()->dataFieldByName($name)->getConfig();

        $conf->getComponentByType(GridFieldDetailForm::class)
            ->setItemRequestClass(PushNotificationsAdminItemRequest::class)
            ->setItemEditFormCallback(function ($form, $component) {
                $record = $form->getRecord();

                if ($record && $record->ID && ! $record->Sent && $record->canSend()) {
                    $form->Actions()->push(
                        FormAction::create('doSend', 'Send')
                            ->addExtraClass('ss-ui-action btn btn-primary font-icon-block-email action--new discard-confirmation')
                            ->setUseButtonTag(true)
                    );
                }
            });

        return $form;
    }
}
