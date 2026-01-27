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
    
    /**
     * Get the MCP servers configuration for this app.
     * Returns an array of MCP server configurations.
     * Each configuration should include:
     * - 'name': Unique identifier for the server (used as array key)
     * - 'class': Server class (optional, for route registration)
     * - 'url': Server URL (optional, if different from default pattern)
     * 
     * @return array<string, array{class?: string, url?: string, middleware?: array}>
     */
    public static function mcpServers(): array;
}