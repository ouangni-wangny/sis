<?php

return [
    /*
    | false = on peut valider hors rayon GPS (utile en local / démo).
    | true  = le rondier doit être géolocalisé dans le rayon du site.
    */
    'controle_enforce_site_radius' => filter_var(
        env('SIS_CONTROLE_ENFORCE_SITE_RADIUS', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'controle' => [
        /** Délai minimum avant de pouvoir recontrôler le même agent posté
         *  (au lieu d'un verrou "une fois par jour" qui empêchait toute
         *  vérification de suivi après une absence constatée). */
        'recheck_min_minutes' => (int) env('SIS_CONTROLE_RECHECK_MIN_MINUTES', 30),
        /** Tolérance autour de heure_debut/heure_fin de la vacation
         *  contrôlée — au-delà, le contrôle est refusé (l'agent contrôlé
         *  n'est pas censé être en poste à cette heure-là). */
        'heure_tolerance_minutes' => (int) env('SIS_CONTROLE_HEURE_TOLERANCE_MINUTES', 60),
    ],

    /** Mentions figées sur les factures proforma (PDF). */
    'commercial' => [
        'affaire_suivie_par' => env('SIS_AFFAIRE_SUIVIE_PAR', 'SERVICE COMMERCIAL'),
        'telephone' => env('SIS_TELEPHONE_COMMERCIAL', '22 50 31 77'),
        'apporteur' => env('SIS_APPORTEUR', ''),
    ],

    /**
     * Masque des numéros de facture : {prefix}-{site}-{AAAA}-{NNNN}
     * Ex. SC-ABJ-2026-0007
     */
    'facture' => [
        'numero_prefix' => env('SIS_FACTURE_NUMERO_PREFIX', 'SC'),
        'numero_site' => env('SIS_FACTURE_NUMERO_SITE', 'ABJ'),
    ],

    /*
    | Contraintes horaires appliquées à la création/modification d'une
    | vacation. Valeurs par défaut usuelles en gardiennage — à ajuster par
    | déploiement selon la convention collective applicable.
    */
    'vacation' => [
        'duree_max_heures' => (float) env('SIS_VACATION_DUREE_MAX_HEURES', 12),
        'repos_min_heures' => (float) env('SIS_VACATION_REPOS_MIN_HEURES', 11),
    ],

    /** Pied de page légal des PDF facture. */
    'company' => [
        'name' => env('SIS_COMPANY_NAME', 'Société Ivoirienne de Sécurité'),
        'legal' => env(
            'SIS_COMPANY_LEGAL',
            '08 BP 902 ABIDJAN 08 SARL au capital de 5 millions de francs CFA RC N° 20.681, CC: 7407073 P Compte BNI N° 027154730005'
        ),
    ],

    /*
    | Compte développeur seedé par DatabaseSeeder. Lu via config() (et non
    | env() direct dans le seeder) pour rester fiable après `config:cache`,
    | systématique en déploiement production (cf. .env.production.example).
    | NB : on lit APP_ENV via env() et non app()->environment() — ce fichier
    | est chargé avant que Laravel ne lie l'environnement dans le conteneur
    | (LoadConfiguration::bootstrap), donc app() y casse (« Target class
    | [env] does not exist »).
    */
    'developer' => [
        'seed' => filter_var(
            env('SIS_SEED_DEVELOPER', env('APP_ENV') !== 'production'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'password' => (string) env('SIS_DEVELOPER_PASSWORD', 'password'),
    ],
];
