<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}
    public function index(): JsonResponse
    {
        $catalog = $this->catalog();

        $roles = Role::with('permissions')->orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'label' => ucfirst($role->name),
            'permissions' => $role->permissions->pluck('name')->values(),
        ]);

        return response()->json([
            'roles' => $roles,
            'permissions' => $catalog['all'],
            'groups' => $catalog['groups'],
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $catalog = $this->catalog();
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in($catalog['all'])],
        ]);

        if ($role->name === 'administrator') {
            $required = ['role.manage', 'user.manage', 'dashboard.view'];
            $missing = array_diff($required, $data['permissions']);
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'permissions' => ['Role administrator wajib tetap punya: '.implode(', ', $required).'.'],
                ]);
            }
        }

        $role->syncPermissions($data['permissions']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role->load('permissions');
        $this->activity->log(
            'role',
            "Update permission role {$role->name} (".$role->permissions->count().' hak akses)',
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Permission role berhasil diperbarui.',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => ucfirst($role->name),
                'permissions' => $role->permissions->pluck('name')->values(),
            ],
        ]);
    }

    /** @return array{all: list<string>, groups: list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>} */
    protected function catalog(): array
    {
        $groupsConfig = config('permissions.groups', []);
        $all = [];
        $groups = [];

        foreach ($groupsConfig as $group) {
            $items = [];
            foreach ($group['permissions'] ?? [] as $name => $label) {
                $all[] = $name;
                $items[] = ['name' => $name, 'label' => $label];
            }
            $groups[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'permissions' => $items,
            ];
        }

        // Ensure DB has any missing catalog permissions
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        return ['all' => $all, 'groups' => $groups];
    }
}
