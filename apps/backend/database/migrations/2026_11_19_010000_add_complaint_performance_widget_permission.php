<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Role yang default mendapat widget Performa Komplain. */
    private const DEFAULT_ROLES = ['administrator', 'manager', 'noc'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate('dashboard.widget.complaint_performance', 'web');

        foreach (self::DEFAULT_ROLES as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->where('name', 'dashboard.widget.complaint_performance')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
