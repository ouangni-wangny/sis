<?php

$base = dirname(__DIR__);
function w(string $rel, string $c): void {
    global $base;
    $p = "$base/$rel";
    if (!is_dir(dirname($p))) mkdir(dirname($p), 0777, true);
    file_put_contents($p, $c);
    echo "OK $rel\n";
}

// Auditable trait
w('app/Domain/Shared/Traits/Auditable.php', <<<'PHP'
<?php

namespace App\Domain\Shared\Traits;

use App\Observers\AuditableObserver;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditableObserver::class);
    }
}

PHP);

w('app/Observers/AuditableObserver.php', <<<'PHP'
<?php

namespace App\Observers;

use App\Events\ModelAudited;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        event(new ModelAudited($model, 'created', null, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        event(new ModelAudited($model, 'updated', $model->getOriginal(), $model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        event(new ModelAudited($model, 'deleted', $model->getOriginal(), null));
    }
}

PHP);

w('app/Events/ModelAudited.php', <<<'PHP'
<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelAudited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $action,
        public ?array $ancien,
        public ?array $nouveau,
    ) {}
}

PHP);

w('app/Listeners/EnregistrerAuditListener.php', <<<'PHP'
<?php

namespace App\Listeners;

use App\Events\ModelAudited;
use App\Models\JournalAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class EnregistrerAuditListener
{
    public function handle(ModelAudited $event): void
    {
        JournalAudit::query()->create([
            'user_id' => Auth::id(),
            'action' => $event->action,
            'auditable_type' => $event->model->getMorphClass(),
            'auditable_id' => $event->model->getKey(),
            'ancien' => $this->sanitize($event->ancien),
            'nouveau' => $this->sanitize($event->nouveau),
            'ip' => Request::ip(),
        ]);
    }

    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        unset($data['password'], $data['pin_hash'], $data['remember_token']);

        return $data;
    }
}

PHP);

// User model
w('app/Models/User.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Domain\Shared\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'name',
        'email',
        'password',
        'pin_hash',
        'matricule',
        'type',
        'statut',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'type' => TypeUser::class,
            'statut' => StatutUser::class,
        ];
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}

PHP);

w('app/Models/Grade.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\TypeAgent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['libelle', 'type_agent', 'description'];

    protected function casts(): array
    {
        return ['type_agent' => TypeAgent::class];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}

PHP);

w('app/Models/Zone.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['nom', 'description'];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}

PHP);

w('app/Models/Client.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\TypeClient;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'type', 'raison_sociale', 'nom_responsable', 'personne_contact',
        'telephone', 'email', 'adresse', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeClient::class,
            'statut' => StatutClient::class,
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }
}

PHP);

w('app/Models/Agent.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Agent extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'user_id', 'grade_id', 'type', 'nom', 'prenom', 'telephone',
        'matricule', 'cnps', 'date_embauche', 'date_expiration_contrat',
        'date_expiration_permis', 'statut',
    ];

    protected $appends = [];

    protected function casts(): array
    {
        return [
            'type' => TypeAgent::class,
            'statut' => StatutAgent::class,
            'date_embauche' => 'date',
            'date_expiration_contrat' => 'date',
            'date_expiration_permis' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
        $this->addMediaCollection('piece_identite')->singleFile()->useDisk('private');
        $this->addMediaCollection('permis')->singleFile()->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->performOnCollections('photo')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 400, 400)
            ->performOnCollections('photo')
            ->nonQueued();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    public function rondes(): HasMany
    {
        return $this->hasMany(Ronde::class);
    }

    public function perimetres(): HasMany
    {
        return $this->hasMany(RondierPerimetre::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }
}

PHP);

w('app/Models/Site.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id', 'zone_id', 'nom', 'adresse', 'responsable',
        'tarif_mensuel', 'latitude', 'longitude', 'rayon_metres',
    ];

    protected function casts(): array
    {
        return [
            'tarif_mensuel' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
            'rayon_metres' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function postes(): HasMany
    {
        return $this->hasMany(Poste::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }
}

PHP);

w('app/Models/Poste.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poste extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['site_id', 'nom', 'agents_requis', 'heure_debut', 'heure_fin'];

    protected function casts(): array
    {
        return ['agents_requis' => 'integer'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

PHP);

w('app/Models/Checkpoint.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checkpoint extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'site_id', 'nom', 'code_qr', 'latitude', 'longitude', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'ordre' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

PHP);

w('app/Models/RondierPerimetre.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RondierPerimetre extends Model
{
    use HasUuids;

    protected $fillable = ['agent_id', 'zone_id', 'site_id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

PHP);

w('app/Models/Vacation.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutVacation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'agent_id', 'site_id', 'poste_id', 'date_debut', 'date_fin',
        'heure_debut', 'heure_fin', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutVacation::class,
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }
}

PHP);

w('app/Models/Ronde.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutRonde;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ronde extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'agent_id', 'site_id', 'vacation_id', 'demarree_at', 'terminee_at',
        'statut', 'progression',
    ];

    protected function casts(): array
    {
        return [
            'demarree_at' => 'datetime',
            'terminee_at' => 'datetime',
            'statut' => StatutRonde::class,
            'progression' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function vacation(): BelongsTo
    {
        return $this->belongsTo(Vacation::class);
    }

    public function rondeCheckpoints(): HasMany
    {
        return $this->hasMany(RondeCheckpoint::class);
    }
}

PHP);

w('app/Models/RondeCheckpoint.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RondeCheckpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'ronde_id', 'checkpoint_id', 'scanne_at', 'latitude', 'longitude', 'valide',
    ];

    protected function casts(): array
    {
        return [
            'scanne_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'valide' => 'boolean',
        ];
    }

    public function ronde(): BelongsTo
    {
        return $this->belongsTo(Ronde::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }
}

PHP);

w('app/Models/Controle.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Controle extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'client_uuid', 'agent_id', 'site_id', 'poste_id', 'controle_agent_id',
        'ronde_id', 'effectue_at', 'latitude', 'longitude', 'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'effectue_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->performOnCollections('photos')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 400, 400)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function ronde(): BelongsTo
    {
        return $this->belongsTo(Ronde::class);
    }
}

PHP);

w('app/Models/Anomalie.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\TypeAnomalie;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Anomalie extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'client_uuid', 'signale_par_id', 'assigne_a_id', 'site_id',
        'type', 'gravite', 'statut', 'commentaire', 'signale_at', 'resolue_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeAnomalie::class,
            'gravite' => GraviteAnomalie::class,
            'statut' => StatutAnomalie::class,
            'signale_at' => 'datetime',
            'resolue_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function signalePar(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'signale_par_id');
    }

    public function assigneA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

PHP);

w('app/Models/Contrat.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\TypeContrat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contrat extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'agent_id', 'type', 'reference', 'date_debut', 'date_fin', 'salaire', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeContrat::class,
            'date_debut' => 'date',
            'date_fin' => 'date',
            'salaire' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')->singleFile()->useDisk('private');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}

PHP);

w('app/Models/Absence.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutAbsence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Absence extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['agent_id', 'date_debut', 'date_fin', 'motif', 'statut'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutAbsence::class,
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}

PHP);

w('app/Models/Offre.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offre extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['libelle', 'description', 'prix_mensuel', 'actif'];

    protected function casts(): array
    {
        return [
            'prix_mensuel' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }
}

PHP);

w('app/Models/Abonnement.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutAbonnement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id', 'offre_id', 'site_id', 'date_debut', 'date_fin', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutAbonnement::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

PHP);

w('app/Models/Facture.php', <<<'PHP'
<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Facture extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'client_id', 'numero', 'date_emission', 'date_echeance',
        'montant_ht', 'montant_tva', 'montant_ttc', 'devise', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'montant_ht' => 'decimal:2',
            'montant_tva' => 'decimal:2',
            'montant_ttc' => 'decimal:2',
            'statut' => StatutFacture::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class);
    }
}

PHP);

w('app/Models/LigneFacture.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneFacture extends Model
{
    use HasUuids;

    protected $fillable = [
        'facture_id', 'description', 'quantite', 'prix_unitaire', 'montant',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:2',
            'prix_unitaire' => 'decimal:2',
            'montant' => 'decimal:2',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}

PHP);

w('app/Models/JournalAudit.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalAudit extends Model
{
    use HasUuids;

    protected $table = 'journal_audit';

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'ancien', 'nouveau', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'ancien' => 'array',
            'nouveau' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}

PHP);

w('app/Models/RapportExport.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RapportExport extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'format', 'statut', 'filtres', 'erreur',
    ];

    protected function casts(): array
    {
        return ['filtres' => 'array'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('export')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

PHP);

echo "Models done\n";
