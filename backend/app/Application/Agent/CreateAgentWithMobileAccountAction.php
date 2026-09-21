<?php

namespace App\Application\Agent;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\Agent;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateAgentWithMobileAccountAction
{
    public function __construct(
        private readonly GenerateAgentMatriculeAction $generateMatricule,
    ) {}

    public function execute(array $data): Agent
    {
        return DB::transaction(function () use ($data) {
            $grade = Grade::query()->findOrFail($data['grade_id']);
            $pin = $data['pin'] ?? (string) random_int(1000, 9999);
            $email = $data['email'] ?? null;
            unset($data['pin'], $data['email'], $data['contrat'], $data['matricule']);

            $matricule = $this->generateMatricule->execute();

            $user = User::query()->create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'name' => trim($data['prenom'].' '.$data['nom']),
                'matricule' => $matricule,
                'pin_hash' => Hash::make($pin),
                'type' => TypeUser::Mobile,
                'statut' => StatutUser::Actif,
                'email' => $email,
                'password' => null,
            ]);

            $role = $grade->type_agent->value;
            $user->assignRole($role);

            $agent = Agent::query()->create([
                ...$data,
                'matricule' => $matricule,
                'user_id' => $user->id,
                'type' => $grade->type_agent->value,
                'statut' => $data['statut'] ?? 'disponible',
            ]);

            $agent->setAttribute('plain_pin', $pin);
            $agent->load(['grade', 'villeRef', 'user', 'media', 'contratActif', 'posteSiege.site']);

            return $agent;
        });
    }
}
