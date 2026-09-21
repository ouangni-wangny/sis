<?php

namespace App\Support;

final class AuditLabels
{
    /** @var array<string, string> */
    public const ACTIONS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'restored' => 'Restauration',
    ];

    /** @var array<string, string> */
    public const ENTITIES = [
        'Agent' => 'Personnel',
        'Contrat' => 'Contrat',
        'Absence' => 'Absence',
        'User' => 'Compte utilisateur',
        'Vacation' => 'Vacation / planning',
        'Zone' => 'Zone',
        'Site' => 'Site',
        'Poste' => 'Poste',
        'RondierPerimetre' => 'Périmètre contrôleur',
        'Client' => 'Client',
        'Offre' => 'Offre',
        'Abonnement' => 'Abonnement',
        'Facture' => 'Facture',
        'Paiement' => 'Paiement',
        'Controle' => 'Contrôle',
        'Anomalie' => 'Anomalie',
        'Ronde' => 'Ronde',
        'Grade' => 'Grade',
        'Ville' => 'Ville',
        'FeatureFlag' => 'Module / fonctionnalité',
        'SystemSetting' => 'Paramètre système',
        'MenuOverride' => 'Menu',
    ];

    /** @var array<string, string> */
    public const FIELDS = [
        'statut' => 'Statut',
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'matricule' => 'Matricule',
        'email' => 'E-mail',
        'telephone' => 'Téléphone',
        'type' => 'Type',
        'reference' => 'Référence',
        'date_debut' => 'Date de début',
        'date_fin' => 'Date de fin',
        'date_embauche' => 'Date d’embauche',
        'periode_essai_mois' => 'Période d’essai (mois)',
        'salaire_base' => 'Salaire de base',
        'salaire_brut' => 'Salaire brut',
        'salaire_net' => 'Salaire net',
        'grade_id' => 'Grade',
        'ville_id' => 'Ville',
        'agent_id' => 'Personnel',
        'client_id' => 'Client',
        'site_id' => 'Site',
        'zone_id' => 'Zone',
        'poste_id' => 'Poste',
        'offre_id' => 'Offre',
        'libelle' => 'Libellé',
        'description' => 'Description',
        'adresse' => 'Adresse',
        'responsable' => 'Responsable',
        'motif' => 'Motif',
        'montant' => 'Montant',
        'numero' => 'Numéro',
        'raison_sociale' => 'Raison sociale',
        'pool_siege' => 'Pool siège',
        'poste_siege_id' => 'Poste siège',
        'jour_repos' => 'Jour de repos',
        'solde_conges_jours' => 'Solde de congés',
        'conges_acquis_annuel' => 'Congés acquis annuels',
        'agents_requis' => 'Agents requis',
        'heure_debut' => 'Heure de début',
        'heure_fin' => 'Heure de fin',
        'heure_debut_nuit' => 'Heure début nuit',
        'heure_fin_nuit' => 'Heure fin nuit',
        'mode_effectif' => 'Mode d’effectif',
        'interne' => 'Site interne',
        'tarif_mensuel' => 'Tarif mensuel',
        'prix_mensuel' => 'Prix mensuel',
        'actif' => 'Actif',
        'periodicite' => 'Périodicité',
        'designation' => 'Désignation',
        'prochaine_facture_le' => 'Prochaine facture',
        'deleted_at' => 'Supprimé le',
        'created_at' => 'Créé le',
        'updated_at' => 'Mis à jour le',
    ];

    /** @var array<string, string> */
    public const VALUES = [
        'disponible' => 'Disponible',
        'en_activite' => 'En activité',
        'conge' => 'Congé',
        'malade' => 'Malade',
        'suspendu' => 'Suspendu',
        'archive' => 'Archivé',
        'actif' => 'Actif',
        'termine' => 'Terminé',
        'resilie' => 'Résilié',
        'en_attente' => 'En attente',
        'approuvee' => 'Approuvée',
        'refusee' => 'Refusée',
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
        'a_recouvrir' => 'À recouvrir',
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'stage' => 'Stage',
        'prestation' => 'Prestation',
        'agent' => 'Agent posté',
        'controleur' => 'Contrôleur',
        'administration' => 'Administration',
    ];

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function entity(?string $type): string
    {
        if (! $type) {
            return 'Élément';
        }

        $base = class_basename($type);

        return self::ENTITIES[$base] ?? $base;
    }

    public static function field(string $field): string
    {
        return self::FIELDS[$field] ?? str_replace('_', ' ', $field);
    }

    public static function value(mixed $value, ?string $field = null): string
    {
        if ($field !== null) {
            return AuditValueResolver::label($field, $value);
        }

        if ($value === null || $value === '') {
            return 'vide';
        }
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        $str = (string) $value;

        return self::VALUES[$str] ?? $str;
    }
}
