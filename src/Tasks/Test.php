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

    protected $tablesToTest = [
        'Subscriber' => ['ClassName' => Subscriber::class, 'Field' => 'OneSignalUserID'],
        'PushNotification' => ['ClassName' => PushNotification::class, 'Field' => 'OneSignalNotificationID'],
    ];

    private static $segment = 'test';
    protected $api = null;

    public function run($request)
    {
        Environment::increaseTimeLimitTo(3600);
        Environment::increaseMemoryLimitTo('512M');

        $this->header("====================================");
        $this->header("====================================");
        $this->header("HAS VALUE");
        $this->header("====================================");
        $this->header("====================================");

        $this->runExcludeOrExclude('buildExcludeScenarioArray', 'not', 'HasValue');

        $this->header("====================================");
        $this->header("====================================");
        $this->header("DOES NOT HAVE VALUE");
        $this->header("====================================");
        $this->header("====================================");

        $this->runExcludeOrExclude('buildIncludeScenarioArray', '', 'DoesNotHaveValue');

    }

    public function runExcludeOrExclude(string $method, string $phrase, string $rightAnswer)
    {
        $testCount = 0;
        foreach ($this->tablesToTest as $tableName => $details) {
            $className = $details['ClassName'];
            $fieldName = $details['Field'];
            $answers = [
                'HasValue' => 0,
                'DoesNotHaveValue' => 0,
            ];
            foreach ($className::get() as $record) {
                if (trim((string) $record->$fieldName)) {
                    $answers['HasValue']++;

                } else {
                    $answers['DoesNotHaveValue']++;
                }
            }
            $answer = $answers[$rightAnswer];
            $tableSafe = $this->replaceTableAndFieldName($tableName, $tableName, $fieldName);
            $fieldSafe = $this->replaceTableAndFieldName($fieldName, $tableName, $fieldName);
            $this->header(
                'testing '.$tableSafe . '.'.$fieldSafe.'
                ('.$className::get()->count().' records)
                for not falsy values.
                Expected answer: '.$answer.'
                (derived by looping through dataobjects)'
            );
            $scenarios = $this->$method($tableName, $fieldName, $className);
            foreach ($scenarios as $i => $results) {
                $testCount++;
                $count = $results['v'];
                $isCorrect = ($count === $answer);
                $isCorrectPhrase = $isCorrect ? 'CORRECT' : 'WRONG ANSWER';
                foreach ($results as $key => $sql) {
                    if ($key !== 'v' && $key !== 'sql') {
                        $sql = $this->recursiveReplaceValues($sql, $tableName, $fieldName);
                        $sql = $this->normalizeWhitespace(print_r($sql, 1));
                        $this->outcome('===== TEST '.$testCount.'====='.PHP_EOL.$isCorrectPhrase. '('. $count.  '), PRODUCED BY: ');
                        $this->outcome($key.' = '.$sql);
                    }
                }
                if (!$isCorrect) {
                    $whereOnlySQL = $cleanSQL = $this->whereOnlyPhrase($results['sql']);
                    $this->outcome('SQL: '.$this->replaceTableAndFieldName($whereOnlySQL, $tableName, $fieldName));
                }
            }
        }
    }



    protected function buildExcludeScenarioArray(string $tableName, string $fieldName, string $className): array
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
                'DB::query' => $sql,
                'v' => (int) DB::query($sql)->value(),
                'sql' => $sql,
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
                'v' => (int) $className::get()->filter($filter)->count(),
                'sql' => $className::get()->filter($filter)->sql(),
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
                'v' => (int) $className::get()->exclude($exclude)->count(),
                'sql' => $className::get()->exclude($exclude)->sql(),
            ];
        }

        return $scenario;
    }

    protected function buildIncludeScenarioArray(string $tableName, string $fieldName, string $className): array
    {
        $scenario = [];

        // SQL based scenarios
        $sqlQueries = [
            'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" IS NULL',
            'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" <> \'\'',
            'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" IS NULL OR "' . $fieldName . '" = \'\'',
            // 'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" <> 0',
            // 'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" NOT IN (\'\', NULL, 0)',
            // 'SELECT COUNT(*) FROM "' . $tableName . '" WHERE "' . $fieldName . '" IS NOT NULL AND "' . $fieldName . '" <> \'\' AND "' . $fieldName . '" <> 0'
        ];

        foreach ($sqlQueries as $key => $sql) {
            $scenario[] = [
                'DB::query' => $sql,
                'v' => (int) DB::query($sql)->value(),
                'sql' => $sql,
            ];
        }

        // ORM based scenarios
        $filters = [
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

        foreach ($filters as $key => $filter) {
            $scenario[] = [
                'filter' => $filter,
                'v' => (int) $className::get()->filter($filter)->count(),
                'sql' => $className::get()->filter($filter)->sql(),
            ];
        }

        // Exclude based scenarios
        $excludes = [
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

        foreach ($excludes as $key => $exclude) {
            $scenario[] = [
                'exclude' => $exclude,
                'v' => (int) $className::get()->exclude($exclude)->count(),
                'sql' => $className::get()->exclude($exclude)->sql(),
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

    protected function normalizeWhitespace(string $input): string
    {
        // Replace multiple spaces, tabs, newlines with a single space
        return preg_replace('/\s+/', ' ', $input);
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

    protected function whereOnlyPhrase(string $sql): string
    {
        // Remove everything before WHERE
        $pos = stripos($sql, ' WHERE ');
        if ($pos !== false) {
            // Return the part of the string starting from WHERE
            $sql = substr($sql, $pos);
        }

        // Remove everything from ORDER BY onward
        $sql = preg_replace('/\sORDER\sBY.*/i', '', $sql);

        return $sql;
    }


}
