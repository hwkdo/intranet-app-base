<?php

namespace Hwkdo\IntranetAppBase;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;

class IntranetAppBase
{
    public static function packageNameForIdentifier(string $identifier): string
    {
        return "hwkdo/intranet-app-{$identifier}";
    }

    /**
     * @param  array{homepage?: ?string, support?: ?array<string, string>}  $packageData
     * @return array{owner: string, repo: string}|null
     */
    public static function parseGithubRepositoryFromPackageData(array $packageData): ?array
    {
        $candidates = [];

        if (isset($packageData['homepage']) && is_string($packageData['homepage'])) {
            $candidates[] = $packageData['homepage'];
        }

        $support = $packageData['support'] ?? null;

        if (is_array($support)) {
            foreach (['source', 'issues', 'docs'] as $key) {
                if (isset($support[$key]) && is_string($support[$key])) {
                    $candidates[] = $support[$key];
                }
            }
        }

        foreach ($candidates as $url) {
            $repository = self::parseGithubRepositoryUrl($url);

            if ($repository !== null) {
                return $repository;
            }
        }

        $packageName = $packageData['name'] ?? null;

        if (is_string($packageName) && str_starts_with($packageName, 'hwkdo/intranet-app-')) {
            $slug = str($packageName)->after('hwkdo/')->toString();

            return [
                'owner' => 'hwkdo',
                'repo' => $slug,
            ];
        }

        return null;
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    public static function parseGithubRepositoryUrl(string $url): ?array
    {
        if (! preg_match('#github\.com/([^/]+)/([^/]+)#i', $url, $matches)) {
            return null;
        }

        $repo = rtrim($matches[2], '.git');

        return [
            'owner' => $matches[1],
            'repo' => $repo,
        ];
    }

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
                        'permissions' => $role['permissions'],
                        'all_users' => $role['all_users'] ?? false,
                        'add_to_existing' => $role['add_to_existing'] ?? false,
                    ];
                }

                // Unterrollen durchgehen (wie "others")
                if (is_array($role)) {
                    collect($role)->each(function ($subRole, $subRoleKey) use (&$roles, $roleKey) {
                        if (isset($subRole['name']) && isset($subRole['permissions'])) {
                            $roles[$roleKey . '.' . $subRoleKey] = [
                                'name' => $subRole['name'],
                                'permissions' => $subRole['permissions'],
                                'all_users' => $subRole['all_users'] ?? false,
                                'add_to_existing' => $subRole['add_to_existing'] ?? false,
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

    /**
     * Lesbarer App-Name für Haupt-Dashboard-Sektionen (z. B. Widget-Menü).
     * Fällt auf den Identifier zurück, wenn keine passende App geladen werden kann.
     */
    public static function displayNameForAppIdentifier(string $identifier): string
    {
        foreach (self::getIntranetAppPackages() as $packageName => $packageData) {
            $appClass = self::getAppClass($packageName, $packageData);

            if ($appClass === null || ! class_exists($appClass)) {
                continue;
            }

            if (! is_subclass_of($appClass, IntranetAppInterface::class)) {
                continue;
            }

            if ($appClass::identifier() === $identifier) {
                return $appClass::app_name();
            }
        }

        return $identifier;
    }
}
