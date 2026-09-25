<?php

namespace App\Support;

/**
 * Catalogue canonique des feature flags + liens menu.
 * Source de vérité pour seed, sync artisan et console développeur.
 */
final class FeatureFlagCatalog
{
    /**
     * @return list<array{key: string, label: string, description: string, group: string, critical?: bool}>
     */
    public static function flags(): array
    {
        return [
            ['key' => 'module.dashboard', 'label' => 'Tableau de bord', 'description' => 'Pilotage / dashboard', 'group' => 'Pilotage'],
            ['key' => 'module.rapports', 'label' => 'Reporting', 'description' => 'Génération de rapports', 'group' => 'Pilotage'],
            ['key' => 'module.zones', 'label' => 'Zones', 'description' => 'Gestion des zones', 'group' => 'Opérations'],
            ['key' => 'module.sites', 'label' => 'Sites', 'description' => 'Sites et postes', 'group' => 'Opérations'],
            ['key' => 'module.agents', 'label' => 'Personnel', 'description' => 'Agents et fiches RH', 'group' => 'Opérations'],
            ['key' => 'module.vacations', 'label' => 'Planning postes', 'description' => 'Vacations / planning postes', 'group' => 'Opérations'],
            ['key' => 'module.planning_controleurs', 'label' => 'Planning contrôleurs', 'description' => 'Affectation contrôleurs / relève', 'group' => 'Opérations'],
            ['key' => 'module.controles', 'label' => 'Contrôles', 'description' => 'Contrôles de présence terrain', 'group' => 'Opérations'],
            ['key' => 'module.controles_siege', 'label' => 'Contrôle siège', 'description' => 'Contrôles Opération sur sites internes', 'group' => 'Opérations'],
            ['key' => 'module.anomalies', 'label' => 'Anomalies', 'description' => 'Signalements et anomalies', 'group' => 'Opérations'],
            ['key' => 'module.rh', 'label' => 'RH (contrats & absences)', 'description' => 'Contrats et absences', 'group' => 'RH'],
            ['key' => 'module.paie', 'label' => 'Paie', 'description' => 'Périodes et bulletins de paie', 'group' => 'RH', 'critical' => true],
            ['key' => 'module.clients', 'label' => 'Clients', 'description' => 'Fichier clients', 'group' => 'Commercial'],
            ['key' => 'module.offres', 'label' => 'Offres', 'description' => 'Catalogue offres', 'group' => 'Commercial'],
            ['key' => 'module.abonnements', 'label' => 'Abonnements', 'description' => 'Contrats d’abonnement', 'group' => 'Commercial'],
            ['key' => 'module.factures', 'label' => 'Factures', 'description' => 'Proformas et factures', 'group' => 'Commercial', 'critical' => true],
            ['key' => 'module.paiements', 'label' => 'Paiements', 'description' => 'Encaissements', 'group' => 'Commercial'],
            ['key' => 'module.tresorerie', 'label' => 'Trésorerie', 'description' => 'Comptes, mouvements, dépenses', 'group' => 'Trésorerie'],
            ['key' => 'module.users', 'label' => 'Utilisateurs', 'description' => 'Comptes back-office', 'group' => 'Administration', 'critical' => true],
            ['key' => 'module.audit', 'label' => 'Journal d’audit', 'description' => 'Traçabilité', 'group' => 'Administration'],
            ['key' => 'module.parametres', 'label' => 'Paramètres', 'description' => 'Grades, villes et référentiels', 'group' => 'Administration'],
        ];
    }

    /**
     * Liens nav_key → feature flag (aligné sur frontend navigation.ts).
     *
     * @return array<string, string>
     */
    public static function navFeatureMap(): array
    {
        return [
            'pilotage.dashboard' => 'module.dashboard',
            'pilotage.rapports' => 'module.rapports',
            'ops.zones' => 'module.zones',
            'ops.sites' => 'module.sites',
            'ops.postes' => 'module.sites',
            'ops.agents' => 'module.agents',
            'ops.perimetres' => 'module.planning_controleurs',
            'ops.vacations' => 'module.vacations',
            'ops.planning_controleurs' => 'module.planning_controleurs',
            'ops.controles' => 'module.controles',
            'ops.anomalies' => 'module.anomalies',
            'rh.contrats' => 'module.rh',
            'rh.paie' => 'module.paie',
            'com.clients' => 'module.clients',
            'com.offres' => 'module.offres',
            'com.abonnements' => 'module.abonnements',
            'com.factures' => 'module.factures',
            'com.paiements' => 'module.paiements',
            'treso.dashboard' => 'module.tresorerie',
            'treso.depenses' => 'module.tresorerie',
            'admin.users' => 'module.users',
            'admin.audit' => 'module.audit',
            'admin.parametres' => 'module.parametres',
        ];
    }

    /** @return list<string> */
    public static function navKeysForFeature(string $featureKey): array
    {
        $keys = [];
        foreach (self::navFeatureMap() as $navKey => $feature) {
            if ($feature === $featureKey) {
                $keys[] = $navKey;
            }
        }

        return $keys;
    }

    public static function groupFor(string $featureKey): ?string
    {
        foreach (self::flags() as $flag) {
            if ($flag['key'] === $featureKey) {
                return $flag['group'];
            }
        }

        return null;
    }

    public static function isCritical(string $featureKey): bool
    {
        foreach (self::flags() as $flag) {
            if ($flag['key'] === $featureKey) {
                return (bool) ($flag['critical'] ?? false);
            }
        }

        return false;
    }
}
