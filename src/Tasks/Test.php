<?php

use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\PushNotifications\Model\PushNotification;
use Sunnysideup\PushNotifications\Model\Subscriber;

class Test extends BuildTask
{
    protected $title = 'Test';

    protected $description = 'Test';

    private static $segment = 'test';
    protected $api = null;

    public function run($request)
    {
        Environment::increaseTimeLimitTo(3600);
        Environment::increaseMemoryLimitTo('512M');
        $class = [
            'Subscriber' => ['ClassName' => Subscriber::class, 'Field' => 'OneSignalUserID'],
            'PushNotification' => ['ClassName' => PushNotification::class, 'Field' => 'OneSignalNotificationID'],
        ];
        foreach ($class as $tableName => $details) {
            $className = $details['ClassName'];
            $fieldName = $details['Field'];
            $answer = 0;
            foreach ($className::get() as $record) {
                if (trim((string) $record->$fieldName)) {
                    $answer++;
                }
            }
            $tableSafe = $this->replaceTableAndFieldName($tableName, $tableName, $fieldName);
            $fieldSafe = $this->replaceTableAndFieldName($fieldName, $tableName, $fieldName);
            $this->header(
                'TESTING '.$tableSafe . '.'.$fieldSafe.'
                FOR NOT FALSY VALUES, TOTAL COUNT '.$className::get()->count().',
                EXPECTED ANSWER: '.$answer.'
                DERIVED BY LOOPING THROUGH DATAOBJECTS'
            );
            $scenarios = $this->buildScenarioArray($tableName, $fieldName, $className);
            foreach ($scenarios as $i => $results) {
                $count = $results['v'];
                if ($count !== $answer) {
                    foreach ($results as $key => $sql) {
                        if ($key !== 'v') {
                            $sql = $this->recursiveReplaceValues($sql, $tableName, $fieldName);
                            $sql = print_r($sql, 1);
                            $this->outcome('====='.PHP_EOL.'WRONG ANSWER: ' . $count.  ', PRODUCED BY: ');
                            $this->outcome($key.' = '.$sql);
                        }
                    }
                } else {
                    foreach ($results as $key => $sql) {
                        if ($key !== 'v') {
                            $sql = $this->recursiveReplaceValues($sql, $tableName, $fieldName);
                            $sql = print_r($sql, 1);
                            $this->outcome('====='.PHP_EOL.'CORRECT ANSWER: ');
                            $this->outcome($key.' = '.$sql);
                        }
                    }
                }

            }
        }
    }

    protected function buildScenarioArray(string $tableName, string $fieldName, string $className): array
    {
        $scenario = [];

        // SQL based scenarios
        $sqlQueries = [
            'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" IS NOT NULL',
            'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" <> \'\'',
            'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" IS NOT NULL AND "' . $fieldName . '" <> \'\'',
            // 'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" <> 0',
            // 'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" NOT IN (\'\', NULL, 0)',
            // 'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" IS NOT NULL AND "' . $fieldName . '" <> \'\' AND "' . $fieldName . '" <> 0'
        ];

        foreach ($sqlQueries as $key => $sql) {
            $scenario[] = [
                'sql' => $sql,
                'v' => (int) DB::query($sql)->value()
            ];
        }

        // ORM based scenarios
        $filters = [
            [$fieldName . ':not' => ['']],
            [$fieldName . ':not' => [null]],
            [$fieldName . ':not' => ['', null]],
            [$fieldName . ':not' => ''],
            [$fieldName . ':not' => null],
            // [$fieldName . ':not' => [0]],
            // [$fieldName . ':not' => [0, null]],
            // [$fieldName . ':not' => 0],
            // [$fieldName . ':not' => ['', 0]],
            // [$fieldName . ':not' => ['', null, 0]],
        ];

        foreach ($filters as $key => $filter) {
            $scenario[] = [
                'filter' => $filter,
                'v' => (int) $className::get()->filter($filter)->count()
            ];
        }

        // Exclude based scenarios
        $excludes = [
            [$fieldName  => ['']],
            [$fieldName  => [null]],
            [$fieldName  => ['', null]],
            [$fieldName  => ''],
            [$fieldName  => null],
            // [$fieldName  => [0]],
            // [$fieldName  => [0, null]],
            // [$fieldName  => ['', 0]],
            // [$fieldName  => ['', null, 0]],
            // [$fieldName  => 0],
        ];

        foreach ($excludes as $key => $exclude) {
            $scenario[] = [
                'exclude' => $exclude,
                'v' => (int) $className::get()->exclude($exclude)->count()
            ];
        }

        return $scenario;
    }

    protected function recursiveReplaceValues($input, $tableName, $fieldName): array|string
    {
        if (is_null($input)) {
            return '[NULL]';
        }
        if ($input === '') {
            return '[EMPTY STRING]';
        }
        if (!is_array($input)) {
            return $this->replaceTableAndFieldName($input, $tableName, $fieldName);
        }
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                // Recursively apply the function to nested arrays
                $input[$key] = $this->recursiveReplaceValues($value, $tableName, $fieldName);
            } elseif (is_null($value)) {
                // Replace null with [NULL]
                $input[$key] = '[NULL]';
            } elseif ($value === '') {
                // Replace empty string with [EMPTY STRING]
                $input[$key] = '[EMPTY STRING]';
            } elseif ($value === 0) {
                // Replace 0 with [ZERO]
                $input[$key] = '[ZERO]';
            } else {
                $input = $this->replaceTableAndFieldName($input, $tableName, $fieldName);
            }
            $newKey = $this->replaceTableAndFieldName($key, $tableName, $fieldName);
            $newVal = $input[$key];
            unset($input[$key]);
            $input[$newKey] = $newVal;
        }

        return $input;
    }

    protected function replaceTableAndFieldName($input, $tableName, $fieldName): string
    {
        if ($tableName === 'Subscriber') {
            $replaceTable = 'MYTABLE';
        } else {
            $replaceTable = 'MYOTHERTABLE';
        }
        $input = str_replace($tableName, $replaceTable, $input);
        $input = str_replace($fieldName, 'MYFIELD', $input);

        return $input;
    }

    protected function header($message)
    {
        if (Director::is_cli()) {
            echo PHP_EOL;
            echo PHP_EOL;
            echo PHP_EOL;
            echo PHP_EOL;
            echo '========================='.PHP_EOL;
            echo $message . PHP_EOL;
            echo '========================='.PHP_EOL;
            ;
        } else {
            echo '<h2>' . $message . '</h2>';
        }
    }

    protected function outcome($mixed)
    {
        if (Director::is_cli()) {
            echo PHP_EOL;
            print_r($mixed);
            echo PHP_EOL;
        } else {
            echo '<pre>';
            print_r($mixed);
            echo '</pre>';
        }
    }

}
