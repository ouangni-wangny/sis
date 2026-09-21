<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Identity\SoftDeleteUserAction;
use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->where('type', TypeUser::Backoffice)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('nom', 'like', $term)
                        ->orWhere('prenom', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $role = $data['role'] ?? 'operation';
        unset($data['role']);

        $user = User::query()->create([
            ...$data,
            'name' => trim(($data['prenom'] ?? '').' '.($data['nom'] ?? '')),
            'password' => Hash::make($data['password']),
            'type' => TypeUser::Backoffice,
            'statut' => $data['statut'] ?? StatutUser::Actif->value,
        ]);
        $user->assignRole($role);

        return new UserResource($user->load('roles'));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['roles', 'permissions']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }
        $user->update($data);

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user, SoftDeleteUserAction $action): Response
    {
        $this->authorize('delete', $user);

        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        $isSuperAdmin = $user->hasRole('super-admin');
        if ($isSuperAdmin) {
            $remainingAdmins = User::query()
                ->role('super-admin')
                ->where('id', '!=', $user->id)
                ->count();

            if ($remainingAdmins < 1) {
                throw ValidationException::withMessages([
                    'user' => 'Impossible de supprimer le dernier super-admin.',
                ]);
            }
        }

        if ($user->hasRole('developpeur')) {
            $remainingDevs = User::query()
                ->role('developpeur')
                ->where('id', '!=', $user->id)
                ->count();

            if ($remainingDevs < 1) {
                throw ValidationException::withMessages([
                    'user' => 'Impossible de supprimer le dernier développeur.',
                ]);
            }
        }

        $action->execute($user);

        return response()->noContent();
    }
}