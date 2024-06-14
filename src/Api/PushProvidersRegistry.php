<?php

namespace Sunnysideup\PushNotifications\Api;

use SilverStripe\Core\Injector\Injector;

/**
 * A registry of all provider classes that are available.
 *
 * @package silverstripe-push
 */
class PushProvidersRegistry
{
    /**
     * This has to be public!
     */
    public $providers = [];

    protected $providersAsEnabledObjects = [];

    /**
     * @param  string $class
     * @return bool
     */
    public function has($class)
    {
        return in_array($class, $this->providers);
    }

    /**
     * @param string $class
     */
    public function remove($class)
    {
        if ($key = array_search($class, $this->providers)) {
            unset($this->providers[$key]);
        }
    }

    /**
     * @return array
     * This is set from the config file.
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @return array
     * This is set from the config file.
     */
    public function getProvidersAsEnabledObjects()
    {
        foreach ($this->providers as $key => $provider) {
            $obj = Injector::inst()->get($provider);
            if ($obj->isEnabled()) {
                $this->providersAsEnabledObjects[$key] = $obj;
            }
        }
        return $this->providersAsEnabledObjects;
    }
}
