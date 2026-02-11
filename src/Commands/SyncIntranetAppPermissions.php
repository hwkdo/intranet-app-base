<?php

namespace Hwkdo\IntranetAppBase\Commands;

use Hwkdo\IntranetAppBase\Events\ExternalAppConfigSynced;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncIntranetAppPermissions extends Command
{
    protected $signature = 'intranet-app:sync-permissions {--all : Synchronisiere alle Apps} {--app= : Synchronisiere nur eine bestimmte App (Identifier)}';

    protected $description = 'Synchronisiert Permissions und Rollen für Intranet-Apps aus ihren Config-Dateien';

    public function handle(): int
    {
        $appIdentifier = $this->option('app');
        $all = $this->option('all');

        if ($appIdentifier && $all) {
            $this->error('Die Optionen --all und --app können nicht gleichzeitig verwendet werden.');

            return self::FAILURE;
        }

        if (! $appIdentifier && ! $all) {
            $this->error('Bitte verwenden Sie entweder --all oder --app={identifier}');

            return self::FAILURE;
        }

        $apps = $this->getAppsToSync($appIdentifier);

        if (empty($apps)) {
            $this->warn('Keine Apps gefunden.');

            return self::FAILURE;
        }

        foreach ($apps as $identifier => $appConfig) {
            $this->info("Synchronisiere App: {$identifier}");
            $this->syncAppPermissions($identifier, $appConfig);

            if (str_starts_with($identifier, 'external-')) {
                $configKey = substr($identifier, 9);
                ExternalAppConfigSynced::dispatch($configKey, $appConfig);
            }
        }

        $this->info('✅ Synchronisierung abgeschlossen!');

        return self::SUCCESS;
    }

    private function getAppsToSync(?string $appIdentifier): array
    {
        if ($appIdentifier) {
            $externalApps = config('external_apps.apps', []);
            if (isset($externalApps[$appIdentifier])) {
                return [$appIdentifier => $externalApps[$appIdentifier]];
            }

            $appConfig = IntranetAppBase::getAppConfig($appIdentifier);

            if (! $appConfig) {
                $this->error("Config für App '{$appIdentifier}' nicht gefunden.");

                return [];
            }

            return [$appIdentifier => $appConfig];
        }

        $intranetApps = IntranetAppBase::getAppsWithConfigs();
        $externalApps = config('external_apps.apps', []);
        $externalWithPrefix = [];
        foreach ($externalApps as $key => $config) {
            if (isset($config['roles'])) {
                $externalWithPrefix['external-'.$key] = $config;
            }
        }

        return array_merge($intranetApps, $externalWithPrefix);
    }

    private function syncAppPermissions(string $identifier, array $appConfig): void
    {
        $permissions = IntranetAppBase::getRequiredPermissionsFromAppConfig($appConfig);
        $roles = IntranetAppBase::getRolesWithPermissionsFromAppConfig($appConfig);

        $this->syncPermissions($identifier, $permissions);
        $this->syncRoles($identifier, $roles);
        $this->syncAllUsersRoles($roles);
    }

    private function syncPermissions(string $identifier, array $requiredPermissions): void
    {
        $this->line('  → Synchronisiere Permissions...');

        $existingPermissions = Permission::whereIn('name', $requiredPermissions)
            ->pluck('name')
            ->toArray();

        foreach ($requiredPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            if (! in_array($permission, $existingPermissions)) {
                $this->line("    ✓ Permission erstellt: {$permission}");
            }
        }

        // Entferne Permissions die nicht mehr benötigt werden
        if (str_starts_with($identifier, 'external-')) {
            $shortId = substr($identifier, 9);
            $appPermissions = Permission::where('name', 'like', 'see-external-app-'.$shortId.'%')
                ->pluck('name')
                ->toArray();
        } else {
            $appPermissions = Permission::where('name', 'like', "%-app-{$identifier}%")
                ->orWhere('name', 'like', "app-{$identifier}%")
                ->pluck('name')
                ->toArray();
        }

        $permissionsToRemove = array_diff($appPermissions, $requiredPermissions);

        foreach ($permissionsToRemove as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $permission->delete();
                $this->line("    ✗ Permission entfernt: {$permissionName}");
            }
        }
    }

    private function syncRoles(string $identifier, array $roles): void
    {
        $this->line('  → Synchronisiere Rollen...');

        $requiredRoleNames = collect($roles)->pluck('name')->toArray();

        foreach ($roles as $role) {
            $roleModel = Role::findOrCreate($role['name'], 'web');

            if (! empty($role['add_to_existing'])) {
                foreach ($role['permissions'] as $permissionName) {
                    if (! $roleModel->hasPermissionTo($permissionName)) {
                        $roleModel->givePermissionTo($permissionName);
                        $this->line("    ✓ Permission '{$permissionName}' zu Rolle '{$role['name']}' hinzugefügt");
                    }
                }
            } else {
                $roleModel->syncPermissions($role['permissions']);
                $this->line("    ✓ Rolle synchronisiert: {$role['name']}");
            }
        }

        // Entferne Rollen die nicht mehr benötigt werden
        if (str_starts_with($identifier, 'external-')) {
            $shortId = substr($identifier, 9);
            $appRoles = Role::where('name', 'like', '%External-'.ucfirst(str_replace('-', '', $shortId)).'%')
                ->orWhere('name', 'like', '%external-'.$shortId.'%')
                ->pluck('name')
                ->toArray();
        } else {
            $appRoles = Role::where('name', 'like', "%{$identifier}%")
                ->orWhere('name', 'like', "App-{$identifier}%")
                ->pluck('name')
                ->toArray();
        }

        $rolesToRemove = array_diff($appRoles, $requiredRoleNames);

        foreach ($rolesToRemove as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->delete();
                $this->line("    ✗ Rolle entfernt: {$roleName}");
            }
        }
    }

    private function syncAllUsersRoles(array $roles): void
    {
        $allUsersRoles = collect($roles)->filter(function ($role) {
            return isset($role['all_users']) && $role['all_users'] === true;
        });

        if ($allUsersRoles->isEmpty()) {
            return;
        }

        $this->line('  → Synchronisiere "all_users" Rollen...');

        // Hole alle aktiven User
        $users = \App\Models\User::aktiv()->get();

        foreach ($allUsersRoles as $roleData) {
            $role = Role::findByName($roleData['name'], 'web');
            
            if (! $role) {
                continue;
            }

            $usersWithoutRole = $users->filter(function ($user) use ($role) {
                return ! $user->hasRole($role);
            });

            foreach ($usersWithoutRole as $user) {
                $user->assignRole($role);
            }

            if ($usersWithoutRole->count() > 0) {
                $this->line("    ✓ Rolle '{$roleData['name']}' zu {$usersWithoutRole->count()} User(n) hinzugefügt");
            }
        }
    }
}
