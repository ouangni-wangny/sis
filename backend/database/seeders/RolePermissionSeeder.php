<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Pilotage
            'dashboard.view',
            'rapports.generate',
            'audit.view',

            // Référentiels / ops terrain
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            'zones.view', 'zones.create', 'zones.update', 'zones.delete', 'zones.manage',
            'sites.view', 'sites.create', 'sites.update', 'sites.delete',
            'postes.view', 'postes.manage',
            'checkpoints.view', 'checkpoints.manage',
            'grades.manage',

            // Agents / périmètre
            'agents.view', 'agents.create', 'agents.update', 'agents.delete',
            'perimetres.view', 'perimetres.manage',

            // Planning / terrain
            'vacations.view', 'vacations.create', 'vacations.update', 'vacations.delete',
            'rondes.view', 'rondes.manage',
            'controles.view', 'controles.create',
            'anomalies.view', 'anomalies.create', 'anomalies.update', 'anomalies.delete',

            // RH
            'contrats.manage',
            'contrats.view',
            'contrats.alerts',
            'absences.manage',
            'absences.view',
            'paie.manage',
            'paie.view',
            'documents.manage',
            'grades.manage',

            // Commercial
            'factures.view', 'factures.create', 'factures.update', 'factures.delete',
            'offres.view', 'offres.create', 'offres.update', 'offres.delete',
            'abonnements.view', 'abonnements.create', 'abonnements.update', 'abonnements.delete',
            'paiements.view', 'paiements.create', 'paiements.update', 'paiements.delete',

            // Trésorerie
            'tresorerie.view', 'tresorerie.manage',
            'depenses.view', 'depenses.manage',

            // Admin
            'users.view', 'users.create', 'users.update', 'users.delete',

            // Console développeur (exclusif)
            'system.features.manage',
            'system.menu.manage',
            'system.settings.manage',
            'system.roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $systemPermissions = [
            'system.features.manage',
            'system.menu.manage',
            'system.settings.manage',
            'system.roles.manage',
        ];

        $metierPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $systemPermissions)
            ->get();

        // Ancien rôle « superviseur » → « operation »
        $this->renameRole('superviseur', 'operation');

        $developpeur = Role::findOrCreate('developpeur', 'web');
        $developpeur->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions($metierPermissions);

        $operation = Role::findOrCreate('operation', 'web');
        $operation->syncPermissions([
            'dashboard.view',
            'rapports.generate',

            // Zones, sites, postes
            'zones.view', 'zones.create', 'zones.update', 'zones.delete', 'zones.manage',
            'sites.view', 'sites.create', 'sites.update', 'sites.delete',
            'postes.view', 'postes.manage',
            'checkpoints.view', 'checkpoints.manage',

            // Agents / rondiers (consultation + mise à jour, pas de création)
            'agents.view', 'agents.update',
            'perimetres.view', 'perimetres.manage',

            // Planning postes, contrôles, anomalies
            'vacations.view', 'vacations.create', 'vacations.update', 'vacations.delete',
            'rondes.view', 'rondes.manage',
            'controles.view', 'controles.create',
            'anomalies.view', 'anomalies.create', 'anomalies.update',
        ]);

        $rh = Role::findOrCreate('rh', 'web');
        $rh->syncPermissions([
            'dashboard.view',

            // Nouveau agent + consultation effectif
            'agents.view', 'agents.create', 'agents.update', 'agents.delete',

            // Contrats, alertes, absences, paie, documents
            'contrats.manage',
            'contrats.view',
            'contrats.alerts',
            'absences.manage',
            'absences.view',
            'paie.manage',
            'paie.view',
            'documents.manage',
            'grades.manage',

            // Lecture soldes pour règlement
            'tresorerie.view',

            // Consultation ops
            'controles.view',
            'anomalies.view',
            'rapports.generate',
        ]);

        $commercial = Role::findOrCreate('commercial', 'web');
        $commercial->syncPermissions([
            'dashboard.view',

            // Clients
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',

            // Factures (proforma + statut) / offres / abonnements / paiements
            'factures.view', 'factures.create', 'factures.update',
            'offres.view', 'offres.create', 'offres.update',
            'abonnements.view', 'abonnements.create', 'abonnements.update',
            'paiements.view', 'paiements.create', 'paiements.update',

            // Compte pour encaissement
            'tresorerie.view',
        ]);

        // Comptes mobile — agent posté
        $agent = Role::findOrCreate('agent', 'web');
        $agent->syncPermissions([
            'vacations.view',
            'anomalies.create', 'anomalies.view',
        ]);

        // Legacy : ancien rôle « rondier » → « controleur »
        $this->renameRole('rondier', 'controleur');

        // Comptes mobile — contrôleur (contrôles de présence)
        $controleur = Role::findOrCreate('controleur', 'web');
        $controleur->syncPermissions([
            'controles.create', 'controles.view',
            'anomalies.create', 'anomalies.view',
            'vacations.view',
            'sites.view',
            'postes.view',
            'agents.view',
        ]);

        // Personnel administratif (fiche RH, pas de terrain)
        $administration = Role::findOrCreate('administration', 'web');
        $administration->syncPermissions([
            'vacations.view',
        ]);
    }

    private function renameRole(string $from, string $to): void
    {
        $legacy = Role::query()->where('name', $from)->where('guard_name', 'web')->first();
        if (! $legacy) {
            return;
        }

        $target = Role::findOrCreate($to, 'web');

        foreach ($legacy->users()->get() as $user) {
            $user->removeRole($legacy);
            if (! $user->hasRole($target)) {
                $user->assignRole($target);
            }
        }

        $legacy->delete();
    }
}
