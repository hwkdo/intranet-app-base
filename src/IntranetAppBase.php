<?php

namespace Hwkdo\IntranetAppBase;

class IntranetAppBase {

    public static function getRequiredPermissionsFromAppConfig(array $appConfig): array {        
        $permissions = collect();
        
        // Prüfen ob es sich um die Rollen-Struktur handelt
        if (isset($appConfig['roles'])) {
            collect($appConfig['roles'])->each(function ($role, $roleKey) use ($permissions) {
                // Direkte Permissions in der Rolle
                if (isset($role['permissions'])) {
                    $permissions->push(...$role['permissions']);
                }
                
                // Unterrollen durchgehen (wie "others")
                if (is_array($role)) {
                    collect($role)->each(function ($subRole, $subRoleKey) use ($permissions) {
                        if (isset($subRole['permissions'])) {
                            $permissions->push(...$subRole['permissions']);
                        }
                    });
                }
            });
        }         
        return $permissions->unique()->toArray();
    }

    public static function getRolesWithPermissionsFromAppConfig(array $appConfig): array {
        $roles = [];
        
        if (isset($appConfig['roles'])) {
            collect($appConfig['roles'])->each(function ($role, $roleKey) use (&$roles) {
                // Direkte Rollen (admin, user)
                if (isset($role['name']) && isset($role['permissions'])) {
                    $roles[$roleKey] = [
                        'name' => $role['name'],
                        'permissions' => $role['permissions']
                    ];
                }
                
                // Unterrollen durchgehen (wie "others")
                if (is_array($role)) {
                    collect($role)->each(function ($subRole, $subRoleKey) use (&$roles, $roleKey) {
                        if (isset($subRole['name']) && isset($subRole['permissions'])) {
                            $roles[$roleKey . '.' . $subRoleKey] = [
                                'name' => $subRole['name'],
                                'permissions' => $subRole['permissions']
                            ];
                        }
                    });
                }
            });
        }
        
        return $roles;
    }

    /**
     * Get all installed Intranet App packages from packages.php
     */
    public static function getIntranetAppPackages(): array
    {
        $packagesFile = base_path('bootstrap/cache/packages.php');

        if (! file_exists($packagesFile)) {
            return [];
        }

        $packages = require $packagesFile;

        return array_filter($packages, function ($key) {
            return str_starts_with($key, 'hwkdo/intranet-app-') &&
                   ! str_starts_with($key, 'hwkdo/intranet-app-base') &&
                   ! str_starts_with($key, 'hwkdo/intranet-app-template');
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Convert package name to app class name
     * e.g., "hwkdo/intranet-app-hwro" -> "Hwkdo\IntranetAppHwro\IntranetAppHwro"
     */
    public static function getAppClass(string $packageName, array $packageData = []): ?string
    {
        $parts = explode('/', $packageName);
        $vendor = ucfirst($parts[0]);
        $packagePart = str_replace('-', '', ucwords($parts[1], '-'));

        return "{$vendor}\\{$packagePart}\\{$packagePart}";
    }

    /**
     * Get all Intranet Apps with their configs
     * Returns array with identifier as key and config as value
     */
    public static function getAppsWithConfigs(): array
    {
        $apps = [];
        $packages = self::getIntranetAppPackages();

        foreach ($packages as $packageName => $packageData) {
            $appClass = self::getAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            #$identifier = str($packageName)->afterLast('-')->value;
            $identifier = str($packageName)->after('intranet-app-')->value;
            $configKey = "intranet-app-{$identifier}";
            $appConfig = config($configKey);

            if ($appConfig && isset($appConfig['roles'])) {
                $apps[$identifier] = $appConfig;
            }
        }

        return $apps;
    }

    /**
     * Get app config for a specific identifier
     */
    public static function getAppConfig(string $identifier): ?array
    {
        $configKey = "intranet-app-{$identifier}";
        $appConfig = config($configKey);

        if ($appConfig && isset($appConfig['roles'])) {
            return $appConfig;
        }

        return null;
    }
}
