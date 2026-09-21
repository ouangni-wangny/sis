<?php

namespace App\Application\Agent;

use App\Models\Agent;
use App\Models\RondierPerimetre;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SoftDeleteAgentAction
{
    public function execute(Agent $agent): void
    {
        DB::transaction(function () use ($agent) {
            RondierPerimetre::query()
                ->where('agent_id', $agent->id)
                ->delete();

            if ($agent->user_id) {
                $user = User::query()->find($agent->user_id);
                if ($user) {
                    $user->tokens()->delete();
                    $user->delete();
                }
            }

            $agent->delete();
        });
    }
}
