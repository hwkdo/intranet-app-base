<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

use Illuminate\Support\Collection;

interface IntranetAppInterface
{
    public static function roles_admin(): Collection;
    public static function roles_user(): Collection;
    public static function identifier(): string;
    public static function app_name(): string;
    public static function app_icon(): string;
    
    /**
     * Get the fully qualified class name of the user settings Data class for this app.
     * Returns null if the app does not have user-specific settings.
     */
    public static function userSettingsClass(): ?string;
    
    /**
     * Get the fully qualified class name of the app settings Data class for this app.
     * Returns null if the app does not have app-wide settings.
     */
    public static function appSettingsClass(): ?string;
}