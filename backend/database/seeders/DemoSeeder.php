<?php

namespace Database\Seeders;

use App\Domain\Commercial\ConditionsCommerciales;
use App\Domain\Contrat\CalculDureeContrat;
use App\Domain\Paie\CalculRemunerationCi;
use App\Domain\Shared\Enums\Civilite;
use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\JourSemaine;
use App\Domain\Shared\Enums\ModePaiement;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Enums\SituationMatrimoniale;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAbsence;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Enums\TypeAnomalie;
use App\Domain\Shared\Enums\TypeClient;
use App\Domain\Shared\Enums\TypeContrat;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\Abonnement;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\BulletinPaie;
use App\Models\Checkpoint;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\Facture;
use App\Models\Grade;
use App\Models\LigneAbonnement;
use App\Models\LigneFacture;
use App\Models\Offre;
use App\Models\Paiement;
use App\Models\PeriodePaie;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\RondeCheckpoint;
use App\Models\RondierPerimetre;
use App\Models\SisNotification;
use App\Models\Site;
use App\Models\SoldeCongesMouvement;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de données volumineux et cohérent pour tests de bout en bout
 * (S.I.S — Société Ivoirienne de Sécurité, Abidjan).
 *
 * Couvre : opérations terrain, RH (contrats, absences, soldes congés,
 * avenants, alertes), paie (périodes + bulletins), commercial, notifications.
 *
 * Usage :
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 *
 * (nécessite RolePermissionSeeder + DatabaseSeeder pour rôles et comptes
 * admin@sis.ci / rh@sis.ci / operation@sis.ci / commercial@sis.ci / comptable@sis.ci)
 *
 * PIN mobile uniforme pour la démo : 1234
 */
class DemoSeeder extends Seeder
{
    /** @var list<Zone> */
    private array $zones = [];

    /** @var list<Client> */
    private array $clients = [];

    /** @var list<Offre> */
    private array $offres = [];

    /** @var list<Site> */
    private array $sites = [];

    /** @var array<string, list<Poste>> siteId => postes */
    private array $postesParSite = [];

    /** @var list<Agent> agents postés (type=agent) */
    private array $agents = [];

    /** @var list<Agent> rondiers (type=rondier) */
    private array $rondiers = [];

    /** @var list<Contrat> */
    private array $contratsActifs = [];

    private ?string $villeAbidjanId = null;

    /** @var list<Absence> */
    private array $absencesApprouvees = [];

    private int $matriculeCounter = 0;

    private int $factureCounter = 0;

    private int $qrCounter = 0;

    public function run(): void
    {
        // Coupe l'audit (JournalAudit) et les autres events pendant le seed :
        // volume de démo, pas d'utilisateur authentifié, pas de bruit à tracer.
        Model::withoutEvents(function () {
            DB::transaction(function () {
                $this->seedZones();
                $this->seedGrades();
                $this->seedVilles();
                $this->seedClients();
                $this->seedOffres();
                $this->seedSites();
                $this->seedPostesEtCheckpoints();
                $this->seedAgents();
                $this->seedContrats();
                $this->seedPerimetres();
                $this->seedVacations();
                $this->seedAbsences();
                $this->seedAbsenceVacationLinks();
                $this->seedCongesMouvements();
                $this->seedRondesEtControles();
                $this->seedAnomalies();
                $this->seedPaie();
                $this->seedNotifications();
                $this->seedAbonnements();
                $this->seedFacturesEtPaiements();
            });
        });

        $this->command?->info('DemoSeeder terminé : '.count($this->clients).' clients, '
            .count($this->sites).' sites, '.count($this->agents).' agents, '
            .count($this->rondiers).' rondiers, '
            .count($this->contratsActifs).' contrats actifs, '
            .Absence::query()->count().' absences, '
            .PeriodePaie::query()->count().' périodes de paie, '
            .BulletinPaie::query()->count().' bulletins. '
            .'PIN mobile démo : 1234.');
    }

    // ------------------------------------------------------------------
    // Référentiel
    // ------------------------------------------------------------------

    private function seedZones(): void
    {
        $communes = [
            'Cocody', 'Plateau', 'Marcory', 'Treichville', 'Koumassi',
            'Yopougon', 'Adjamé', 'Abobo', 'Port-Bouët', 'Bingerville',
            'Anyama', 'Songon', 'Grand-Bassam', 'Dabou', 'Jacqueville',
            'Bonoua', 'Alépé', 'Azaguié', 'Brofodoumé', 'Oghlwapo',
            'Vridi', 'Zone 4', 'Riviera Palmeraie', 'Angré',
            'Deux Plateaux', 'Riviera 2', 'Riviera 3', 'Angré Chu',
            'Yopougon Sicogi', 'Yopougon Niangon', 'Abobo Sagbé',
            'Koumassi Remblais', 'Marcory Zone 4c', 'Treichville Avenue 16',
            'Port-Bouët Vridi Canal', 'Songon M’Brathé', 'Anyama Akoupé',
            'Bingerville Ébrié', 'Grand-Bassam Quartier France',
            'Dabou Centre', 'Jacqueville Lagune', 'Bonoua Est',
            'Plateau Centre-Affaires', 'Cocody Ambassades',
            'Zone Industrielle Yopougon', 'Zone Franche Vridi',
            'Aéroport Surroundings', 'Université Campus',
        ];

        foreach ($communes as $nom) {
            $this->zones[] = Zone::query()->create([
                'nom' => $nom,
                'description' => "Secteur d'intervention {$nom} (Abidjan et périphérie).",
            ]);
        }
    }

    private function seedGrades(): void
    {
        $gradeAgent = Grade::query()->firstOrCreate(
            ['libelle' => 'Agent de sécurité'],
            ['type_agent' => TypeAgent::Agent, 'description' => 'Agent posté']
        );
        $gradeControleur = Grade::query()->firstOrCreate(
            ['libelle' => 'Contrôleur'],
            ['type_agent' => TypeAgent::Controleur, 'description' => 'Contrôleur terrain / contrôles']
        );

        $gradesRetires = Grade::query()
            ->whereIn('libelle', ['Chef d’équipe', 'Superviseur terrain'])
            ->get();

        foreach ($gradesRetires as $grade) {
            Agent::query()
                ->where('grade_id', $grade->id)
                ->update([
                    'grade_id' => $grade->type_agent === TypeAgent::Controleur
                        ? $gradeControleur->id
                        : $gradeAgent->id,
                ]);
            $grade->delete();
        }
    }

    private function seedVilles(): void
    {
        $labels = [
            'Abidjan',
            'Bouaké',
            'Yamoussoukro',
            'San-Pédro',
            'Korhogo',
            'Daloa',
            'Man',
            'Gagnoa',
            'Grand-Bassam',
            'Bingerville',
        ];
        foreach ($labels as $libelle) {
            $ville = Ville::query()->firstOrCreate(['libelle' => $libelle]);
            if ($libelle === 'Abidjan') {
                $this->villeAbidjanId = $ville->id;
            }
        }
    }

    private function seedClients(): void
    {
        $entreprises = [
            ['Banque Atlantique Côte d\'Ivoire', 'Cocody'],
            ['SGBCI - Agence Plateau', 'Plateau'],
            ['NSIA Banque', 'Plateau'],
            ['Hôtel Ivoire Cocody', 'Cocody'],
            ['Résidence Les Palmiers', 'Cocody'],
            ['Sococe Distribution', 'Marcory'],
            ['Prosuma Groupe', 'Marcory'],
            ['CDCI Supermarché Treichville', 'Treichville'],
            ['SIR - Raffinerie Vridi', 'Port-Bouët'],
            ['Solibra Industries', 'Yopougon'],
            ['Nestlé Côte d\'Ivoire', 'Yopougon'],
            ['Orange Côte d\'Ivoire', 'Plateau'],
            ['MTN Côte d\'Ivoire', 'Marcory'],
            ['Groupe Scolaire Les Génies', 'Cocody'],
            ['Lycée Sainte Marie', 'Cocody'],
            ['Clinique Farah', 'Cocody'],
            ['Cité SIR Port-Bouët', 'Port-Bouët'],
            ['Zone Industrielle Koumassi SA', 'Koumassi'],
            ['Marché de Gros Bingerville', 'Bingerville'],
            ['Centre Commercial Cap Sud', 'Marcory'],
            ['Immeuble Alpha 2000', 'Plateau'],
            ['Usine Filtisac', 'Yopougon'],
            ['Airport Handling Abidjan', 'Port-Bouët'],
            ['Pharmacie de la Paix', 'Adjamé'],
            ['Résidence Atlantique', 'Cocody'],
            ['Complexe Sportif Parc des Sports', 'Treichville'],
            ['Entrepôt Bolloré Logistics', 'Vridi'],
            ['Siège Ecobank CI', 'Plateau'],
            ['Université Félix Houphouët-Boigny', 'Cocody'],
            ['Clinique La Providence', 'Yopougon'],
            ['Marché Gouro Adjamé', 'Adjamé'],
            ['Palais de la Culture', 'Treichville'],
            ['Port Autonome d\'Abidjan', 'Port-Bouët'],
            ['Aéroport FHB Abidjan', 'Port-Bouët'],
            ['Canal+ Côte d\'Ivoire', 'Cocody'],
            ['RTI Radiodiffusion', 'Cocody'],
            ['CFAO Motors Abidjan', 'Marcory'],
            ['Toyota Côte d\'Ivoire', 'Zone 4'],
            ['Unilever CI', 'Yopougon'],
            ['SIFCA Groupe', 'Plateau'],
            ['Petroci Holding', 'Plateau'],
            ['CNPS Siège', 'Cocody'],
            ['Assemblée Nationale', 'Plateau'],
            ['Ministère de la Sécurité', 'Plateau'],
            ['CHU de Cocody', 'Cocody'],
            ['CHU de Treichville', 'Treichville'],
            ['Pharmacie de l\'Indénié', 'Adjamé'],
            ['Carrefour Market Plateau', 'Plateau'],
            ['Auchan Abidjan Mall', 'Cocody'],
            ['Ivoire Trade Center', 'Plateau'],
            ['Cité Administrative Plateau', 'Plateau'],
            ['SODECI Direction', 'Cocody'],
            ['CIE Direction Générale', 'Plateau'],
            ['Pont Houphouët-Boigny Sécurité', 'Plateau'],
            ['Gare Routière Adjamé', 'Adjamé'],
            ['Marché de Yopougon Siporex', 'Yopougon'],
            ['Université Nangui Abrogoua', 'Abobo'],
            ['INPHB Yamoussoukro Antenne Abj', 'Cocody'],
            ['Résidence Palm Club', 'Cocody'],
            ['Hôtel Pullman Abidjan', 'Plateau'],
            ['Novotel Abidjan', 'Plateau'],
            ['Mövenpick Abidjan', 'Zone 4'],
            ['Société Ivoirienne de Banque', 'Plateau'],
            ['Bridge Bank Group', 'Cocody'],
            ['Coris Bank International', 'Plateau'],
            ['Total Energies Stations Réseau', 'Marcory'],
            ['Vivo Energy Shell CI', 'Plateau'],
            ['DHL Express Abidjan', 'Port-Bouët'],
            ['Maersk Logistics CI', 'Vridi'],
            ['Sitarail Gare', 'Treichville'],
        ];

        foreach ($entreprises as [$nom, $commune]) {
            $this->clients[] = Client::query()->create([
                'type' => TypeClient::Entreprise,
                'raison_sociale' => $nom,
                'nom_responsable' => null,
                'personne_contact' => $this->fullName(),
                'telephone' => $this->phone(),
                'email' => $this->slugEmail($nom),
                'adresse' => "Boulevard principal, {$commune}, Abidjan",
                'statut' => $this->weighted([
                    StatutClient::Actif->value => 88,
                    StatutClient::Suspendu->value => 8,
                    StatutClient::Resilie->value => 4,
                ]),
            ]);
        }

        $particuliers = [
            ['Yao', 'Marcel'], ['Konan', 'Affoué'], ['Touré', 'Aminata'],
            ['Bamba', 'Issa'], ['Kouadio', 'Awa'], ['Cissé', 'Ibrahim'],
            ['Ouattara', 'Fatou'], ['Diallo', 'Seydou'], ['Traoré', 'Mariam'],
            ['Koffi', 'Jean'], ['N\'Guessan', 'Clarisse'], ['Sanogo', 'Boubacar'],
        ];
        foreach ($particuliers as [$nom, $prenom]) {
            $this->clients[] = Client::query()->create([
                'type' => TypeClient::Particulier,
                'raison_sociale' => "{$prenom} {$nom}",
                'nom_responsable' => null,
                'personne_contact' => null,
                'telephone' => $this->phone(),
                'email' => $this->slugEmail("{$prenom}.{$nom}"),
                'adresse' => 'Villa privée, Riviera, Cocody, Abidjan',
                'statut' => StatutClient::Actif->value,
            ]);
        }
    }

    private function seedOffres(): void
    {
        $catalogue = [
            ['Gardiennage jour (agent posté)', 180000],
            ['Gardiennage nuit (agent posté)', 200000],
            ['Gardiennage 24h/24 (rotation 2 agents)', 380000],
            ['Rondes de surveillance mobile', 120000],
            ['Sécurité événementielle (ponctuelle)', 250000],
            ['Télésurveillance + intervention', 150000],
        ];

        foreach ($catalogue as $index => [$libelle, $prix]) {
            $this->offres[] = Offre::query()->create([
                'libelle' => $libelle,
                'description' => "Prestation standard S.I.S — {$libelle}.",
                'prix_mensuel' => $prix,
                'actif' => $index < 5, // 1 offre inactive pour tests catalogue
            ]);
        }

        $this->offres[] = Offre::query()->create([
            'libelle' => 'Pack VIP multi-sites (archivé)',
            'description' => 'Ancienne offre catalogue — inactive.',
            'prix_mensuel' => 850000,
            'actif' => false,
        ]);
    }

    private function seedSites(): void
    {
        $suffixes = ['Siège', 'Agence', 'Entrepôt', 'Annexe', 'Site principal', 'Dépôt', 'Showroom'];

        foreach ($this->clients as $client) {
            $nbSites = $client->type === TypeClient::Particulier ? 1 : $this->rand(2, 5);
            for ($i = 0; $i < $nbSites; $i++) {
                $zone = $this->pick($this->zones);
                $suffix = $i === 0 ? $suffixes[4] : $this->pick($suffixes);
                $this->sites[] = Site::query()->create([
                    'client_id' => $client->id,
                    'zone_id' => $zone->id,
                    'nom' => "{$client->raison_sociale} — {$suffix}",
                    'adresse' => "Zone {$zone->nom}, Abidjan",
                    'responsable' => $this->fullName(),
                    'tarif_mensuel' => $this->rand(150000, 950000),
                    'latitude' => 5.30 + (mt_rand(-1500, 1500) / 10000),
                    'longitude' => -4.02 + (mt_rand(-1500, 1500) / 10000),
                    'rayon_metres' => $this->pick([100, 150, 200, 250, 300]),
                ]);
            }
        }
    }

    private function seedPostesEtCheckpoints(): void
    {
        $nomsJour = ['Poste principal', 'Entrée véhicules', 'Portail arrière', 'Accueil', 'Parking'];

        foreach ($this->sites as $site) {
            $nbPostes = $this->rand(2, 4);
            $postes = [];
            for ($i = 0; $i < $nbPostes; $i++) {
                // Mix jour / nuit / 24h (heure_debut_nuit renseignée).
                $mode = $this->pick(['jour', 'jour', 'nuit', '24h']);
                if ($mode === 'nuit') {
                    $postes[] = Poste::query()->create([
                        'site_id' => $site->id,
                        'nom' => 'Poste nuit',
                        'agents_requis' => $this->pick([1, 1, 2]),
                        'heure_debut' => '19:00:00',
                        'heure_fin' => '07:00:00',
                    ]);
                } elseif ($mode === '24h') {
                    $postes[] = Poste::query()->create([
                        'site_id' => $site->id,
                        'nom' => 'Poste 24h',
                        // Effectif total jour+nuit (ex. 2 → 1 par quart).
                        'agents_requis' => $this->pick([2, 2, 2, 4]),
                        'heure_debut' => '07:00:00',
                        'heure_fin' => '19:00:00',
                        'heure_debut_nuit' => '19:00:00',
                        'heure_fin_nuit' => '07:00:00',
                    ]);
                } else {
                    $postes[] = Poste::query()->create([
                        'site_id' => $site->id,
                        'nom' => $this->pick($nomsJour),
                        'agents_requis' => $this->pick([1, 1, 1, 2]),
                        'heure_debut' => '07:00:00',
                        'heure_fin' => '19:00:00',
                    ]);
                }
            }
            $this->postesParSite[$site->id] = $postes;

            $nbCheckpoints = $this->rand(3, 6);
            for ($i = 1; $i <= $nbCheckpoints; $i++) {
                $this->qrCounter++;
                Checkpoint::query()->create([
                    'site_id' => $site->id,
                    'nom' => "Point de contrôle {$i}",
                    'code_qr' => 'QR-DEMO-'.str_pad((string) $this->qrCounter, 5, '0', STR_PAD_LEFT),
                    'latitude' => $site->latitude ? $site->latitude + (mt_rand(-50, 50) / 100000) : null,
                    'longitude' => $site->longitude ? $site->longitude + (mt_rand(-50, 50) / 100000) : null,
                    'ordre' => $i,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // RH / effectifs
    // ------------------------------------------------------------------

    private function seedAgents(): void
    {
        $gradeAgent = Grade::where('libelle', 'Agent de sécurité')->firstOrFail();
        $gradeControleur = Grade::where('libelle', 'Contrôleur')->firstOrFail();

        // ~220 agents postés, ~55 rondiers — volume E2E élevé.
        for ($i = 0; $i < 220; $i++) {
            $agent = $this->createAgent($gradeAgent, TypeAgent::Agent, $i < 180);
            $this->agents[] = $agent;
        }

        for ($i = 0; $i < 55; $i++) {
            $agent = $this->createAgent($gradeControleur, TypeAgent::Controleur, true);
            $this->rondiers[] = $agent;
        }
    }

    private function createAgent(Grade $grade, TypeAgent $type, bool $withMobileAccount): Agent
    {
        [$prenom, $nom, $civilite] = $this->ivorianName();
        $matricule = $this->nextMatricule();

        $userId = null;
        if ($withMobileAccount) {
            $user = User::query()->create([
                'nom' => $nom,
                'prenom' => $prenom,
                'name' => "{$prenom} {$nom}",
                'matricule' => $matricule,
                'pin_hash' => Hash::make('1234'),
                'type' => TypeUser::Mobile,
                'statut' => StatutUser::Actif,
                'email' => null,
                'password' => null,
            ]);
            $user->assignRole($type->value);
            $userId = $user->id;
        }

        return Agent::query()->create([
            'user_id' => $userId,
            'grade_id' => $grade->id,
            'type' => $type,
            'nom' => $nom,
            'prenom' => $prenom,
            'civilite' => $civilite,
            'date_naissance' => Carbon::now()->subYears($this->rand(20, 55))->subDays($this->rand(0, 365))->toDateString(),
            'lieu_naissance' => $this->pick(['Abidjan', 'Bouaké', 'Yamoussoukro', 'Daloa', 'San-Pédro', 'Korhogo']),
            'situation_matrimoniale' => $this->pick(SituationMatrimoniale::cases())->value,
            'nombre_enfants' => $this->pick([0, 0, 1, 2, 3, 4]),
            'nationalite' => mt_rand(1, 100) <= 90 ? 'Ivoirienne' : $this->pick(['Burkinabé', 'Malienne', 'Guinéenne']),
            'telephone' => $this->phone(),
            'numero_cni' => 'CI'.mt_rand(100000000, 999999999),
            'ville_id' => $this->villeAbidjanId,
            'ville' => 'Abidjan',
            'domicile' => $this->pick(['Cocody', 'Yopougon', 'Abobo', 'Koumassi', 'Marcory', 'Port-Bouët']).', Abidjan',
            'matricule' => $matricule,
            'cnps' => (string) mt_rand(1000000, 9999999),
            'date_embauche' => Carbon::now()->subMonths($this->rand(1, 72))->toDateString(),
            'date_expiration_permis' => Carbon::now()->addDays($this->rand(-20, 400))->toDateString(),
            // Agents postés : démarrent disponibles (en_activite vient des vacations).
            // Congé / maladie viennent des absences approuvées, pas d’un tirage initial.
            // Contrôleurs : en_activite uniquement après attribution de zone (seedPerimetres).
            'statut' => $type === TypeAgent::Controleur
                ? $this->weighted([
                    StatutAgent::Disponible->value => 84,
                    StatutAgent::Conge->value => 8,
                    StatutAgent::Malade->value => 5,
                    StatutAgent::Suspendu->value => 2,
                    StatutAgent::Archive->value => 1,
                ])
                : $this->weighted([
                    StatutAgent::Disponible->value => 97,
                    StatutAgent::Suspendu->value => 2,
                    StatutAgent::Archive->value => 1,
                ]),
            'jour_repos' => $this->pick(JourSemaine::cases())->value,
            'conges_acquis_annuel' => $this->pick([26, 28, 30, 30, 30, 32]),
            'solde_conges_jours' => $this->rand(0, 28),
        ]);
    }

    private function seedContrats(): void
    {
        foreach ([...$this->agents, ...$this->rondiers] as $index => $agent) {
            if ($agent->statut === StatutAgent::Archive) {
                continue;
            }

            $isControleur = $agent->type === TypeAgent::Controleur;
            $base = $isControleur ? $this->rand(110000, 190000) : $this->rand(85000, 150000);
            $transport = 25000;
            $tenue = 10000;
            $responsabilite = $isControleur ? $this->rand(15000, 40000) : ($index < 15 ? $this->rand(10000, 25000) : 0);
            $indemnite = $isControleur ? $this->rand(0, 20000) : 0;
            $sursalaire = $this->rand(0, 20000);

            $calc = CalculRemunerationCi::calculer([
                'salaire_base' => $base,
                'indemnite_fonction' => $indemnite,
                'prime_responsabilite' => $responsabilite,
                'prime_transport' => $transport,
                'prime_entretien_tenue' => $tenue,
                'sursalaire' => $sursalaire,
                'situation_matrimoniale' => $agent->situation_matrimoniale?->value,
                'nombre_enfants' => $agent->nombre_enfants ?? 0,
            ]);

            $type = $this->weighted([
                TypeContrat::Cdi->value => 62,
                TypeContrat::Cdd->value => 25,
                TypeContrat::Stage->value => 8,
                TypeContrat::Prestation->value => 5,
            ]);

            $dateDebut = Carbon::parse($agent->date_embauche);
            $dateFin = match ($type) {
                TypeContrat::Cdd->value => $dateDebut->copy()->addMonths($this->rand(6, 18))->toDateString(),
                TypeContrat::Stage->value => $dateDebut->copy()->addMonths($this->rand(3, 6))->toDateString(),
                TypeContrat::Prestation->value => $dateDebut->copy()->addMonths($this->rand(12, 24))->toDateString(),
                default => mt_rand(1, 100) <= 12
                    ? $dateDebut->copy()->addMonths($this->rand(18, 36))->toDateString()
                    : null,
            };

            // Quelques contrats proches de l'échéance pour tester les alertes.
            if ($index < 12 && $dateFin === null) {
                $dateFin = Carbon::now()->addDays($this->rand(5, 28))->toDateString();
            } elseif ($index >= 12 && $index < 20 && $dateFin !== null) {
                $dateFin = Carbon::now()->addDays($this->rand(3, 25))->toDateString();
            }

            $periodeEssai = match ($type) {
                TypeContrat::Cdi->value => $this->pick([2, 3, 3, 4]),
                TypeContrat::Cdd->value => $this->pick([0, 1, 1, 2]),
                default => 0,
            };

            // Quelques contrats en période d'essai bientôt finie.
            if ($index >= 20 && $index < 28 && $periodeEssai > 0) {
                $daysIntoEssai = max(1, ($periodeEssai * 30) - $this->rand(2, 12));
                $dateDebut = Carbon::now()->subDays($daysIntoEssai);
                // Recalcule la fin relative au nouveau début (évite date_fin < date_debut).
                $dateFin = match ($type) {
                    TypeContrat::Cdd->value => $dateDebut->copy()->addMonths($this->rand(6, 18))->toDateString(),
                    TypeContrat::Stage->value => $dateDebut->copy()->addMonths($this->rand(3, 6))->toDateString(),
                    TypeContrat::Prestation->value => $dateDebut->copy()->addMonths($this->rand(12, 24))->toDateString(),
                    default => null,
                };
            }

            $statut = $agent->statut === StatutAgent::Suspendu
                ? 'suspendu'
                : 'actif';

            $contrat = Contrat::query()->create([
                'agent_id' => $agent->id,
                'type' => $type,
                'reference' => 'CTR-'.$agent->matricule,
                'date_debut' => $dateDebut->toDateString(),
                'date_fin' => $dateFin,
                'duree_mois' => CalculDureeContrat::fromDates(
                    $dateDebut->toDateString(),
                    $dateFin,
                ),
                'periode_essai_mois' => $periodeEssai,
                'salaire_base' => $base,
                'indemnite_fonction' => $indemnite,
                'prime_responsabilite' => $responsabilite,
                'prime_transport' => $transport,
                'prime_entretien_tenue' => $tenue,
                'sursalaire' => $sursalaire,
                'nombre_enfants' => $agent->nombre_enfants ?? 0,
                'parts_igr' => $calc['parts_igr'],
                'montant_igr' => $calc['montant_igr'],
                'retenue_cnps' => $calc['retenue_cnps'],
                'salaire_brut' => $calc['salaire_brut'],
                'salaire_net' => $calc['salaire_net'],
                'statut' => $statut,
            ]);

            if ($statut === 'actif') {
                $this->contratsActifs[] = $contrat;
            }

            // Avenants (augmentation) pour ~15 % des CDI actifs.
            if (
                $type === TypeContrat::Cdi->value
                && $statut === 'actif'
                && mt_rand(1, 100) <= 15
            ) {
                $nouveauBase = $base + $this->rand(5000, 20000);
                $calcAvenant = CalculRemunerationCi::calculer([
                    'salaire_base' => $nouveauBase,
                    'indemnite_fonction' => $indemnite,
                    'prime_responsabilite' => $responsabilite,
                    'prime_transport' => $transport,
                    'prime_entretien_tenue' => $tenue,
                    'sursalaire' => $sursalaire,
                    'situation_matrimoniale' => $agent->situation_matrimoniale?->value,
                    'nombre_enfants' => $agent->nombre_enfants ?? 0,
                ]);
                $debutAvenant = Carbon::now()->subMonths($this->rand(1, 6));

                $avenant = Contrat::query()->create([
                    'agent_id' => $agent->id,
                    'contrat_parent_id' => $contrat->id,
                    'type' => TypeContrat::Cdi->value,
                    'reference' => 'CTR-'.$agent->matricule.'-AV',
                    'date_debut' => $debutAvenant->toDateString(),
                    'date_fin' => null,
                    'duree_mois' => null,
                    'periode_essai_mois' => 0,
                    'salaire_base' => $nouveauBase,
                    'indemnite_fonction' => $indemnite,
                    'prime_responsabilite' => $responsabilite,
                    'prime_transport' => $transport,
                    'prime_entretien_tenue' => $tenue,
                    'sursalaire' => $sursalaire,
                    'nombre_enfants' => $agent->nombre_enfants ?? 0,
                    'parts_igr' => $calcAvenant['parts_igr'],
                    'montant_igr' => $calcAvenant['montant_igr'],
                    'retenue_cnps' => $calcAvenant['retenue_cnps'],
                    'salaire_brut' => $calcAvenant['salaire_brut'],
                    'salaire_net' => $calcAvenant['salaire_net'],
                    'statut' => 'actif',
                ]);

                $contrat->update([
                    'statut' => 'termine',
                    'date_fin' => $debutAvenant->copy()->subDay()->toDateString(),
                    'duree_mois' => CalculDureeContrat::fromDates(
                        $contrat->date_debut->toDateString(),
                        $debutAvenant->copy()->subDay()->toDateString(),
                    ),
                ]);
                $this->contratsActifs = array_values(array_filter(
                    $this->contratsActifs,
                    fn (Contrat $c) => $c->id !== $contrat->id,
                ));
                $this->contratsActifs[] = $avenant;
            }
        }

        // Quelques anciens contrats terminés / résiliés pour l'historique.
        foreach (array_slice($this->agents, 0, 25) as $agent) {
            $debut = Carbon::parse($agent->date_embauche)->subYears(2);
            $fin = $debut->copy()->addMonths($this->rand(6, 18));
            Contrat::query()->create([
                'agent_id' => $agent->id,
                'type' => TypeContrat::Cdd->value,
                'reference' => 'CTR-'.$agent->matricule.'-OLD',
                'date_debut' => $debut->toDateString(),
                'date_fin' => $fin->toDateString(),
                'duree_mois' => CalculDureeContrat::fromDates($debut->toDateString(), $fin->toDateString()),
                'periode_essai_mois' => 1,
                'salaire_base' => 90000,
                'prime_transport' => 25000,
                'prime_entretien_tenue' => 10000,
                'salaire_brut' => 125000,
                'salaire_net' => 110000,
                'statut' => $this->pick(['termine', 'resilie']),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Terrain
    // ------------------------------------------------------------------

    private function seedPerimetres(): void
    {
        // Attribution zone ↔ rondier (1 zone = 1 rondier principal).
        // Les premiers rondiers peuvent aussi recevoir une 2ᵉ zone libre
        // pour couvrir davantage de sites en démo.
        $availableZones = collect($this->zones)->shuffle()->values();
        $zoneIndex = 0;

        foreach ($this->rondiers as $rondier) {
            // Un rondier inactif ne monopolise pas une zone en démo.
            if (in_array($rondier->statut, [
                StatutAgent::Archive,
                StatutAgent::Suspendu,
                StatutAgent::Conge,
                StatutAgent::Malade,
            ], true)) {
                continue;
            }
            $zone = $availableZones->get($zoneIndex);
            if (! $zone) {
                break;
            }
            RondierPerimetre::query()->create([
                'agent_id' => $rondier->id,
                'zone_id' => $zone->id,
            ]);
            $zoneIndex++;
        }

        // Zones restantes → 2ᵉ périmètre pour les premiers rondiers.
        $rondierIndex = 0;
        while ($zoneIndex < $availableZones->count()) {
            $zone = $availableZones->get($zoneIndex);
            $rondier = $this->rondiers[$rondierIndex] ?? null;
            if (! $zone || ! $rondier) {
                break;
            }
            RondierPerimetre::query()->create([
                'agent_id' => $rondier->id,
                'zone_id' => $zone->id,
            ]);
            $zoneIndex++;
            $rondierIndex++;
        }

        // Aligne le statut sur la règle métier (SyncRondierPerimetreAction) :
        // zone assignée → en_activite, sinon → disponible.
        // Ne touche pas congé / malade / suspendu / archivé.
        $rondiersAvecZone = RondierPerimetre::query()
            ->whereIn('agent_id', collect($this->rondiers)->pluck('id'))
            ->whereNotNull('zone_id')
            ->pluck('agent_id')
            ->unique()
            ->all();

        foreach ($this->rondiers as $rondier) {
            if (! in_array($rondier->statut, [
                StatutAgent::Disponible,
                StatutAgent::EnActivite,
            ], true)) {
                continue;
            }

            $hasZone = in_array($rondier->id, $rondiersAvecZone, true);
            $next = $hasZone ? StatutAgent::EnActivite : StatutAgent::Disponible;
            if ($rondier->statut !== $next) {
                $rondier->update(['statut' => $next]);
            }
        }
    }

    /** @return list<Site> sites du périmètre d'un rondier */
    private function sitesDuPerimetre(Agent $rondier): array
    {
        $zoneIds = RondierPerimetre::query()
            ->where('agent_id', $rondier->id)
            ->whereNotNull('zone_id')
            ->pluck('zone_id')
            ->all();

        return array_values(array_filter(
            $this->sites,
            fn (Site $s) => in_array($s->zone_id, $zoneIds, true),
        ));
    }

    private function seedVacations(): void
    {
        // Fenêtre : 30 jours passés -> 10 jours futurs (volume E2E).
        // Respecte le planning postes : quarts jour / nuit / 24h + jour_repos.
        $jours = range(-30, 10);

        foreach ($this->agents as $agent) {
            if (! $this->agentAssignablePourVacation($agent)) {
                continue;
            }
            $sitesPossibles = $this->sites;
            if ($sitesPossibles === []) {
                continue;
            }
            $sitesHabituels = collect($sitesPossibles)->shuffle()->take($this->rand(1, 3))->values();

            foreach ($jours as $offset) {
                if (mt_rand(1, 100) > 80) {
                    continue;
                }

                $date = Carbon::now()->addDays($offset);

                // Respect du jour de repos hebdomadaire de l'agent.
                if ($this->isJourRepos($agent, $date)) {
                    continue;
                }

                $site = $sitesHabituels->random();
                $postes = $this->postesParSite[$site->id] ?? [];
                $poste = $postes !== [] ? $this->pick($postes) : null;

                [$heureDebut, $heureFin, $overnight] = $this->horairesVacationPourPoste($poste);

                $statut = match (true) {
                    $offset < -2 && mt_rand(1, 100) <= 4 => StatutVacation::Annulee->value,
                    default => $this->vacationStatutPourDate($date),
                };

                Vacation::query()->create([
                    'agent_id' => $agent->id,
                    'site_id' => $site->id,
                    'poste_id' => $poste?->id,
                    'date_debut' => $date->toDateString(),
                    'date_fin' => $overnight
                        ? $date->copy()->addDay()->toDateString()
                        : $date->toDateString(),
                    'heure_debut' => $heureDebut,
                    'heure_fin' => $heureFin,
                    'statut' => $statut,
                ]);
            }
        }

        // Couverture réaliste de postes 24h (jour + nuit) sur les jours récents.
        $this->seedCouverture24hRecente();

        // Couverture complète sur quelques postes simples à 2 agents.
        $postesDoubles = collect($this->postesParSite)
            ->flatten()
            ->filter(fn (Poste $p) => ! $p->estCouverture24h() && (int) $p->agents_requis >= 2)
            ->shuffle()
            ->take(25);
        foreach ($postesDoubles as $poste) {
            $date = Carbon::now()->subDays($this->rand(0, 7));
            $overnight = $this->isOvernightHours(
                (string) ($poste->heure_debut ?? '07:00:00'),
                (string) ($poste->heure_fin ?? '19:00:00'),
            );
            $agentsDispo = collect($this->agents)
                ->filter(fn (Agent $a) => $this->agentAssignablePourVacation($a)
                    && ! $this->isJourRepos($a, $date))
                ->shuffle()
                ->take((int) $poste->agents_requis)
                ->values();
            foreach ($agentsDispo as $agent) {
                if (Vacation::query()
                    ->where('agent_id', $agent->id)
                    ->whereDate('date_debut', $date->toDateString())
                    ->exists()) {
                    continue;
                }
                Vacation::query()->create([
                    'agent_id' => $agent->id,
                    'site_id' => $poste->site_id,
                    'poste_id' => $poste->id,
                    'date_debut' => $date->toDateString(),
                    'date_fin' => $overnight
                        ? $date->copy()->addDay()->toDateString()
                        : $date->toDateString(),
                    'heure_debut' => $poste->heure_debut ?? '07:00:00',
                    'heure_fin' => $poste->heure_fin ?? '19:00:00',
                    'statut' => $this->vacationStatutPourDate($date),
                ]);
            }
        }

        // Quelques vacations déjà à recouvrir (ops) hors absences.
        $candidats = Vacation::query()
            ->whereIn('statut', [StatutVacation::Planifiee->value, StatutVacation::EnCours->value])
            ->inRandomOrder()
            ->limit(40)
            ->get();
        foreach ($candidats as $vacation) {
            $vacation->update(['statut' => StatutVacation::ARecouvrir->value]);
        }

        $this->reconcileStatutsAgentsPostes();
    }

    /** Agents postés : en_activite s’il reste du planning actif, sinon disponible. */
    private function reconcileStatutsAgentsPostes(): void
    {
        $today = Carbon::now()->toDateString();
        $actifs = Vacation::query()
            ->whereIn('statut', [
                StatutVacation::Planifiee->value,
                StatutVacation::EnCours->value,
            ])
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            })
            ->pluck('agent_id')
            ->unique()
            ->all();

        foreach ($this->agents as $agent) {
            if (! in_array($agent->statut, [
                StatutAgent::Disponible,
                StatutAgent::EnActivite,
            ], true)) {
                continue;
            }

            $next = in_array($agent->id, $actifs, true)
                ? StatutAgent::EnActivite
                : StatutAgent::Disponible;
            if ($agent->statut !== $next) {
                $agent->update(['statut' => $next]);
            }
        }
    }

    private function agentAssignablePourVacation(Agent $agent): bool
    {
        return ! in_array($agent->statut, [
            StatutAgent::Archive,
            StatutAgent::Suspendu,
            StatutAgent::Conge,
            StatutAgent::Malade,
        ], true);
    }

    private function vacationStatutPourDate(Carbon $date): string
    {
        $today = Carbon::now()->startOfDay();
        $day = $date->copy()->startOfDay();

        if ($day->lt($today)) {
            return StatutVacation::Terminee->value;
        }
        if ($day->eq($today)) {
            return StatutVacation::EnCours->value;
        }

        return StatutVacation::Planifiee->value;
    }

    /**
     * Horaires vacation selon le type de poste (jour / nuit / 24h).
     * Poste 24h : un quart au hasard (jour OU nuit), pas les deux sur la même vacation.
     *
     * @return array{0: string, 1: string, 2: bool} [debut, fin, overnight]
     */
    private function horairesVacationPourPoste(?Poste $poste): array
    {
        if (! $poste) {
            return ['07:00:00', '19:00:00', false];
        }

        if ($poste->estCouverture24h()) {
            $quartNuit = mt_rand(0, 1) === 1;
            if ($quartNuit) {
                return [
                    $poste->heure_debut_nuit ?? '19:00:00',
                    $poste->heure_fin_nuit ?? '07:00:00',
                    true,
                ];
            }

            return [
                $poste->heure_debut ?? '07:00:00',
                $poste->heure_fin ?? '19:00:00',
                false,
            ];
        }

        $debut = $poste->heure_debut ?? '07:00:00';
        $fin = $poste->heure_fin ?? '19:00:00';
        $overnight = $this->isOvernightHours((string) $debut, (string) $fin);

        return [$debut, $fin, $overnight];
    }

    private function isOvernightHours(string $heureDebut, string $heureFin): bool
    {
        return Carbon::parse($heureFin)->format('H:i') < Carbon::parse($heureDebut)->format('H:i');
    }

    private function isJourRepos(Agent $agent, Carbon $date): bool
    {
        if ($agent->jour_repos === null) {
            return false;
        }

        $repos = $agent->jour_repos instanceof JourSemaine
            ? $agent->jour_repos
            : JourSemaine::tryFrom((string) $agent->jour_repos);

        return $repos !== null
            && $repos === JourSemaine::fromCarbonDayOfWeek($date->dayOfWeek);
    }

    /** Assure jour + nuit sur un échantillon de postes 24h (jours récents). */
    private function seedCouverture24hRecente(): void
    {
        $postes24h = collect($this->postesParSite)
            ->flatten()
            ->filter(fn (Poste $p) => $p->estCouverture24h())
            ->shuffle()
            ->take(40);

        $agentsActifs = collect($this->agents)
            ->filter(fn (Agent $a) => $this->agentAssignablePourVacation($a))
            ->values();

        foreach ($postes24h as $poste) {
            foreach (range(0, 5) as $offset) {
                $date = Carbon::now()->subDays($offset);
                $parQuart = max(1, (int) ceil($poste->agents_requis / 2));

                foreach (['jour', 'nuit'] as $quart) {
                    $overnight = $quart === 'nuit';
                    $heureDebut = $overnight
                        ? ($poste->heure_debut_nuit ?? '19:00:00')
                        : ($poste->heure_debut ?? '07:00:00');
                    $heureFin = $overnight
                        ? ($poste->heure_fin_nuit ?? '07:00:00')
                        : ($poste->heure_fin ?? '19:00:00');

                    for ($i = 0; $i < $parQuart; $i++) {
                        $agent = $agentsActifs->shuffle()->first(function (Agent $a) use ($date) {
                            return ! $this->isJourRepos($a, $date)
                                && ! Vacation::query()
                                    ->where('agent_id', $a->id)
                                    ->whereDate('date_debut', $date->toDateString())
                                    ->exists();
                        });
                        if (! $agent) {
                            break;
                        }

                        Vacation::query()->create([
                            'agent_id' => $agent->id,
                            'site_id' => $poste->site_id,
                            'poste_id' => $poste->id,
                            'date_debut' => $date->toDateString(),
                            'date_fin' => $overnight
                                ? $date->copy()->addDay()->toDateString()
                                : $date->toDateString(),
                            'heure_debut' => $heureDebut,
                            'heure_fin' => $heureFin,
                            'statut' => $this->vacationStatutPourDate($date),
                        ]);
                    }
                }
            }
        }
    }

    private function seedRondesEtControles(): void
    {
        $joursPasses = range(-30, 0);

        foreach ($this->rondiers as $rondier) {
            $sites = $this->sitesDuPerimetre($rondier);
            if ($sites === []) {
                continue;
            }

            foreach ($joursPasses as $offset) {
                if (mt_rand(1, 100) > 75) {
                    continue;
                }
                $date = Carbon::now()->addDays($offset);
                $site = $this->pick($sites);

                $ronde = Ronde::query()->create([
                    'agent_id' => $rondier->id,
                    'site_id' => $site->id,
                    'vacation_id' => null,
                    'demarree_at' => $date->copy()->setTime(9, mt_rand(0, 59)),
                    'terminee_at' => $date->copy()->setTime(10, mt_rand(0, 59)),
                    'statut' => StatutRonde::Terminee->value,
                    'progression' => 100,
                ]);

                $checkpoints = Checkpoint::where('site_id', $site->id)->get();
                foreach ($checkpoints as $checkpoint) {
                    RondeCheckpoint::query()->create([
                        'ronde_id' => $ronde->id,
                        'checkpoint_id' => $checkpoint->id,
                        'scanne_at' => $ronde->demarree_at?->copy()->addMinutes(mt_rand(1, 45)),
                        'latitude' => $checkpoint->latitude,
                        'longitude' => $checkpoint->longitude,
                        'valide' => mt_rand(1, 100) <= 92,
                    ]);
                }

                // Contrôles de présence des agents postés sur ce site ce jour-là
                // (un seul contrôle par agent contrôlé et par jour — règle métier).
                $vacationsDuJour = Vacation::query()
                    ->where('site_id', $site->id)
                    ->whereDate('date_debut', $date->toDateString())
                    ->with(['agent', 'poste'])
                    ->get()
                    ->filter(fn (Vacation $v) => $v->agent
                        && $v->agent->type === TypeAgent::Agent
                        && $v->agent->id !== $rondier->id);

                foreach ($vacationsDuJour as $vacation) {
                    if (mt_rand(1, 100) > 70) {
                        continue;
                    }
                    $agentPoste = $vacation->agent;
                    if (Controle::where('controle_agent_id', $agentPoste->id)
                        ->whereDate('effectue_at', $date->toDateString())
                        ->exists()) {
                        continue;
                    }

                    $present = mt_rand(1, 100) <= 85;
                    $commentaire = $present
                        ? 'Présence confirmée au poste'
                        : 'Absence constatée — agent hors poste';

                    Controle::query()->create([
                        'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'agent_id' => $rondier->id,
                        'site_id' => $site->id,
                        'poste_id' => $vacation->poste_id,
                        'controle_agent_id' => $agentPoste->id,
                        'ronde_id' => $ronde->id,
                        'effectue_at' => $date->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59)),
                        'latitude' => $site->latitude,
                        'longitude' => $site->longitude,
                        'commentaire' => $commentaire,
                        'resultat' => $present
                            ? ResultatControle::Present->value
                            : ResultatControle::Absent->value,
                    ]);

                    if (! $present && mt_rand(1, 100) <= 70) {
                        Anomalie::query()->create([
                            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'signale_par_id' => $rondier->id,
                            'assigne_a_id' => User::query()->where('email', 'operation@sis.ci')->value('id'),
                            'site_id' => $site->id,
                            'type' => TypeAnomalie::AbsencePoste->value,
                            'gravite' => GraviteAnomalie::Haute->value,
                            'statut' => StatutAnomalie::Ouverte->value,
                            'commentaire' => "Absence au poste constatée lors du contrôle ({$agentPoste->matricule}).",
                            'signale_at' => $date->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59)),
                            'resolue_at' => null,
                        ]);
                    }
                }
            }
        }
    }

    private function seedAnomalies(): void
    {
        $types = array_values(array_filter(
            TypeAnomalie::cases(),
            fn (TypeAnomalie $t) => $t !== TypeAnomalie::AbsencePoste,
        ));
        $tousLesAgents = [...$this->agents, ...$this->rondiers];

        for ($i = 0; $i < 120; $i++) {
            $signaleur = $this->pick($tousLesAgents);
            $site = $this->pick($this->sites);
            $date = Carbon::now()->subDays($this->rand(0, 40))->setTime($this->rand(0, 23), mt_rand(0, 59));
            $statut = $this->weighted([
                StatutAnomalie::Ouverte->value => 30,
                StatutAnomalie::EnCours->value => 25,
                StatutAnomalie::Resolue->value => 45,
            ]);
            $operationUser = User::query()->where('email', 'operation@sis.ci')->first();

            Anomalie::query()->create([
                'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'signale_par_id' => $signaleur->id,
                'assigne_a_id' => ($statut !== StatutAnomalie::Ouverte->value && $operationUser)
                    ? $operationUser->id
                    : null,
                'site_id' => $site->id,
                'type' => $this->pick($types)->value,
                'gravite' => $this->weighted([
                    GraviteAnomalie::Basse->value => 30,
                    GraviteAnomalie::Moyenne->value => 40,
                    GraviteAnomalie::Haute->value => 22,
                    GraviteAnomalie::Critique->value => 8,
                ]),
                'statut' => $statut,
                'commentaire' => $this->anomalieCommentaire(),
                'signale_at' => $date,
                'resolue_at' => $statut === StatutAnomalie::Resolue->value
                    ? $date->copy()->addHours($this->rand(1, 48))
                    : null,
            ]);
        }
    }

    private function seedAbsences(): void
    {
        $tousLesAgents = [...$this->agents, ...$this->rondiers];
        $types = [
            TypeAbsence::Maladie->value,
            TypeAbsence::Conge->value,
            TypeAbsence::Autre->value,
            TypeAbsence::Permission->value,
        ];
        $motifs = [
            'Maladie', 'Congé annuel', 'Événement familial', 'Permission exceptionnelle',
            'Certificat médical', 'Mariage', 'Décès familial', 'Examen administratif',
        ];

        /** @var list<array{agent_id: string, debut: string, fin: string}> */
        $ranges = [];

        for ($i = 0; $i < 70; $i++) {
            $agent = $this->pick($tousLesAgents);
            if (in_array($agent->statut, [StatutAgent::Archive, StatutAgent::Suspendu], true)) {
                continue;
            }

            $debut = Carbon::now()->subDays($this->rand(-15, 40));
            $fin = $debut->copy()->addDays($this->rand(1, 10));
            $type = $this->pick($types);

            // Création uniquement en_attente / approuvee (AbsenceStatutTransition::CREATABLE).
            $cible = $this->weighted([
                StatutAbsence::Approuvee->value => 55,
                StatutAbsence::EnAttente->value => 28,
                StatutAbsence::Refusee->value => 10,
                StatutAbsence::Annulee->value => 7,
            ]);
            $statutCreation = in_array($cible, [
                StatutAbsence::Refusee->value,
                StatutAbsence::Annulee->value,
            ], true)
                ? StatutAbsence::EnAttente->value
                : $cible;

            $overlap = false;
            foreach ($ranges as $range) {
                if ($range['agent_id'] !== $agent->id) {
                    continue;
                }
                if ($debut->toDateString() <= $range['fin'] && $fin->toDateString() >= $range['debut']) {
                    $overlap = true;
                    break;
                }
            }
            if ($overlap) {
                continue;
            }

            $absence = Absence::query()->create([
                'agent_id' => $agent->id,
                'type' => $type,
                'date_debut' => $debut->toDateString(),
                'date_fin' => $fin->toDateString(),
                'motif' => $this->pick($motifs),
                'statut' => $statutCreation,
            ]);

            $ranges[] = [
                'agent_id' => $agent->id,
                'debut' => $debut->toDateString(),
                'fin' => $fin->toDateString(),
            ];

            // Simule la transition depuis en_attente pour refusee / annulee.
            if ($cible !== $statutCreation) {
                $absence->update(['statut' => $cible]);
            }

            if ($absence->fresh()->statut === StatutAbsence::Approuvee) {
                $this->absencesApprouvees[] = $absence->fresh();

                if ($type === TypeAbsence::Conge->value) {
                    $agent->update(['statut' => StatutAgent::Conge->value]);
                } elseif ($type === TypeAbsence::Maladie->value) {
                    $agent->update(['statut' => StatutAgent::Malade->value]);
                }
            }
        }
    }

    private function seedAbsenceVacationLinks(): void
    {
        foreach ($this->absencesApprouvees as $absence) {
            $vacations = Vacation::query()
                ->where('agent_id', $absence->agent_id)
                ->whereIn('statut', [
                    StatutVacation::Planifiee->value,
                    StatutVacation::EnCours->value,
                ])
                ->whereDate('date_debut', '<=', $absence->date_fin)
                ->where(function ($q) use ($absence) {
                    $q->whereNull('date_fin')
                        ->orWhereDate('date_fin', '>=', $absence->date_debut);
                })
                ->get();

            foreach ($vacations as $vacation) {
                $vacation->update([
                    'absence_id' => $absence->id,
                    'statut' => StatutVacation::ARecouvrir->value,
                ]);
            }
        }
    }

    private function seedCongesMouvements(): void
    {
        $rhUser = User::query()->where('email', 'rh@sis.ci')->first();

        /** @var array<string, float> */
        $soldes = [];

        foreach ([...$this->agents, ...$this->rondiers] as $agent) {
            $acquis = (float) ($agent->conges_acquis_annuel ?: 30);
            $soldes[$agent->id] = $acquis;
            SoldeCongesMouvement::query()->create([
                'agent_id' => $agent->id,
                'absence_id' => null,
                'type' => 'acquisition_annuelle',
                'jours' => $acquis,
                'solde_apres' => $acquis,
                'motif' => 'Acquisition annuelle '.$this->rand(2025, 2026),
                'user_id' => $rhUser?->id,
                'created_at' => Carbon::now()->subMonths($this->rand(1, 8)),
            ]);
        }

        $conges = collect($this->absencesApprouvees)
            ->filter(function (Absence $a) {
                $type = $a->type instanceof TypeAbsence ? $a->type->value : (string) $a->type;

                return $type === TypeAbsence::Conge->value;
            })
            ->sortBy(fn (Absence $a) => (string) $a->date_debut)
            ->values();

        foreach ($conges as $absence) {
            $agentId = $absence->agent_id;
            $jours = max(1, $absence->date_debut->diffInDays($absence->date_fin) + 1);
            $solde = max(0, ($soldes[$agentId] ?? 0) - $jours);
            $soldes[$agentId] = $solde;

            SoldeCongesMouvement::query()->create([
                'agent_id' => $agentId,
                'absence_id' => $absence->id,
                'type' => 'deduction_absence',
                'jours' => -$jours,
                'solde_apres' => $solde,
                'motif' => 'Absence congé approuvée',
                'user_id' => $rhUser?->id,
            ]);

            Agent::query()->whereKey($agentId)->update(['solde_conges_jours' => $solde]);
        }
    }

    private function seedPaie(): void
    {
        $periodesDef = [
            [Carbon::now()->subMonths(3), StatutPeriodePaie::Cloturee],
            [Carbon::now()->subMonths(2), StatutPeriodePaie::Cloturee],
            [Carbon::now()->subMonths(1), StatutPeriodePaie::Validee],
            [Carbon::now(), StatutPeriodePaie::Brouillon],
        ];

        foreach ($periodesDef as [$ref, $statut]) {
            $debut = $ref->copy()->startOfMonth();
            $fin = $ref->copy()->endOfMonth();

            $periode = PeriodePaie::query()->create([
                'mois' => (int) $debut->month,
                'annee' => (int) $debut->year,
                'date_debut' => $debut->toDateString(),
                'date_fin' => $fin->toDateString(),
                'statut' => $statut->value,
                'commentaire' => 'Période démo '.$debut->translatedFormat('F Y'),
            ]);

            foreach ($this->contratsActifs as $contrat) {
                $agent = $contrat->agent ?? Agent::query()->find($contrat->agent_id);
                if (! $agent || $agent->statut === StatutAgent::Archive) {
                    continue;
                }

                // Pas de bulletin avant l’entrée en vigueur du contrat.
                if ($contrat->date_debut && Carbon::parse($contrat->date_debut)->gt($fin)) {
                    continue;
                }
                if ($contrat->date_fin && Carbon::parse($contrat->date_fin)->lt($debut)) {
                    continue;
                }

                $bulletinStatut = match ($statut) {
                    StatutPeriodePaie::Cloturee => $this->weighted([
                        StatutBulletinPaie::Paye->value => 85,
                        StatutBulletinPaie::Valide->value => 15,
                    ]),
                    StatutPeriodePaie::Validee => $this->weighted([
                        StatutBulletinPaie::Valide->value => 70,
                        StatutBulletinPaie::Paye->value => 20,
                        StatutBulletinPaie::Brouillon->value => 10,
                    ]),
                    default => StatutBulletinPaie::Brouillon->value,
                };

                BulletinPaie::query()->create([
                    'periode_paie_id' => $periode->id,
                    'agent_id' => $agent->id,
                    'contrat_id' => $contrat->id,
                    'salaire_brut' => $contrat->salaire_brut ?? 0,
                    'retenue_cnps' => $contrat->retenue_cnps ?? 0,
                    'montant_igr' => $contrat->montant_igr ?? 0,
                    'salaire_net' => $contrat->salaire_net ?? 0,
                    'statut' => $bulletinStatut,
                    'paye_le' => $bulletinStatut === StatutBulletinPaie::Paye->value
                        ? $fin->copy()->addDays($this->rand(1, 5))
                        : null,
                    'details' => array_filter([
                        'matricule' => $agent->matricule,
                        'agent' => trim($agent->prenom.' '.$agent->nom),
                        'contrat_reference' => $contrat->reference,
                        'contrat_type' => $contrat->type instanceof \BackedEnum
                            ? $contrat->type->value
                            : (string) $contrat->type,
                        'parts_igr' => $contrat->parts_igr,
                        'salaire_percu' => in_array($bulletinStatut, [
                            StatutBulletinPaie::Paye->value,
                            StatutBulletinPaie::Valide->value,
                        ], true)
                            ? (float) ($contrat->salaire_net ?? 0)
                            : null,
                        'salaire_renseigne_le' => in_array($bulletinStatut, [
                            StatutBulletinPaie::Paye->value,
                            StatutBulletinPaie::Valide->value,
                        ], true)
                            ? now()->toIso8601String()
                            : null,
                    ], fn ($v) => $v !== null),
                ]);
            }
        }
    }

    private function seedNotifications(): void
    {
        $rh = User::query()->where('email', 'rh@sis.ci')->first();
        $ops = User::query()->where('email', 'operation@sis.ci')->first();
        $admin = User::query()->where('email', 'admin@sis.ci')->first();

        $templates = [
            ['documents_expiration', 'Documents / contrats à échéance', 'Plusieurs permis et contrats arrivent à échéance sous 30 jours.'],
            ['absences_en_attente', 'Absences en attente', 'Des demandes d’absence attendent une validation RH.'],
            ['contrats_surveillance', 'Contrats à surveiller', 'Des contrats approchent de la fin d’essai ou de contrat.'],
            ['paie_periode', 'Période de paie à traiter', 'La période du mois en cours est en brouillon — générer les bulletins.'],
            ['absence_approuvee', 'Absence approuvée', 'Une absence a été approuvée et des vacations marquées à recouvrir.'],
        ];

        foreach ([$rh, $ops, $admin] as $user) {
            if (! $user) {
                continue;
            }

            foreach ($templates as $i => [$type, $titre, $message]) {
                SisNotification::query()->create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'titre' => $titre,
                    'message' => $message,
                    'meta' => ['source' => 'demo'],
                    'lue_le' => $i % 2 === 0 ? Carbon::now()->subDays($this->rand(1, 5)) : null,
                    'created_at' => Carbon::now()->subDays($this->rand(0, 10)),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Commercial
    // ------------------------------------------------------------------

    private function seedAbonnements(): void
    {
        /** @var array<string, list<Site>> */
        $sitesParClient = [];
        foreach ($this->sites as $site) {
            $sitesParClient[$site->client_id][] = $site;
        }

        foreach ($this->clients as $client) {
            $sites = $sitesParClient[$client->id] ?? [];
            if ($sites === []) {
                continue;
            }

            foreach ($sites as $site) {
                if (mt_rand(1, 100) > 92) {
                    continue;
                }

                $offre = $this->pick($this->offres);
                $periodicite = $this->weighted([
                    PeriodiciteFacturation::Mensuel->value => 70,
                    PeriodiciteFacturation::Trimestriel->value => 22,
                    PeriodiciteFacturation::Annuel->value => 8,
                ]);
                $dateDebut = Carbon::now()->subMonths($this->rand(1, 14))->startOfMonth();
                $statut = $this->weighted([
                    StatutAbonnement::Actif->value => 72,
                    StatutAbonnement::Suspendu->value => 12,
                    StatutAbonnement::Resilie->value => 8,
                    StatutAbonnement::Expire->value => 8,
                ]);

                $prochaineFacture = null;
                if ($statut === StatutAbonnement::Actif->value) {
                    // ~25% en retard de facturation (ops commercial).
                    $prochaineFacture = mt_rand(1, 100) <= 25
                        ? Carbon::now()->subDays($this->rand(3, 20))->toDateString()
                        : Carbon::now()->addDays($this->rand(1, 25))->toDateString();
                }

                $abonnement = Abonnement::query()->create([
                    'client_id' => $client->id,
                    'offre_id' => $offre->id,
                    'designation' => $offre->libelle,
                    'site_id' => $site->id,
                    'periodicite' => $periodicite,
                    'date_debut' => $dateDebut->toDateString(),
                    'date_fin' => $statut === StatutAbonnement::Expire->value
                        ? Carbon::now()->subDays($this->rand(5, 40))->toDateString()
                        : null,
                    'prochaine_facture_le' => $prochaineFacture,
                    'statut' => $statut,
                ]);

                LigneAbonnement::query()->create([
                    'abonnement_id' => $abonnement->id,
                    'offre_id' => $offre->id,
                    'description' => "{$offre->libelle} — {$site->nom}",
                    'quantite' => 1,
                    'prix_unitaire' => $offre->prix_mensuel,
                    'montant' => $offre->prix_mensuel,
                    'ordre' => 1,
                ]);

                // Multi-lignes occasionnelles (pack + ronde).
                if (mt_rand(1, 100) <= 30 && count($this->offres) > 1) {
                    $offre2 = $this->pick(array_values(array_filter(
                        $this->offres,
                        fn (Offre $o) => $o->id !== $offre->id,
                    )));
                    LigneAbonnement::query()->create([
                        'abonnement_id' => $abonnement->id,
                        'offre_id' => $offre2->id,
                        'description' => "{$offre2->libelle} — option",
                        'quantite' => 1,
                        'prix_unitaire' => $offre2->prix_mensuel,
                        'montant' => $offre2->prix_mensuel,
                        'ordre' => 2,
                    ]);
                }
            }
        }
    }

    private function seedFacturesEtPaiements(): void
    {
        $abonnements = Abonnement::with(['client', 'lignes', 'site', 'offre'])->get();

        foreach ($abonnements as $abonnement) {
            // Pas de facturation sur abonnement suspendu / résilié / expiré hors période active.
            $abonnementStatut = $abonnement->statut instanceof \BackedEnum
                ? $abonnement->statut->value
                : (string) $abonnement->statut;
            if (in_array($abonnementStatut, [
                StatutAbonnement::Suspendu->value,
            ], true)) {
                continue;
            }

            $periodicite = $abonnement->periodicite;
            $nbFactures = $this->rand(3, 5);
            $periodeDebut = Carbon::parse($abonnement->date_debut);
            $limiteFacturation = Carbon::now();
            if ($abonnement->date_fin) {
                $limiteFacturation = Carbon::parse($abonnement->date_fin)->min($limiteFacturation);
            }
            if (in_array($abonnementStatut, [
                StatutAbonnement::Resilie->value,
                StatutAbonnement::Expire->value,
            ], true) && $abonnement->date_fin) {
                $limiteFacturation = Carbon::parse($abonnement->date_fin);
            }

            for ($i = 0; $i < $nbFactures; $i++) {
                [$pDebut, $pFin] = ConditionsCommerciales::periodeFacturee(
                    $periodeDebut->toDateString(),
                    $periodicite,
                );
                if (Carbon::parse($pDebut)->isFuture() || Carbon::parse($pDebut)->gt($limiteFacturation)) {
                    break;
                }

                $lignes = $abonnement->lignes->isNotEmpty()
                    ? $abonnement->lignes
                    : collect();
                $montantHt = (float) $lignes->sum('montant');
                if ($montantHt <= 0) {
                    $montantHt = (float) ($abonnement->offre?->prix_mensuel ?? 150000);
                }
                $tauxTva = 18.0;
                $montantTva = round($montantHt * $tauxTva / 100, 2);
                $montantTtc = round($montantHt + $montantTva, 2);
                $delaiJours = $this->pick(ConditionsCommerciales::delaisPaiementJours());

                $this->factureCounter++;
                $numero = 'SC/ABJ/N°'.str_pad((string) $this->factureCounter, 4, '0', STR_PAD_LEFT);

                $statut = Carbon::parse($pFin)->isPast()
                    ? $this->weighted([
                        StatutFacture::Valide->value => 80,
                        StatutFacture::EnAttente->value => 15,
                        StatutFacture::Annule->value => 5,
                    ])
                    : StatutFacture::EnAttente->value;

                $client = $abonnement->client;

                $facture = Facture::query()->create([
                    'client_id' => $abonnement->client_id,
                    'abonnement_id' => $abonnement->id,
                    'site_id' => $abonnement->site_id,
                    'numero' => $numero,
                    'date_emission' => $pDebut,
                    'date_echeance' => ConditionsCommerciales::dateEcheance($pDebut, $delaiJours),
                    'periode_debut' => $pDebut,
                    'periode_fin' => $pFin,
                    'periodicite' => $periodicite,
                    'date_debut_service' => $pDebut,
                    'date_fin_service' => $pFin,
                    'montant_ht' => $montantHt,
                    'montant_tva' => $montantTva,
                    'montant_ttc' => $montantTtc,
                    'devise' => 'XOF',
                    'statut' => $statut,
                    'lieu_emission' => 'Abidjan',
                    'affaire_suivie_par' => 'Service Commercial S.I.S',
                    'telephone_commercial' => $this->phone(),
                    'taux_tva' => $tauxTva,
                    'client_nom' => $client?->raison_sociale,
                    'client_adresse' => $client?->adresse,
                    'client_telephone' => $client?->telephone,
                    'client_email' => $client?->email,
                    'notes' => ConditionsCommerciales::noteAbonnement($periodicite, $montantHt),
                    'conditions_paiement' => ConditionsCommerciales::libelleDelaiPaiement($delaiJours),
                    'delai_paiement_jours' => $delaiJours,
                    'delai_validite' => '1 mois',
                    'duree_contrat_min' => 'Tous nos contrats sont conclus pour une durée minimum d’un an.',
                    'signataire_nom' => null,
                    'signataire_fonction' => 'La Direction Commerciale',
                    'montant_ttc_lettres' => null,
                ]);

                $ordre = 1;
                if ($lignes->isNotEmpty()) {
                    foreach ($lignes as $ligne) {
                        LigneFacture::query()->create([
                            'facture_id' => $facture->id,
                            'offre_id' => $ligne->offre_id,
                            'code_article' => null,
                            'description' => $ligne->description,
                            'quantite' => $ligne->quantite,
                            'prix_unitaire' => $ligne->prix_unitaire,
                            'montant' => $ligne->montant,
                            'ordre' => $ordre++,
                        ]);
                    }
                } else {
                    LigneFacture::query()->create([
                        'facture_id' => $facture->id,
                        'offre_id' => $abonnement->offre_id,
                        'code_article' => null,
                        'description' => $abonnement->designation ?? 'Prestation de gardiennage',
                        'quantite' => 1,
                        'prix_unitaire' => $montantHt,
                        'montant' => $montantHt,
                        'ordre' => 1,
                    ]);
                }

                if ($statut === StatutFacture::Valide->value) {
                    $this->seedPaiementsPourFacture($facture, $montantTtc, $pDebut);
                }

                $periodeDebut = Carbon::parse($pFin)->addDay();
            }
        }
    }

    private function seedPaiementsPourFacture(Facture $facture, float $montantTtc, string $pDebut): void
    {
        $rand = mt_rand(1, 100);
        $datePaiement = Carbon::parse($pDebut)->addDays($this->rand(1, 30));
        if ($datePaiement->isFuture()) {
            $datePaiement = Carbon::now();
        }

        if ($rand <= 65) {
            // Payée intégralement en une fois.
            Paiement::query()->create([
                'facture_id' => $facture->id,
                'montant' => $montantTtc,
                'date_paiement' => $datePaiement->toDateString(),
                'mode' => $this->weighted([
                    ModePaiement::Virement->value => 45,
                    ModePaiement::Wave->value => 15,
                    ModePaiement::Mtn->value => 10,
                    ModePaiement::Orange->value => 5,
                    ModePaiement::Cheque->value => 15,
                    ModePaiement::Especes->value => 10,
                ]),
                'reference' => 'PMT-'.mt_rand(100000, 999999),
                'notes' => null,
            ]);
        } elseif ($rand <= 85) {
            // Paiement partiel.
            $partiel = round($montantTtc * (mt_rand(30, 70) / 100), 2);
            Paiement::query()->create([
                'facture_id' => $facture->id,
                'montant' => $partiel,
                'date_paiement' => $datePaiement->toDateString(),
                'mode' => $this->weighted([
                    ModePaiement::Virement->value => 45,
                    ModePaiement::Wave->value => 15,
                    ModePaiement::Mtn->value => 10,
                    ModePaiement::Orange->value => 5,
                    ModePaiement::Cheque->value => 15,
                    ModePaiement::Especes->value => 10,
                ]),
                'reference' => 'PMT-'.mt_rand(100000, 999999),
                'notes' => 'Acompte',
            ]);
        }
        // Sinon (15%) : facture validée mais non payée — cas réaliste de relance.
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function nextMatricule(): string
    {
        $this->matriculeCounter++;
        $num = ($this->matriculeCounter - 1) % 10000;
        $letterIndex = intdiv($this->matriculeCounter - 1, 10000);

        return sprintf('%04d%s', $num, chr(65 + $letterIndex));
    }

    /** @return array{0: string, 1: string, 2: string} [prenom, nom, civilite] */
    private function ivorianName(): array
    {
        $nomsFamille = [
            'Kouassi', 'Koffi', 'Kouame', 'Konan', 'Yao', 'Kra', 'N\'Guessan', 'Aka',
            'Traoré', 'Diabaté', 'Ouattara', 'Coulibaly', 'Bamba', 'Koné', 'Sanogo',
            'Diallo', 'Cissé', 'Touré', 'Assi', 'Brou', 'Kacou', 'Angoran', 'Silué', 'Yeo',
        ];
        $prenomsHomme = [
            'Kouadio', 'Kouassi', 'Yao', 'Konan', 'Adama', 'Ibrahim', 'Moussa', 'Seydou',
            'Boubacar', 'Marcel', 'Serge', 'Jean-Baptiste', 'Franck', 'Aristide', 'Mamadou',
        ];
        $prenomsFemme = [
            'Adjoua', 'Akissi', 'Amenan', 'Affoué', 'Ange', 'Aya', 'Fatou', 'Mariam',
            'Aminata', 'Clarisse', 'Solange', 'Viviane', 'Rokia', 'Awa',
        ];

        $estHomme = mt_rand(0, 1) === 0;
        $prenom = $estHomme ? $this->pick($prenomsHomme) : $this->pick($prenomsFemme);
        $nom = $this->pick($nomsFamille);
        $civilite = $estHomme ? Civilite::Monsieur->value : $this->pick([Civilite::Madame->value, Civilite::Mademoiselle->value]);

        return [$prenom, $nom, $civilite];
    }

    private function fullName(): string
    {
        [$prenom, $nom] = $this->ivorianName();

        return "{$prenom} {$nom}";
    }

    private function phone(): string
    {
        $prefixes = ['01', '05', '07'];

        return sprintf(
            '%s %02d %02d %02d %02d',
            $this->pick($prefixes),
            mt_rand(0, 99),
            mt_rand(0, 99),
            mt_rand(0, 99),
            mt_rand(0, 99),
        );
    }

    private function slugEmail(string $base): string
    {
        $slug = mb_strtolower(preg_replace('/[^a-zA-Z0-9]+/', '.', $base) ?? 'contact');
        $slug = trim($slug, '.');

        return "contact@{$slug}.ci";
    }

    private function anomalieCommentaire(): string
    {
        return $this->pick([
            'Portail arrière laissé ouvert lors de la ronde.',
            'Individu non identifié aperçu près de la clôture.',
            'Éclairage extérieur hors service côté parking.',
            'Caméra de vidéosurveillance en panne, poste 2.',
            'Tentative d\'intrusion signalée par un riverain.',
            'Fuite d\'eau constatée près du local technique.',
            'Odeur de fumée détectée en zone de stockage.',
            'Agent posté absent au moment du contrôle.',
            'Véhicule stationné suspect devant l\'entrée principale.',
            'Alarme incendie déclenchée sans cause identifiée.',
        ]);
    }

    /** @template T @param list<T> $items @return T */
    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }

    private function rand(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /** @param array<string, int> $weights */
    private function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $r = mt_rand(1, $total);
        $cumulative = 0;
        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($r <= $cumulative) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }
}
