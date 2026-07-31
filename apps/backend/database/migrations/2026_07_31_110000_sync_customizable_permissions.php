<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $groups = config('permissions.groups', []);
        $allNames = [];
        foreach ($groups as $group) {
            foreach (array_keys($group['permissions'] ?? []) as $name) {
                $allNames[] = $name;
                Permission::findOrCreate($name, 'web');
            }
        }

        $defaults = config('permissions.role_defaults', []);
        foreach ($defaults as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            if (in_array('*', $perms, true)) {
                $role->syncPermissions($allNames);
            } else {
                $valid = array_values(array_intersect($perms, $allNames));
                $role->syncPermissions($valid);
            }
        }

        // Drop obsolete coarse permissions that are no longer in catalog
        Permission::query()
            ->whereNotIn('name', $allNames)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally empty — permission catalog is additive for app behavior.
    }
};
