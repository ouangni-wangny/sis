<?php

namespace App\Policies;

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('agents.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Agent $model): bool
    {
        if ($user->hasRole('operation') && $model->type === TypeAgent::Administration) {
            return false;
        }

        return $user->can('agents.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('agents.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Agent $model): bool
    {
        return $user->can('agents.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Agent $model): bool
    {
        return $user->can('agents.delete') || $user->hasRole('super-admin');
    }
}
