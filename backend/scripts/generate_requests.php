<?php

$base = dirname(__DIR__);
function w(string $rel, string $c): void {
    global $base;
    $p = "$base/$rel";
    if (!is_dir(dirname($p))) mkdir(dirname($p), 0777, true);
    file_put_contents($p, $c);
    echo "OK $rel\n";
}

function formRequest(string $ns, string $class, string $rulesBody, bool $authorizeTrue = true): string {
    $auth = $authorizeTrue ? 'return true;' : 'return false;';
    return <<<PHP
<?php

namespace App\\Http\\Requests\\{$ns};

use Illuminate\\Foundation\\Http\\FormRequest;

class {$class} extends FormRequest
{
    public function authorize(): bool
    {
        {$auth}
    }

    public function rules(): array
    {
        return [
{$rulesBody}
        ];
    }
}

PHP;
}

w('app/Http/Requests/Auth/LoginRequest.php', formRequest('Auth', 'LoginRequest', <<<'R'
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
R'));

w('app/Http/Requests/Auth/MobileLoginRequest.php', formRequest('Auth', 'MobileLoginRequest', <<<'R'
            'matricule' => ['required', 'string'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
            'device_name' => ['nullable', 'string', 'max:255'],
R'));

w('app/Http/Requests/Client/StoreClientRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Client;

use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\TypeClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TypeClient::class)],
            'raison_sociale' => ['required', 'string', 'max:255'],
            'nom_responsable' => ['nullable', 'string', 'max:255'],
            'personne_contact' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'adresse' => ['nullable', 'string'],
            'statut' => ['nullable', Rule::enum(StatutClient::class)],
        ];
    }
}
PHP);

w('app/Http/Requests/Client/UpdateClientRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Client;

use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\TypeClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(TypeClient::class)],
            'raison_sociale' => ['sometimes', 'string', 'max:255'],
            'nom_responsable' => ['nullable', 'string', 'max:255'],
            'personne_contact' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'adresse' => ['nullable', 'string'],
            'statut' => ['sometimes', Rule::enum(StatutClient::class)],
        ];
    }
}
PHP);

w('app/Http/Requests/Zone/StoreZoneRequest.php', formRequest('Zone', 'StoreZoneRequest', <<<'R'
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
R'));

w('app/Http/Requests/Zone/UpdateZoneRequest.php', formRequest('Zone', 'UpdateZoneRequest', <<<'R'
            'nom' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
R'));

w('app/Http/Requests/Grade/StoreGradeRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Grade;

use App\Domain\Shared\Enums\TypeAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:255'],
            'type_agent' => ['required', Rule::enum(TypeAgent::class)],
            'description' => ['nullable', 'string'],
        ];
    }
}
PHP);

w('app/Http/Requests/Grade/UpdateGradeRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Grade;

use App\Domain\Shared\Enums\TypeAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'string', 'max:255'],
            'type_agent' => ['sometimes', Rule::enum(TypeAgent::class)],
            'description' => ['nullable', 'string'],
        ];
    }
}
PHP);

w('app/Http/Requests/Site/StoreSiteRequest.php', formRequest('Site', 'StoreSiteRequest', <<<'R'
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'zone_id' => ['required', 'uuid', 'exists:zones,id'],
            'nom' => ['required', 'string', 'max:255'],
            'adresse' => ['nullable', 'string'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'tarif_mensuel' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'rayon_metres' => ['nullable', 'integer', 'min:50'],
            'postes' => ['nullable', 'array'],
            'postes.*.nom' => ['required_with:postes', 'string'],
            'postes.*.agents_requis' => ['nullable', 'integer', 'min:1'],
            'postes.*.heure_debut' => ['nullable', 'date_format:H:i'],
            'postes.*.heure_fin' => ['nullable', 'date_format:H:i'],
R'));

w('app/Http/Requests/Site/UpdateSiteRequest.php', formRequest('Site', 'UpdateSiteRequest', <<<'R'
            'client_id' => ['sometimes', 'uuid', 'exists:clients,id'],
            'zone_id' => ['sometimes', 'uuid', 'exists:zones,id'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'adresse' => ['nullable', 'string'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'tarif_mensuel' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'rayon_metres' => ['nullable', 'integer', 'min:50'],
R'));

w('app/Http/Requests/Poste/StorePosteRequest.php', formRequest('Poste', 'StorePosteRequest', <<<'R'
            'nom' => ['required', 'string', 'max:255'],
            'agents_requis' => ['nullable', 'integer', 'min:1'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
R'));

w('app/Http/Requests/Checkpoint/StoreCheckpointRequest.php', formRequest('Checkpoint', 'StoreCheckpointRequest', <<<'R'
            'nom' => ['required', 'string', 'max:255'],
            'code_qr' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'ordre' => ['nullable', 'integer', 'min:0'],
R'));

w('app/Http/Requests/Agent/StoreAgentRequest.php', formRequest('Agent', 'StoreAgentRequest', <<<'R'
            'grade_id' => ['required', 'uuid', 'exists:grades,id'],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'matricule' => ['required', 'string', 'max:50', 'unique:agents,matricule'],
            'cnps' => ['nullable', 'string', 'max:50'],
            'date_embauche' => ['nullable', 'date'],
            'date_expiration_contrat' => ['nullable', 'date'],
            'date_expiration_permis' => ['nullable', 'date'],
            'statut' => ['nullable', 'string'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'email' => ['nullable', 'email'],
R'));

w('app/Http/Requests/Agent/UpdateAgentRequest.php', formRequest('Agent', 'UpdateAgentRequest', <<<'R'
            'grade_id' => ['sometimes', 'uuid', 'exists:grades,id'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'cnps' => ['nullable', 'string', 'max:50'],
            'date_embauche' => ['nullable', 'date'],
            'date_expiration_contrat' => ['nullable', 'date'],
            'date_expiration_permis' => ['nullable', 'date'],
            'statut' => ['sometimes', 'string'],
R'));

w('app/Http/Requests/Agent/UploadAgentPhotoRequest.php', formRequest('Agent', 'UploadAgentPhotoRequest', <<<'R'
            'photo' => ['required', 'image', 'max:5120'],
R'));

w('app/Http/Requests/Agent/SyncPerimetreRequest.php', formRequest('Agent', 'SyncPerimetreRequest', <<<'R'
            'items' => ['required', 'array'],
            'items.*.zone_id' => ['nullable', 'uuid', 'exists:zones,id'],
            'items.*.site_id' => ['nullable', 'uuid', 'exists:sites,id'],
R'));

w('app/Http/Requests/Vacation/StoreVacationRequest.php', formRequest('Vacation', 'StoreVacationRequest', <<<'R'
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
            'statut' => ['nullable', 'string'],
R'));

w('app/Http/Requests/Ronde/StoreRondeRequest.php', formRequest('Ronde', 'StoreRondeRequest', <<<'R'
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'vacation_id' => ['nullable', 'uuid', 'exists:vacations,id'],
R'));

w('app/Http/Requests/Ronde/ScannerCheckpointRequest.php', formRequest('Ronde', 'ScannerCheckpointRequest', <<<'R'
            'checkpoint_id' => ['required', 'uuid', 'exists:checkpoints,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'code_qr' => ['nullable', 'string'],
R'));

w('app/Http/Requests/Controle/StoreControleRequest.php', formRequest('Controle', 'StoreControleRequest', <<<'R'
            'client_uuid' => ['nullable', 'uuid'],
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'controle_agent_id' => ['nullable', 'uuid', 'exists:agents,id'],
            'ronde_id' => ['nullable', 'uuid', 'exists:rondes,id'],
            'effectue_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'commentaire' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
R'));

w('app/Http/Requests/Anomalie/StoreAnomalieRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Anomalie;

use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\TypeAnomalie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnomalieRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'signale_par_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'type' => ['required', Rule::enum(TypeAnomalie::class)],
            'gravite' => ['nullable', Rule::enum(GraviteAnomalie::class)],
            'commentaire' => ['nullable', 'string'],
            'signale_at' => ['nullable', 'date'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }
}
PHP);

w('app/Http/Requests/Anomalie/UpdateAnomalieStatutRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Anomalie;

use App\Domain\Shared\Enums\StatutAnomalie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnomalieStatutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::enum(StatutAnomalie::class)],
            'assigne_a_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }
}
PHP);

w('app/Http/Requests/Contrat/StoreContratRequest.php', <<<'PHP'
<?php

namespace App\Http\Requests\Contrat;

use App\Domain\Shared\Enums\TypeContrat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContratRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'type' => ['required', Rule::enum(TypeContrat::class)],
            'reference' => ['nullable', 'string'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
            'salaire' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
PHP);

w('app/Http/Requests/Absence/StoreAbsenceRequest.php', formRequest('Absence', 'StoreAbsenceRequest', <<<'R'
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string'],
            'statut' => ['nullable', 'string'],
R'));

w('app/Http/Requests/Absence/UpdateAbsenceRequest.php', formRequest('Absence', 'UpdateAbsenceRequest', <<<'R'
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['sometimes', 'date'],
            'motif' => ['nullable', 'string'],
            'statut' => ['sometimes', 'string'],
R'));

w('app/Http/Requests/Offre/StoreOffreRequest.php', formRequest('Offre', 'StoreOffreRequest', <<<'R'
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_mensuel' => ['required', 'numeric', 'min:0'],
            'actif' => ['nullable', 'boolean'],
R'));

w('app/Http/Requests/Offre/UpdateOffreRequest.php', formRequest('Offre', 'UpdateOffreRequest', <<<'R'
            'libelle' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_mensuel' => ['sometimes', 'numeric', 'min:0'],
            'actif' => ['nullable', 'boolean'],
R'));

w('app/Http/Requests/Abonnement/StoreAbonnementRequest.php', formRequest('Abonnement', 'StoreAbonnementRequest', <<<'R'
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'offre_id' => ['required', 'uuid', 'exists:offres,id'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
            'statut' => ['nullable', 'string'],
R'));

w('app/Http/Requests/Facture/GenererFactureRequest.php', formRequest('Facture', 'GenererFactureRequest', <<<'R'
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
R'));

w('app/Http/Requests/User/StoreUserRequest.php', formRequest('User', 'StoreUserRequest', <<<'R'
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string'],
            'statut' => ['nullable', 'string'],
R'));

w('app/Http/Requests/User/UpdateUserRequest.php', formRequest('User', 'UpdateUserRequest', <<<'R'
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string'],
            'statut' => ['sometimes', 'string'],
R'));

w('app/Http/Requests/Rapport/StoreRapportRequest.php', formRequest('Rapport', 'StoreRapportRequest', <<<'R'
            'format' => ['nullable', 'in:pdf,xlsx'],
            'filtres' => ['nullable', 'array'],
R'));

w('app/Http/Requests/Mobile/MobileSyncRequest.php', formRequest('Mobile', 'MobileSyncRequest', <<<'R'
            'controles' => ['nullable', 'array'],
            'controles.*.client_uuid' => ['required', 'uuid'],
            'controles.*.agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'controles.*.site_id' => ['required', 'uuid', 'exists:sites,id'],
            'controles.*.poste_id' => ['nullable', 'uuid'],
            'controles.*.latitude' => ['nullable', 'numeric'],
            'controles.*.longitude' => ['nullable', 'numeric'],
            'controles.*.commentaire' => ['nullable', 'string'],
            'controles.*.effectue_at' => ['nullable', 'date'],
            'anomalies' => ['nullable', 'array'],
            'anomalies.*.client_uuid' => ['required', 'uuid'],
            'anomalies.*.signale_par_id' => ['required', 'uuid', 'exists:agents,id'],
            'anomalies.*.site_id' => ['required', 'uuid', 'exists:sites,id'],
            'anomalies.*.type' => ['required', 'string'],
            'anomalies.*.gravite' => ['nullable', 'string'],
            'anomalies.*.commentaire' => ['nullable', 'string'],
R'));

echo "Requests done\n";
