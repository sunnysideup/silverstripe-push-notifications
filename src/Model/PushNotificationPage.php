<?php

namespace Sunnysideup\PushNotifications\Model;

use Exception;
use Page;
use SilverStripe\Control\Controller;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\PushNotifications\Controllers\PushNotificationPageController;

class PushNotificationPage extends Page
{
    private static $table_name = 'PushNotificationPage';

    private static $controller_name = PushNotificationPageController::class;

    protected function modifyJsonValue(string $filePath, string $key, $newValue): void
    {
        // Check if the file exists
        if (! file_exists($filePath)) {
            throw new Exception('File not found.');
        }

        // Read the file contents
        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new Exception('Failed to read the file.');
        }

        // Decode the JSON data into an associative array
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode JSON: ' . json_last_error_msg());
        }

        // Modify the value
        $data[$key] = $newValue;

        // Encode the data back to JSON
        $newJsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($newJsonContent === false) {
            throw new Exception('Failed to encode JSON: ' . json_last_error_msg());
        }

        // Save the modified JSON data back to the file
        $result = file_put_contents($filePath, $newJsonContent);
        if ($result === false) {
            throw new Exception('Failed to write to the file.');
        }
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Modify the JSON value
        $this->modifyJsonValue($this->getManifestPath(), 'name', SiteConfig::current_site_config()->Title . ' - ' . $this->Title);
        $this->modifyJsonValue($this->getManifestPath(), 'start_url', $this->Link());
    }

    protected function getManifestPath(): string
    {
        return Controller::join_links(BASE_PATH, 'public', 'manifest.json');
    }
}
