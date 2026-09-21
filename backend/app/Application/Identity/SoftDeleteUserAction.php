<?php

namespace App\Application\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SoftDeleteUserAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
