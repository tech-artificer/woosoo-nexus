<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;

class AppEnvironment
{
    /**
     * Check if the application is running in the local environment.
     */
    public static function isLocal()
    {   
        return Config::get('app.instance_type') === 'local';
    }

    /**
     * Check if the application is running in a production or staging environment (cloud).
     */
    public static function isCloud(): bool
    {
        return Config::get('app.instance_type') === 'cloud';
    }

    public static function appVersion(): string
    {
        return Config::get('app.version');
    }
}