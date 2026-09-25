<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Rôles seedés référencés en dur ailleurs dans le code (UserController,
     * StoreUserRequest…) : ni renommables, ni supprimables depuis la console.
     */
    private const PROTECTED_ROLES = [
        'super-admin',
        'developpeur',
        'operation',
        'rh',
        'commercial',
        'comptable',
        'agent',
        'controleur',
        'administration',
    ];

    public function permissions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.roles.manage'), 403);

        $query = Permission::query()->where('guard_name', 'web');

        // Les permissions system.* (hors gestion des rôles côté admin) restent
        // réservées à la console développeur.
        if (! $this->isDeveloppeur($request)) {
            $query->where('name', 'not like', 'system.%');
        }

        $permissions = $query
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission) {
                $meta = PermissionCatalog::describe($permission->name);

                return [
                    'name' => $permission->name,
                    'label' => $meta['label'],
                    'group' => $meta['group'],
                ];
            })
            ->sortBy([
                fn ($a, $b) => PermissionCatalog::groupRank($a['group']) <=> PermissionCatalog::groupRank($b['group']),
                fn ($a, $b) => $a['label'] <=> $b['label'],
            ])
            ->values();

        return response()->json(['data' => $permissions]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.roles.manage'), 403);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->present($role));

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('system.roles.manage'), 403);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ], [
            'name.regex' => 'Le nom du rôle doit être en minuscules, avec des tirets (ex : chef-projet).',
        ]);

        $permissions = $this->sanitizePermissions($request, $data['permissions'] ?? []);

        $role = DB::transaction(function () use ($data, $permissions) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            return $role;
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'data' => $this->present($role->load('permissions:id,name')),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()?->can('system.roles.manage'), 403);
        $this->ensureGuardMatches($role);
        $this->ensureCanEditRole($request, $role);

        $protected = in_array($role->name, self::PROTECTED_ROLES, true);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ], [
            'name.regex' => 'Le nom du rôle doit être en minuscules, avec des tirets (ex : chef-projet).',
        ]);

        if ($protected && isset($data['name']) && $data['name'] !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'Ce rôle est intégré au système et ne peut pas être renommé.',
            ]);
        }

        DB::transaction(function () use ($request, $role, $data) {
            if (isset($data['name'])) {
                $role->update(['name' => $data['name']]);
            }
            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions(
                    $this->sanitizePermissions($request, $data['permissions'], $role)
                );
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'data' => $this->present($role->fresh()->load('permissions:id,name')),
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()?->can('system.roles.manage'), 403);
        $this->ensureGuardMatches($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Ce rôle est intégré au système et ne peut pas être supprimé.',
            ]);
        }

        if ($role->users()->count() > 0) {
            throw ValidationException::withMessages([
                'role' => 'Ce rôle est encore assigné à des utilisateurs : réassignez-les avant suppression.',
            ]);
        }

        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(null, 204);
    }

    private function ensureGuardMatches(Role $role): void
    {
        abort_unless($role->guard_name === 'web', 404);
    }

    private function isDeveloppeur(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('developpeur');
    }

    private function ensureCanEditRole(Request $request, Role $role): void
    {
        if ($this->isDeveloppeur($request)) {
            return;
        }

        if ($role->name === 'developpeur') {
            throw ValidationException::withMessages([
                'role' => 'Seul un développeur peut modifier le rôle développeur.',
            ]);
        }
    }

    /**
     * Les non-développeurs ne peuvent ni assigner ni retirer les permissions system.*.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function sanitizePermissions(Request $request, array $permissions, ?Role $existing = null): array
    {
        if ($this->isDeveloppeur($request)) {
            return array_values($permissions);
        }

        $systemAttempt = array_values(array_filter(
            $permissions,
            fn (string $p) => str_starts_with($p, 'system.')
        ));

        if ($systemAttempt !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'Les permissions système (system.*) sont réservées au développeur.',
            ]);
        }

        $kept = array_values(array_filter(
            $permissions,
            fn (string $p) => ! str_starts_with($p, 'system.')
        ));

        // Préserver les system.* déjà présentes (ex. system.roles.manage sur super-admin)
        // quand l’admin synchronise sans les voir dans le catalogue filtré.
        if ($existing) {
            $existingSystem = $existing->permissions
                ->pluck('name')
                ->filter(fn (string $p) => str_starts_with($p, 'system.'))
                ->values()
                ->all();

            $kept = array_values(array_unique([...$kept, ...$existingSystem]));
        }

        return $kept;
    }

    /** @return array<string, mixed> */
    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'protected' => in_array($role->name, self::PROTECTED_ROLES, true),
            'users_count' => $role->users()->count(),
            'permissions' => $role->permissions->pluck('name')->values(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }
}
