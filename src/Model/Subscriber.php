<?php

namespace Sunnysideup\PushNotifications\Model;

use SilverStripe\ORM\DataObject;

/**
 * Class \Sunnysideup\PushNotifications\Model\Subscriber
 *
 * @property string $Title
 */
class Subscriber extends DataObject
{
    private static $table_name = 'Subscriber';

    private static $db = array(
        'Title'            => 'Text',
    );

}
