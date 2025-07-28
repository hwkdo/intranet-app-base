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
}
