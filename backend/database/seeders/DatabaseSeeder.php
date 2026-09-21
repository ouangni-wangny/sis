<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $backofficeUsers = [
            [
                'email' => 'developpeur@sis.ci',
                'nom' => 'Développeur',
                'prenom' => 'SIS',
                'role' => 'developpeur',
            ],
            [
                'email' => 'admin@sis.ci',
                'nom' => 'Admin',
                'prenom' => 'SIS',
                'role' => 'super-admin',
            ],
            [
                'email' => 'operation@sis.ci',
                'nom' => 'Operation',
                'prenom' => 'SIS',
                'role' => 'operation',
            ],
            [
                'email' => 'rh@sis.ci',
                'nom' => 'RH',
                'prenom' => 'SIS',
                'role' => 'rh',
            ],
            [
                'email' => 'commercial@sis.ci',
                'nom' => 'Commercial',
                'prenom' => 'SIS',
                'role' => 'commercial',
            ],
        ];

        $seedDeveloper = (bool) config('sis.developer.seed');
        $developerPassword = (string) config('sis.developer.password');

        foreach ($backofficeUsers as $row) {
            if ($row['role'] === 'developpeur' && ! $seedDeveloper) {
                continue;
            }

            $password = $row['role'] === 'developpeur'
                ? $developerPassword
                : 'password';

            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'nom' => $row['nom'],
                    'prenom' => $row['prenom'],
                    'name' => trim($row['prenom'].' '.$row['nom']),
                    'password' => Hash::make($password),
                    'type' => TypeUser::Backoffice,
                    'statut' => StatutUser::Actif,
                ]
            );
            $user->syncRoles([$row['role']]);
        }

        Grade::query()->firstOrCreate(
            ['libelle' => 'Agent de sécurité'],
            ['type_agent' => TypeAgent::Agent, 'description' => 'Agent posté']
        );

        Grade::query()->firstOrCreate(
            ['libelle' => 'Contrôleur'],
            ['type_agent' => TypeAgent::Controleur, 'description' => 'Contrôleur terrain / contrôles']
        );

        Grade::query()->firstOrCreate(
            ['libelle' => 'Administration'],
            ['type_agent' => TypeAgent::Administration, 'description' => 'Personnel administratif']
        );

        // Nettoyage des grades démo retirés (réaffectation éventuelle).
        $gradeAgent = Grade::query()->where('libelle', 'Agent de sécurité')->first();
        $gradeControleur = Grade::query()->where('libelle', 'Contrôleur')->first();
        if ($gradeAgent && $gradeControleur) {
            foreach (Grade::query()->whereIn('libelle', ['Chef d’équipe', 'Superviseur terrain'])->get() as $grade) {
                \App\Models\Agent::query()
                    ->where('grade_id', $grade->id)
                    ->update([
                        'grade_id' => $grade->type_agent === TypeAgent::Controleur
                            ? $gradeControleur->id
                            : $gradeAgent->id,
                    ]);
                $grade->delete();
            }
        }
    }
}
