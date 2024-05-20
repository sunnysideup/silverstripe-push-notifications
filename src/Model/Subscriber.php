<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\ORM\DataObject;

/**
 * @package silverstripe-push
 */
class Subscriber extends DataObject
{
    private static $db = array(
        'Title'            => 'Text',
    );

}
