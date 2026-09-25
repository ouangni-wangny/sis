<?php

namespace App\Support;

/**
 * Métadonnées d'affichage (libellé, groupe) pour les permissions Spatie.
 * Source de vérité pour la console développeur (onglets Permissions / Rôles).
 */
final class PermissionCatalog
{
    /** @var array<string, string> Libellé FR par ressource (préfixe avant le premier point). */
    private const RESOURCE_LABELS = [
        'dashboard' => 'Tableau de bord',
        'rapports' => 'Rapports',
        'audit' => 'Journal d’audit',
        'clients' => 'Clients',
        'zones' => 'Zones',
        'sites' => 'Sites',
        'postes' => 'Postes',
        'checkpoints' => 'Checkpoints',
        'grades' => 'Grades',
        'agents' => 'Agents',
        'perimetres' => 'Périmètres',
        'vacations' => 'Vacations',
        'rondes' => 'Rondes',
        'controles' => 'Contrôles',
        'anomalies' => 'Anomalies',
        'contrats' => 'Contrats',
        'absences' => 'Absences',
        'paie' => 'Paie',
        'documents' => 'Documents RH',
        'offres' => 'Offres',
        'abonnements' => 'Abonnements',
        'factures' => 'Factures',
        'paiements' => 'Paiements',
        'tresorerie' => 'Trésorerie',
        'depenses' => 'Dépenses',
        'users' => 'Utilisateurs',
    ];

    /** @var array<string, string> Libellé FR pour les sous-ressources system.* (2e segment). */
    private const SYSTEM_RESOURCE_LABELS = [
        'features' => 'Modules',
        'menu' => 'Menus',
        'settings' => 'Paramètres système',
        'roles' => 'Rôles & permissions',
    ];

    /** @var array<string, string> Libellé FR par action (suffixe). */
    private const ACTION_LABELS = [
        'view' => 'Consulter',
        'create' => 'Créer',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
        'manage' => 'Gérer',
        'generate' => 'Générer',
        'alerts' => 'Alertes',
    ];

    /** @var array<string, string> Groupe FR par ressource. */
    private const RESOURCE_GROUPS = [
        'dashboard' => 'Pilotage',
        'rapports' => 'Pilotage',
        'audit' => 'Administration',
        'clients' => 'Commercial',
        'zones' => 'Opérations',
        'sites' => 'Opérations',
        'postes' => 'Opérations',
        'checkpoints' => 'Opérations',
        'grades' => 'Administration',
        'agents' => 'Opérations',
        'perimetres' => 'Opérations',
        'vacations' => 'Opérations',
        'rondes' => 'Opérations',
        'controles' => 'Opérations',
        'anomalies' => 'Opérations',
        'contrats' => 'RH',
        'absences' => 'RH',
        'paie' => 'RH',
        'documents' => 'RH',
        'offres' => 'Commercial',
        'abonnements' => 'Commercial',
        'factures' => 'Commercial',
        'paiements' => 'Commercial',
        'tresorerie' => 'Trésorerie',
        'depenses' => 'Trésorerie',
        'users' => 'Administration',
    ];

    /** Ordre d'affichage des groupes dans la console développeur. */
    public const GROUP_ORDER = [
        'Pilotage',
        'Opérations',
        'RH',
        'Commercial',
        'Trésorerie',
        'Administration',
        'Développeur',
    ];

    /** @return array{label: string, group: string} */
    public static function describe(string $permission): array
    {
        $segments = explode('.', $permission);

        if ($segments[0] === 'system') {
            $sub = $segments[1] ?? '';
            $action = $segments[2] ?? 'manage';
            $resourceLabel = self::SYSTEM_RESOURCE_LABELS[$sub] ?? ucfirst($sub);
            $actionLabel = self::ACTION_LABELS[$action] ?? ucfirst($action);

            return [
                'label' => "{$actionLabel} · {$resourceLabel}",
                'group' => 'Développeur',
            ];
        }

        $resource = $segments[0];
        $action = $segments[1] ?? 'manage';
        $resourceLabel = self::RESOURCE_LABELS[$resource] ?? ucfirst($resource);
        $actionLabel = self::ACTION_LABELS[$action] ?? ucfirst($action);

        return [
            'label' => "{$resourceLabel} · {$actionLabel}",
            'group' => self::RESOURCE_GROUPS[$resource] ?? 'Autre',
        ];
    }

    public static function groupRank(string $group): int
    {
        $index = array_search($group, self::GROUP_ORDER, true);

        return $index === false ? count(self::GROUP_ORDER) : $index;
    }
}
