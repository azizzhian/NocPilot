<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Role yang default mendapat menu Gamas. */
    private const GAMAS_VIEW_ROLES = ['administrator', 'manager', 'noc', 'teknisi'];

    /** Role yang default mendapat widget Statistik Gamas. */
    private const WIDGET_ROLES = ['administrator', 'manager', 'noc'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->give('gamas.view', self::GAMAS_VIEW_ROLES);
        $this->give('dashboard.widget.gamas', self::WIDGET_ROLES);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->whereIn('name', ['gamas.view', 'dashboard.widget.gamas'])->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /** @param  array<int, string>  $roleNames */
    private function give(string $permissionName, array $roleNames): void
    {
        $permission = Permission::findOrCreate($permissionName, 'web');

        foreach ($roleNames as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }
};
