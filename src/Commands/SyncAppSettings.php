<?php

namespace Hwkdo\IntranetAppBase\Commands;

use App\Data\UserAppSettings;
use App\Models\User;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncAppSettings extends Command
{
    protected $signature = 'intranet-app:sync-settings {--force : Force sync without confirmation}';
    protected $description = 'Synchronize app settings with database and user settings';

    public function handle(): int
    {
        $this->info('Starting Intranet App Settings Synchronization...');
        
        $packages = $this->getIntranetAppPackages();
        $updatedApps = [];
        
        foreach ($packages as $packageName => $packageData) {
            $appClass = $this->getAppClass($packageName, $packageData);
            
            if (!$appClass || !class_exists($appClass)) {
                continue;
            }
            
            if (!is_subclass_of($appClass, IntranetAppInterface::class)) {
                continue;
            }
            
            $identifier = $appClass::identifier();
            $appName = $appClass::app_name();
            
            $this->info("Processing app: {$appName} ({$identifier})");
            
            // Sync App Settings
            if ($appClass::appSettingsClass()) {
                $this->syncAppSettings($appClass, $identifier);
                $updatedApps[] = "{$appName} (App Settings)";
            }
            
            // Sync User Settings
            if ($appClass::userSettingsClass()) {
                $this->syncUserSettings($appClass, $identifier);
                $updatedApps[] = "{$appName} (User Settings)";
            }
        }
        
        if (empty($updatedApps)) {
            $this->info('No apps found or no settings to sync.');
            return self::SUCCESS;
        }
        
        $this->info('Successfully synchronized settings for:');
        foreach ($updatedApps as $app) {
            $this->line("  - {$app}");
        }
        
        return self::SUCCESS;
    }
    
    private function syncAppSettings(string $appClass, string $identifier): void
    {
        $appSettingsClass = $appClass::appSettingsClass();
        $settingsModelClass = $this->getSettingsModelClass($appClass);
        
        if (!$settingsModelClass || !class_exists($settingsModelClass)) {
            $this->warn("  Settings model not found for {$identifier}");
            return;
        }
        
        // Get current settings from database
        $currentSettings = $settingsModelClass::current();
        
        if (!$currentSettings) {
            $this->warn("  No current settings found for {$identifier}");
            return;
        }
        
        // Get default settings from Data class
        $defaultSettings = new $appSettingsClass();
        $defaultArray = $defaultSettings->toArray();
        $currentArray = $currentSettings->settings ? $currentSettings->settings->toArray() : [];
        
        // Check for new properties
        $newProperties = array_diff_key($defaultArray, $currentArray);
        
        if (empty($newProperties)) {
            $this->line("  App settings for {$identifier} are up to date");
            return;
        }
        
        // Merge new properties with existing settings
        $mergedSettings = array_merge($currentArray, $newProperties);
        $newSettingsInstance = $appSettingsClass::from($mergedSettings);
        
        // Update database
        $currentSettings->settings = $newSettingsInstance;
        $currentSettings->save();
        
        $this->info("  Added new properties to {$identifier}: " . implode(', ', array_keys($newProperties)));
    }
    
    private function syncUserSettings(string $appClass, string $identifier): void
    {
        $userSettingsClass = $appClass::userSettingsClass();
        
        if (!$userSettingsClass || !class_exists($userSettingsClass)) {
            $this->warn("  User settings class not found for {$identifier}");
            return;
        }
        
        $users = User::all();
        $updatedUsers = 0;
        
        foreach ($users as $user) {
            $userAppSettings = $user->settings;
            
            if (!$userAppSettings instanceof UserAppSettings) {
                $userAppSettings = new UserAppSettings();
            }
            
            // Get current app settings for this user
            $currentAppSettings = $userAppSettings->__get($identifier);
            
            if (!$currentAppSettings) {
                // Create new settings with defaults
                $defaultSettings = new $userSettingsClass();
                $userAppSettings->__set($identifier, $defaultSettings);
                $updatedUsers++;
            } else {
                // Check for new properties and merge
                $defaultSettings = new $userSettingsClass();
                $defaultArray = $defaultSettings->toArray();
                $currentArray = $currentAppSettings->toArray();
                
                $newProperties = array_diff_key($defaultArray, $currentArray);
                
                if (!empty($newProperties)) {
                    $mergedSettings = array_merge($currentArray, $newProperties);
                    $newSettingsInstance = $userSettingsClass::from($mergedSettings);
                    $userAppSettings->__set($identifier, $newSettingsInstance);
                    $updatedUsers++;
                }
            }
            
            // Save updated settings
            $user->settings = $userAppSettings;
            $user->save();
        }
        
        if ($updatedUsers > 0) {
            $this->info("  Updated user settings for {$updatedUsers} users in {$identifier}");
        } else {
            $this->line("  User settings for {$identifier} are up to date");
        }
    }
    
    private function getSettingsModelClass(string $appClass): ?string
    {
        // Try to determine the settings model class based on app class
        $reflection = new \ReflectionClass($appClass);
        $namespace = $reflection->getNamespaceName();
        
        // Common patterns for settings model classes
        $possibleNames = [
            $namespace . '\\Models\\' . $reflection->getShortName() . 'Settings',
            $namespace . '\\Models\\IntranetApp' . $reflection->getShortName() . 'Settings',
        ];
        
        foreach ($possibleNames as $className) {
            if (class_exists($className)) {
                return $className;
            }
        }
        
        return null;
    }
    
    private function getIntranetAppPackages(): array
    {
        $packagesFile = base_path('bootstrap/cache/packages.php');
        
        if (!file_exists($packagesFile)) {
            return [];
        }
        
        $packages = require $packagesFile;
        
        return array_filter($packages, function ($key) {
            return str_starts_with($key, 'hwkdo/intranet-app-') &&
                   !str_starts_with($key, 'hwkdo/intranet-app-base');
        }, ARRAY_FILTER_USE_KEY);
    }
    
    private function getAppClass(string $packageName, array $packageData): ?string
    {
        // Convert package name to class name
        // e.g., "hwkdo/intranet-app-hwro" -> "Hwkdo\IntranetAppHwro\IntranetAppHwro"
        $parts = explode('/', $packageName);
        $vendor = ucfirst($parts[0]);
        $packagePart = str_replace('-', '', ucwords($parts[1], '-'));
        
        return "$vendor\\$packagePart\\$packagePart";
    }
}
